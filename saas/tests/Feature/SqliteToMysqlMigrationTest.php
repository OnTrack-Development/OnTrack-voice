<?php

namespace Tests\Feature;

use App\Services\Security\VaultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PDO;
use Tests\TestCase;

class SqliteToMysqlMigrationTest extends TestCase
{
    use RefreshDatabase;

    private string $tempVault;

    private string $tmpSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempVault = sys_get_temp_dir().'/iovoice-mig-vault-'.bin2hex(random_bytes(4));
        @mkdir($this->tempVault, 0700, true);
        config(['ontrack.vault_path' => $this->tempVault]);
        $this->tmpSqlite = sys_get_temp_dir().'/legacy-src-'.bin2hex(random_bytes(4)).'.sqlite';
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpSqlite);
        if (is_dir($this->tempVault)) {
            foreach (glob($this->tempVault.'/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->tempVault);
        }
        parent::tearDown();
    }

    public function test_migrates_legacy_sqlite_with_zero_data_loss_and_preserves_auth_and_vault_decryption(): void
    {
        /** @var VaultService $vault */
        $vault = app(VaultService::class);
        $sealedApiSecret = $vault->seal('AIzaSyTestLegacyKey9999999');
        $sealedTranscript = $vault->sealTranscript("العميل: السلام عليكم\nالإيجنت: وعليكم السلام، أهلاً بك!");
        $legacyHash = Hash::make('LegacyUserSecret99!');
        $now = gmdate('Y-m-d H:i:s');

        $pdo = new PDO('sqlite:'.$this->tmpSqlite, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT, password_hash TEXT, name TEXT, status TEXT, auth_version INTEGER)');
        $pdo->exec('CREATE TABLE accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, kind TEXT, parent_id INTEGER, name TEXT, slug TEXT, status TEXT, brand_name TEXT, logo_path TEXT, domain TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE memberships (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, account_id INTEGER, role TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE sites (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER, name TEXT, domain TEXT, status TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE agents (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER, site_id INTEGER, name TEXT, kind TEXT, voice TEXT, instructions TEXT, knowledge TEXT, flow_json TEXT, status TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE ai_relay_entries (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER, scope TEXT, driver TEXT, label TEXT, model TEXT, endpoint TEXT, secret_enc TEXT, priority INTEGER, enabled INTEGER, created_at TEXT)');
        $pdo->exec('CREATE TABLE device_bindings (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER, site_id INTEGER, agent_id INTEGER, installation_id TEXT, sim_slot INTEGER, slot_claim TEXT, device_name TEXT, token_hash TEXT, status TEXT, created_at TEXT, agent_revision INTEGER)');
        $pdo->exec('CREATE TABLE calls (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER, site_id INTEGER, phone TEXT, name TEXT, duration_seconds INTEGER, status TEXT, direction TEXT, is_demo INTEGER, created_at TEXT, external_call_id TEXT, device_binding_id INTEGER, sim_slot INTEGER)');
        $pdo->exec('CREATE TABLE tenant_call_transcripts (account_id INTEGER, site_id INTEGER, device_binding_id INTEGER, sim_slot INTEGER, call_id TEXT, transcript TEXT, source TEXT, created_at TEXT)');

        $pdo->prepare('INSERT INTO users (id, email, password_hash, name, status, auth_version) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([7, 'migrated@ontrackegy.com', $legacyHash, 'سارة المالكة', 'active', 2]);
        $pdo->prepare('INSERT INTO accounts (id, kind, parent_id, name, slug, status, brand_name, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([3, 'customer', 0, 'عيادات النخبة', 'elite-clinics', 'active', 'النخبة', $now]);
        $pdo->prepare('INSERT INTO memberships (id, user_id, account_id, role, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([1, 7, 3, 'owner', $now]);
        $pdo->prepare('INSERT INTO sites (id, account_id, name, domain, status, created_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([4, 3, 'فرع المهندسين', 'elite.ontrackegy.com', 'active', $now]);
        $pdo->prepare('INSERT INTO agents (id, account_id, site_id, name, kind, voice, instructions, knowledge, flow_json, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([11, 3, 4, 'موظفة الحجز', 'voice', 'google.kore', 'حجز المواعيد', 'العيادة من 10 لـ 10', '{}', 'published', $now]);
        $pdo->prepare('INSERT INTO ai_relay_entries (id, account_id, scope, driver, label, model, endpoint, secret_enc, priority, enabled, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([5, 3, 'voice', 'gemini_live', 'Key1', 'gemini-2.5-flash-native-audio-preview-09-2025', '', $sealedApiSecret, 1, 1, $now]);
        $pdo->prepare('INSERT INTO device_bindings (id, account_id, site_id, agent_id, installation_id, sim_slot, slot_claim, device_name, token_hash, status, created_at, agent_revision) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([9, 3, 4, 11, 'xiaomi-install-999', 0, hash('sha256', 'xiaomi-install-999|0'), 'Xiaomi Note 12', hash('sha256', 'otdev_'.str_repeat('a', 64)), 'active', $now, 1]);
        $pdo->prepare('INSERT INTO calls (id, account_id, site_id, phone, name, duration_seconds, status, direction, is_demo, created_at, external_call_id, device_binding_id, sim_slot) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([22, 3, 4, '+201009998877', 'طارق المريض', 95, 'completed', 'inbound', 0, $now, 'call-mig-001', 9, 0]);
        $pdo->prepare('INSERT INTO tenant_call_transcripts (account_id, site_id, device_binding_id, sim_slot, call_id, transcript, source, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([3, 4, 9, 0, 'call-mig-001', $sealedTranscript, 'device_live', $now]);

        $this->artisan('ontrack:migrate-sqlite', [
            '--source' => $this->tmpSqlite,
            '--fresh' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => 7, 'email' => 'migrated@ontrackegy.com']);
        $this->assertDatabaseHas('accounts', ['id' => 3, 'slug' => 'elite-clinics']);
        $this->assertDatabaseHas('agents', ['id' => 11, 'name' => 'موظفة الحجز', 'status' => 'published']);
        $this->assertDatabaseHas('calls', ['id' => 22, 'external_call_id' => 'call-mig-001', 'duration_seconds' => 95]);

        // Legacy user authenticates seamlessly after migration
        $this->assertTrue(Auth::attempt(['email' => 'migrated@ontrackegy.com', 'password' => 'LegacyUserSecret99!']));

        // Sealed secret & encrypted transcript still decrypt identically using preserved console-vault.key
        $migratedRelay = DB::table('ai_relay_entries')->where('id', 5)->first();
        $this->assertSame('AIzaSyTestLegacyKey9999999', $vault->unseal($migratedRelay->secret_enc));

        $migratedTx = DB::table('tenant_call_transcripts')->where('call_id', 'call-mig-001')->first();
        $this->assertSame("العميل: السلام عليكم\nالإيجنت: وعليكم السلام، أهلاً بك!", $vault->unsealTranscript($migratedTx->transcript));
    }
}
