<?php

namespace App\Services\Channels\WhatsApp;

use App\Models\Account;
use App\Models\WhatsappConnection;

final class WhatsAppGatewayService
{
    /**
     * Resolves the active driver for an account.
     */
    public static function resolveDriver(int $accountId): WhatsAppDriverInterface
    {
        $connection = WhatsappConnection::where('account_id', $accountId)
            ->where('status', 'connected')
            ->first();

        // If Meta Cloud API credentials are configured
        if ($connection && $connection->phone_number_id && $connection->access_token) {
            return new MetaOfficialDriver(
                $connection->phone_number_id,
                $connection->access_token
            );
        }

        // Fallback to Baileys QR driver for office numbers
        $sessionKey = 'account_' . $accountId;
        return new BaileysQrDriver($sessionKey);
    }
}
