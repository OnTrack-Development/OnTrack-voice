<?php

namespace App\Services\Security;

use RuntimeException;

class VaultService
{
    public function vaultDir(): string
    {
        $configured = (string) config('ontrack.vault_path');
        $dir = $configured !== '' ? $configured : storage_path('app/ontrack-vault');
        if (! is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        return rtrim($dir, '/\\');
    }

    public function secretKey(): string
    {
        $path = $this->vaultDir().DIRECTORY_SEPARATOR.'console-vault.key';
        if (! is_file($path)) {
            $fh = @fopen($path, 'x');
            if ($fh !== false) {
                try {
                    @chmod($path, 0600);
                    fwrite($fh, random_bytes(32));
                } finally {
                    fclose($fh);
                }
            }
        }
        $key = @file_get_contents($path);
        if (! is_string($key) || strlen($key) !== 32) {
            throw new RuntimeException('مفتاح خزنة الأسرار غير متاح');
        }

        return $key;
    }

    public function hmacSecret(string $filename = 'sim_pairing_hmac_v2.key'): string
    {
        $path = $this->vaultDir().DIRECTORY_SEPARATOR.basename($filename);
        if (! is_file($path)) {
            $fh = @fopen($path, 'x');
            if ($fh !== false) {
                try {
                    @chmod($path, 0600);
                    fwrite($fh, random_bytes(32));
                } finally {
                    fclose($fh);
                }
            }
        }
        $key = @file_get_contents($path);
        if (! is_string($key) || strlen($key) !== 32) {
            throw new RuntimeException('مفتاح HMAC غير متاح');
        }

        return $key;
    }

    /**
     * Exact legacy-compatible AES-256-GCM envelope: base64(nonce[12] . tag[16] . cipher).
     */
    public function seal(string $plain): string
    {
        $nonce = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->secretKey(), OPENSSL_RAW_DATA, $nonce, $tag);
        if ($cipher === false || strlen($tag) !== 16) {
            throw new RuntimeException('تعذر تشفير السر');
        }

        return base64_encode($nonce.$tag.$cipher);
    }

    private array $unsealCache = [];
    private array $jsonFileCache = [];

    public function unseal(?string $box): ?string
    {
        if (! is_string($box) || $box === '') {
            return null;
        }
        if (isset($this->unsealCache[$box])) {
            return $this->unsealCache[$box];
        }
        $raw = base64_decode($box, true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $plain = openssl_decrypt(
            substr($raw, 28),
            'aes-256-gcm',
            $this->secretKey(),
            OPENSSL_RAW_DATA,
            substr($raw, 0, 12),
            substr($raw, 12, 16)
        );

        if ($plain !== false) {
            if (count($this->unsealCache) > 256) {
                $this->unsealCache = [];
            }
            $this->unsealCache[$box] = $plain;
            return $plain;
        }

        return null;
    }

    public function sealTranscript(string $plain): string
    {
        return 'enc:v1:'.$this->seal($plain);
    }

    public function unsealTranscript(?string $stored): ?string
    {
        if (! is_string($stored) || $stored === '') {
            return null;
        }
        if (str_starts_with($stored, 'enc:v1:')) {
            return $this->unseal(substr($stored, 7));
        }

        return $stored;
    }

    public function readJsonFile(string $relativePath, array $default = []): array
    {
        $path = $this->vaultDir().DIRECTORY_SEPARATOR.ltrim($relativePath, '/\\');
        if (is_link($path)) {
            throw new RuntimeException('مسار القراءة غير آمن');
        }
        if (! is_file($path)) {
            return $default;
        }
        $mtime = filemtime($path);
        if (isset($this->jsonFileCache[$path]) && $this->jsonFileCache[$path]['mtime'] === $mtime) {
            return $this->jsonFileCache[$path]['data'];
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        $data = is_array($decoded) ? $decoded : $default;
        if (count($this->jsonFileCache) > 64) {
            $this->jsonFileCache = [];
        }
        $this->jsonFileCache[$path] = ['mtime' => $mtime, 'data' => $data];

        return $data;
    }

    public function writeJsonFileAtomic(string $relativePath, array $payload): void
    {
        $path = $this->vaultDir().DIRECTORY_SEPARATOR.ltrim($relativePath, '/\\');
        $dir = dirname($path);
        if (is_link($dir) || is_link($path)) {
            throw new RuntimeException('مسار الحفظ غير آمن');
        }
        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new RuntimeException('تعذر إنشاء مجلد التخزين الآمن');
        }
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $tmp = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
        try {
            if (file_put_contents($tmp, $encoded, LOCK_EX) !== strlen($encoded)) {
                throw new RuntimeException('تعذر كتابة ملف البيانات');
            }
            @chmod($tmp, 0600);
            if (! rename($tmp, $path)) {
                throw new RuntimeException('تعذر اعتماد ملف البيانات');
            }
            @chmod($path, 0600);
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }
}
