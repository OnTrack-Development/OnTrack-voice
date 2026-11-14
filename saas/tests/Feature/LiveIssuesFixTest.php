<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Campaigns\CampaignDialerService;
use App\Services\Console\ConsoleContextService;
use App\Services\Mobile\CallLedgerService;
use App\Services\Mobile\DevicePairingService;
use App\Services\Security\VaultService;
use App\Services\Voice\VoiceSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LiveIssuesFixTest extends TestCase
{
    use RefreshDatabase;

    private function seedBoundDevice(string $kind = 'customer'): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $user = User::factory()->create();
        $accountId = DB::table('accounts')->insertGetId([
            'kind' => $kind,
            'parent_id' => 0,
            'name' => 'Fix Test Co',
            'slug' => 'fix-test-co-'.uniqid(),
            'status' => 'active',
            'created_at' => $now,
        ]);
        $siteId = DB::table('sites')->insertGetId([
            'account_id' => $accountId,
            'name' => 'الفرع الرئيسي',
            'domain' => 'main.example.test',
            'status' => 'active',
            'created_at' => $now,
        ]);
        DB::table('memberships')->insert([
            'account_id' => $accountId,
            'user_id' => $user->id,
            'role' => 'owner',
            'created_at' => $now,
        ]);

        $planId = DB::table('plans')->insertGetId([
            'kind' => 'user',
            'name' => 'Pro',
            'status' => 'active',
            'call_quota' => 500,
            'site_limit' => 5,
            'agent_limit' => 5,
            'staff_limit' => 10,
            'reseller_customers' => 0,
            'price_minor' => 0,
            'currency' => 'EGP',
            'billing_interval' => 'monthly',
        ]);
        DB::table('subscriptions')->insert([
            'account_id' => $accountId,
            'plan_id' => $planId,
            'status' => 'active',
            'starts_at' => gmdate('Y-m-d H:i:s', time() - 3600),
            'ends_at' => gmdate('Y-m-d H:i:s', time() + 86400),
            'created_at' => $now,
        ]);

        $agentId = DB::table('agents')->insertGetId([
            'account_id' => $accountId,
            'site_id' => $siteId,
            'name' => 'انجي',
            'kind' => 'voice',
            'voice' => 'google.aoede',
            'instructions' => 'تقييم خدمة العملاء.',
            'flow_json' => VoiceSessionService::DEFAULT_FLOW,
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        /** @var VoiceSessionService $vs */
        $vs = app(VoiceSessionService::class);
        $vs->saveAnswerPolicy($accountId, $agentId, [
            'auto_answer' => true,
            'answer_delay_seconds' => 3,
            'response_style' => 'egyptian_short',
            'max_call_minutes' => 10,
            'fallback' => 'human',
            'scenario_mode' => 'scenario',
            'scenario_text' => 'مرحبا، معك انجي. كيف كانت خدمتك اليوم؟',
        ]);
        $vs->publishAgent($accountId, $agentId, $user->id);

        /** @var DevicePairingService $pairing */
        $pairing = app(DevicePairingService::class);
        $code = $pairing->createPairing($accountId, $siteId, $agentId, $user->id, 0);
        $bound = $pairing->redeemPairing($code, 'install-live-fix-'.uniqid(), 0, 'Xiaomi Note 12 Pro');
        $binding = DB::table('device_bindings')->where('id', $bound['device_id'])->first();

        return [$user, $accountId, $siteId, $agentId, (array) $binding, ['Authorization' => 'Bearer '.$bound['device_token']]];
    }

    public function test_voice_session_enforces_top_brevity_barge_in_rules_and_max_output_tokens(): void
    {
        [$user, $accountId, $siteId, $agentId, $binding] = $this->seedBoundDevice();

        /** @var VoiceSessionService $vs */
        $vs = app(VoiceSessionService::class);
        DB::table('ai_relay_entries')->insert([
            'account_id' => $accountId,
            'scope' => 'voice',
            'driver' => 'gemini_live',
            'label' => 'Primary',
            'model' => 'models/gemini-2.5-flash-native-audio-preview-12-2025',
            'priority' => 1,
            'enabled' => 1,
            'secret_enc' => app(VaultService::class)->seal('AIzaTestFakeGeminiKey1234567890'),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $session = $vs->issueLiveVoiceSession($binding, fn () => 'auth_tokens/test_ephemeral_token_0123456789');

        $this->assertSame(1024, $session['max_output_tokens']);
        $this->assertStringStartsWith('[قاعدة الأداء والتمثيل الصوتي والمشاعر]', $session['system_instruction']);
        $this->assertStringContainsString('[قاعدة المقاطعة (Barge-In)]', $session['system_instruction']);
        $this->assertStringContainsString('[قاعدة قفل اللغة العربية]', $session['system_instruction']);
    }

    public function test_call_analysis_uses_gemini_rest_json_and_syncs_calls_summary_and_ui(): void
    {
        [$user, $accountId, $siteId, $agentId, $binding, $authHeaders] = $this->seedBoundDevice();

        $callId = 'call-summary-01';
        $now = gmdate('Y-m-d H:i:s');
        DB::table('calls')->insert([
            'account_id' => $accountId,
            'site_id' => $siteId,
            'phone' => '+201012345678',
            'name' => 'نور الهدى',
            'duration_seconds' => 45,
            'status' => 'ended',
            'direction' => 'inbound',
            'is_demo' => 0,
            'created_at' => $now,
            'external_call_id' => $callId,
            'device_binding_id' => $binding['id'],
            'sim_slot' => 0,
            'agent_id' => $agentId,
            'agent_name' => 'انجي',
        ]);

        // Test Gemini REST JSON structured parser via mocked $restTransport
        /** @var VoiceSessionService $vs */
        $vs = app(VoiceSessionService::class);
        /** @var CallLedgerService $ledger */
        $ledger = app(CallLedgerService::class);

        $seenModel = null;
        $saved = $vs->analyzeCallTranscript(
            $binding,
            $callId,
            "المتصل: السلام عليكم أنا نور وعجبتني الخدمة جداً 9 من 10\nالإيجنت: شكراً لحضرتك يا فندم.",
            fn (array $rep) => $ledger->saveReport($accountId, $siteId, (int) $binding['id'], $rep['call_id'], $rep, 'device'),
            null,
            false,
            function (string $model, array $payload) use (&$seenModel): array {
                $seenModel = $model;

                return [
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'text' => json_encode([
                                    'summary' => 'أبدت المتصلة رضاها عن الخدمة ومنحتها تقييم 9 من 10.',
                                    'customer_notes' => 'العميلة نور الهدى راضية عن الخدمة.',
                                    'outcome' => 'satisfied',
                                    'caller_gender' => 'female',
                                    'ai_rating' => 5,
                                    'ai_mood' => 'إيجابي',
                                    'handoff_requested' => false,
                                    'handoff_reason' => '',
                                ], JSON_UNESCAPED_UNICODE),
                            ]],
                        ],
                    ]],
                ];
            }
        );

        $this->assertSame('gemini-3.5-flash-lite', $seenModel);
        $this->assertTrue($saved['saved']);

        $callRow = DB::table('calls')->where('external_call_id', $callId)->first();
        $this->assertSame('أبدت المتصلة رضاها عن الخدمة ومنحتها تقييم 9 من 10.', $callRow->summary);
        $this->assertSame('female', $callRow->caller_gender);
        $this->assertSame(5, (int) $callRow->ai_rating);

        // Auto-summarization via workspace_call_transcript never emits "تعذر التلخيص الآلي"
        $callId2 = 'call-fallback-02';
        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_call_event',
            'call_id' => $callId2,
            'sequence' => 1,
            'phase' => 'ended',
            'duration_seconds' => 30,
        ])->assertOk();

        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_call_transcript',
            'call_id' => $callId2,
            'transcript' => "المتصل: عايز أحجز ميعاد بكرة لو سمحت\nالإيجنت: تحت أمرك يا فندم.",
        ])->assertOk();

        $rep2 = DB::table('tenant_call_reports')->where('call_id', $callId2)->first();
        $this->assertNotNull($rep2);
        $this->assertStringNotContainsString('تعذر التلخيص الآلي', $rep2->summary);

        // Web calls tab shows Cairo time, summary column, and 'ended' status filter
        $this->actingAs($user)
            ->get('/?tab=calls&status=ended')
            ->assertOk()
            ->assertSee('أبدت المتصلة رضاها عن الخدمة')
            ->assertSee('التاريخ (القاهرة)')
            ->assertSee('ملخص المكالمة');
    }

    public function test_cairo_timezone_campaigns_add_contacts_and_device_lease_lifecycle(): void
    {
        config(['ontrack.campaign_dial_enabled' => true]);
        $this->assertSame('Africa/Cairo', config('app.timezone'));

        [$user, $accountId, $siteId, $agentId, $binding, $authHeaders] = $this->seedBoundDevice();

        /** @var CampaignDialerService $dialer */
        $dialer = app(CampaignDialerService::class);
        $imported = $dialer->importContactsBulk($accountId, $siteId, "سامي عادل,+201557275228\nندى طارق,01099887766", true);
        $this->assertSame(2, $imported['imported_count']);

        $tzCairo = new \DateTimeZone('Africa/Cairo');
        $startCairo = (new \DateTimeImmutable('-10 minutes', $tzCairo))->format('Y-m-d\TH:i');
        $endCairo = (new \DateTimeImmutable('+2 hours', $tzCairo))->format('Y-m-d\TH:i');

        $campaignId = $dialer->createCampaign($accountId, $siteId, [
            'name' => 'حملة القاهرة التجريبية',
            'device_binding_id' => $binding['id'],
            'starts_at' => $startCairo,
            'ends_at' => $endCairo,
            'scenario' => 'مرحباً {name}، نؤكد معك موعدك.',
            'contact_ids' => [$imported['contact_ids'][0]],
            'arm_now' => 1,
        ]);

        // Add 2nd contact to existing campaign
        $this->actingAs($user)->post('/console/action', [
            'action' => 'add_campaign_contacts',
            'campaign_id' => $campaignId,
            'contact_ids' => [$imported['contact_ids'][1]],
        ])->assertRedirect();

        $campaigns = $dialer->listCampaigns($accountId);
        $this->assertSame(2, $campaigns[0]['target_count']);
        $this->assertNotEmpty($campaigns[0]['starts_at_cairo']);

        // Device claim -> verify -> finish
        $claimRes = $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_campaign_claim',
        ])->assertOk()->json();

        $this->assertNotNull($claimRes['claim']);
        $this->assertSame('+201557275228', $claimRes['claim']['phone']);

        $attemptId = $claimRes['claim']['id'];
        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_campaign_verify',
            'attempt_id' => $attemptId,
        ])->assertOk()->assertJson(['ok' => true, 'verification' => ['allowed' => true]]);

        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_campaign_finish',
            'attempt_id' => $attemptId,
            'result' => 'completed',
        ])->assertOk()->assertJson(['ok' => true, 'attempt' => ['saved' => true, 'result' => 'completed']]);

        $campaignsAfter = $dialer->listCampaigns($accountId);
        $this->assertSame(1, $campaignsAfter[0]['attempts_done']);
    }

    public function test_separated_reseller_admin_navigation_quick_switch_and_isolation(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $admin = User::factory()->create();
        $resellerUser = User::factory()->create();
        $customerUser = User::factory()->create();

        $platformId = DB::table('accounts')->insertGetId([
            'kind' => 'platform',
            'parent_id' => 0,
            'name' => 'IO Platform',
            'slug' => 'io-platform',
            'status' => 'active',
            'created_at' => $now,
        ]);
        DB::table('sites')->insert(['account_id' => $platformId, 'name' => 'Main', 'domain' => 'app.ontrackegy.com', 'status' => 'active', 'created_at' => $now]);
        DB::table('memberships')->insert(['account_id' => $platformId, 'user_id' => $admin->id, 'role' => 'owner', 'created_at' => $now]);

        $resellerId = DB::table('accounts')->insertGetId([
            'kind' => 'reseller',
            'parent_id' => 0,
            'name' => 'Delta Partner',
            'slug' => 'delta-partner',
            'status' => 'active',
            'created_at' => $now,
        ]);
        DB::table('sites')->insert(['account_id' => $resellerId, 'name' => 'Delta Site', 'domain' => 'delta.ontrackegy.com', 'status' => 'active', 'created_at' => $now]);
        DB::table('memberships')->insert(['account_id' => $resellerId, 'user_id' => $resellerUser->id, 'role' => 'owner', 'created_at' => $now]);

        $childId = DB::table('accounts')->insertGetId([
            'kind' => 'customer',
            'parent_id' => $resellerId,
            'name' => 'Delta Child Clinic',
            'slug' => 'delta-child',
            'status' => 'active',
            'created_at' => $now,
        ]);
        DB::table('sites')->insert(['account_id' => $childId, 'name' => 'Clinic Site', 'domain' => 'child.ontrackegy.com', 'status' => 'active', 'created_at' => $now]);

        $otherCustId = DB::table('accounts')->insertGetId([
            'kind' => 'customer',
            'parent_id' => 0,
            'name' => 'Direct Customer',
            'slug' => 'direct-customer',
            'status' => 'active',
            'created_at' => $now,
        ]);
        DB::table('sites')->insert(['account_id' => $otherCustId, 'name' => 'Direct Site', 'domain' => 'direct.ontrackegy.com', 'status' => 'active', 'created_at' => $now]);
        DB::table('memberships')->insert(['account_id' => $otherCustId, 'user_id' => $customerUser->id, 'role' => 'owner', 'created_at' => $now]);

        $userPlanId = DB::table('plans')->insertGetId([
            'kind' => 'user', 'name' => 'Growth 1K', 'status' => 'active', 'call_quota' => 1000,
            'site_limit' => 2, 'agent_limit' => 3, 'staff_limit' => 5, 'reseller_customers' => 0,
            'price_minor' => 50000, 'currency' => 'EGP', 'billing_interval' => 'monthly',
        ]);

        // 1. Platform admin sees BOTH Reseller & Administration sidebar groups + top switcher
        $this->actingAs($admin)
            ->get('/?tab=overview')
            ->assertOk()
            ->assertSee('الحسابات والريسيلرز')
            ->assertSee('Delta Child Clinic');

        $this->actingAs($admin)->get('/?tab=platform-ai')->assertOk();
        $this->actingAs($admin)->get('/?tab=platform-plans')->assertOk();

        // 2. Platform admin 1-click quick_switch_tenant into Direct Customer & back
        $this->actingAs($admin)->post('/console/action', [
            'action' => 'quick_switch_tenant',
            'target_account_id' => $otherCustId,
        ])->assertRedirect('/?tab=overview');

        $this->actingAs($admin)
            ->get('/?tab=overview')
            ->assertOk()
            ->assertSee('Direct Customer');

        $this->actingAs($admin)->post('/console/action', [
            'action' => 'quick_switch_tenant',
            'target_account_id' => $platformId,
        ])->assertRedirect('/?tab=overview');

        // 3. Reseller sees ONLY Reseller group, NOT Platform Admin group, and gets 403 on platform-only tabs
        $this->actingAs($resellerUser)
            ->get('/?tab=overview')
            ->assertOk()
            ->assertSee('العملاء التابعون')
            ->assertDontSee('إدارة المنصة (Administration)');

        $this->actingAs($resellerUser)->get('/?tab=platform-ai')->assertStatus(403);
        $this->actingAs($resellerUser)->get('/?tab=reseller-tenants')->assertOk()->assertSee('Delta Child Clinic');

        // Reseller allocates plan to own child -> OK; foreign customer -> 403
        $this->actingAs($resellerUser)->post('/console/action', [
            'action' => 'reseller_allocate_plan',
            'target_account_id' => $childId,
            'plan_id' => $userPlanId,
        ])->assertRedirect();

        $this->assertSame($userPlanId, (int) DB::table('subscriptions')->where('account_id', $childId)->where('status', 'active')->value('plan_id'));

        $this->actingAs($resellerUser)->post('/console/action', [
            'action' => 'reseller_allocate_plan',
            'target_account_id' => $otherCustId,
            'plan_id' => $userPlanId,
        ])->assertStatus(403);

        // Reseller cannot quick_switch_tenant to foreign customer
        $this->actingAs($resellerUser)->post('/console/action', [
            'action' => 'quick_switch_tenant',
            'target_account_id' => $otherCustId,
        ])->assertStatus(403);

        // 4. Customer sees NEITHER Reseller NOR Platform Admin sections and gets 403 on management tabs
        $this->actingAs($customerUser)
            ->get('/?tab=overview')
            ->assertOk()
            ->assertDontSee('الحسابات والريسيلرز')
            ->assertDontSee('عملاء الريسيلر')
            ->assertDontSee('إدارة المنصة (Administration)');

        $this->actingAs($customerUser)->get('/?tab=reseller-tenants')->assertStatus(403);
        $this->actingAs($customerUser)->get('/?tab=accounts')->assertStatus(403);
    }

    public function test_v65_campaign_queue_status_dial_next_unblock_and_web_polling(): void
    {
        config(['ontrack.campaign_dial_enabled' => true]);

        [$user, $accountId, $siteId, $agentId, $binding, $authHeaders] = $this->seedBoundDevice();

        /** @var CampaignDialerService $dialer */
        $dialer = app(CampaignDialerService::class);
        $imported = $dialer->importContactsBulk($accountId, $siteId, "عميل أول,+201557275228\nعميل ثان,+201099887766", true);
        $this->assertSame(2, $imported['imported_count']);

        $tzCairo = new \DateTimeZone('Africa/Cairo');
        $startFuture = (new \DateTimeImmutable('+30 minutes', $tzCairo))->format('Y-m-d\TH:i');
        $endFuture = (new \DateTimeImmutable('+4 hours', $tzCairo))->format('Y-m-d\TH:i');

        // 1. Auto-fallback when device_binding_id is omitted (< 1)
        $campId = $dialer->createCampaign($accountId, $siteId, [
            'name' => 'حملة الطابور الحي',
            'device_binding_id' => 0,
            'starts_at' => $startFuture,
            'ends_at' => $endFuture,
            'scenario' => 'أهلاً {name}',
            'contact_ids' => $imported['contact_ids'],
            'arm_now' => 1,
        ]);
        $rowCamp = DB::table('tenant_campaigns')->where('id', $campId)->first();
        $this->assertSame((int) $binding['id'], (int) $rowCamp->device_binding_id);

        // 2. Heartbeat carries campaign_queue_status <= 16KB and no \u06 escapes
        $hb = $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'heartbeat',
        ])->assertOk();
        $rawBody = (string) $hb->getContent();
        $this->assertLessThanOrEqual(16384, strlen($rawBody));
        $this->assertStringNotContainsString('\u06', $rawBody);
        $hbJson = $hb->json();
        $this->assertSame($campId, $hbJson['profile']['campaign_queue_status']['campaign_id']);
        $this->assertNotNull($hbJson['profile']['campaign_queue_status']['next_contact']);
        $this->assertGreaterThan(0, $hbJson['profile']['campaign_queue_status']['next_contact']['seconds_until_dial']);

        // 3. Simulate completed call on contact 1 + stuck unknown/pre-dial row on contact 0
        $c0 = DB::table('contacts')->where('account_id', $accountId)->where('phone', '+201557275228')->first();
        $c1 = DB::table('contacts')->where('account_id', $accountId)->where('phone', '+201099887766')->first();
        $nowEpoch = time();
        DB::table('tenant_campaign_attempts')->insert([
            [
                'id' => bin2hex(random_bytes(16)),
                'campaign_id' => $campId,
                'account_id' => $accountId,
                'site_id' => $siteId,
                'device_binding_id' => (int) $binding['id'],
                'sim_slot' => 0,
                'contact_id' => (int) $c0->id,
                'phone_key' => '201557275228',
                'status' => 'unknown',
                'leased_epoch' => $nowEpoch - 120,
            ],
            [
                'id' => bin2hex(random_bytes(16)),
                'campaign_id' => $campId,
                'account_id' => $accountId,
                'site_id' => $siteId,
                'device_binding_id' => (int) $binding['id'],
                'sim_slot' => 0,
                'contact_id' => (int) $c1->id,
                'phone_key' => '201099887766',
                'status' => 'completed',
                'leased_epoch' => $nowEpoch - 300,
            ],
        ]);

        // Claim blocked due to unknown attempt
        $blocked = $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_campaign_claim',
        ])->assertOk()->json();
        $this->assertNull($blocked['claim']);
        $this->assertSame('uncertain_call_requires_review', $blocked['reason']);

        // 4. Web campaign_dial_next action clears stuck unknown row (preserving completed), bumps starts_at -> now, sets running
        $this->actingAs($user)->post('/console/action', [
            'action' => 'campaign_dial_next',
            'campaign_id' => $campId,
        ])->assertRedirect();

        $this->assertSame('running', DB::table('tenant_campaigns')->where('id', $campId)->value('status'));
        $this->assertNull(DB::table('tenant_campaign_attempts')->where('campaign_id', $campId)->where('contact_id', (int) $c0->id)->first());
        $this->assertSame('completed', DB::table('tenant_campaign_attempts')->where('campaign_id', $campId)->where('contact_id', (int) $c1->id)->value('status'));

        // 5. Web JSON queue endpoint reflects unblocked next contact with seconds_until_dial == 0
        $queueRes = $this->actingAs($user)->getJson('/api/console/campaigns/queue')->assertOk()->json();
        $this->assertTrue($queueRes['ok']);
        $this->assertSame('+201557275228', $queueRes['queue']['next_contact']['phone']);
        $this->assertSame(0, $queueRes['queue']['next_contact']['seconds_until_dial']);

        // 6. Device can now claim contact 0 immediately (completed contact 1 is NOT re-claimed!)
        $unblockedResp = $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_campaign_claim',
        ]);
        $this->assertSame(200, $unblockedResp->status(), (string) $unblockedResp->getContent());
        $unblocked = $unblockedResp->json();
        $this->assertNotNull($unblocked['claim']);
        $this->assertSame('+201557275228', $unblocked['claim']['phone']);

        // 7. Pause -> arm returns 'ready', start returns 'running'
        $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_campaign_action',
            'id' => $campId,
            'action' => 'pause',
            'confirmed' => true,
        ])->assertOk()->assertJsonPath('campaign.status', 'paused');

        $armRes = $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_campaign_action',
            'id' => $campId,
            'action' => 'arm',
            'confirmed' => true,
        ])->assertOk()->json();
        $this->assertSame('ready', $armRes['campaign']['status']);

        $startRes = $this->withHeaders($authHeaders)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_campaign_action',
            'id' => $campId,
            'action' => 'start',
            'confirmed' => true,
        ])->assertOk()->json();
        $this->assertSame('running', $startRes['campaign']['status']);
    }

    public function testEmotionalProsodyInstructionRestoration(): void
    {
        [$user, $accountId, $siteId, $agentId, $binding, $authHeaders] = $this->seedBoundDevice();

        /** @var VoiceSessionService $vs */
        $vs = app(VoiceSessionService::class);
        $definition = $vs->buildDefinitionForBinding($binding);
        $instruction = $definition['instruction'];

        $this->assertStringContainsString('قاعدة الأداء والتمثيل الصوتي والمشاعر', $instruction);
        $this->assertStringContainsString('التزم حرفياً بنبرة المشاعر والتنهيدات والتعبيرات الصوتية والعفوية الموصوفة في السيناريو', $instruction);
        $this->assertStringNotContainsString('قاعدة قِصر الرد الإلزامية', $instruction);
        $this->assertStringNotContainsString('توصيفات الأداء بين قوسين (...) أو [...] غير منطوقة؛ ممنوع نطقها أبدًا.', $instruction);
    }
}
