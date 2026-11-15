<?php

namespace App\Services\Channels\WhatsApp;

use App\Models\Support\SupportWhatsAppSession;
use Illuminate\Support\Str;

final class WhatsAppVerification
{
    /**
     * Creates a short-lived 2FA verification challenge for a phone number.
     *
     * @param int $accountId
     * @param string $phone
     * @param string $baseUrl Customer portal URL (e.g. WHMCS or SaaS portal)
     * @return array{token: string, verify_url: string, expires_in_minutes: int}
     */
    public static function createChallenge(int $accountId, string $phone, string $baseUrl): array
    {
        $token = Str::random(40);
        $phoneHash = hash('sha256', $phone);

        $session = SupportWhatsAppSession::firstOrCreate(
            ['account_id' => $accountId, 'phone' => $phone],
            ['phone_hash' => $phoneHash]
        );

        $expiresAt = now()->addMinutes(15);
        $session->update([
            'verification_token' => $token,
            'verification_expires_at' => $expiresAt,
            'is_verified' => false,
        ]);

        $url = rtrim($baseUrl, '/') . "/clientverify.php?token={$token}";

        return [
            'token' => $token,
            'verify_url' => $url,
            'expires_in_minutes' => 15,
        ];
    }

    /**
     * Validates and completes a verification challenge.
     */
    public static function confirm(string $token, string $clientId): array
    {
        $session = SupportWhatsAppSession::where('verification_token', $token)
            ->where('verification_expires_at', '>', now())
            ->first();

        if (!$session) {
            return ['ok' => false, 'message' => 'الرمز غير صالح أو منتهي الصلاحية.'];
        }

        $session->update([
            'is_verified' => true,
            'verified_at' => now(),
            'client_id' => $clientId,
            'verification_token' => null,
            'verification_expires_at' => null,
        ]);

        return [
            'ok' => true,
            'account_id' => $session->account_id,
            'phone' => $session->phone,
            'client_id' => $clientId,
            'message' => 'تم تأكيد ربط الحساب بنجاح.',
        ];
    }
}
