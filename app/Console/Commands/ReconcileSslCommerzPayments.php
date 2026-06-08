<?php

namespace App\Console\Commands;

use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Utils\CartManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recovers SSLCOMMERZ transactions that were paid by the customer but for which no
 * order was created (the historical bug: the site never validated the transaction
 * server-side and the success redirect was lost, so digital_payment_success() — and
 * therefore the order — never ran, while SSLCOMMERZ held the funds).
 *
 * Usage:
 *   # 1) List recent unpaid order payment-requests:
 *   php artisan sslcommerz:reconcile --report --days=30
 *
 *   # 2a) Recover by payment_id alone — looks up SSL by the stored tran_id (preferred):
 *   php artisan sslcommerz:reconcile --payment_id=<uuid>
 *
 *   # 2b) Or force a specific val_id (e.g. for old rows that never stored a tran_id):
 *   php artisan sslcommerz:reconcile --payment_id=<uuid> --val_id=<val_id> [--force]
 *
 * Recovery is idempotent: it only acts when is_paid is still 0 and SSLCOMMERZ confirms
 * the transaction as VALID/VALIDATED with a matching amount.
 */
class ReconcileSslCommerzPayments extends Command
{
    protected $signature = 'sslcommerz:reconcile
                            {--report : List recent unpaid order payment-requests to reconcile against the SSLCOMMERZ panel}
                            {--all : In --report mode, also include already-paid rows (shows an is_paid column)}
                            {--days=30 : How many days back to scan in --report mode}
                            {--validate-only : Only validate a --val_id at SSLCOMMERZ to release the held funds; no DB writes, no order}
                            {--inspect : Read-only: show a payment\'s amount + the customer\'s current checked-cart state (is it auto-recoverable?)}
                            {--payment_id= : The payment_requests.id (UUID) to recover/inspect}
                            {--val_id= : SSLCOMMERZ val_id (required for --validate-only; optional for recover when no stored tran_id)}
                            {--tran_id= : Optional tran_id to query SSL with (defaults to the stored one)}
                            {--force : Recover even if the validated amount differs from the requested amount}';

    protected $description = 'Validate and recover SSLCOMMERZ payments that were paid but never turned into an order.';

    public function handle(): int
    {
        if ($this->option('report')) {
            return $this->report((int)$this->option('days'), (bool)$this->option('all'));
        }

        if ($this->option('validate-only')) {
            $valId = $this->option('val_id');
            if (!$valId) {
                $this->error('--validate-only requires --val_id=<SSL Id from panel>.');
                return self::INVALID;
            }
            return $this->validateOnly($valId);
        }

        $paymentId = $this->option('payment_id');
        if (!$paymentId) {
            $this->error('Provide --report, --validate-only --val_id=..., or --payment_id (with --inspect or --val_id).');
            return self::INVALID;
        }

        if ($this->option('inspect')) {
            return $this->inspect($paymentId);
        }

        return $this->recover($paymentId, $this->option('val_id'), $this->option('tran_id'), (bool)$this->option('force'));
    }

    # Validate a val_id at SSLCOMMERZ to flip it to "API Validated: Yes" and release the held
    # funds. Pure money release — no payment_requests change, no order. Idempotent.
    private function validateOnly(string $valId): int
    {
        [$storeId, $storePass, $base, $verifyPeer, $mode] = $this->resolveCredentials();
        if (!$storeId) {
            $this->error('Could not resolve SSLCOMMERZ credentials from addon_settings (key_name=ssl_commerz).');
            return self::FAILURE;
        }
        $this->line("Using SSLCOMMERZ '{$mode}' store '{$storeId}'.");

        $validation = $this->callValidationApi($base . '/validator/api/validationserverAPI.php', $valId, $storeId, $storePass, $verifyPeer);
        if (!isset($validation->status)) {
            $this->error("No validation response from SSLCOMMERZ for val_id={$valId}.");
            return self::FAILURE;
        }
        if (!in_array($validation->status, ['VALID', 'VALIDATED'])) {
            $this->error("Not valid at SSLCOMMERZ (status: {$validation->status}). Check the val_id was copied exactly from the panel.");
            return self::FAILURE;
        }
        $this->info("OK — status {$validation->status}, amount " . ($validation->amount ?? '?')
            . ", tran_id " . ($validation->tran_id ?? '?') . ". Funds released; panel should now show API Validated: Yes.");
        return self::SUCCESS;
    }

    # Read-only preview: is this payment auto-recoverable (does the customer's checked cart still
    # exist and roughly match what they paid)? Writes nothing.
    private function inspect(string $paymentId): int
    {
        $payment = PaymentRequest::find($paymentId);
        if (!$payment) {
            $this->error("payment_requests row not found: {$paymentId}");
            return self::FAILURE;
        }

        $ad = json_decode($payment->additional_data, true) ?? [];
        $payer = json_decode($payment->payer_information, true) ?? [];
        $customerId = $ad['customer_id'] ?? null;
        $isGuest = $ad['is_guest'] ?? null;

        # Recreate the same customer context the live callback uses, so the cart resolves identically.
        if (isset($ad['is_guest']) && $ad['is_guest'] == 0 && $customerId) {
            request()->merge(['user' => User::find($customerId)]);
        }
        request()->merge([
            'customer_id' => $customerId,
            'is_guest' => $ad['is_guest'] ?? 0,
            'guest_id' => ($ad['is_guest_in_order'] ?? 0) ? $customerId : null,
            'payment_request_from' => $ad['payment_mode'] ?? 'web',
        ]);

        $checkedCount = $customerId !== null
            ? Cart::where('customer_id', $customerId)->where('is_checked', 1)->count()
            : 0;
        $productSubtotal = 0;
        try {
            $productSubtotal = CartManager::getOnlyCartProductPriceGrandTotal(type: 'checked');
        } catch (\Throwable $e) {
            // leave 0
        }

        $this->info("Payment {$payment->id}");
        $this->line("  is_paid:        " . (int)$payment->is_paid);
        $this->line("  paid amount:    {$payment->payment_amount} {$payment->currency_code}");
        $this->line("  payer:          " . ($payer['name'] ?? '?') . " (" . ($payer['phone'] ?? '?') . ")");
        $this->line("  customer_id:    " . ($customerId ?? 'null') . "   is_guest: " . ($isGuest ?? '?'));
        $this->line("  checked items:  {$checkedCount}");
        $this->line("  cart subtotal:  {$productSubtotal}");

        if ($checkedCount > 0) {
            $this->info("=> Cart still has items. Recover should rebuild the order — verify the order amount matches the paid amount after:");
            $this->line("   php artisan sslcommerz:reconcile --payment_id={$payment->id} --val_id=<SSL Id>");
        } else {
            $this->warn("=> Cart is empty for this customer. Auto-rebuild not possible — release funds with --validate-only and create the order manually in admin.");
        }
        return self::SUCCESS;
    }

    private function report(int $days, bool $all = false): int
    {
        $candidates = PaymentRequest::where('attribute', 'order')
            ->when(!$all, fn($q) => $q->where('is_paid', 0))
            ->where('created_at', '>=', now()->subDays($days))
            ->orderByDesc('created_at')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info("No matching 'order' payment-requests in the last {$days} days.");
            return self::SUCCESS;
        }

        $rows = $candidates->map(function ($p) {
            $payer = json_decode($p->payer_information, true) ?? [];
            return [
                $p->id,
                (int)$p->is_paid,
                $p->payment_amount . ' ' . $p->currency_code,
                $payer['name'] ?? '-',
                $payer['phone'] ?? '-',
                $p->transaction_id ?: '-',
                (string)$p->created_at,
            ];
        })->toArray();

        $this->table(['payment_id', 'is_paid', 'amount', 'payer', 'phone', 'tran_id', 'created_at'], $rows);
        $this->info("Found {$candidates->count()} candidate(s). For rows with a tran_id, just run:");
        $this->line('  php artisan sslcommerz:reconcile --payment_id=<id>');
        $this->info("For old rows showing tran_id = '-', copy the val_id (SSL Id) from the panel and run:");
        $this->line('  php artisan sslcommerz:reconcile --payment_id=<id> --val_id=<val_id_from_panel>');
        return self::SUCCESS;
    }

