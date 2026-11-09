<?php
$defaults = [
    'app_name' => 'OnTrack Gemini Live Demo',
    'gemini_api_key' => '',
    'gemini_model' => 'gemini-3.8-live',
    'company_name' => 'OnTrack Development',
];

// Preserve the existing deployment's config until the host operator migrates it.
// Newly configured credentials live outside public_html, but legacy settings
// stay readable and are never silently overwritten by a security upgrade.
$legacy = __DIR__ . '/demo_config.local.php';
$private = '/home/appontrack/.ontrack-demo/demo_config.php';
$local = (is_file($private) && !is_link($private)) ? $private : $legacy;
if (is_readable($local) && !is_link($local)) {
    $localCfg = require $local;
    if (is_array($localCfg)) $defaults = array_replace($defaults, $localCfg);
}

$defaults['gemini_model'] = 'gemini-3.8-live';

return $defaults;
