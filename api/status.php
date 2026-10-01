<?php
require __DIR__ . '/bootstrap.php';
json_out([
    'ok' => true,
    'gemini_configured' => !empty($config['gemini_api_key']),
    'gemini_model' => $config['gemini_model'],
    'gemini_thinking_level' => $config['gemini_thinking_level'] ?? 'low',
    'tts_engine' => 'No-key TTS: Device + Edge Read Aloud',
    'voice_api_key_required' => false,
    'voices' => [
        'male' => $config['male_voice'],
        'female' => $config['female_voice'],
    ],
    'dialect' => 'Egyptian Arabic',
    'company' => $config['company_name'],
]);
