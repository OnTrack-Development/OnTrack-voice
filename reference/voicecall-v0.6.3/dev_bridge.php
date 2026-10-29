<?php
declare(strict_types=1);

/**
 * OnTrack Voice Demo - Temporary Dev Bridge
 * Root-level maintenance bridge for shared hosting.
 * Safe scope: status/configure/update/rollback/lock only.
 * Updates are accepted only from github.com / api.github.com archives.
 */

const BRIDGE_VERSION = '1.0.0';
const BRIDGE_DIR = __DIR__ . '/.devbridge';
const BRIDGE_CONFIG = BRIDGE_DIR . '/config.json';
const BRIDGE_LOCK = BRIDGE_DIR . '/LOCKED';
const BRIDGE_LOG = BRIDGE_DIR . '/bridge.log';
const BRIDGE_BACKUPS = BRIDGE_DIR . '/backups';
const BRIDGE_TMP = BRIDGE_DIR . '/tmp';
const MAINTENANCE_TOKEN = 'REDACTED_LOCAL_SECRET';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

function out(array $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function param(string $name, ?string $default = null): ?string {
    if (isset($_POST[$name])) return is_string($_POST[$name]) ? trim($_POST[$name]) : $default;
    if (isset($_GET[$name])) return is_string($_GET[$name]) ? trim($_GET[$name]) : $default;
    return $default;
}

function log_line(string $message): void {
    @mkdir(BRIDGE_DIR, 0700, true);
    @file_put_contents(BRIDGE_LOG, '[' . gmdate('c') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function auth(): void {
    $provided = $_SERVER['HTTP_X_MAINTENANCE_TOKEN'] ?? param('token', '');
    if (!is_string($provided) || !hash_equals(MAINTENANCE_TOKEN, $provided)) {
        log_line('Denied request from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        out(['ok' => false, 'error' => 'unauthorized'], 401);
    }
}

function ensure_dirs(): void {
    foreach ([BRIDGE_DIR, BRIDGE_BACKUPS, BRIDGE_TMP] as $d) {
        if (!is_dir($d) && !@mkdir($d, 0700, true) && !is_dir($d)) {
            out(['ok' => false, 'error' => 'cannot_create_directory', 'path' => $d], 500);
        }
    }
    $deny = BRIDGE_DIR . '/.htaccess';
    if (!file_exists($deny)) {
        @file_put_contents($deny, "Require all denied\nDeny from all\n");
    }
    $index = BRIDGE_DIR . '/index.html';
    if (!file_exists($index)) @file_put_contents($index, '');
}

function default_config(): array {
    return [
        'github_owner' => '',
        'github_repo' => '',
        'branch' => 'main',
        'exclude' => [
            'config.php',
            '.devbridge',
            'dev_bridge.php',
            'logs',
            '.git',
            '.github'
        ],
        'updated_at' => null,
        'last_backup' => null,
        'last_commit' => null,
    ];
}

function load_config(): array {
    ensure_dirs();
    $cfg = default_config();
    if (is_file(BRIDGE_CONFIG)) {
        $raw = @file_get_contents(BRIDGE_CONFIG);
        $saved = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($saved)) $cfg = array_replace($cfg, $saved);
    }
    return $cfg;
}

function save_config(array $cfg): void {
    ensure_dirs();
    $ok = @file_put_contents(BRIDGE_CONFIG, json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
    if ($ok === false) out(['ok' => false, 'error' => 'cannot_write_bridge_config'], 500);
}

function clean_name(string $s): string {
    return preg_replace('/[^A-Za-z0-9_.-]/', '', $s) ?: '';
}

function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $p = $f->getPathname();
        $f->isDir() ? @rmdir($p) : @unlink($p);
    }
    @rmdir($dir);
}

function is_excluded(string $relative, array $exclude): bool {
    $relative = trim(str_replace('\\', '/', $relative), '/');
    foreach ($exclude as $item) {
        $item = trim(str_replace('\\', '/', (string)$item), '/');
        if ($item === '') continue;
        if ($relative === $item || str_starts_with($relative, $item . '/')) return true;
    }
    return false;
}

function copy_tree(string $src, string $dst, string $root, array $exclude): void {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $from = $item->getPathname();
        $rel = ltrim(str_replace('\\', '/', substr($from, strlen($src))), '/');
        if ($rel === '' || is_excluded($rel, $exclude)) continue;
        $to = $dst . '/' . $rel;
        if ($item->isDir()) {
            if (!is_dir($to)) @mkdir($to, 0755, true);
        } else {
            $parent = dirname($to);
            if (!is_dir($parent)) @mkdir($parent, 0755, true);
            if (!@copy($from, $to)) {
                throw new RuntimeException('copy_failed: ' . $rel);
            }
        }
    }
}

function backup_current(array $exclude): string {
    if (!class_exists('ZipArchive')) throw new RuntimeException('ZipArchive extension is required for backup/update.');
    ensure_dirs();
    $name = 'backup_' . gmdate('Ymd_His') . '.zip';
    $path = BRIDGE_BACKUPS . '/' . $name;
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('cannot_create_backup');
    }
    $root = __DIR__;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $abs = $file->getPathname();
        $rel = ltrim(str_replace('\\', '/', substr($abs, strlen($root))), '/');
        if ($rel === '' || is_excluded($rel, array_merge($exclude, ['.devbridge']))) continue;
        $zip->addFile($abs, $rel);
    }
    $zip->close();
    return $name;
}

