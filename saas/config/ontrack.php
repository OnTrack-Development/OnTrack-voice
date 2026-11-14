<?php

return [
    'vault_path' => env('ONTRACK_VAULT_PATH', is_dir('/home/appontrack/.ontrack-saas') ? '/home/appontrack/.ontrack-saas' : storage_path('app/ontrack-vault')),
    'legacy_sqlite_path' => env('ONTRACK_LEGACY_SQLITE_PATH', '/home/appontrack/.ontrack-saas/ontrack-console.sqlite'),
    'public_domain' => env('ONTRACK_PUBLIC_DOMAIN', 'app.ontrackegy.com'),
    'admin_host' => env('ONTRACK_ADMIN_HOST', 'app.ontrackegy.com'),
    'gemini_api_key' => env('ONTRACK_GEMINI_API_KEY', ''),
    'gemini_live_model' => env('ONTRACK_GEMINI_LIVE_MODEL', 'gemini-2.5-flash-native-audio-preview-12-2025'),
    'campaign_dial_enabled' => filter_var(env('ONTRACK_TENANT_CAMPAIGN_DIAL_ENABLED', false), FILTER_VALIDATE_BOOL),
    'apk_version_code' => 77,
    'apk_version_name' => '0.3.54',
    'ota_revision' => 77,
    'apk_download_path' => '/mobile/updates/native/latest.apk',
];
