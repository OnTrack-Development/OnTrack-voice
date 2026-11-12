<?php

namespace Tests\Feature;

use App\Services\Mobile\DevicePairingService;
use App\Services\Security\VaultService;
use App\Services\Voice\VoiceCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EnterpriseUnificationTest extends TestCase
{
    use RefreshDatabase;

    private string $tempVault;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempVault = sys_get_temp_dir().'/iovoice-test-vault-'.bin2hex(random_bytes(4));
        @mkdir($this->tempVault, 0700, true);
        config(['ontrack.vault_path' => $this->tempVault]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempVault)) {
            foreach (glob($this->tempVault.'/{*,*/*}', GLOB_BRACE) ?: [] as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }
            @rmdir($this->tempVault.'/voice-lab');
            @rmdir($this->tempVault.'/voice-samples');
            @rmdir($this->tempVault);
        }
        parent::tearDown();
    }

    public function test_legacy_password_hash_login_without_totp_and_enterprise_tabs_work(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $hash = Hash::make('EnterprisePass2026!');

        $userId = DB::table('users')->insertGetId([
            'name' => 'أحمد المدير',
            'email' => 'legacy-owner@ontrackegy.com',
            'password' => null,
            'password_hash' => $hash,
            'status' => 'active',
            'auth_version' => 1,
        ]);
        $accountId = DB::table('accounts')->insertGetId([
            'kind' => 'platform',
            'parent_id' => 0,
            'name' => 'IO Voice HQ',
            'slug' => 'iovoice-hq',
            'status' => 'active',
            'brand_name' => 'IO Voice',
            'created_at' => $now,
        ]);
        DB::table('sites')->insert([
            'account_id' => $accountId,
            'name' => 'الموقع الرئيسي',
            'domain' => 'app.ontrackegy.com',
            'status' => 'active',
            'created_at' => $now,
        ]);
        DB::table('memberships')->insert([
            'user_id' => $userId,
            'account_id' => $accountId,
            'role' => 'owner',
            'created_at' => $now,
        ]);

        // Unauthenticated visitor sees public portal
        $this->get('/')->assertOk()->assertSee('IO Voice');

        // Login with email+password only (no 2FA / TOTP prompt)
        $resp = $this->post('/login', [
            'email' => 'legacy-owner@ontrackegy.com',
            'password' => 'EnterprisePass2026!',
        ]);
        $resp->assertRedirect('/');
        $this->assertAuthenticated();

        foreach (['overview', 'agents', 'devices', 'calls', 'campaigns', 'automations', 'billing', 'settings', 'accounts', 'plans'] as $tab) {
            $this->get('/'.$tab)->assertOk()->assertSee('IO Voice');
            $this->get('/?tab='.$tab)->assertOk();
        }
    }

    public function test_theme_toggle_and_agent_publish_and_sim_hot_swap(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $user = \App\Models\User::factory()->create();
        $accountId = DB::table('accounts')->insertGetId([
            'kind' => 'platform',
            'parent_id' => 0,
            'name' => 'IO Voice Test',
            'slug' => 'io-test',
            'status' => 'active',
            'created_at' => $now,
        ]);
        $siteId = DB::table('sites')->insertGetId([
            'account_id' => $accountId,
            'name' => 'الفرع الرئيسي',
            'domain' => 'app.ontrackegy.com',
            'status' => 'active',
            'created_at' => $now,
        ]);
        DB::table('memberships')->insert([
            'user_id' => $user->id,
            'account_id' => $accountId,
            'role' => 'owner',
            'created_at' => $now,
        ]);

        $this->actingAs($user)->postJson('/console/action', [
            'action' => 'toggle_theme',
            'theme' => 'light',
        ])->assertOk()->assertJson(['ok' => true, 'theme' => 'light']);
        $this->assertSame('light', session('iovoice_theme'));

        // Create two agents and publish both
        $res1 = $this->actingAs($user)->postJson('/console/action', [
            'action' => 'create_agent',
            'name' => 'إيجنت المبيعات',
            'voice' => 'google.aoede',
            'instructions' => 'ساعد العملاء في المبيعات.',
        ])->assertOk()->json();
        $agent1 = (int) $res1['agent_id'];

        $res2 = $this->actingAs($user)->postJson('/console/action', [
            'action' => 'create_agent',
            'name' => 'إيجنت الدعم',
            'voice' => 'google.kore',
            'instructions' => 'حل مشاكل العملاء.',
        ])->assertOk()->json();
        $agent2 = (int) $res2['agent_id'];

        $this->actingAs($user)->postJson('/console/action', ['action' => 'publish_agent', 'agent_id' => $agent1])
            ->assertOk()->assertJson(['ok' => true, 'revision' => 1]);
        $this->actingAs($user)->postJson('/console/action', ['action' => 'publish_agent', 'agent_id' => $agent2])
            ->assertOk()->assertJson(['ok' => true, 'revision' => 1]);

        // Pair device on SIM1 (slot 0) to agent1
        $pairResp = $this->actingAs($user)->postJson('/console/action', [
            'action' => 'create_device_pairing',
            'agent_id' => $agent1,
            'sim_slot' => 0,
        ])->assertOk()->json();
        $code = (string) $pairResp['device_pair_code'];
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $code);

        $redeem = $this->postJson('/saas/console/device/v1/index.php', [
            'operation' => 'pair',
            'code' => $code,
            'installation_id' => 'xiaomi-note12-install-01',
            'sim_slot' => 0,
            'device_name' => 'Xiaomi Note 12 Pro 5G',
        ])->assertCreated()->json();

        $deviceId = (int) $redeem['binding']['device_id'];
        $tokenHashBefore = DB::table('device_bindings')->where('id', $deviceId)->value('token_hash');
        $slotClaimBefore = DB::table('device_bindings')->where('id', $deviceId)->value('slot_claim');

        // Hot swap SIM1 agent from agent1 -> agent2 without changing token_hash or slot_claim
        $this->actingAs($user)->postJson('/console/action', [
            'action' => 'change_device_agent',
            'device_id' => $deviceId,
            'agent_id' => $agent2,
        ])->assertOk()->assertJson([
            'ok' => true,
            'device_id' => $deviceId,
            'agent_id' => $agent2,
            'agent_revision' => 2,
        ]);

        $fresh = DB::table('device_bindings')->where('id', $deviceId)->first();
        $this->assertSame($tokenHashBefore, $fresh->token_hash);
        $this->assertSame($slotClaimBefore, $fresh->slot_claim);
        $this->assertSame($agent2, (int) $fresh->agent_id);
        $this->assertSame(2, (int) $fresh->agent_revision);
    }

    public function test_fish_catalog_sync_and_zero_token_local_sample_cache(): void
    {
        $vault = app(VaultService::class);
        $catalog = app(VoiceCatalogService::class);

        $sampleUrl = 'https://cdn.fish.audio/sample-female-egy.wav';
        $payload = json_encode([
            'items' => [
                [
                    '_id' => '0123456789abcdef0123456789abcdef',
                    'title' => 'سارة المصرية',
                    'description' => 'Egyptian Arabic female warm conversational voice',
                    'languages' => ['ar'],
                    'tags' => ['egypt', 'female'],
                    'samples' => [['audio' => $sampleUrl]],
                ],
            ],
        ]);

        $sync = $catalog->syncFishCatalog(
            'test-fish-key-12345',
            'sara',
            10,
            fn () => ['status' => 200, 'body' => $payload],
            true
        );
        $this->assertSame(1, $sync['synced']);
        $this->assertSame(1, $sync['inserted']);

        $voice = DB::table('voices')->where('code', 'fish.0123456789abcdef0123456789abcdef')->first();
        $this->assertNotNull($voice);
        $this->assertSame('female', $voice->gender);
        $this->assertSame('مصري', $voice->dialect);

        // First sample fetch calls transport (1 token spent) and caches WAV locally
        $wavHeader = 'RIFF'.pack('V', 160).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', 120).str_repeat("\x00", 128);
        $calls = 0;
        $first = $catalog->ensureVoiceSample((int) $voice->id, function () use (&$calls, $wavHeader) {
            $calls++;

            return ['bytes' => $wavHeader];
        });
        $this->assertFalse($first['cached']);
        $this->assertSame(1, $first['tokens_spent']);
        $this->assertSame(1, $calls);

        // Second sample play MUST hit local disk cache with 0 API calls and 0 tokens spent
        $second = $catalog->ensureVoiceSample((int) $voice->id, function () use (&$calls, $wavHeader) {
            $calls++;

            return ['bytes' => $wavHeader];
        });
        $this->assertTrue($second['cached']);
        $this->assertSame(0, $second['tokens_spent']);
        $this->assertSame(1, $calls);

        // Clean up cached sample from public/assets/voice-samples
        $pubFile = public_path('assets/voice-samples/fish.0123456789abcdef0123456789abcdef-'.$voice->id.'.wav');
        if (is_file($pubFile)) {
            @unlink($pubFile);
        }
    }
}