function curl_download(string $url, string $dest): void {
    $parts = parse_url($url);
    $host = strtolower((string)($parts['host'] ?? ''));
    if (!in_array($host, ['github.com', 'api.github.com', 'codeload.github.com'], true)) {
        throw new RuntimeException('Only GitHub update sources are allowed.');
    }
    $fp = @fopen($dest, 'wb');
    if (!$fp) throw new RuntimeException('cannot_open_download_target');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'OnTrack-Voice-DevBridge/' . BRIDGE_VERSION,
        CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
    ]);
    $ok = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    fclose($fp);
    if (!$ok || $status < 200 || $status >= 300) {
        @unlink($dest);
        throw new RuntimeException('download_failed http=' . $status . ' ' . $err);
    }
}

function github_commit(array $cfg): ?string {
    if (empty($cfg['github_owner']) || empty($cfg['github_repo'])) return null;
    $url = sprintf('https://api.github.com/repos/%s/%s/commits/%s', rawurlencode($cfg['github_owner']), rawurlencode($cfg['github_repo']), rawurlencode($cfg['branch'] ?: 'main'));
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'OnTrack-Voice-DevBridge/' . BRIDGE_VERSION,
        CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($status !== 200 || !is_string($raw)) return null;
    $j = json_decode($raw, true);
    return is_array($j) && isset($j['sha']) ? (string)$j['sha'] : null;
}

function apply_update(array &$cfg): array {
    if (!class_exists('ZipArchive')) throw new RuntimeException('ZipArchive extension is required.');
    $owner = clean_name((string)$cfg['github_owner']);
    $repo = clean_name((string)$cfg['github_repo']);
    $branch = clean_name((string)($cfg['branch'] ?: 'main'));
    if ($owner === '' || $repo === '') throw new RuntimeException('bridge_not_configured');

    ensure_dirs();
    $backup = backup_current((array)$cfg['exclude']);
    $cfg['last_backup'] = $backup;
    save_config($cfg);

    $work = BRIDGE_TMP . '/update_' . bin2hex(random_bytes(5));
    @mkdir($work, 0700, true);
    $zipPath = $work . '/source.zip';
    $extract = $work . '/src';
    @mkdir($extract, 0700, true);

    $url = sprintf('https://api.github.com/repos/%s/%s/zipball/%s', $owner, $repo, $branch);
    curl_download($url, $zipPath);

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) throw new RuntimeException('invalid_update_zip');
    if (!$zip->extractTo($extract)) {
        $zip->close();
        throw new RuntimeException('extract_failed');
    }
    $zip->close();

    $top = array_values(array_filter(scandir($extract) ?: [], fn($v) => $v !== '.' && $v !== '..'));
    if (count($top) !== 1 || !is_dir($extract . '/' . $top[0])) throw new RuntimeException('unexpected_github_archive');
    $sourceRoot = $extract . '/' . $top[0];

    copy_tree($sourceRoot, __DIR__, __DIR__, (array)$cfg['exclude']);
    $sha = github_commit($cfg);
    $cfg['updated_at'] = gmdate('c');
    $cfg['last_commit'] = $sha;
    save_config($cfg);
    rrmdir($work);
    log_line('Update applied from ' . $owner . '/' . $repo . '@' . $branch . ' commit=' . ($sha ?? 'unknown'));

    return ['backup' => $backup, 'commit' => $sha, 'updated_at' => $cfg['updated_at']];
}

