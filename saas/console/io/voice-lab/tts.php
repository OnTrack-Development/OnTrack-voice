<?php
declare(strict_types=1);
require_once __DIR__ . '/security.php';
// Isolated demo-only Fish TTS bridge. The caller provides a key on every request;
// neither the key nor the generated audio is stored by this script.
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('method_not_allowed');
}
$labUser = voice_lab_access(true);
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = strtolower((string)parse_url('https://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
if ($origin !== '' && strtolower((string)parse_url($origin, PHP_URL_HOST)) !== $host) {
    http_response_code(403);
    exit('wrong_origin');
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 12000) {
    http_response_code(413);
    exit('request_too_large');
}
$key = voice_lab_saved_key((int)$labUser['id'], 'fish', (string)($_SERVER['HTTP_X_FISH_API_KEY'] ?? ''));
session_write_close();
if ($key === '') {
    http_response_code(400);
    exit('fish_key_required');
}
// Do not enforce a guessed Fish key prefix; provider validates the actual credential.
if (strlen($key) > 2048 || preg_match('/[\x00-\x1F\x7F]/', $key)) {
    http_response_code(400);
    exit('unsafe_fish_key');
}
$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    exit('invalid_json');
}
$text = trim((string)($body['text'] ?? ''));
$voice = strtolower(trim((string)($body['reference_id'] ?? '')));
$model = (string)($body['model'] ?? '');
if ($text === '' || strlen($text) > 5500 || !preg_match('/^[a-f0-9]{32}$/', $voice)
    || !in_array($model, ['drama-3', 's2.1-pro-free', 's2.1-pro'], true)) {
    http_response_code(422);
    exit('invalid_tts_input');
}
if (!function_exists('curl_init')) {
    http_response_code(503);
    exit('host_curl_extension_missing');
}
$upstreamStatus = 502;
$started = false;
$sentBytes = 0;
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) { @ob_end_flush(); }
$client = curl_init('https://api.fish.audio/v1/tts');
curl_setopt_array($client, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
        'Accept: audio/mpeg',
        'model: ' . $model,
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'text' => $text,
        'reference_id' => $voice,
        'format' => 'mp3',
        'streaming' => true,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_CONNECTTIMEOUT => 12,
    CURLOPT_TIMEOUT => 100,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HEADERFUNCTION => static function ($ch, string $header) use (&$upstreamStatus): int {
        if (preg_match('/^HTTP\/[\d.]+\s+(\d{3})\b/', $header, $match)) {
            $upstreamStatus = (int)$match[1];
        }
        return strlen($header);
    },
    CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$started, &$sentBytes, &$upstreamStatus): int {
        if (!$started) {
            http_response_code($upstreamStatus);
            header('Content-Type: ' . ($upstreamStatus >= 200 && $upstreamStatus < 300 ? 'audio/mpeg' : 'text/plain; charset=utf-8'));
            header('X-Accel-Buffering: no');
            $started = true;
        }
        $sentBytes += strlen($chunk);
        echo $chunk;
        @flush();
        return strlen($chunk);
    },
]);
$ok = curl_exec($client);
$curlError = curl_error($client);
curl_close($client);
if (!$ok && !$started) {
    http_response_code(502);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Fish upstream error: ' . $curlError;
} elseif (!$started) {
    http_response_code($upstreamStatus);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Fish returned empty audio';
}
