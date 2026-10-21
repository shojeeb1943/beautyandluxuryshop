<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Alerts when a customer paid but no order exists (paid payment without an order, or a payment
 * the order hook failed on and was rolled back to unpaid). Fix with: php artisan sslcommerz:reconcile --payment_id=<id>
 */
class CheckOrphanPayments extends Command
{
    protected $signature = 'payments:check-orphans {--days=3}';
    protected $description = 'Email/log an alert for paid payments that have no order.';

    public function handle(): int
    {
        $since = now()->subDays((int)$this->option('days'));

        $paidNoOrder = DB::table('payment_requests as pr')
            ->leftJoin('orders as o', 'o.transaction_ref', '=', 'pr.transaction_id')
            ->where('pr.is_paid', 1)
            ->where('pr.success_hook', 'digital_payment_success')
            ->where('pr.created_at', '>=', $since)
            ->whereNull('o.id')
            ->get(['pr.id', 'pr.transaction_id', 'pr.payment_amount', 'pr.payer_information']);

        $rolledBack = DB::table('payment_audit_logs as a')
            ->join('payment_requests as pr', 'pr.id', '=', 'a.payment_id')
            ->where('a.event', 'order_missing')
            ->where('a.created_at', '>=', $since)
            ->where('pr.is_paid', 0)
            ->get(['pr.id', 'pr.transaction_id', 'pr.payment_amount', 'pr.payer_information'])
            ->unique('id');

        $rows = $paidNoOrder->concat($rolledBack)->unique('id');
        if ($rows->isEmpty()) {
            $this->info('No orphan payments.');
            return self::SUCCESS;
        }

        $lines = $rows->map(function ($r) {
            $payer = json_decode($r->payer_information, true) ?? [];
            return "{$r->transaction_id} | {$r->payment_amount} | " . ($payer['name'] ?? '?') . ' ' . ($payer['phone'] ?? '')
                . " | php artisan sslcommerz:reconcile --payment_id={$r->id}";
        })->implode("\n");

        Log::critical("Orphan payments (paid, no order):\n" . $lines);
        $this->error($lines);

        # at most one email per distinct set per day
        $key = 'orphan_payments_alert_' . md5($rows->pluck('id')->sort()->implode(','));
        $to = getWebConfig(name: 'company_email');
        if ($to && Cache::add($key, 1, now()->addDay())) {
            try {
                Mail::raw("Customers paid but no order exists:\n\n" . $lines, fn($m) => $m->to($to)->subject('ALERT: paid payment without order'));
            } catch (\Throwable $e) {
                Log::error('Orphan alert mail failed: ' . $e->getMessage());
            }
        }
        return self::FAILURE;
    }
}
