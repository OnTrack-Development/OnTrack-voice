<?php

/**
 * OnTrack Bridge — Local PII Tokenizer
 * Zero-Knowledge Tokenization running locally on the client's WHMCS server.
 * Replaces names, emails, phones, domains, and secrets with opaque tokens BEFORE
 * any payload is transmitted to OnTrack SaaS, and detokenizes AI responses locally.
 */
class LocalPiiTokenizer
{
    private static array $memoryTokens = [];

    /**
     * Tokenizes sensitive fields in an array before sending to the central SaaS.
     *
     * @param array $payload
     * @param string $sessionKey Ticket ID or message reference
     * @return array Tokenized payload
     */
    public static function tokenize(array $payload, string $sessionKey = 'default'): array
    {
        $map = [];
        $domainCount = 0;
        $emailCount = 0;

        // 1. Client Name
        if (!empty($payload['client_name'])) {
            $token = '[CLIENT_NAME_1]';
            $map[$token] = (string)$payload['client_name'];
            $payload['client_name'] = $token;
        }

        // 2. Client Email
        if (!empty($payload['client_email'])) {
            $emailCount++;
            $token = '[EMAIL_' . $emailCount . ']';
            $map[$token] = (string)$payload['client_email'];
            $payload['client_email'] = $token;
        }

        // 3. Client Phone
        if (!empty($payload['client_phone'])) {
            $token = '[PHONE_1]';
            $map[$token] = (string)$payload['client_phone'];
            $payload['client_phone'] = $token;
        }

        // 4. Tokenize text content (message & subject) for domains, emails, and IPs
        if (!empty($payload['message'])) {
            $res = self::tokenizeText((string)$payload['message'], $map, $domainCount, $emailCount);
            $payload['message'] = $res['text'];
            $map = $res['map'];
            $domainCount = $res['domainCount'];
            $emailCount = $res['emailCount'];
        }

        if (!empty($payload['subject'])) {
            $res = self::tokenizeText((string)$payload['subject'], $map, $domainCount, $emailCount);
            $payload['subject'] = $res['text'];
            $map = $res['map'];
            $domainCount = $res['domainCount'];
            $emailCount = $res['emailCount'];
        }

        self::saveMapping($sessionKey, $map);

        return $payload;
    }

    /**
     * Detokenizes a response received from OnTrack SaaS, restoring real names & domains.
     *
     * @param string $text
     * @param string $sessionKey
     * @return string
     */
    public static function detokenize(string $text, string $sessionKey = 'default'): string
    {
        $map = self::loadMapping($sessionKey);
        if (empty($map)) {
            return $text;
        }

        return str_replace(array_keys($map), array_values($map), $text);
    }

    private static function tokenizeText(string $text, array $existingMap, int &$domainCount, int &$emailCount): array
    {
        $map = $existingMap;

        // Tokenize Emails
        $text = preg_replace_callback('/[a-zA-Z0-9_\.\+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-\.]+/u', function ($m) use (&$map, &$emailCount) {
            $val = $m[0];
            $existingToken = array_search($val, $map, true);
            if ($existingToken) {
                return $existingToken;
            }
            $emailCount++;
            $token = '[EMAIL_' . $emailCount . ']';
            $map[$token] = $val;
            return $token;
        }, $text) ?? $text;

        // Tokenize Domains (e.g. example.com, my-site.net, clientdomain.org)
        $text = preg_replace_callback('/\b(?:[a-zA-Z0-9-]+\.)+(?:com|net|org|io|eg|com\.eg|app|me|co|info|biz)\b/i', function ($m) use (&$map, &$domainCount) {
            $val = $m[0];
            // Don't tokenize common system domains
            if (in_array(strtolower($val), ['google.com', 'facebook.com', 'ontrackegy.com'], true)) {
                return $val;
            }
            $existingToken = array_search($val, $map, true);
            if ($existingToken) {
                return $existingToken;
            }
            $domainCount++;
            $token = '[DOMAIN_' . $domainCount . ']';
            $map[$token] = $val;
            return $token;
        }, $text) ?? $text;

        // Redact Passwords / Secrets
        $text = preg_replace('/(password|passwd|pwd|secret)\s*[:=]\s*["\']?([^"\'\s\r\n]{4,})/i', '$1: [REDACTED_SECRET]', $text) ?? $text;

        return ['text' => $text, 'map' => $map, 'domainCount' => $domainCount, 'emailCount' => $emailCount];
    }

    private static function saveMapping(string $key, array $map): void
    {
        self::$memoryTokens[$key] = $map;

        // Also persist in WHMCS table if capsule database is available
        try {
            if (class_exists('\WHMCS\Database\Capsule')) {
                \WHMCS\Database\Capsule::table('mod_ontrack_bridge_tokens')->updateOrInsert(
                    ['session_key' => $key],
                    ['mapping_json' => json_encode($map, JSON_UNESCAPED_UNICODE), 'updated_at' => date('Y-m-d H:i:s')]
                );
            }
        } catch (\Throwable $e) {}
    }

    private static function loadMapping(string $key): array
    {
        if (isset(self::$memoryTokens[$key])) {
            return self::$memoryTokens[$key];
        }

        try {
            if (class_exists('\WHMCS\Database\Capsule')) {
                $row = \WHMCS\Database\Capsule::table('mod_ontrack_bridge_tokens')
                    ->where('session_key', $key)
                    ->first();
                if ($row && !empty($row->mapping_json)) {
                    return json_decode((string)$row->mapping_json, true) ?: [];
                }
            }
        } catch (\Throwable $e) {}

        return [];
    }
}
