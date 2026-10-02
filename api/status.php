<?php
require __DIR__ . '/bootstrap.php';

json_out([
    'ok' => true,
    'gemini_configured' => !empty($config['gemini_api_key']),
    'model' => 'gemini-3.8-live',
    'mode' => 'native_audio_live',
    'transport' => 'WebSocket client-to-server with ephemeral token',
    'input_audio' => 'PCM16 live stream',
    'output_audio' => 'PCM16 24kHz live stream',
    'legacy_stt_tts_used' => false,
]);
