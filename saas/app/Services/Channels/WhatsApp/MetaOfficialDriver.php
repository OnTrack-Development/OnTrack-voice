<?php

namespace App\Services\Channels\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class MetaOfficialDriver implements WhatsAppDriverInterface
{
    private string $phoneNumberId;
    private string $accessToken;
    private string $apiVersion;

    public function __construct(string $phoneNumberId, string $accessToken, string $apiVersion = 'v21.0')
    {
        $this->phoneNumberId = $phoneNumberId;
        $this->accessToken = $accessToken;
        $this->apiVersion = $apiVersion;
    }

    public function sendText(string $toPhone, string $text, array $meta = []): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->cleanPhone($toPhone),
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $text],
        ];

        return $this->post($payload);
    }

    public function sendButtons(string $toPhone, string $bodyText, array $buttons, array $meta = []): array
    {
        // Meta supports up to 3 interactive reply buttons
        $formattedButtons = [];
        foreach (array_slice($buttons, 0, 3) as $b) {
            $formattedButtons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => (string)($b['id'] ?? 'btn_' . uniqid()),
                    'title' => mb_substr((string)($b['title'] ?? 'Option'), 0, 20, 'UTF-8'),
                ],
            ];
        }

        if (empty($formattedButtons)) {
            return $this->sendText($toPhone, $bodyText, $meta);
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->cleanPhone($toPhone),
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => mb_substr($bodyText, 0, 1024, 'UTF-8')],
                'action' => ['buttons' => $formattedButtons],
            ],
        ];

        return $this->post($payload);
    }

    public function sendList(string $toPhone, string $title, string $bodyText, array $sections, array $meta = []): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->cleanPhone($toPhone),
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'header' => ['type' => 'text', 'text' => mb_substr($title, 0, 60, 'UTF-8')],
                'body' => ['text' => mb_substr($bodyText, 0, 1024, 'UTF-8')],
                'action' => [
                    'button' => 'خيارات القائمة',
                    'sections' => $sections,
                ],
            ],
        ];

        return $this->post($payload);
    }

    private function post(array $payload): array
    {
        try {
            $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";
            $response = Http::withToken($this->accessToken)
                ->timeout(10)
                ->post($url, $payload);

            if ($response->successful()) {
                return ['ok' => true, 'response' => $response->json()];
            }

            Log::error('[MetaOfficialDriver] Send failed: ' . $response->body());
            return ['ok' => false, 'error' => $response->body(), 'status' => $response->status()];
        } catch (\Throwable $e) {
            Log::error('[MetaOfficialDriver] Exception: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function cleanPhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone) ?? $phone;
    }
}
