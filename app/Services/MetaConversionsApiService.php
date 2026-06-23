<?php

namespace App\Services;

use App\Models\AnalyticScript;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaConversionsApiService
{
    private const API_VERSION = 'v19.0';
    private const API_URL = 'https://graph.facebook.com';

    public static function sendEvent(
        string $eventName,
        array  $customData,
        string $eventSourceUrl,
        array  $userData = [],
        string $eventId = null
    ): void {
        $script = AnalyticScript::where(['type' => 'meta_pixel', 'is_active' => 1])->first();
        if (!$script || !$script->script_id || !$script->script) {
            return;
        }

        $pixelId     = $script->script_id;
        $accessToken = $script->script;

        $payload = [
            'data' => [[
                'event_name'       => $eventName,
                'event_time'       => time(),
                'event_id'         => $eventId ?? uniqid('capi_', true),
                'event_source_url' => $eventSourceUrl,
                'action_source'    => 'website',
                'user_data'        => array_filter($userData),
                'custom_data'      => $customData,
            ]],
            'access_token' => $accessToken,
        ];

        try {
            Http::timeout(5)->post(
                self::API_URL . '/' . self::API_VERSION . '/' . $pixelId . '/events',
                $payload
            );
        } catch (\Throwable $e) {
            Log::warning('Meta CAPI error: ' . $e->getMessage());
        }
    }

    public static function hashData(string $value): string
    {
        return hash('sha256', strtolower(trim($value)));
    }

    public static function buildUserData(array $requestData = []): array
    {
        return [
            'client_ip_address' => request()->ip(),
            'client_user_agent' => request()->userAgent(),
            'fbc'               => $_COOKIE['_fbc'] ?? null,
            'fbp'               => $_COOKIE['_fbp'] ?? null,
            'em'  => isset($requestData['email']) ? [self::hashData($requestData['email'])] : null,
            'ph'  => isset($requestData['phone']) ? [self::hashData(preg_replace('/\D/', '', $requestData['phone']))] : null,
            'fn'  => isset($requestData['name'])  ? [self::hashData($requestData['name'])]  : null,
        ];
    }
}
