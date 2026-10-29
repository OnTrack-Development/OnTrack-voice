<?php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: no-store');

$legacyConfig = [];
$legacyPath = __DIR__ . '/../config.php';
if (is_readable($legacyPath)) {
    $tmp = require $legacyPath;
    if (is_array($tmp)) $legacyConfig = $tmp;
}
$demoConfig = require __DIR__ . '/demo_config.php';
$config = array_replace($legacyConfig, $demoConfig);

$kbPath = __DIR__ . '/../data/knowledge.json';
$kb = json_decode((string)@file_get_contents($kbPath), true);
if (!is_array($kb)) $kb = [];

function json_out($data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function clean_text(string $text, int $max): string {
    $text = trim(strip_tags($text));
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return mb_substr($text, 0, $max, 'UTF-8');
}
function extract_gemini_text(array $data): string {
    $parts = $data['candidates'][0]['content']['parts'] ?? [];
    $chunks = [];
    foreach ($parts as $part) {
        if (isset($part['text']) && is_string($part['text'])) $chunks[] = $part['text'];
    }
    return trim(implode("\n", $chunks));
}
function safe_google_error($raw): string {
    if (!is_string($raw) || $raw === '') return 'No response body';
    $j = json_decode($raw, true);
    $msg = $j['error']['message'] ?? '';
    if (is_string($msg) && $msg !== '') return mb_substr($msg, 0, 500, 'UTF-8');
    return 'Google API returned an error';
}
