<?php

/**
 * OnTrack Bridge — Privacy & Audit Log
 * Persists local audit records showing exactly what data left the WHMCS server.
 */
class OnTrackBridgeAudit
{
    public static function log(string $event, array $payload, array $response, int $httpStatus = 200): void
    {
        try {
            if (class_exists('\WHMCS\Database\Capsule')) {
                \WHMCS\Database\Capsule::table('mod_ontrack_bridge_audit')->insert([
                    'event' => $event,
                    'endpoint' => (string)($payload['endpoint'] ?? '/api/v1/bridge/events'),
                    'payload_summary' => mb_substr(json_encode($payload, JSON_UNESCAPED_UNICODE), 0, 1000),
                    'http_status' => $httpStatus,
                    'response_summary' => mb_substr(json_encode($response, JSON_UNESCAPED_UNICODE), 0, 500),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Throwable $e) {}
    }

    public static function recent(int $limit = 20): array
    {
        try {
            if (class_exists('\WHMCS\Database\Capsule')) {
                $rows = \WHMCS\Database\Capsule::table('mod_ontrack_bridge_audit')
                    ->orderBy('id', 'desc')
                    ->take($limit)
                    ->get();
                return json_decode(json_encode($rows), true) ?: [];
            }
        } catch (\Throwable $e) {}

        return [];
    }
}
