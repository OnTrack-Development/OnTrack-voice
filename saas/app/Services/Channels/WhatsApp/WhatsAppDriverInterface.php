<?php

namespace App\Services\Channels\WhatsApp;

interface WhatsAppDriverInterface
{
    /**
     * Sends plain text message.
     */
    public function sendText(string $toPhone, string $text, array $meta = []): array;

    /**
     * Sends interactive button message.
     */
    public function sendButtons(string $toPhone, string $bodyText, array $buttons, array $meta = []): array;

    /**
     * Sends interactive list message.
     */
    public function sendList(string $toPhone, string $title, string $bodyText, array $sections, array $meta = []): array;
}
