<?php

namespace App\Services\Channels\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class BaileysQrDriver implements WhatsAppDriverInterface
{
    private string $bridgeEndpoint;
    private string $sessionKey;

    public function __construct(string $sessionKey, string $bridgeEndpoint = 'http://127.0.0.1:3000')
    {
        $this->sessionKey = $sessionKey;
        $this->bridgeEndpoint = rtrim($bridgeEndpoint, '/');
    }

    public function sendText(string $toPhone, string $text, array $meta = []): array
    {
        return $this->post('/message/text', [
            'sessionId' => $this->sessionKey,
            'to' => $this->formatJid($toPhone),
            'text' => $text,
        ]);
    }

    public function sendButtons(string $toPhone, string $bodyText, array $buttons, array $meta = []): array
    {
        // Try native Baileys interactive buttons/poll if supported, or smart numbered menu
        $menuLines = [];
        $menuLines[] = $bodyText;
        $menuLines[] = "\n--- خيارات الرد السريع ---";

        $index = 1;
        $buttonMap = [];
        foreach ($buttons as $b) {
            $title = (string)($b['title'] ?? 'خيار');
            $id = (string)($b['id'] ?? (string)$index);
            $menuLines[] = "[{$index}] {$title}";
            $buttonMap[$index] = $id;
            $index++;
        }
        $menuLines[] = "👉 يمكنك الرد بالرقم أو الضغط المباشر.";

        $fullText = implode("\n", $menuLines);

        return $this->post('/message/text', [
            'sessionId' => $this->sessionKey,
            'to' => $this->formatJid($toPhone),
            'text' => $fullText,
            'metadata' => ['button_map' => $buttonMap],
        ]);
    }

    public function sendList(string $toPhone, string $title, string $bodyText, array $sections, array $meta = []): array
    {
        $lines = ["*{$title}*\n{$bodyText}"];
        foreach ($sections as $section) {
            $secTitle = $section['title'] ?? '';
            if ($secTitle !== '') $lines[] = "\n*{$secTitle}*";
            foreach ((array)($section['rows'] ?? []) as $row) {
                $lines[] = "• " . ($row['title'] ?? '') . (!empty($row['description']) ? " - " . $row['description'] : '');
            }
        }

        return $this->sendText($toPhone, implode("\n", $lines), $meta);
    }

    private function post(string $endpoint, array $payload): array
    {
        try {
            $url = $this->bridgeEndpoint . $endpoint;
            $response = Http::timeout(6)->post($url, $payload);
            if ($response->successful()) {
                return ['ok' => true, 'response' => $response->json()];
            }
            return ['ok' => false, 'error' => $response->body()];
        } catch (\Throwable $e) {
            Log::warning("[BaileysQrDriver] Error calling bridge: " . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function formatJid(string $phone): string
    {
        $clean = preg_replace('/[^0-9]/', '', $phone) ?? $phone;
        return "{$clean}@s.whatsapp.net";
    }
}
