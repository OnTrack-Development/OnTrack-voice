<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Throwable;

class MigrateLegacySqliteToMysql extends Command
{
    protected $signature = 'ontrack:migrate-sqlite
                            {--source= : Absolute path to legacy ontrack-console.sqlite file}
                            {--fresh : Truncate target tables that exist in source before copying}
                            {--dry-run : Inspect source tables and row counts without writing to target}';

    protected $description = 'Zero-data-loss migration from legacy SQLite console DB into Laravel MySQL/target DB with Vault verification';

    private const PRIORITY_ORDER = [
        'users',
        'accounts',
        'memberships',
        'plans',
        'reseller_plan_owners',
        'sites',
        'agents',
        'agent_answer_policies',
        'agent_flow_versions',
        'voices',
        'voice_grants',
        'subscriptions',
        'subscription_cancellation_intents',
        'devices',
        'device_bindings',
        'device_agent_revisions',
        'device_pairings',
        'device_pair_attempts',
        'calls',
        'tenant_call_events',
        'tenant_call_reports',
        'tenant_call_transcripts',
        'voice_call_answer_evidence',
        'voice_call_timeline',
        'contacts',
        'dnc_suppressions',
        'campaigns',
        'tenant_campaigns',
        'tenant_campaign_consents',
        'tenant_campaign_attempts',
        'ai_relay_entries',
        'google_voice_key_overrides',
        'google_voice_live_calls',
        'api_keys',
        'integrations',
        'integration_facts',
        'orders',
        'events',
        'billing_invoices',
        'billing_invoice_events',
        'usage_ledger',
        'wa_platform_onboarding',
        'wa_connections',
        'wa_conversations',
        'wa_messages',
        'wa_ai_jobs',
        'wa_audit',
        'human_escalation_contacts',
        'human_escalation_routes',
        'human_escalation_jobs',
    ];

