<?php
declare(strict_types=1);
require_once __DIR__ . '/security.php';
// Single-use short-lived Google Live auth token. Never store or return the long-lived API key.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); http_response_code(405); exit('method_not_allowed'); }
$labUser = voice_lab_access(true);
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$host = strtolower((string)parse_url('https://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
if ($origin !== '' && strtolower((string)parse_url($origin, PHP_URL_HOST)) !== $host) { http_response_code(403); exit('wrong_origin'); }
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 2048) { http_response_code(413); exit('request_too_large'); }
$key = voice_lab_saved_key((int)$labUser['id'], 'google', (string)($_SERVER['HTTP_X_GEMINI_API_KEY'] ?? ''));
session_write_close();
if ($key === '') { http_response_code(400); exit('gemini_key_required'); }
// Provider decides validity; only reject unsafe HTTP-header values.
if (strlen($key) > 2048 || preg_match('/[\x00-\x1F\x7F]/', $key)) { http_response_code(400); exit('unsafe_google_key'); }
if (!function_exists('curl_init')) { http_response_code(503); exit('host_curl_extension_missing'); }
// Use the documented minimal REST payload. Google defaults to a one-minute
// new-session window and a 30-minute token lifetime. An unsupported constraint
// must not prevent an otherwise valid owner API key from connecting.
$payload = json_encode(['uses' => 1], JSON_UNESCAPED_SLASHES);
$client = curl_init('https://generativelanguage.googleapis.com/v1beta/auth_tokens');
curl_setopt_array($client, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['x-goog-api-key: ' . $key, 'Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT => 22,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_FOLLOWLOCATION => false,
]);
$out = curl_exec($client);
$code = (int)curl_getinfo($client, CURLINFO_HTTP_CODE);
curl_close($client);
if (!is_string($out)) { http_response_code(502); exit('live_token_network_error'); }
if ($code < 200 || $code >= 300) {
    $decodedError = json_decode($out, true);
    $googleError = is_array($decodedError) && is_array($decodedError['error'] ?? null)
        ? $decodedError['error'] : [];
    $googleStatus = (string)($googleError['status'] ?? '');
    $googleMessage = (string)($googleError['message'] ?? '');
    // Never reflect a credential, arbitrary upstream HTML, or long payloads.
    $googleMessage = str_replace($key, '[redacted]', $googleMessage);
    $googleMessage = (string)preg_replace('/AIza[A-Za-z0-9_-]+/', '[redacted]', $googleMessage);
    $googleMessage = (string)preg_replace('/[\x00-\x1F\x7F]/', ' ', $googleMessage);
    $googleMessage = mb_substr($googleMessage, 0, 200);
    http_response_code(in_array($code, [400,401,403,404,429], true) ? $code : 502);
    echo json_encode([
        'ok' => false,
        'error' => $code === 429 ? 'live_token_rate_limited' : 'google_live_token_rejected',
        'google_http' => $code,
        'google_status' => $googleStatus,
        'detail' => $googleMessage,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
$decoded = json_decode($out, true);
$token = is_array($decoded) ? (string)($decoded['name'] ?? '') : '';
if ($token === '') { http_response_code(502); exit('live_token_missing'); }
echo json_encode(['token' => $token, 'model' => 'gemini-3.8-live'], JSON_UNESCAPED_SLASHES);
