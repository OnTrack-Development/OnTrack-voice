<?php
$local = __DIR__ . '/demo_config.local.php';
if (is_readable($local)) {
    $cfg = require $local;
    if (is_array($cfg)) return $cfg;
}
return [
    'app_name' => 'OnTrack Voice Demo',
    'gemini_api_key' => '',
    'gemini_model' => 'gemini-3.5-flash-lite',
    'gemini_timeout' => 25,
    'gemini_max_output_tokens' => 500,
    'male_voice' => 'ar-EG-ShakirNeural',
    'female_voice' => 'ar-EG-SalmaNeural',
    'company_name' => 'OnTrack Development',
    'max_user_chars' => 1800,
    'max_history_items' => 10,
];
