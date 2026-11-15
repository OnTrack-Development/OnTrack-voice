<?php

namespace App\Services\Channels\WhatsApp;

use App\Models\Support\SupportWhatsAppSession;
use Illuminate\Support\Facades\Log;

final class HumanHandoff
{
    /**
     * Mutes AI on a WhatsApp session when a human takeover is initiated.
     *
     * @param int $accountId
     * @param string $phone
     * @param int $durationMinutes Duration to mute AI (default: 60 minutes)
     * @param string $reason
     */
    public static function muteAi(int $accountId, string $phone, int $durationMinutes = 60, string $reason = 'staff_takeover'): void
    {
        $session = SupportWhatsAppSession::where('account_id', $accountId)
            ->where('phone', $phone)
            ->first();

        if ($session) {
            $session->update([
                'is_muted_for_human' => true,
                'muted_until' => now()->addMinutes($durationMinutes),
            ]);
            Log::info("[HumanHandoff] AI muted for phone {$phone} on account {$accountId} until {$session->muted_until} (Reason: {$reason})");
        }
    }

    /**
     * Unmutes AI and returns control to the agent.
     */
    public static function unmuteAi(int $accountId, string $phone): void
    {
        $session = SupportWhatsAppSession::where('account_id', $accountId)
            ->where('phone', $phone)
            ->first();

        if ($session) {
            $session->update([
                'is_muted_for_human' => false,
                'muted_until' => null,
            ]);
            Log::info("[HumanHandoff] AI unmuted for phone {$phone} on account {$accountId}");
        }
    }

    /**
     * Checks if the session is currently muted for human.
     */
    public static function isMuted(int $accountId, string $phone): bool
    {
        $session = SupportWhatsAppSession::where('account_id', $accountId)
            ->where('phone', $phone)
            ->first();

        if (!$session) {
            return false;
        }

        if ($session->is_muted_for_human) {
            if ($session->muted_until && $session->muted_until->isPast()) {
                $session->update(['is_muted_for_human' => false, 'muted_until' => null]);
                return false;
            }
            return true;
        }

        return false;
    }
}
