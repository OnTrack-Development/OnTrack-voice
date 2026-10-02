<?php
require __DIR__ . '/bootstrap.php';

json_out([
    'ok' => true,
    'gemini_configured' => !empty($config['gemini_api_key']),
    'model' => 'gemini-3.8-live',
    'version' => '0.5.2',
    'mode' => 'client_preview',
    'sdk' => '@google/genai 2.25.0',
    'auth' => 'ephemeral_token',
    'voice_selection' => true,
    'transcription' => true,
    'mute_control' => true,
    'demo_knowledge' => true,
    'voice_url_sanitization' => true,
    'clickable_links' => true,
    'legacy_stt_tts_used' => false,
]);
