<?php
$defaults = [
    'app_name' => 'OnTrack Voice Demo',
    'gemini_api_key' => '',
    'gemini_model' => 'gemini-3.8-flash',
    'gemini_timeout' => 30,
    'gemini_max_output_tokens' => 700,
    'gemini_thinking_level' => 'low',
    'male_voice' => 'ar-EG-ShakirNeural',
    'female_voice' => 'ar-EG-SalmaNeural',
    'company_name' => 'OnTrack Development',
    'max_user_chars' => 1800,
    'max_history_items' => 10,
];

$local = __DIR__ . '/demo_config.local.php';
if (is_readable($local)) {
    $localCfg = require $local;
    if (is_array($localCfg)) $defaults = array_replace($defaults, $localCfg);
}

$defaults['gemini_model'] = 'gemini-3.8-flash';
$defaults['gemini_thinking_level'] = 'low';
$defaults['male_voice'] = 'ar-EG-ShakirNeural';
$defaults['female_voice'] = 'ar-EG-SalmaNeural';

return $defaults;
