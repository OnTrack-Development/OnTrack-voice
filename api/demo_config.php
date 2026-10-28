<?php
$defaults = [
    'app_name' => 'OnTrack Gemini Live Demo',
    'gemini_api_key' => '',
    'gemini_model' => 'gemini-3.8-live',
    'company_name' => 'OnTrack Development',
];

$local = __DIR__ . '/demo_config.local.php';
if (is_readable($local)) {
    $localCfg = require $local;
    if (is_array($localCfg)) $defaults = array_replace($defaults, $localCfg);
}

$defaults['gemini_model'] = 'gemini-3.8-live';

return $defaults;