    private function recover(string $paymentId, ?string $valId, ?string $tranId, bool $force): int
    {
        $payment = PaymentRequest::find($paymentId);
        if (!$payment) {
            $this->error("payment_requests row not found: {$paymentId}");
            return self::FAILURE;
        }

        if ((int)$payment->is_paid === 1) {
            $existing = Order::where('transaction_ref', $payment->transaction_id)->pluck('id')->all();
            if (count($existing)) {
                $this->warn("Already marked paid. Existing order(s): " . implode(', ', $existing));
                return self::SUCCESS;
            }
            # Paid flag set but no order (e.g. an earlier recovery whose cart was gone). Roll the
            # flag back so the row reappears in --report for manual order creation.
            PaymentRequest::where('id', $payment->id)->update(['is_paid' => 0]);
            $this->warn("Was marked paid but has NO order — is_paid rolled back to 0 so it shows in --report. Create the order manually in admin.");
            return self::SUCCESS;
        }

        [$storeId, $storePass, $base, $verifyPeer, $mode] = $this->resolveCredentials();
        if (!$storeId) {
            $this->error('Could not resolve SSLCOMMERZ credentials from addon_settings (key_name=ssl_commerz).');
            return self::FAILURE;
        }
        $this->line("Using SSLCOMMERZ '{$mode}' store '{$storeId}' ({$base}).");

        $tranId = $tranId ?: $payment->transaction_id;

        # Determine the val_id to validate with. Prefer querying SSL by the stored tran_id
        # (no hand-typed val_id needed); fall back to a directly-supplied --val_id.
        if (empty($valId) && !empty($tranId)) {
            $el = $this->queryByTranId($base, $tranId, $storeId, $storePass, $verifyPeer);
            if (isset($el->val_id) && $el->val_id !== '') {
                $valId = $el->val_id;
            }
        }

        if (empty($valId)) {
            $this->error('Could not determine a val_id (no tran_id match at SSLCOMMERZ and no --val_id given). '
                . 'Copy the "SSL Id" from the panel transaction details and pass --val_id=...');
            return self::FAILURE;
        }

        # AUTHORITATIVE VALIDATION via validationserverAPI — this is what marks the transaction
        # "API Validated = Yes" at SSLCOMMERZ and releases the held funds.
        $validation = $this->callValidationApi($base . '/validator/api/validationserverAPI.php', $valId, $storeId, $storePass, $verifyPeer);
        if (!isset($validation->status)) {
            $this->error("No validation response from SSLCOMMERZ for val_id={$valId}.");
            return self::FAILURE;
        }
        if (!in_array($validation->status, ['VALID', 'VALIDATED'])) {
            $this->error("Transaction is not valid at SSLCOMMERZ (status: {$validation->status}).");
            return self::FAILURE;
        }
        $amountMatches = abs(floatval($validation->amount ?? 0) - floatval($payment->payment_amount)) < 1;
        if (!$amountMatches && !$force) {
            $this->error("Amount mismatch: validated " . ($validation->amount ?? 'null') . " vs requested {$payment->payment_amount}. Use --force to override.");
            return self::FAILURE;
        }
        $this->info("Validated at SSLCOMMERZ (status {$validation->status}) — hold released.");

        # IDEMPOTENT TRANSITION — only proceed if still unpaid.
        $affected = PaymentRequest::where('id', $payment->id)->where('is_paid', 0)->update([
            'payment_method' => 'ssl_commerz',
            'is_paid' => 1,
            'transaction_id' => $payment->transaction_id ?: ($validation->tran_id ?? $tranId),
        ]);

        $payment = PaymentRequest::find($payment->id);

        if ($affected > 0 && function_exists($payment->success_hook)) {
            call_user_func($payment->success_hook, $payment);
        }

        $createdOrders = Order::where('transaction_ref', $payment->transaction_id)->get(['id', 'order_amount']);
        if ($createdOrders->count()) {
            $orderIds = $createdOrders->pluck('id')->implode(', ');
            $ordersTotal = (float)$createdOrders->sum('order_amount');
            $this->info("Recovered payment {$payment->id}. Order(s) created: {$orderIds}");
            if (abs($ordersTotal - (float)$payment->payment_amount) >= 1) {
                $this->warn("⚠ Created order total ({$ordersTotal}) differs from the paid amount ({$payment->payment_amount}) — "
                    . "the customer's cart likely changed since payment. REVIEW order(s) {$orderIds} in admin and cancel/correct if the items are wrong.");
            }
            return self::SUCCESS;
        }

        # No order was produced — the shopping cart that the order is built from no longer
        # exists for this old payment. Roll back is_paid so the row stays visible/honest, and
        # tell the operator to create the order manually. (SSL is already validated above.)
        PaymentRequest::where('id', $payment->id)->update(['is_paid' => 0]);
        $payer = json_decode($payment->payer_information, true) ?? [];
        $this->warn("SSL validated & hold released, but NO order was created because the shopping cart for this old payment no longer exists.");
        $this->warn("=> Create this order MANUALLY in admin: payer '" . ($payer['name'] ?? '?') . "' (" . ($payer['phone'] ?? '?') . "), amount {$payment->payment_amount} {$payment->currency_code}, tran_id {$payment->transaction_id}. (is_paid left at 0.)");
        return self::SUCCESS;
    }

