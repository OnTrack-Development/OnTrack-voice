<?php

/**
 * OnTrack Bridge — White-Label Widget Proxy
 * Acts as a local reverse proxy for web chat & voice assistant widgets.
 * Allows client's end-users to interact with the AI assistant through the client's own domain.
 */

require_once __DIR__ . '/../../../init.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $config = [];
    if (class_exists('\WHMCS\Database\Capsule')) {
        $rows = \WHMCS\Database\Capsule::table('tbladdonmodules')
            ->where('module', 'ontrack_bridge')
            ->get();
        foreach ($rows as $r) {
            $config[$r->setting] = $r->value;
        }
    }

    $saasUrl = rtrim((string)($config['saas_url'] ?? 'https://app.ontrackegy.com'), '/');
    $integrationKey = trim((string)($config['integration_key'] ?? ''));
    $secretKey = trim((string)($config['secret_key'] ?? ''));

    if ($integrationKey === '' || $secretKey === '') {
        echo json_encode(['error' => 'OnTrack Bridge is not configured.']);
        exit;
    }

    $action = $_GET['action'] ?? 'chat';
    $input = file_get_contents('php://input');

    $targetUrl = "{$saasUrl}/api/v1/bridge/widget?action=" . urlencode($action);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$input}", $secretKey);

    $ch = curl_init($targetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        "X-Ontrack-Key: {$integrationKey}",
        "X-Ontrack-Timestamp: {$timestamp}",
        "X-Ontrack-Signature: {$signature}",
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    http_response_code($httpCode ?: 200);
    echo $response ?: json_encode(['error' => 'Empty response from central server.']);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Proxy error: ' . $e->getMessage()]);
}
