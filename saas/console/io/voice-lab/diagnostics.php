<?php
declare(strict_types=1);
require_once __DIR__ . '/security.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
$method = (string)($_SERVER['REQUEST_METHOD'] ?? '');
if (!in_array($method, ['GET','POST'], true)) {
    header('Allow: GET, POST');
    voice_lab_error(405, 'method_not_allowed');
}
$user = voice_lab_access($method === 'POST');
$uid = (int)$user['id'];
if ($method === 'GET') {
    if (!empty($user['voice_lab_guest'])) {
        // The access code cannot expose the owner's saved server diagnostics.
        echo '{"ok":true,"entries":[]}';
        exit;
    }
    session_write_close();
    try {
        echo json_encode(['ok'=>true, 'entries'=>voice_lab_trace_read($uid)],
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    } catch (Throwable $e) { voice_lab_error(503, 'diagnostics_unavailable'); }
    exit;
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 1200) voice_lab_error(413, 'request_too_large');
$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload) || !is_string($payload['stage'] ?? null)
    || !is_string($payload['attempt'] ?? null) || !is_array($payload['meta'] ?? null)) {
    voice_lab_error(400, 'invalid_diagnostic');
}
$allowed = [
    'call_start','token_fetch_start','token_fetch_ok','token_fetch_error','token_http_error',
    'token_invalid_reply','ws_connect_start','ws_open','setup_sent','setup_complete',
    'setup_timeout','ws_error','ws_close','provider_error','mic_permission_start',
    'mic_ready','mic_error','first_audio','audio_error','call_error','call_stopped',
    'audio_context_resume_start','audio_context_resume_ok','audio_context_resume_error',
    'text_sent','turn_complete','ws_invalid_message',
    'fish_request_start','fish_http_response','fish_first_audio','fish_complete','fish_error'
];
if (!in_array($payload['stage'], $allowed, true)
    || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $payload['attempt'])) {
    voice_lab_error(422, 'invalid_diagnostic_event');
}
if (!empty($user['voice_lab_guest'])) {
    // Keep session-local browser diagnostics out of the owner's historical logs.
    echo '{"ok":true}';
    exit;
}
session_write_close();
voice_lab_trace_log($uid, $payload['attempt'], $payload['stage'], $payload['meta']);
echo '{"ok":true}';
