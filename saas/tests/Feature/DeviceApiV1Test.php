<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Mobile\DevicePairingService;
use App\Services\Security\VaultService;
use App\Services\Voice\VoiceSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeviceApiV1Test extends TestCase
{
    use RefreshDatabase;

    private string $tempVault;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempVault = sys_get_temp_dir().'/iovoice-dev-vault-'.bin2hex(random_bytes(4));
        @mkdir($this->tempVault, 0700, true);
        config(['ontrack.vault_path' => $this->tempVault]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempVault)) {
            foreach (glob($this->tempVault.'/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->tempVault);
        }
        parent::tearDown();
    }

    public function test_device_api_enforces_method_auth_and_end_to_end_sim_lifecycle(): void
    {
        // GET must fail with 405 Method Not Allowed
        $this->get('/saas/console/device/v1/index.php')->assertStatus(405)->assertJson(['ok' => false, 'error' => 'method_not_allowed']);
        $this->get('/device/v1/index.php')->assertStatus(405);

        // Missing or bad bearer token must fail with 401
        $this->postJson('/device/v1/index.php', ['operation' => 'heartbeat'])->assertStatus(401);
        $this->withHeaders(['Authorization' => 'Bearer otdev_invalid_short'])->postJson('/device/v1/index.php', ['operation' => 'heartbeat'])->assertStatus(401);

        $now = gmdate('Y-m-d H:i:s');
        $user = User::factory()->create();
        $accountId = DB::table('accounts')->insertGetId([
            'kind' => 'platform',
            'parent_id' => 0,
            'name' => 'Device Test Co',
            'slug' => 'device-test-co',
            'status' => 'active',
            'created_at' => $now,
        ]);
        $siteId = DB::table('sites')->insertGetId([
            'account_id' => $accountId,
            'name' => 'فرع المعادي',
            'domain' => 'maadi.ontrackegy.com',
            'status' => 'active',
            'created_at' => $now,
        ]);
        DB::table('memberships')->insert([
            'user_id' => $user->id,
            'account_id' => $accountId,
            'role' => 'owner',
            'created_at' => $now,
        ]);

        $agent1 = DB::table('agents')->insertGetId([
            'account_id' => $accountId,
            'site_id' => $siteId,
            'name' => 'استقبال المعادي',
            'kind' => 'voice',
            'voice' => 'google.aoede',
            'instructions' => 'الرد السريع على استفسارات الحجز.',
            'knowledge' => 'ساعات العمل 9 صباحاً - 9 مساءً.',
            'flow_json' => VoiceSessionService::DEFAULT_FLOW,
            'status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $agent2 = DB::table('agents')->insertGetId([
            'account_id' => $accountId,
            'site_id' => $siteId,
            'name' => 'خدمة كبار العملاء',
            'kind' => 'voice',
            'voice' => 'google.kore',
            'instructions' => 'أولوية قصوى لعملاء VIP.',
            'knowledge' => 'خدمة سريعة على مدار اليوم.',
            'flow_json' => VoiceSessionService::DEFAULT_FLOW,
            'status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        /** @var VoiceSessionService $vs */
        $vs = app(VoiceSessionService::class);
        $vs->saveAnswerPolicy($accountId, $agent1, $vs->defaultAnswerPolicy());
        $vs->saveAnswerPolicy($accountId, $agent2, $vs->defaultAnswerPolicy());
        $pub1 = $vs->publishAgent($accountId, $agent1, $user->id);
        $vs->publishAgent($accountId, $agent2, $user->id);

        /** @var DevicePairingService $pairing */
        $pairing = app(DevicePairingService::class);
        $code = $pairing->createPairing($accountId, $siteId, $agent1, $user->id, 0);

        $pairResp = $this->postJson('/saas/console/device/v1/index.php', [
            'operation' => 'pair',
            'code' => $code,
            'installation_id' => 'xiaomi-e2e-install-abc123',
            'sim_slot' => 0,
            'device_name' => 'Xiaomi Note 12 Pro 5G',
        ])->assertCreated()->json();

        $token = $pairResp['binding']['device_token'];
        $deviceId = (int) $pairResp['binding']['device_id'];

        $authHeaders = ['Authorization' => 'Bearer '.$token];

        // Heartbeat matches Android TenantDeviceEnrollment.kt expected JSON keys
        $hb1 = $this->withHeaders($authHeaders)
            ->postJson('/device/v1/index.php', ['operation' => 'heartbeat'])
            ->assertOk()
            ->json();

        $this->assertTrue($hb1['ok']);
        $this->assertTrue($hb1['profile']['flow_ready']);
        $this->assertSame($agent1, $hb1['profile']['agent_id']);
        $this->assertSame(1, $hb1['profile']['agent_revision']);
        $this->assertSame($pub1['revision'], $hb1['profile']['flow_revision']);
        $this->assertSame($pub1['hash'], $hb1['profile']['flow_hash']);

        // Published flow matches Android verification fields
        $flowResp = $this->withHeaders($authHeaders)
            ->postJson('/device/v1/index.php', ['operation' => 'published_flow'])
            ->assertOk()
            ->json();

        $this->assertSame($pub1['revision'], $flowResp['revision']);
        $this->assertSame($pub1['hash'], $flowResp['hash']);
        $this->assertIsArray($flowResp['flow']['nodes']);

        // Call lifecycle: event -> report -> sealed transcript
        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_call_event',
            'call_id' => 'call-dev-001',
            'status' => 'completed',
            'phone' => '+201055544433',
            'name' => 'كريم حسن',
            'duration_seconds' => 70,
            'direction' => 'inbound',
            'caller_gender' => 'male',
            'ai_rating' => 5,
            'ai_mood' => 'happy',
        ])->assertOk()->assertJson(['ok' => true, 'call_id' => 'call-dev-001']);

        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_call_report',
            'call_id' => 'call-dev-001',
            'summary' => 'استفسر العميل عن الباقة السنوية وتم الحجز.',
            'outcome' => 'booked',
            'handoff_status' => 'not_requested',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_call_transcript',
            'call_id' => 'call-dev-001',
            'transcript' => 'المتصل: عايز أحجز استشارة بكرة',
        ])->assertOk()->assertJson(['ok' => true]);

        $storedTx = DB::table('tenant_call_transcripts')->where('device_binding_id', $deviceId)->value('transcript');
        $this->assertStringStartsWith('enc:v1:', $storedTx);
        $this->assertSame('المتصل: عايز أحجز استشارة بكرة', app(VaultService::class)->unsealTranscript($storedTx));

        // Hot-swap agent on SIM slot 0 to agent2 via web console
        $this->actingAs($user)->postJson('/console/action', [
            'action' => 'change_device_agent',
            'device_id' => $deviceId,
            'agent_id' => $agent2,
        ])->assertOk()->assertJson(['ok' => true, 'agent_revision' => 2]);

        // Same bearer token immediately picks up agent2 & agent_revision=2 on next heartbeat
        $hb2 = $this->withHeaders($authHeaders)
            ->postJson('/saas/console/device/v1/index.php', ['operation' => 'heartbeat'])
            ->assertOk()
            ->json();

        $this->assertSame($agent2, $hb2['profile']['agent_id']);
        $this->assertSame(2, $hb2['profile']['agent_revision']);

        // Legacy /saas/console/ redirect parity
        $this->actingAs($user)
            ->get('/saas/console/?tab=flows&site='.$siteId.'&agent='.$agent2)
            ->assertRedirect('/?tab=agents&site_id='.$siteId.'&agent_id='.$agent2);
    }

    public function test_full_legacy_control_plane_parity_routes_and_guards(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $user = User::factory()->create();
        $accountId = DB::table('accounts')->insertGetId([
            'kind' => 'platform',
            'parent_id' => 0,
            'name' => 'Parity Platform Co',
            'slug' => 'parity-platform-co',
            'status' => 'active',
            'created_at' => $now,
        ]);
        $siteId = DB::table('sites')->insertGetId([
            'account_id' => $accountId,
            'name' => 'الفرع الرئيسي',
            'domain' => 'main.ontrackegy.com',
            'status' => 'active',
            'created_at' => $now,
        ]);
        DB::table('memberships')->insert([
            'user_id' => $user->id,
            'account_id' => $accountId,
            'role' => 'owner',
            'created_at' => $now,
        ]);

        $flowJson = json_encode([
            'nodes' => [
                ['id' => 'start', 'type' => 'start', 'text' => 'بداية'],
                ['id' => 'ask1', 'type' => 'ask', 'text' => 'أهلاً، تحب تحجز معاد ولا تستفسر؟'],
                ['id' => 'end_book', 'type' => 'end', 'text' => 'تم تسجيل طلب الحجز.'],
                ['id' => 'end_info', 'type' => 'end', 'text' => 'ساعات العمل من 9 صباحاً حتى 9 مساءً.'],
            ],
            'edges' => [
                ['from' => 'start', 'to' => 'ask1', 'label' => ''],
                ['from' => 'ask1', 'to' => 'end_book', 'label' => 'حجز'],
                ['from' => 'ask1', 'to' => 'end_info', 'label' => 'استفسار'],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $agentId = DB::table('agents')->insertGetId([
            'account_id' => $accountId,
            'site_id' => $siteId,
            'name' => 'سارة الاستقبال',
            'kind' => 'voice',
            'voice' => 'google.aoede',
            'instructions' => 'الرد باختصار',
            'knowledge' => 'فرع المعادي',
            'flow_json' => $flowJson,
            'status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        /** @var VoiceSessionService $vs */
        $vs = app(VoiceSessionService::class);
        $vs->saveAnswerPolicy($accountId, $agentId, $vs->defaultAnswerPolicy());
        $vs->publishAgent($accountId, $agentId, $user->id);

        /** @var DevicePairingService $pairing */
        $pairing = app(DevicePairingService::class);
        $code = $pairing->createPairing($accountId, $siteId, $agentId, $user->id, 1);
        $pairResp = $this->postJson('/device/v1/index.php', [
            'operation' => 'pair',
            'code' => $code,
            'installation_id' => 'xiaomi-parity-device-001',
            'sim_slot' => 1,
            'device_name' => 'Xiaomi Test SIM2',
        ])->assertCreated()->json();

        $token = $pairResp['binding']['device_token'];
        $deviceId = (int) $pairResp['binding']['device_id'];
        $authHeaders = ['Authorization' => 'Bearer '.$token];

        // Configure escalation contact & route BEFORE call starts
        $escContactId = DB::table('human_escalation_contacts')->insertGetId([
            'account_id' => $accountId,
            'name' => 'مدير المبيعات',
            'phone_e164' => '+201099887766',
            'recipient_role' => 'manager',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('human_escalation_routes')->insert([
            'agent_id' => $agentId,
            'account_id' => $accountId,
            'site_id' => $siteId,
            'contact_id' => $escContactId,
            'enabled' => 1,
            'updated_at' => gmdate('Y-m-d H:i:s', time() - 60),
            'delay_seconds' => 10,
        ]);

        // Phased structured call events + usage ledger + timeline immutability
        $uuid = '123e4567-e89b-42d3-a456-426614174000';
        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_call_event',
            'call_id' => $uuid,
            'sequence' => 1,
            'phase' => 'ringing',
        ])->assertOk()->assertJson(['ok' => true, 'accepted' => true, 'duplicate' => false]);

        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_call_event',
            'call_id' => $uuid,
            'sequence' => 2,
            'phase' => 'active',
        ])->assertOk();

        // Sequence 2 replay is idempotent
        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_call_event',
            'call_id' => $uuid,
            'sequence' => 2,
            'phase' => 'active',
        ])->assertOk()->assertJson(['duplicate' => true]);

        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_call_event',
            'call_id' => $uuid,
            'sequence' => 3,
            'phase' => 'identified',
            'phone_number' => '+201022334455',
            'contact_name' => 'أحمد محمود',
        ])->assertOk();

        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_call_event',
            'call_id' => $uuid,
            'sequence' => 4,
            'phase' => 'customer_human_request',
            'handoff_reason' => 'العميل يريد التحدث مع مدير المبيعات',
            'customer_notes' => 'عميل مهتم بباقة الشركات',
        ])->assertOk();

        // Timeline event + immutability check
        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_call_timeline',
            'call_id' => $uuid,
            'event_id' => 'evt_000001',
            'speaker' => 'agent',
            'event_type' => 'spoken_text',
            'text' => 'أهلاً بحضرتك يا أستاذ أحمد، لحظة وهحوّلك للإدارة.',
            'occurred_at' => gmdate('Y-m-d H:i:s'),
        ])->assertOk()->assertJson(['ok' => true, 'saved' => true, 'duplicate' => false]);

        // Altered replay with same event_id must return 409 conflict
        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_call_timeline',
            'call_id' => $uuid,
            'event_id' => 'evt_000001',
            'speaker' => 'agent',
            'event_type' => 'spoken_text',
            'text' => 'نص مختلف بعد الاعتماد!',
            'occurred_at' => gmdate('Y-m-d H:i:s'),
        ])->assertStatus(409)->assertJson(['ok' => false, 'error' => 'timeline_replay_changed']);

        // End call -> triggers connected_call ledger
        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_call_event',
            'call_id' => $uuid,
            'sequence' => 5,
            'phase' => 'ended',
            'duration_seconds' => 42,
        ])->assertOk();

        $this->assertSame(1, DB::table('usage_ledger')->where('account_id', $accountId)->where('kind', 'connected_call')->count());

        // Device cannot erase requested handoff or set completed directly
        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_call_report',
            'call_id' => $uuid,
            'summary' => 'ملخص',
            'outcome' => 'interested',
            'handoff_status' => 'not_requested',
        ])->assertStatus(422)->assertJson(['ok' => false, 'error' => 'human_handoff_request_cannot_be_erased_by_device']);

        // Transcript save & fetch
        $txText = 'المتصل: السلام عليكم، محتاج أتكلم مع مدير المبيعات بخصوص التعاقد.';
        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_call_transcript',
            'call_id' => $uuid,
            'transcript' => $txText,
        ])->assertOk();

        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_call_transcript',
            'call_id' => $uuid,
        ])->assertOk()->assertJson(['ok' => true, 'call_id' => $uuid, 'transcript' => $txText]);

        // Escalation claim & ack after due_epoch elapsed
        DB::table('human_escalation_jobs')->where('call_id', $uuid)->update(['due_epoch' => time() - 5]);
        $claimJob = $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_handoff_claim',
        ])->assertOk()->json('job');
        $this->assertNotNull($claimJob);
        $this->assertSame('+201099887766', $claimJob['phone']);

        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_handoff_ack',
            'job_id' => $claimJob['id'],
            'stage' => 'dial_started',
        ])->assertOk()->assertJson(['ok' => true, 'status' => 'dial_started']);

        // Deterministic flow runtime via device endpoint
        $flowStart = $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'flow_start',
            'call_id' => 'flowcall_001',
        ])->assertOk()->json();
        $this->assertSame('active', $flowStart['status']);
        $this->assertSame(2, $flowStart['step_version']);

        // Stale step_version must return 409
        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'flow_step',
            'session_id' => $flowStart['session_id'],
            'step_version' => 1,
            'reply' => 'حجز',
        ])->assertStatus(409)->assertJson(['ok' => false, 'error' => 'flow_step_version_conflict']);

        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'flow_step',
            'session_id' => $flowStart['session_id'],
            'step_version' => $flowStart['step_version'],
            'reply' => 'عايز حجز معاد',
        ])->assertOk()->assertJson(['ok' => true, 'status' => 'completed', 'done' => true]);

        // Caller identify + PIN verification
        $intId = DB::table('integrations')->insertGetId([
            'account_id' => $accountId,
            'name' => 'CRM Core',
            'provider' => 'custom',
            'status' => 'active',
            'created_at' => $now,
        ]);
        DB::table('integration_facts')->insert([
            'account_id' => $accountId,
            'integration_id' => $intId,
            'category' => 'bookings',
            'fact_key' => 'today_slots',
            'fact_value' => '14:00, 16:00',
            'updated_at' => $now,
        ]);
        DB::table('voice_staff_numbers')->insert([
            'account_id' => $accountId,
            'user_id' => $user->id,
            'phone_e164' => '+201000011122',
            'pin_hash' => password_hash('482910', PASSWORD_DEFAULT),
            'scopes_json' => json_encode(['general', 'bookings']),
            'status' => 'active',
            'failed_attempts' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'caller_identify',
            'phone' => '+201000011122',
        ])->assertOk()->assertJson(['ok' => true, 'recognized' => true, 'pin_required' => true, 'authenticated' => false]);

        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'caller_verify',
            'phone' => '+201000011122',
            'pin' => '000000',
        ])->assertStatus(403)->assertJson(['ok' => false, 'error' => 'caller_not_verified']);

        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'caller_verify',
            'phone' => '+201000011122',
            'pin' => '482910',
        ])->assertOk()->assertJsonPath('authenticated', true)->assertJsonCount(1, 'facts');

        // Contacts import (JSON + CSV base64), consent, DNC, campaign create/arm/claim/verify/finish
        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_file_import',
            'confirmed' => true,
            'name' => 'leads.csv',
            'data_b64' => base64_encode("العميل الأول,+201012345678\nالعميل الثاني,+201112345679"),
        ])->assertOk()->assertJsonPath('counts.added', 2);

        $contactsList = $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_contacts',
        ])->assertOk()->json('contacts');
        $c1Opaque = $contactsList[1]['id'];
        $c2Opaque = $contactsList[0]['id'];

        // Set DNC on contact 2 and verify consent is blocked
        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_contact_dnc',
            'confirmed' => true,
            'id' => $c2Opaque,
            'blocked' => true,
        ])->assertOk();
        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_contact_consent',
            'confirmed' => true,
            'id' => $c2Opaque,
            'method' => 'written',
            'evidence' => 'طلب تسجيل صريح عبر الموقع',
        ])->assertStatus(422)->assertJson(['error' => 'dnc_cannot_be_overridden']);

        // Record consent on contact 1, create & arm campaign
        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_contact_consent',
            'confirmed' => true,
            'id' => $c1Opaque,
            'method' => 'written',
            'evidence' => 'موافقة موثقة عبر نموذج الحجز',
        ])->assertOk();

        $tzCairo = new \DateTimeZone('Africa/Cairo');
        $startsLocal = (new \DateTimeImmutable('-10 minutes', $tzCairo))->format('Y-m-d\TH:i');
        $endsLocal = (new \DateTimeImmutable('+2 hours', $tzCairo))->format('Y-m-d\TH:i');

        $cmpCreated = $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_campaign_create',
            'confirmed' => true,
            'name' => 'حملة تجربة موحدة',
            'starts' => $startsLocal,
            'ends' => $endsLocal,
            'scenario' => 'أهلاً يا فندم معاك مساعد الشركة.',
            'contact_ids' => [$c1Opaque],
        ])->assertOk()->json('campaign');

        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_campaign_action',
            'confirmed' => true,
            'id' => $cmpCreated['id'],
            'action' => 'arm',
        ])->assertOk()->assertJsonPath('campaign.status', 'ready');

        config(['ontrack.campaign_dial_enabled' => true]);
        $claimed = $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_campaign_claim',
        ])->assertOk()->json('claim');
        $this->assertNotNull($claimed);

        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_campaign_verify',
            'attempt_id' => $claimed['id'],
        ])->assertOk()->assertJsonPath('verification.allowed', true);

        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_campaign_finish',
            'attempt_id' => $claimed['id'],
            'result' => 'completed',
        ])->assertOk()->assertJsonPath('attempt.result', 'completed');

        // Voice key failover unit + controller contract
        $vs->addRelayEntry($accountId, 'voice', 'gemini_live', 'Key 1', 'gemini-2.5-flash-native-audio-preview-12-2025', 'AIzaSyFakeKeyOne123456', 1);
        $vs->addRelayEntry($accountId, 'voice', 'gemini_live', 'Key 2', 'gemini-2.5-flash-native-audio-preview-12-2025', 'AIzaSyFakeKeyTwo123456', 2);

        $bindingArr = app(DevicePairingService::class)->authorizeBearer('Bearer '.$token);
        $session = $vs->issueLiveVoiceSession($bindingArr, fn () => 'auth_tokens/mock_ephemeral_token_001');
        $this->assertArrayHasKey('failover_ticket', $session);
        $rotated = $vs->rotateLiveVoiceSession($bindingArr, $session['failover_ticket'], 0, '429', fn () => 'auth_tokens/mock_ephemeral_token_002');
        $this->assertSame('auth_tokens/mock_ephemeral_token_002', $rotated['token']);
        $this->assertSame(1, $rotated['failover_generation']);

        // Stale generation 0 over HTTP must fail with 409 conflict
        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'voice_failover',
            'failover_ticket' => $session['failover_ticket'],
            'failover_generation' => 0,
            'failure_code' => '429',
        ])->assertStatus(409)->assertJson(['ok' => false, 'error' => 'voice_failover_conflict']);

        // TTS endpoint parity guards
        $this->flushHeaders();
        $this->get('/device/v1/tts.php')->assertStatus(405);
        $this->get('/saas/console/device/v1/tts.php')->assertStatus(405);
        $this->postJson('/device/v1/tts.php', ['text' => 'مرحبا'])->assertStatus(401);
        $this->withHeaders($authHeaders)->postJson('/device/v1/tts.php', ['text' => ''])->assertStatus(422);
        // Currently published agent voice is google.aoede -> 403 premium_voice_not_published
        $this->withHeaders($authHeaders)->postJson('/saas/console/device/v1/tts.php', ['text' => 'أهلاً بك'])->assertStatus(403)
            ->assertJson(['ok' => false, 'error' => 'premium_voice_not_published']);

        // Disconnect conflict on second call
        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', ['operation' => 'disconnect'])->assertOk();
        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', ['operation' => 'disconnect'])->assertStatus(401);
    }
}
