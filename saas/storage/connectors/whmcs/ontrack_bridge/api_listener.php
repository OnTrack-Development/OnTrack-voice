<?php

/**
 * OnTrack Bridge — Remote Tool Execution Listener
 * Receives signed commands from OnTrack SaaS central server and executes them locally on WHMCS.
 */

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/LocalPiiTokenizer.php';
require_once __DIR__ . '/AuditLog.php';

header('Content-Type: application/json; charset=UTF-8');

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

    $secretKey = trim((string)($config['secret_key'] ?? ''));
    if ($secretKey === '') {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Module secret key is not set.']);
        exit;
    }

    $rawPayload = file_get_contents('php://input');
    $timestamp = (int)($_SERVER['HTTP_X_ONTRACK_TIMESTAMP'] ?? 0);
    $signature = (string)($_SERVER['HTTP_X_ONTRACK_SIGNATURE'] ?? '');

    // 1. Verify Timestamp Window (5 minutes)
    if (abs(time() - $timestamp) > 300) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Timestamp expired or skewed.']);
        exit;
    }

    // 2. Verify HMAC Signature
    $expectedSig = hash_hmac('sha256', "{$timestamp}.{$rawPayload}", $secretKey);
    if (!hash_equals($expectedSig, $signature)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Invalid HMAC signature.']);
        exit;
    }

    $data = json_decode($rawPayload, true) ?: [];
    $action = (string)($data['action'] ?? '');
    $params = (array)($data['params'] ?? []);

    $result = ['ok' => true];

    switch ($action) {
        case 'test_connection':
            $result = [
                'ok' => true,
                'whmcs_version' => $GLOBALS['CONFIG']['Version'] ?? '8.x',
                'status' => 'operational',
                'timestamp' => time(),
            ];
            break;

        case 'get_client_summary':
            $clientId = (int)($params['client_id'] ?? 0);
            $sendInvoices = !empty($config['enable_invoices_sync']);

            $client = [];
            $services = [];
            $invoices = [];

            if ($clientId > 0 && class_exists('\WHMCS\Database\Capsule')) {
                $cRow = \WHMCS\Database\Capsule::table('tblclients')->where('id', $clientId)->first();
                if ($cRow) {
                    $client = [
                        'id' => $cRow->id,
                        'name' => $cRow->firstname . ' ' . $cRow->lastname,
                        'email' => $cRow->email,
                        'company' => $cRow->companyname,
                        'currency' => $cRow->currency,
                    ];
                }

                $sRows = \WHMCS\Database\Capsule::table('tblhosting')->where('userid', $clientId)->get();
                foreach ($sRows as $s) {
                    $services[] = [
                        'id' => $s->id,
                        'domain' => $s->domain,
                        'status' => $s->domainstatus,
                        'package_id' => $s->packageid,
                    ];
                }

                if ($sendInvoices) {
                    $iRows = \WHMCS\Database\Capsule::table('tblinvoices')
                        ->where('userid', $clientId)
                        ->orderBy('id', 'desc')
                        ->take(5)
                        ->get();
                    foreach ($iRows as $inv) {
                        $invoices[] = [
                            'id' => $inv->id,
                            'total' => $inv->total,
                            'status' => $inv->status,
                            'duedate' => $inv->duedate,
                        ];
                    }
                }
            }

            // Apply Privacy Tokenization
            $clientTokenized = LocalPiiTokenizer::tokenize($client, "client_{$clientId}");
            $result = [
                'ok' => true,
                'client' => $clientTokenized,
                'services' => $services,
                'invoices' => $invoices,
            ];
            break;

        case 'post_ticket_reply':
            $ticketId = (int)($params['ticket_id'] ?? 0);
            $rawReply = (string)($params['message'] ?? '');
            $adminUser = (string)($params['admin_username'] ?? 'OnTrack AI');

            // Detokenize the message locally before publishing to restore real names/domains!
            $cleanReply = LocalPiiTokenizer::detokenize($rawReply, "ticket_{$ticketId}");

            $postResult = localAPI('AddTicketReply', [
                'ticketid' => $ticketId,
                'message' => $cleanReply,
                'adminusername' => $adminUser,
            ]);

            $result = [
                'ok' => ($postResult['result'] ?? '') === 'success',
                'whmcs_result' => $postResult,
            ];
            break;

        case 'cpanel_diagnostics':
            if (empty($config['enable_cpanel_diagnostics'])) {
                $result = ['ok' => false, 'error' => 'cPanel diagnostics disabled in module settings.'];
                break;
            }

            // Return mock/safe hardware load metrics
            $result = [
                'ok' => true,
                'load_average' => '0.45, 0.50, 0.40',
                'disk_usage' => '12.4 GB / 100 GB (12%)',
                'php_version' => PHP_VERSION,
                'status' => 'healthy',
            ];
            break;

        default:
            http_response_code(400);
            $result = ['ok' => false, 'error' => "Unknown action: {$action}"];
            break;
    }

    OnTrackBridgeAudit::log($action, $data, $result);
    echo json_encode($result);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
