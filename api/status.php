<?php
require __DIR__ . '/bootstrap.php';
json_out([
    'ok' => true,
    'gemini_configured' => !empty($config['gemini_api_key']),
    'gemini_model' => $config['gemini_model'],
    'tts_engine' => 'Server-side Edge Read Aloud - no API key',
    'edge_transport_available' => function_exists('stream_socket_client') && extension_loaded('openssl'),
    'voices' => [
        'male' => $config['male_voice'],
        'female' => $config['female_voice'],
    ],
    'company' => $config['company_name'],
]);
