<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class AttachmentVision
{
    /**
     * Extracts diagnostic evidence from image attachments.
     *
     * @param array $imagePaths List of local file paths or URLs
     * @param string $apiKey Gemini API Key
     * @return array{domains: array, error_messages: array, summary: string, raw_text: string}
     */
    public static function extractEvidence(array $imagePaths, string $apiKey = ''): array
    {
        $domains = [];
        $errorMessages = [];
        $rawText = '';

        if (empty($imagePaths)) {
            return [
                'domains' => [],
                'error_messages' => [],
                'summary' => 'No images provided.',
                'raw_text' => '',
            ];
        }

        // Process images with Gemini API if key is present
        if ($apiKey !== '') {
            try {
                $parts = [];
                $parts[] = [
                    'text' => "You are a technical support OCR analyzer. Extract any domain names, server error messages (like 500 Internal Server Error, 404, 502 Bad Gateway, DNS errors, database errors, PHP stack traces), and summarize the technical problem. Return strictly JSON with keys: domains (array of strings), errors (array of strings), summary (string)."
                ];

                foreach (array_slice($imagePaths, 0, 3) as $path) {
                    if (is_file($path) && is_readable($path)) {
                        $mime = mime_content_type($path) ?: 'image/jpeg';
                        $data = base64_encode(file_get_contents($path));
                        $parts[] = [
                            'inline_data' => [
                                'mime_type' => $mime,
                                'data' => $data,
                            ],
                        ];
                    }
                }

                if (count($parts) > 1) {
                    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}";
                    $response = Http::timeout(10)->post($url, [
                        'contents' => [['parts' => $parts]],
                        'generationConfig' => ['responseMimeType' => 'application/json'],
                    ]);

                    if ($response->successful()) {
                        $json = json_decode($response->json('candidates.0.content.parts.0.text') ?? '', true);
                        if (is_array($json)) {
                            $domains = (array)($json['domains'] ?? []);
                            $errorMessages = (array)($json['errors'] ?? []);
                            $summary = (string)($json['summary'] ?? '');
                            return [
                                'domains' => Security::redactSensitiveRecursive($domains),
                                'error_messages' => Security::redactSensitiveRecursive($errorMessages),
                                'summary' => Security::redactSensitiveText($summary)['text'],
                                'raw_text' => $response->body(),
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[AttachmentVision] Vision extraction exception: ' . $e->getMessage());
            }
        }

        return [
            'domains' => $domains,
            'error_messages' => $errorMessages,
            'summary' => 'Vision analysis fallback.',
            'raw_text' => $rawText,
        ];
    }
}
