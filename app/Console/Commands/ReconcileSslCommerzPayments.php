<?php

namespace App\Console\Commands;

use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentRequest;
use App\Models\ShippingAddress;
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
                            {--validate-only : Only validate at SSLCOMMERZ to release the held funds (give --tran_id, recommended, or --val_id); no DB writes, no order}
                            {--inspect : Read-only: show a payment\'s amount + the customer\'s current checked-cart state (is it auto-recoverable?)}
                            {--payment_id= : The payment_requests.id (UUID) to recover/inspect}
                            {--val_id= : SSLCOMMERZ val_id (required for --validate-only; optional for recover when no stored tran_id)}
                            {--tran_id= : Optional tran_id to query SSL with (defaults to the stored one)}
                            {--rebuild-all : When recovering, mark all of the customer\'s cart rows checked (use if items exist but are unchecked)}
                            {--force : Recover even if the validated amount differs from the requested amount}';

    protected $description = 'Validate and recover SSLCOMMERZ payments that were paid but never turned into an order.';

    public function handle(): int
    {
        # The web entry (index.php) defines this constant; artisan does not. Order generation
        # (invoice/image paths in file_path.php) needs it. Match the server's index.php ('root').
        if (!defined('DOMAIN_POINTED_DIRECTORY')) {
            define('DOMAIN_POINTED_DIRECTORY', 'root');
        }

        if ($this->option('report')) {
            return $this->report((int)$this->option('days'), (bool)$this->option('all'));
        }

        if ($this->option('validate-only')) {
            $valId = $this->option('val_id');
            $tranId = $this->option('tran_id');
            if (!$valId && !$tranId) {
                $this->error('--validate-only requires --tran_id=<Transaction ID from panel> (recommended) or --val_id=<SSL Id>.');
                return self::INVALID;
            }
            return $this->validateOnly($valId, $tranId);
        }

        $paymentId = $this->option('payment_id');
        if (!$paymentId) {
            $this->error('Provide --report, --validate-only --val_id=..., or --payment_id (with --inspect or --val_id).');
            return self::INVALID;
        }

        if ($this->option('inspect')) {
            return $this->inspect($paymentId);
        }

        return $this->recover($paymentId, $this->option('val_id'), $this->option('tran_id'), (bool)$this->option('force'), (bool)$this->option('rebuild-all'));
    }

    # Validate a val_id at SSLCOMMERZ to flip it to "API Validated: Yes" and release the held
    # funds. Pure money release — no payment_requests change, no order. Idempotent.
    private function validateOnly(?string $valId, ?string $tranId = null): int
    {
        [$storeId, $storePass, $base, $verifyPeer, $mode] = $this->resolveCredentials();
        if (!$storeId) {
            $this->error('Could not resolve SSLCOMMERZ credentials from addon_settings (key_name=ssl_commerz).');
            return self::FAILURE;
        }
        $this->line("Using SSLCOMMERZ '{$mode}' store '{$storeId}'.");

        # Prefer resolving the val_id from the (short, reliably-copied) Transaction ID — avoids
        # mis-typing the long SSL Id.
        if (empty($valId) && !empty($tranId)) {
            $el = $this->queryByTranId($base, $tranId, $storeId, $storePass, $verifyPeer);
            if (isset($el->val_id) && $el->val_id !== '') {
                $valId = $el->val_id;
                $this->line("Resolved val_id from tran_id {$tranId}.");
            }
        }
        if (empty($valId)) {
            $this->error("Could not resolve a val_id (tran_id '{$tranId}' not found at SSLCOMMERZ, and no --val_id given). Check the Transaction ID was copied from the panel.");
            return self::FAILURE;
        }

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

        # Old guest payments stored customer_id=null. The guest's id still lives on the saved
        # shipping address — recover it so we can locate the (uncleared) guest cart.
        $effectiveId = $customerId;
        $idSource = 'additional_data.customer_id';
        if (empty($effectiveId)) {
            $effectiveId = $this->resolveGuestIdFromAddress($ad);
            $idSource = $effectiveId ? 'recovered from shipping address' : 'unresolved';
        }

        # Recreate the same customer context the live callback uses, so the cart resolves identically.
        if (isset($ad['is_guest']) && $ad['is_guest'] == 0 && $effectiveId) {
            request()->merge(['user' => User::find($effectiveId)]);
        }
        request()->merge([
            'customer_id' => $effectiveId,
            'is_guest' => $ad['is_guest'] ?? 0,
            'guest_id' => $effectiveId,
            'payment_request_from' => $ad['payment_mode'] ?? 'web',
        ]);

        $checkedCount = $effectiveId !== null
            ? Cart::where('customer_id', $effectiveId)->where('is_checked', 1)->count()
            : 0;
        $totalForCustomer = $effectiveId !== null
            ? Cart::where('customer_id', $effectiveId)->count()
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
        $this->line("  effective id:   " . ($effectiveId ?? 'null') . "   ({$idSource}); is_guest: " . ($isGuest ?? '?'));
        $this->line("  cart rows:      {$totalForCustomer} total, {$checkedCount} checked");
        $this->line("  cart subtotal:  {$productSubtotal}");
        $this->line("  address_id:     " . ($ad['address_id'] ?? '?') . "   billing: " . ($ad['billing_address_id'] ?? '?'));

        if ($effectiveId !== null && $totalForCustomer > 0) {
            $this->line("  cart line items (raw):");
            $items = Cart::where('customer_id', $effectiveId)
                ->get(['product_id', 'name', 'variant', 'quantity', 'price', 'is_checked', 'created_at']);
            foreach ($items as $it) {
                $chk = $it->is_checked ? 'x' : ' ';
                $this->line("    [{$chk}] {$it->name} | variant: " . ($it->variant ?: '-')
                    . " | qty: {$it->quantity} | unit price: {$it->price} | product#{$it->product_id} | added {$it->created_at}");
            }
        }

        if ($checkedCount > 0) {
            $this->info("=> Cart still has items. Rebuild with (it auto-recovers the guest id too):");
            $this->line("   php artisan sslcommerz:reconcile --payment_id={$payment->id} --tran_id=<Transaction ID>");
        } elseif ($totalForCustomer > 0) {
            $this->warn("=> Cart has {$totalForCustomer} item(s) but none are CHECKED. Run with --rebuild-all to include them, or create the order manually.");
        } else {
            $this->warn("=> No cart rows survive for this customer. Auto-rebuild not possible — create the order manually in admin (funds already released).");
        }
        return self::SUCCESS;
    }

    # Old guest payments stored customer_id=null; recover the guest id from the shipping address
    # that was saved with the checkout (ShippingAddress.customer_id holds the guest id).
    private function resolveGuestIdFromAddress(array $ad): ?string
    {
        foreach (['address_id', 'billing_address_id'] as $key) {
            $addressId = $ad[$key] ?? null;
            if (!empty($addressId)) {
                $address = ShippingAddress::find($addressId);
                if ($address && !empty($address->customer_id)) {
                    return (string)$address->customer_id;
                }
            }
        }
        return null;
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

    private function recover(string $paymentId, ?string $valId, ?string $tranId, bool $force, bool $rebuildAll = false): int
    {
        $payment = PaymentRequest::find($paymentId);
        if (!$payment) {
            $this->error("payment_requests row not found: {$paymentId}");
            return self::FAILURE;
        }

        if ((int)$payment->is_paid === 1) {
            $existing = Order::where('transaction_ref', $payment->transaction_id)
                ->where('order_status', '!=', 'canceled')
                ->pluck('id')->all();
            if (count($existing)) {
                $this->warn("Already marked paid. Existing order(s): " . implode(', ', $existing));
                return self::SUCCESS;
            }
            # Paid flag set but no order — reset it and continue to the rebuild below in one pass.
            PaymentRequest::where('id', $payment->id)->update(['is_paid' => 0]);
            $payment = PaymentRequest::find($payment->id);
            $this->line("Was marked paid with no order — reset is_paid=0 and retrying the rebuild.");
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

        # For old guest payments (customer_id=null), inject the guest id recovered from the saved
        # shipping address so the order hook can locate the guest's (uncleared) cart.
        $ad = json_decode($payment->additional_data, true) ?? [];
        if (empty($ad['customer_id'])) {
            $gid = $this->resolveGuestIdFromAddress($ad);
            if ($gid) {
                $ad['customer_id'] = $gid;
                $ad['is_guest_in_order'] = 1;
                PaymentRequest::where('id', $payment->id)->update(['additional_data' => json_encode($ad)]);
                $this->line("Recovered guest id {$gid} from shipping address for cart lookup.");
            }
        }
        $effectiveId = $ad['customer_id'] ?? null;

        # Optionally re-check all of this customer's cart rows (an order is built only from CHECKED
        # items; old carts may have been left unchecked).
        if ($rebuildAll && $effectiveId) {
            $marked = Cart::where('customer_id', $effectiveId)->update(['is_checked' => 1]);
            $this->line("--rebuild-all: marked {$marked} cart row(s) checked.");
        }

        # IDEMPOTENT TRANSITION — only the run that flips is_paid 0 -> 1 builds the order.
        $affected = PaymentRequest::where('id', $payment->id)->where('is_paid', 0)->update([
            'payment_method' => 'ssl_commerz',
            'is_paid' => 1,
            'transaction_id' => $payment->transaction_id ?: ($validation->tran_id ?? $tranId),
        ]);

        $payment = PaymentRequest::find($payment->id);

        if ($effectiveId) {
            $dbgChecked = Cart::where('customer_id', $effectiveId)->where('is_checked', 1)->count();
            $this->line("Pre-build: {$dbgChecked} checked cart item(s) for effective id {$effectiveId}.");
        }

        if ($affected > 0 && function_exists($payment->success_hook)) {
            call_user_func($payment->success_hook, $payment);
        }

        $createdOrders = Order::where('transaction_ref', $payment->transaction_id)
            ->where('order_status', '!=', 'canceled')
            ->get(['id', 'order_amount']);
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