    /**
     * @return array{0:?string,1:?string,2:string,3:bool,4:string} [store_id, store_password, base_url, verifyPeer, mode]
     */
    private function resolveCredentials(): array
    {
        $config = DB::table('addon_settings')
            ->where('key_name', 'ssl_commerz')
            ->where('settings_type', 'payment_config')
            ->first();

        if (!$config) {
            return [null, null, '', true, 'unknown'];
        }

        $values = json_decode($config->mode === 'live' ? $config->live_values : $config->test_values);
        $base = $config->mode === 'live'
            ? 'https://securepay.sslcommerz.com'
            : 'https://sandbox.sslcommerz.com';

        return [
            $values->store_id ?? null,
            $values->store_password ?? null,
            $base,
            $config->mode !== 'live', // peer verification OFF on live (host CA bundle can't verify SSL's cert), matches the controller/index()
            $config->mode ?? 'unknown',
        ];
    }

    # Query SSLCOMMERZ by merchant tran_id (merchantTransIDvalidationAPI.php) and return the
    # matching transaction element (with status, val_id, amount), or null.
    private function queryByTranId(string $base, string $tranId, string $storeId, string $storePass, bool $verifyPeer)
    {
        $url = $base . '/validator/api/merchantTransIDvalidationAPI.php'
            . '?tran_id=' . urlencode($tranId)
            . '&store_id=' . urlencode($storeId)
            . '&store_passwd=' . urlencode($storePass)
            . '&v=1&format=json';

        $resp = $this->httpGetJson($url, $verifyPeer);
        if (!$resp) {
            return null;
        }
        if (isset($resp->APIConnect) && $resp->APIConnect !== 'DONE') {
            $this->warn("merchantTransID API returned APIConnect={$resp->APIConnect}.");
        }
        if (!empty($resp->element) && is_array($resp->element)) {
            foreach ($resp->element as $el) {
                if (isset($el->tran_id) && $el->tran_id == $tranId) {
                    return $el;
                }
            }
            return $resp->element[0];
        }
        $this->warn("No transaction found at SSLCOMMERZ for tran_id={$tranId}.");
        return null;
    }

    private function callValidationApi(string $url, string $valId, string $storeId, string $storePass, bool $verifyPeer)
    {
        $requested = $url
            . '?val_id=' . urlencode($valId)
            . '&store_id=' . urlencode($storeId)
            . '&store_passwd=' . urlencode($storePass)
            . '&v=1&format=json';

        return $this->httpGetJson($requested, $verifyPeer);
    }

    private function httpGetJson(string $url, bool $verifyPeer)
    {
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_TIMEOUT, 30);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, $verifyPeer ? 2 : 0);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, $verifyPeer ? 2 : 0);

        $result = curl_exec($handle);
        $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $errno = curl_errno($handle);
        $error = curl_error($handle);
        curl_close($handle);

        if ($code != 200 || $errno) {
            $this->error("SSLCOMMERZ API call failed (HTTP {$code}, curl {$errno}: {$error}).");
            return null;
        }
        return json_decode($result);
    }
}