function rollback(array &$cfg): array {
    if (!class_exists('ZipArchive')) throw new RuntimeException('ZipArchive extension is required.');
    $backup = clean_name((string)($cfg['last_backup'] ?? ''));
    if ($backup === '' || !is_file(BRIDGE_BACKUPS . '/' . $backup)) throw new RuntimeException('no_backup_available');
    $zip = new ZipArchive();
    if ($zip->open(BRIDGE_BACKUPS . '/' . $backup) !== true) throw new RuntimeException('cannot_open_backup');
    if (!$zip->extractTo(__DIR__)) {
        $zip->close();
        throw new RuntimeException('rollback_extract_failed');
    }
    $zip->close();
    $cfg['updated_at'] = gmdate('c');
    save_config($cfg);
    log_line('Rollback restored ' . $backup);
    return ['restored' => $backup, 'at' => $cfg['updated_at']];
}

auth();
ensure_dirs();
$action = strtolower((string)param('action', 'status'));

if (is_file(BRIDGE_LOCK) && $action !== 'status') {
    out(['ok' => false, 'locked' => true, 'message' => 'Dev bridge is locked.'], 423);
}

try {
    $cfg = load_config();

    if ($action === 'status') {
        $appConfig = __DIR__ . '/config.php';
        $knowledge = __DIR__ . '/data/knowledge.json';
        out([
            'ok' => true,
            'bridge_version' => BRIDGE_VERSION,
            'locked' => is_file(BRIDGE_LOCK),
            'php' => PHP_VERSION,
            'curl' => function_exists('curl_init'),
            'zip' => class_exists('ZipArchive'),
            'root_writable' => is_writable(__DIR__),
            'app_config' => is_readable($appConfig),
            'knowledge' => is_readable($knowledge),
            'github' => [
                'owner' => $cfg['github_owner'],
                'repo' => $cfg['github_repo'],
                'branch' => $cfg['branch'],
                'last_commit' => $cfg['last_commit'],
            ],
            'updated_at' => $cfg['updated_at'],
            'last_backup' => $cfg['last_backup'],
        ]);
    }

    if ($action === 'configure') {
        $owner = clean_name((string)param('owner', ''));
        $repo = clean_name((string)param('repo', ''));
        $branch = clean_name((string)param('branch', 'main')) ?: 'main';
        if ($owner === '' || $repo === '') out(['ok' => false, 'error' => 'owner_and_repo_required'], 400);
        $cfg['github_owner'] = $owner;
        $cfg['github_repo'] = $repo;
        $cfg['branch'] = $branch;
        save_config($cfg);
        log_line('Configured github=' . $owner . '/' . $repo . '@' . $branch);
        out(['ok' => true, 'configured' => ['owner' => $owner, 'repo' => $repo, 'branch' => $branch]]);
    }

    if ($action === 'update') {
        $result = apply_update($cfg);
        out(['ok' => true, 'action' => 'update', 'result' => $result]);
    }

    if ($action === 'rollback') {
        $result = rollback($cfg);
        out(['ok' => true, 'action' => 'rollback', 'result' => $result]);
    }

    if ($action === 'lock') {
        @file_put_contents(BRIDGE_LOCK, 'locked ' . gmdate('c'));
        log_line('Bridge locked');
        out(['ok' => true, 'locked' => true, 'message' => 'Remote maintenance bridge is now locked.']);
    }

    out(['ok' => false, 'error' => 'unknown_action'], 400);
} catch (Throwable $e) {
    log_line('ERROR action=' . $action . ' message=' . $e->getMessage());
    out(['ok' => false, 'error' => $e->getMessage()], 500);
}
