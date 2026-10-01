<?php
require __DIR__ . '/bootstrap.php';
json_out([
    'ok' => true,
    'gemini_configured' => !empty($config['gemini_api_key']),
    'gemini_model' => $config['gemini_model'],
    'gemini_thinking_level' => $config['gemini_thinking_level'] ?? 'low',
    'tts_engine' => 'VoiceTut-TTS via free Hugging Face Space',
    'tts_fallback' => 'Edge Egyptian',
    'voice_api_key_required' => false,
    'voicetut_speakers' => [
        'male' => ['Abdelrahman','Abdullah','Kamal','Hossam','Mohamed','Omar','Sayed','Zaki','Aly','Essam','Ahmed'],
        'female' => ['Asmaa','Esraa','Hanan','Sarah','Yasmin','Omnia']
    ],
    'company' => $config['company_name'],
]);
