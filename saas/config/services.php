<?php
return [
    'kashier' => [
        'mode' => env('KASHIER_MODE', 'test'),
        'merchant_id' => env('KASHIER_MERCHANT_ID'),
        'api_key' => env('KASHIER_API_KEY'),
        'secret_key' => env('KASHIER_SECRET_KEY'),
    ],
];
