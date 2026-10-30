<?php

namespace App\Support;

/**
 * Central URL policy for business connectors.
 * HTTPS-only, public hosts only, no userinfo, port 443; DNS resolved and every
 * IP re-checked so private/metadata/loopback targets are never stored.
 */
final class UrlPolicy
{
    public static function isValidBaseUrl(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return true;
        }
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https') {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        if (isset($parts['port']) && (int) $parts['port'] !== 443) {
            return false;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        return self::isPublicHost($host);
    }

    public static function isPublicHost(string $host): bool
    {
        $host = trim($host, '[]');
        $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        if ($isIp) {
            return self::isPublicIp($host);
        }
        if (in_array($host, ['localhost', 'localhost.localdomain'], true)) {
            return false;
        }
        $ips = @gethostbynamel($host);
        if ($ips === false || $ips === []) {
            return false; // unresolvable or DNS failure: do not store as if public
        }
        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                return false;
            }
        }
        return true;
    }

    private static function isPublicIp(string $ip): bool
    {
        if (in_array($ip, ['0.0.0.0', '::', '::1', '169.254.169.254'], true)) {
            return false;
        }
        $filtered = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        return $filtered !== false;
    }

    /**
     * Relative path only: starts with '/', no protocol-relative '//', no full URL,
     * no backslashes, no '..' traversal. Concatenation may never change the host.
     */
    public static function isValidPath(?string $path): bool
    {
        if ($path === null || trim($path) === '') {
            return false;
        }
        $path = trim($path);
        if (strlen($path) > 500) {
            return false;
        }
        if (! str_starts_with($path, '/')) {
            return false;
        }
        if (preg_match('#^//#', $path)) {
            return false;
        }
        if (stripos($path, '://') !== false) {
            return false;
        }
        if (str_contains($path, '\\')) {
            return false;
        }
        if (preg_match('#(^|/)(\.\./|\.\.$)#', $path)) {
            return false;
        }
        return true;
    }
}