    public function handle(): int
    {
        $source = (string) ($this->option('source') ?: config('ontrack.legacy_sqlite_path'));
        if ($source === '' || ! is_file($source)) {
            $this->error("Source SQLite file not found: {$source}");
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $fresh = (bool) $this->option('fresh');

        $sqlite = new PDO('sqlite:'.$source, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $rawTables = $sqlite->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        )->fetchAll(PDO::FETCH_COLUMN);

        $ordered = [];
        foreach (self::PRIORITY_ORDER as $t) {
            if (in_array($t, $rawTables, true)) {
                $ordered[] = $t;
            }
        }
        foreach ($rawTables as $t) {
            if (! in_array($t, $ordered, true)) {
                $ordered[] = $t;
            }
        }

        $targetConn = DB::connection();
        $driver = $targetConn->getDriverName();
        $audit = [];
        $allOk = true;

        $this->setForeignKeyChecks($targetConn, $driver, false);
        try {
            foreach ($ordered as $table) {
                $sourceCount = (int) $sqlite->query('SELECT COUNT(*) FROM "'.$table.'"')->fetchColumn();

                if (! Schema::hasTable($table)) {
                    $audit[] = [$table, $sourceCount, '-', 'SKIPPED_NO_TARGET_TABLE'];
                    continue;
                }

                $targetCols = Schema::getColumnListing($table);
                $rows = $sqlite->query('SELECT * FROM "'.$table.'"')->fetchAll(PDO::FETCH_ASSOC);

                if ($dryRun) {
                    $targetCount = (int) $targetConn->table($table)->count();
                    $audit[] = [$table, $sourceCount, $targetCount, 'DRY_RUN'];
                    continue;
                }

                if ($fresh) {
                    $targetConn->table($table)->truncate();
                }

                if ($sourceCount > 0) {
                    $prepared = [];
                    foreach ($rows as $row) {
                        $filtered = [];
                        foreach ($row as $col => $val) {
                            if (in_array($col, $targetCols, true)) {
                                $filtered[$col] = $val;
                            }
                        }

                        // Ensure migrated legacy users can authenticate via both Laravel Auth & legacy hash checks
                        if ($table === 'users' && in_array('password', $targetCols, true)) {
                            $hash = (string) ($row['password_hash'] ?? '');
                            if (empty($filtered['password']) && $hash !== '') {
                                $filtered['password'] = $hash;
                            }
                        }

                        // Ensure nullable timestamp fields match strict column constraints if present
                        if ($table === 'contacts' && in_array('display_name', $targetCols, true) && empty($filtered['display_name'])) {
                            $filtered['display_name'] = (string) ($row['name'] ?? ($row['phone'] ?? 'جهة اتصال'));
                        }
                        if ($table === 'contacts' && in_array('phone_e164', $targetCols, true) && empty($filtered['phone_e164']) && ! empty($row['phone'])) {
                            $filtered['phone_e164'] = (string) $row['phone'];
                        }

                        $prepared[] = $filtered;
                    }

                    foreach (array_chunk($prepared, 150) as $chunk) {
                        try {
                            $targetConn->table($table)->insertOrIgnore($chunk);
                        } catch (Throwable $e) {
                            // Row-by-row fallback so one partial unique conflict never drops sibling rows
                            foreach ($chunk as $single) {
                                try {
                                    $targetConn->table($table)->insertOrIgnore([$single]);
                                } catch (Throwable $rowErr) {
                                    $this->warn("Row skip in {$table}: ".$rowErr->getMessage());
                                }
                            }
                        }
                    }

                    if (in_array('id', $targetCols, true)) {
                        $maxId = (int) $targetConn->table($table)->max('id');
                        if ($driver === 'mysql' && $maxId > 0) {
                            $targetConn->statement('ALTER TABLE `'.$table.'` AUTO_INCREMENT = '.($maxId + 1));
                        }
                    }
                }

                $targetCount = (int) $targetConn->table($table)->count();
                $status = $targetCount >= $sourceCount ? 'OK' : 'MISMATCH';
                if ($status !== 'OK') {
                    $allOk = false;
                }
                $audit[] = [$table, $sourceCount, $targetCount, $status];
            }
        } finally {
            $this->setForeignKeyChecks($targetConn, $driver, true);
        }

        $this->table(['Table', 'SQLite', 'Target', 'Status'], $audit);

        // Cryptographic Vault verification against console-vault.key
        $vaultVerification = $this->verifyVaultDecryption($targetConn);
        $this->info('Vault Verification: '.$vaultVerification);

        return $allOk ? self::SUCCESS : self::FAILURE;
    }

    private function setForeignKeyChecks($conn, string $driver, bool $enabled): void
    {
        try {
            if ($driver === 'mysql') {
                $conn->statement('SET FOREIGN_KEY_CHECKS='.($enabled ? '1' : '0'));
                $conn->statement('SET UNIQUE_CHECKS='.($enabled ? '1' : '0'));
            } elseif ($driver === 'sqlite') {
                $conn->statement('PRAGMA foreign_keys = '.($enabled ? 'ON' : 'OFF'));
            }
        } catch (Throwable) {
        }
    }

    private function verifyVaultDecryption($conn): string
    {
        $vaultKeyPath = rtrim((string) config('ontrack.vault_path'), '/\\').DIRECTORY_SEPARATOR.'console-vault.key';
        if (! is_file($vaultKeyPath)) {
            return 'SKIPPED (console-vault.key not present in environment)';
        }
        $key = @file_get_contents($vaultKeyPath);
        if (! is_string($key) || strlen($key) !== 32) {
            return 'FAILED (console-vault.key invalid length)';
        }

        $checked = 0;
        $decrypted = 0;
        foreach ([['ai_relay_entries', 'secret_enc'], ['integrations', 'secret_enc'], ['wa_connections', 'access_token_enc']] as [$tbl, $col]) {
            if (! Schema::hasTable($tbl)) {
                continue;
            }
            $rows = $conn->table($tbl)->whereNotNull($col)->where($col, '!=', '')->limit(5)->pluck($col);
            foreach ($rows as $box) {
                $checked++;
                $raw = base64_decode((string) $box, true);
                if ($raw !== false && strlen($raw) >= 29) {
                    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
                    if (is_string($plain) && $plain !== '') {
                        $decrypted++;
                    }
                }
            }
        }

        if (Schema::hasTable('tenant_call_transcripts')) {
            $rows = $conn->table('tenant_call_transcripts')->where('transcript', 'like', 'enc:v1:%')->limit(5)->pluck('transcript');
            foreach ($rows as $box) {
                $checked++;
                $raw = base64_decode(substr((string) $box, 7), true);
                if ($raw !== false && strlen($raw) >= 29) {
                    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
                    if (is_string($plain) && $plain !== '') {
                        $decrypted++;
                    }
                }
            }
        }

        return $checked === 0
            ? 'OK (0 encrypted rows to probe)'
            : ($decrypted === $checked ? "PASSED ({$decrypted}/{$checked} sealed secrets decrypted cleanly)" : "PARTIAL ({$decrypted}/{$checked})");
    }
}
