<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\PaymentRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recovers SSLCOMMERZ transactions that were paid by the customer but for which no
 * order was created (the historical bug: the site never validated the transaction
 * server-side and the success redirect was lost, so digital_payment_success() — and
 * therefore the order — never ran, while SSLCOMMERZ held the funds).
 *
 * Usage:
 *   # 1) List recent unpaid order payment-requests to map against the SSLCOMMERZ panel:
 *   php artisan sslcommerz:reconcile --report --days=30
 *
 *   # 2) Recover one transaction (val_id is taken from the SSLCOMMERZ panel/IPN email):
 *   php artisan sslcommerz:reconcile --payment_id=<uuid> --val_id=<val_id> [--tran_id=<tran_id>] [--force]
 *
 * Recovery is idempotent: it only acts when is_paid is still 0 and SSLCOMMERZ confirms
 * the transaction as VALID/VALIDATED with a matching amount.
 */
class ReconcileSslCommerzPayments extends Command
{
    protected $signature = 'sslcommerz:reconcile
                            {--report : List recent unpaid order payment-requests to reconcile against the SSLCOMMERZ panel}
                            {--days=30 : How many days back to scan in --report mode}
                            {--payment_id= : The payment_requests.id (UUID) to recover}
                            {--val_id= : The SSLCOMMERZ val_id of the successful transaction (from the panel)}
                            {--tran_id= : Optional tran_id to store (defaults to the one returned by SSLCOMMERZ)}
                            {--force : Recover even if the validated amount differs from the requested amount}';

    protected $description = 'Validate and recover SSLCOMMERZ payments that were paid but never turned into an order.';

    public function handle(): int
    {
        if ($this->option('report')) {
            return $this->report((int)$this->option('days'));
        }

        $paymentId = $this->option('payment_id');
        $valId = $this->option('val_id');

        if (!$paymentId || !$valId) {
            $this->error('Provide --report, or both --payment_id and --val_id. See: php artisan help sslcommerz:reconcile');
            return self::INVALID;
        }

        return $this->recover($paymentId, $valId, $this->option('tran_id'), (bool)$this->option('force'));
    }

    private function report(int $days): int
    {
        $candidates = PaymentRequest::where('is_paid', 0)
            ->where('attribute', 'order')
            ->where('created_at', '>=', now()->subDays($days))
            ->orderByDesc('created_at')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info("No unpaid 'order' payment-requests in the last {$days} days.");
            return self::SUCCESS;
        }

        $rows = $candidates->map(function ($p) {
            $payer = json_decode($p->payer_information, true) ?? [];
            return [
                $p->id,
                $p->payment_amount . ' ' . $p->currency_code,
                $payer['name'] ?? '-',
                $payer['phone'] ?? '-',
                $p->transaction_id ?: '-',
                (string)$p->created_at,
            ];
        })->toArray();

        $this->table(['payment_id', 'amount', 'payer', 'phone', 'tran_id', 'created_at'], $rows);
        $this->info("Found {$candidates->count()} candidate(s). Match each against a successful transaction in the SSLCOMMERZ panel, then run:");
        $this->line('  php artisan sslcommerz:reconcile --payment_id=<id> --val_id=<val_id_from_panel>');
        return self::SUCCESS;
    }

    private function recover(string $paymentId, string $valId, ?string $tranId, bool $force): int
    {
        $payment = PaymentRequest::find($paymentId);
        if (!$payment) {
            $this->error("payment_requests row not found: {$paymentId}");
            return self::FAILURE;
        }

        if ((int)$payment->is_paid === 1) {
            $existing = Order::where('transaction_ref', $payment->transaction_id)->pluck('id')->all();
            $this->warn("Already marked paid. " . (count($existing)
                    ? "Existing order(s): " . implode(', ', $existing)
                    : "No order found by transaction_ref — inspect manually."));
            return self::SUCCESS;
        }

        [$storeId, $storePass, $validationUrl, $verifyPeer] = $this->resolveCredentials();
        if (!$storeId) {
            $this->error('Could not resolve SSLCOMMERZ credentials from addon_settings (key_name=ssl_commerz).');
            return self::FAILURE;
        }

        $validation = $this->callValidationApi($validationUrl, $valId, $storeId, $storePass, $verifyPeer);
        if (!isset($validation->status)) {
            $this->error('Failed to reach the SSLCOMMERZ validation API or empty response.');
            return self::FAILURE;
        }

        if (!in_array($validation->status, ['VALID', 'VALIDATED'])) {
            $this->error("Transaction is not valid at SSLCOMMERZ (status: {$validation->status}).");
            return self::FAILURE;
        }

        $amountMatches = abs(floatval($validation->amount) - floatval($payment->payment_amount)) < 1;
        if (!$amountMatches && !$force) {
            $this->error("Amount mismatch: validated {$validation->amount} vs requested {$payment->payment_amount}. Use --force to override.");
            return self::FAILURE;
        }

        # IDEMPOTENT TRANSITION — only proceed if still unpaid.
        $affected = PaymentRequest::where('id', $payment->id)->where('is_paid', 0)->update([
            'payment_method' => 'ssl_commerz',
            'is_paid' => 1,
            'transaction_id' => $tranId ?: ($validation->tran_id ?? $payment->transaction_id),
        ]);

        $payment = PaymentRequest::find($payment->id);

        if ($affected > 0 && function_exists($payment->success_hook)) {
            call_user_func($payment->success_hook, $payment);
            $orders = Order::where('transaction_ref', $payment->transaction_id)->pluck('id')->all();
            $this->info("Recovered payment {$payment->id}. Order(s) created: " . (count($orders) ? implode(', ', $orders) : '(check success hook output)'));
            return self::SUCCESS;
        }

        $this->warn('No state change (already processed concurrently) or success hook missing.');
        return self::SUCCESS;
    }

    /**
     * @return array{0:?string,1:?string,2:string,3:bool} [store_id, store_password, validation_url, verifyPeer]
     */
    private function resolveCredentials(): array
    {
        $config = DB::table('addon_settings')
            ->where('key_name', 'ssl_commerz')
            ->where('settings_type', 'payment_config')
            ->first();

        if (!$config) {
            return [null, null, '', true];
        }

        $values = json_decode($config->mode === 'live' ? $config->live_values : $config->test_values);
        $base = $config->mode === 'live'
            ? 'https://securepay.sslcommerz.com'
            : 'https://sandbox.sslcommerz.com';

        return [
            $values->store_id ?? null,
            $values->store_password ?? null,
            $base . '/validator/api/validationserverAPI.php',
            $config->mode !== 'live', // peer verification OFF on live (host CA bundle can't verify SSL's cert), matches the controller/index()
        ];
    }

    private function callValidationApi(string $url, string $valId, string $storeId, string $storePass, bool $verifyPeer)
    {
        $requested = $url
            . '?val_id=' . urlencode($valId)
            . '&store_id=' . urlencode($storeId)
            . '&store_passwd=' . urlencode($storePass)
            . '&v=1&format=json';

        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $requested);
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
            $this->error("Validation API call failed (HTTP {$code}, curl {$errno}: {$error}).");
            return null;
        }
        return json_decode($result);
    }
}
