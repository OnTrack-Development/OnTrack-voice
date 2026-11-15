<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Mobile\DevicePairingService;
use App\Services\Security\VaultService;
use App\Services\Voice\VoiceSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class V041UpgradeTest extends TestCase
{
    use RefreshDatabase;

    private function seedTenant(): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $user = User::factory()->create();
        $accountId = DB::table('accounts')->insertGetId([
            'kind' => 'customer',
            'parent_id' => 0,
            'name' => 'V041 Co',
            'slug' => 'v041-co-'.uniqid(),
            'status' => 'active',
            'created_at' => $now,
        ]);
        $siteId = DB::table('sites')->insertGetId([
            'account_id' => $accountId,
            'name' => 'الفرع الرئيسي',
            'domain' => 'v041.example.test',
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
            'name' => 'Enterprise',
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
            'name' => 'مساعد الاستقبال',
            'kind' => 'voice',
            'voice' => 'google.aoede',
            'instructions' => 'مساعدة العملاء وتوجيههم للقسم المناسب.',
            'flow_json' => VoiceSessionService::DEFAULT_FLOW,
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        /** @var DevicePairingService $pairing */
        $pairing = app(DevicePairingService::class);
        $code = $pairing->createPairing($accountId, $siteId, $agentId, $user->id, 0);
        $bound = $pairing->redeemPairing($code, 'install-v041-'.uniqid(), 0, 'Xiaomi Note 12 Pro');
        $binding = DB::table('device_bindings')->where('id', $bound['device_id'])->first();

        return [$user, $accountId, $siteId, $agentId, (array) $binding, ['Authorization' => 'Bearer '.$bound['device_token']]];
    }

    public function test_update_agent_persists_ivr_and_manager_callback_and_syncs_routes(): void
    {
        [$user, $accountId, $siteId, $agentId] = $this->seedTenant();
        session(['account_id' => $accountId, 'site_id' => $siteId]);

        $resp = $this->actingAs($user)->post('/console/action', [
            'action' => 'update_agent',
            'agent_id' => $agentId,
            'name' => 'مساعد IVR الذكي',
            'voice' => 'google.aoede',
            'auto_answer' => '1',
            'answer_delay_seconds' => '4',
            'response_style' => 'egyptian_short',
            'max_call_minutes' => '10',
            'fallback' => 'human',
            'scenario_mode' => 'builder',
            'human_handoff' => '1',
            'manager_callback_enabled' => '1',
            'manager_callback_phone' => '01012345678',
            'ivr_enabled' => '1',
            'ivr_greeting' => 'أهلاً بك في IO Voice. للمبيعات قُل 1، وللدعم قُل 2.',
            'ivr_digit' => ['1', '2', '0'],
            'ivr_label' => ['المبيعات', 'الدعم الفني', 'المدير'],
            'ivr_prompt' => ['اشرح الباقات والأسعار باختصار', 'ساعد في حل المشكلات التقنية', 'حوّل الطلب فوراً للمدير'],
            'ivr_target_agent_id' => ['0', '0', '0'],
            'ivr_transfer_phone' => ['', '', '+201012345678'],
            'publish_now' => '1',
        ]);
        $resp->assertRedirect();

        $agent = DB::table('agents')->where('id', $agentId)->first();
        $this->assertSame(1, (int) $agent->manager_callback_enabled);
        $this->assertSame('+201012345678', $agent->manager_callback_phone);
        $this->assertSame(1, (int) $agent->ivr_enabled);
        $this->assertStringContainsString('للمبيعات', (string) $agent->ivr_greeting);

        $route = DB::table('human_escalation_routes')->where('account_id', $accountId)->where('agent_id', $agentId)->first();
        $this->assertNotNull($route);
        $contact = DB::table('human_escalation_contacts')->where('id', $route->contact_id)->first();
        $this->assertSame('+201012345678', $contact->phone_e164);

        // Verify web console tabs render both features
        $this->actingAs($user)->get('/?tab=agents&agent_id='.$agentId)
            ->assertOk()
            ->assertSee('المعاودة الفورية مع المدير')
            ->assertSee('+201012345678')
            ->assertSee('المبيعات');
    }

    public function test_settings_update_manager_callback_normalizes_and_renders(): void
    {
        [$user, $accountId, $siteId] = $this->seedTenant();
        session(['account_id' => $accountId, 'site_id' => $siteId]);

        $this->actingAs($user)->post('/console/action', [
            'action' => 'update_manager_callback',
            'manager_callback_phone' => '01099887766',
            'manager_label' => 'المدير المناوب',
        ])->assertRedirect();

        $site = DB::table('sites')->where('id', $siteId)->first();
        $this->assertSame('+201099887766', $site->manager_callback_phone);

        $this->actingAs($user)->get('/?tab=settings')
            ->assertOk()
            ->assertSee('الرقم الافتراضي لمعاودة المدير بعد المكالمة')
            ->assertSee('+201099887766');
    }

    public function test_voice_session_includes_ivr_and_callback_and_ended_event_queues_10s_callback(): void
    {
        [$user, $accountId, $siteId, $agentId, $binding, $headers] = $this->seedTenant();
        session(['account_id' => $accountId, 'site_id' => $siteId]);

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

        /** @var VoiceSessionService $vs */
        $vs = app(VoiceSessionService::class);
        $vs->saveAnswerPolicy($accountId, $agentId, [
            'auto_answer' => true,
            'answer_delay_seconds' => 3,
            'response_style' => 'egyptian_short',
            'human_handoff' => true,
            'manager_callback_enabled' => true,
            'manager_callback_phone' => '+201012345678',
            'ivr_enabled' => true,
            'ivr_greeting' => 'أهلاً بك، للمبيعات اضغط 1.',
            'ivr_routes' => [
                ['digit' => '1', 'label' => 'المبيعات', 'department_prompt' => 'ساعد في طلبات الشراء'],
            ],
        ]);
        $vs->publishAgent($accountId, $agentId, $user->id);

        $session = $vs->issueLiveVoiceSession($binding, static fn () => 'auth_tokens/test-token-v041');
        $this->assertSame(1024, $session['max_output_tokens']);
        $this->assertStringStartsWith('[قاعدة الأداء والتمثيل الصوتي والمشاعر]', $session['system_instruction']);
        $this->assertStringContainsString('[بروتوكول القائمة التفاعلية (IVR) وتوجيه الأقسام]', $session['system_instruction']);
        $this->assertTrue($session['ivr_enabled']);
        $this->assertTrue($session['manager_callback_enabled']);
        $this->assertSame('+201012345678', $session['manager_callback_phone']);

        // Heartbeat includes campaigns_summary and compact ivr_routes within 16KB
        $hb = $this->withHeaders($headers)->postJson('/device/v1/index.php', [
            'operation' => 'heartbeat',
        ])->assertOk();
        $hb->assertJsonPath('ok', true);
        $hb->assertJsonPath('profile.answer_policy.ivr_enabled', true);
        $hb->assertJsonPath('profile.campaigns_summary.ready_count', 0);

        // Call ended schedules a 10-second manager callback job and returns manager_callback metadata
        $callId = 'call_v041_cb_001';
        $this->withHeaders($headers)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_call_event',
            'call_id' => $callId,
            'sequence' => 1,
            'phase' => 'active',
            'direction' => 'inbound',
            'duration_seconds' => 0,
        ])->assertOk();

        $this->withHeaders($headers)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_call_event',
            'call_id' => $callId,
            'sequence' => 2,
            'phase' => 'customer_human_request',
            'handoff_reason' => 'طلب العميل التواصل مع المدير',
        ])->assertOk();

        $ended = $this->withHeaders($headers)->postJson('/device/v1/index.php', [
            'operation' => 'workspace_call_event',
            'call_id' => $callId,
            'sequence' => 3,
            'phase' => 'ended',
            'direction' => 'inbound',
            'duration_seconds' => 12,
        ])->assertOk();

        $ended->assertJsonPath('accepted', true)
            ->assertJsonPath('manager_callback.enabled', true)
            ->assertJsonPath('manager_callback.phone', '+201012345678')
            ->assertJsonPath('manager_callback.delay_seconds', 10)
            ->assertJsonPath('manager_callback.queued', true)
            ->assertJsonPath('escalation.queued', true)
            ->assertJsonPath('escalation.ready_after_seconds', 10);
    }
}
