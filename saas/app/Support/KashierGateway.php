<?php
namespace App\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class KashierGateway
{
    public static function mode(): string
    {
        return config('services.kashier.mode', 'test') === 'live' ? 'live' : 'test';
    }

    public static function merchantId(): string { return (string) config('services.kashier.merchant_id', ''); }
    public static function apiKey(): string { return (string) config('services.kashier.api_key', ''); }
    public static function secretKey(): string { return (string) config('services.kashier.secret_key', ''); }

    public static function createSession(array $payload): array
    {
        if (!self::merchantId() || !self::apiKey() || !self::secretKey()) {
            throw new RuntimeException('لم يتم ضبط بيانات Kashier على الخادم');
        }
        $endpoint = self::mode() === 'test'
            ? 'https://test-api.kashier.io/v3/payment/sessions'
            : 'https://api.kashier.io/v3/payment/sessions';
        $response = Http::timeout(18)->withHeaders([
            'Authorization' => self::secretKey(),
            'api-key' => self::apiKey(),
        ])->asJson()->post($endpoint, $payload);
        if (!$response->successful()) throw new RuntimeException('Kashier payment session failed: HTTP '. $response->status());
        $result = $response->json();
        $url = (string) (data_get($result, 'data.sessionUrl') ?? data_get($result, 'sessionUrl') ?? '');
        $sessionId = (string) (data_get($result, 'data.sessionId') ?? data_get($result, 'sessionId') ?? '');
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'payments.kashier.io') {
            throw new RuntimeException('Kashier returned an invalid checkout URL');
        }
        return ['url'=>$url,'session_id'=>$sessionId];
    }

    public static function validWebhook(array $event, string $header): bool
    {
        $data = $event['data'] ?? null;
        if (!is_array($data) || !is_array($data['signatureKeys'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/i', $header) || !self::apiKey()) return false;
        $keys = $data['signatureKeys'];
        if (count($keys) < 4 || count($keys) > 40 || count(array_unique($keys)) !== count($keys)) return false;
        foreach (['amount','currency','merchantOrderId','status','transactionId'] as $needed) {
            if (!in_array($needed, $keys, true)) return false;
        }
        sort($keys, SORT_STRING);
        $values = [];
        foreach ($keys as $key) {
            if (!is_string($key) || !preg_match('/^[A-Za-z][A-Za-z0-9]*$/D', $key)
                || !array_key_exists($key, $data) || !is_scalar($data[$key])) return false;
            $values[] = $key.'='.rawurlencode((string) $data[$key]);
        }
        return hash_equals(strtolower($header), hash_hmac('sha256', implode('&', $values), self::apiKey()));
    }
}
