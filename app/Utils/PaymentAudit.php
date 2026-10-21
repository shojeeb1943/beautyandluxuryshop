<?php

namespace App\Utils;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Append-only trail of the payment -> order lifecycle (who/when/IP/browser/outcome).
 * Never throws: auditing must not break a payment callback.
 */
class PaymentAudit
{
    public static function log(string $event, ?string $paymentId = null, ?string $tranId = null, ?string $result = null, array $context = []): void
    {
        try {
            $request = request();
            DB::table('payment_audit_logs')->insert([
                'payment_id' => $paymentId,
                'tran_id' => $tranId,
                'event' => $event,
                'result' => $result,
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string)$request->userAgent(), 0, 255),
                'context' => json_encode($context),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('PaymentAudit failed: ' . $e->getMessage());
        }
    }
}
