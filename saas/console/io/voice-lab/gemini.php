<?php
declare(strict_types=1);
// Personal voice-lab Gemini text bridge: no request or API key storage.
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('method_not_allowed');
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = strtolower((string)parse_url('https://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
if ($origin !== '' && strtolower((string)parse_url($origin, PHP_URL_HOST)) !== $host) {
    http_response_code(403);
    exit('wrong_origin');
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 25000) {
    http_response_code(413);
    exit('request_too_large');
}
$key = trim((string)($_SERVER['HTTP_X_GEMINI_API_KEY'] ?? ''));
if (strlen($key) < 20 || strlen($key) > 256 || !preg_match('/^[A-Za-z0-9_-]+$/', $key)) {
    http_response_code(400);
    exit('gemini_key_required');
}
$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    exit('invalid_json');
}
$model = (string)($body['model'] ?? '');
if (!in_array($model, ['gemini-3.8-flash', 'gemini-3-flash-preview'], true)) {
    http_response_code(422);
    exit('unsupported_text_model');
}
$system = trim((string)($body['system'] ?? ''));
$messages = $body['messages'] ?? null;
if ($system === '' || strlen($system) > 5000 || !is_array($messages) || count($messages) < 1 || count($messages) > 11) {
    http_response_code(422);
    exit('invalid_prompt');
}
$contents = [];
foreach ($messages as $message) {
    if (!is_array($message)) {
        http_response_code(422);
        exit('invalid_message');
    }
    $role = (string)($message['role'] ?? '');
    $text = trim((string)($message['content'] ?? ''));
    if (!in_array($role, ['user', 'assistant'], true) || $text === '' || strlen($text) > 3000) {
        http_response_code(422);
        exit('invalid_message');
    }
    $contents[] = ['role' => $role === 'assistant' ? 'model' : 'user', 'parts' => [['text' => $text]]];
}
if (($contents[count($contents) - 1]['role'] ?? '') !== 'user') {
    http_response_code(422);
    exit('missing_user_turn');
}
if (!function_exists('curl_init')) {
    http_response_code(503);
    exit('host_curl_extension_missing');
}
$payload = json_encode([
    'systemInstruction' => ['parts' => [['text' => $system]]],
    'contents' => $contents,
    'generationConfig' => ['temperature' => 1.1, 'maxOutputTokens' => 1024],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($payload === false) {
    http_response_code(422);
    exit('invalid_encoding');
}
$client = curl_init('https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent');
curl_setopt_array($client, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['x-goog-api-key: ' . $key, 'Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 55,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response = curl_exec($client);
$code = (int)curl_getinfo($client, CURLINFO_HTTP_CODE);
curl_close($client);
if (!is_string($response)) {
    http_response_code(502);
    exit('gemini_network_error');
}
if ($code < 200 || $code >= 300) {
    http_response_code(in_array($code, [400, 401, 403, 404, 429], true) ? $code : 502);
    exit($code === 429 ? 'gemini_rate_limited' : 'gemini_upstream_error');
}
$parsed = json_decode($response, true);
$parts = $parsed['candidates'][0]['content']['parts'] ?? [];
$reply = '';
if (is_array($parts)) {
    foreach ($parts as $part) {
        if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
            $reply .= $part['text'];
        }
    }
}
$reply = trim($reply);
if ($reply === '') {
    http_response_code(502);
    exit('gemini_no_text_response');
}
echo json_encode(['text' => $reply, 'model' => $model], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
