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
if (strlen($key) < 20 || strlen($key) > 256 || !preg_match('/^[A-Za-z0-9_-]+$/', $key)) { http_response_code(400); exit('gemini_key_required'); }
if (!function_exists('curl_init')) { http_response_code(503); exit('host_curl_extension_missing'); }
$payload = json_encode([
    'uses' => 1,
    'expireTime' => gmdate('Y-m-d\TH:i:s\Z', time() + 1800),
    'newSessionExpireTime' => gmdate('Y-m-d\TH:i:s\Z', time() + 60),
    'liveConnectConstraints' => ['model' => 'models/gemini-3.8-live'],
], JSON_UNESCAPED_SLASHES);
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
    http_response_code(in_array($code, [400,401,403,404,429], true) ? $code : 502);
    exit($code === 429 ? 'live_token_rate_limited' : 'live_token_upstream_error');
}
$decoded = json_decode($out, true);
$token = is_array($decoded) ? (string)($decoded['name'] ?? '') : '';
if ($token === '') { http_response_code(502); exit('live_token_missing'); }
echo json_encode(['token' => $token, 'model' => 'gemini-3.8-live'], JSON_UNESCAPED_SLASHES);
