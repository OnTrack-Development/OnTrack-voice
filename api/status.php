<?php
require __DIR__ . '/bootstrap.php';

json_out([
    'ok' => true,
    'gemini_configured' => !empty($config['gemini_api_key']),
    'model' => 'gemini-3.8-live',
    'version' => '0.4.5',
    'mode' => 'minimal_constrained_live',
    'transport' => 'BidiGenerateContentConstrained + ephemeral token',
    'setup' => 'model + generationConfig.responseModalities only',
    'input_audio' => 'PCM16 16kHz mono',
    'output_audio' => 'PCM16 24kHz mono',
    'legacy_stt_tts_used' => false,
]);
