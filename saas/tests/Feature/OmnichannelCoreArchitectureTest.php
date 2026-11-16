<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Support\SupportTicket;
use App\Services\Billing\FeatureGateService;
use App\Services\Support\MultiAgentOrchestrator;
use App\Services\Support\ReviewerVerdict;
use App\Services\Support\Security;
use App\Services\Channels\WhatsApp\WhatsAppReplyActions;
use App\Services\Channels\WhatsApp\WhatsAppTicketWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__ . '/../../storage/connectors/whmcs/ontrack_bridge/LocalPiiTokenizer.php';

class OmnichannelCoreArchitectureTest extends TestCase
{
    use RefreshDatabase;
    public function test_feature_gate_service_enforces_limits_and_allowances(): void
    {
        $service = new FeatureGateService();
        $account = Account::first() ?? Account::create(['name' => 'Test Account', 'slug' => 'test_account']);

        $this->assertTrue($service->allows($account, 'voice_calls_minutes'));
        $this->assertTrue($service->allows($account, 'whatsapp_dynamic_buttons'));
        $this->assertTrue($service->allows($account, 'multi_agent_consultations'));

        $this->assertGreaterThan(0, $service->limitFor($account, 'multi_agent_consultations'));
        $this->assertGreaterThan(0, $service->limitFor($account, 'attachment_vision_scans'));

        // Consume zero or unit without error
        $service->consume($account, 'multi_agent_consultations', 1);
        $this->assertTrue(true);
    }

    public function test_security_redaction_hides_sensitive_credentials(): void
    {
        $raw = "Here is my password: SuperSecret123! and my token: bearer 98452148754124584124";
        $redacted = Security::redactSensitiveText($raw);

        $this->assertTrue($redacted['detected']);
        $this->assertStringNotContainsString('SuperSecret123!', $redacted['text']);
        $this->assertStringContainsString('[REDACTED_CREDENTIAL]', $redacted['text']);
    }

    public function test_reviewer_verdict_evaluates_draft_correctly(): void
    {
        $draft = "أهلاً بك، تكلفة الباقة الشهرية هي 150 جنيه شاملة الدعم الفني.";
        $catalog = [['name' => 'باقة سحابية', 'price' => 150.0]];

        $result = ReviewerVerdict::evaluate($draft, ['catalog_items' => $catalog]);

        $this->assertEquals('approved', $result['verdict']);
        $this->assertGreaterThanOrEqual(0.80, $result['confidence']);
    }

    public function test_multi_agent_orchestrator_pipeline_execution(): void
    {
        $orchestrator = new MultiAgentOrchestrator();
        $account = Account::first() ?? Account::create(['name' => 'Demo Account', 'slug' => 'demo_acc']);

        $payload = [
            'account_id' => $account->id,
            'external_ticket_id' => 'WHMCS-TEST-99',
            'client_name' => 'محمود',
            'subject' => 'مقارنة بين الاستضافة وسيرفرات VPS',
            'message' => 'عايز أعرف الفرق بين الاستضافة المشتركة وسيرفر VPS من ناحية الأسعار والمواصفات.',
            'evidence' => [
                'catalog' => [
                    ['category' => 'shared', 'name' => 'باقة استضافة سريعة', 'price' => 150],
                    ['category' => 'vps', 'name' => 'سيرفر سحابي 4GB', 'price' => 500]
                ]
            ]
        ];

        $res = $orchestrator->process($payload);

        $this->assertNotNull($res['ticket_id']);
        $this->assertEquals('WHMCS-TEST-99', $res['external_ticket_id']);
        $this->assertNotEmpty($res['draft_text']);
        $this->assertEquals('approved', $res['reviewer_verdict']);
        $this->assertNotEmpty($res['consultations']);
    }

    public function test_whatsapp_reply_actions_vocabulary(): void
    {
        $buttonsIdle = WhatsAppReplyActions::select('idle', 'sales');
        $this->assertCount(3, $buttonsIdle);
        $this->assertEquals(WhatsAppReplyActions::BUTTON_HOSTING_PLANS, $buttonsIdle[0]['id']);

        $buttonsCreate = WhatsAppReplyActions::select('wa_ticket_create');
        $this->assertCount(3, $buttonsCreate);
        $this->assertEquals(WhatsAppReplyActions::BUTTON_CONFIRM, $buttonsCreate[0]['id']);
    }

    public function test_whatsapp_ticket_workflow_state_machine(): void
    {
        $account = Account::first() ?? Account::create(['name' => 'WA Test', 'slug' => 'wa_test']);
        $phone = '+201099887766';

        // 1. Initial trigger -> requires verification
        $res1 = WhatsAppTicketWorkflow::handle($account->id, $phone, 'فتح تذكرة');
        $this->assertTrue($res1['handled']);
        $this->assertStringContainsString('تأكيد حسابك', $res1['reply']);

        // Mark session as verified for testing
        \App\Models\Support\SupportWhatsAppSession::where('account_id', $account->id)
            ->where('phone', $phone)
            ->update(['is_verified' => true, 'client_id' => '888']);

        // 2. Open ticket again -> moves to wa_ticket_collect
        $res2 = WhatsAppTicketWorkflow::handle($account->id, $phone, 'فتح تذكرة');
        $this->assertTrue($res2['handled']);
        $this->assertEquals('wa_ticket_collect', $res2['state']);

        // 3. Send problem description -> moves to wa_ticket_create (preview)
        $res3 = WhatsAppTicketWorkflow::handle($account->id, $phone, 'عندي مشكلة في إرسال البريد الإلكتروني من الموقع');
        $this->assertTrue($res3['handled']);
        $this->assertEquals('wa_ticket_create', $res3['state']);
        $this->assertStringContainsString('مسودة التذكرة', $res3['reply']);

        // 4. Confirm draft -> creates ticket and returns to idle
        $res4 = WhatsAppTicketWorkflow::handle($account->id, $phone, 'اعتمدها');
        $this->assertTrue($res4['handled']);
        $this->assertEquals('idle', $res4['state']);
        $this->assertNotNull($res4['ticket_id']);
        $this->assertStringContainsString('تم إنشاء التذكرة بنجاح', $res4['reply']);
    }

    public function test_local_pii_tokenizer_zero_knowledge(): void
    {
        $payload = [
            'client_name' => 'محمد أحمد',
            'client_email' => 'client@mybusiness.com',
            'client_phone' => '01012345678',
            'subject' => 'عطل في نطاق myclientdomain.com',
            'message' => 'الموقع على myclientdomain.com لا يعمل.',
        ];

        $sessionKey = 'unit_test_session_1';
        $tokenized = \LocalPiiTokenizer::tokenize($payload, $sessionKey);

        // Verify that real data is replaced by tokens
        $this->assertEquals('[CLIENT_NAME_1]', $tokenized['client_name']);
        $this->assertEquals('[EMAIL_1]', $tokenized['client_email']);
        $this->assertEquals('[PHONE_1]', $tokenized['client_phone']);
        $this->assertStringNotContainsString('myclientdomain.com', $tokenized['message']);
        $this->assertStringContainsString('[DOMAIN_', $tokenized['message']);

        // Now test detokenization
        $aiDraft = "أهلاً أستاذ [CLIENT_NAME_1]، تم فحص النطاق [DOMAIN_1] ووجدنا أنه يحتاج لتحديث سجلات A.";
        $restored = \LocalPiiTokenizer::detokenize($aiDraft, $sessionKey);

        $this->assertStringContainsString('محمد أحمد', $restored);
        $this->assertStringContainsString('myclientdomain.com', $restored);
        $this->assertStringNotContainsString('[CLIENT_NAME_1]', $restored);
    }

    public function test_bridge_api_endpoints(): void
    {
        $account = Account::create(['name' => 'Demo Account', 'slug' => 'otint_demo_account']);

        // 1. Status endpoint
        $response = $this->withHeaders([
            'X-Ontrack-Key' => 'otint_demo_account',
            'X-Ontrack-Timestamp' => (string)time(),
        ])->getJson('/api/v1/bridge/status');

        $response->assertStatus(200);
        $this->assertTrue($response->json('connected'));
        $this->assertNotNull($response->json('plan_name'));

        // 2. Widget proxy endpoint
        $widgetRes = $this->withHeaders([
            'X-Ontrack-Key' => 'otint_demo_account',
        ])->postJson('/api/v1/bridge/widget', [
            'message' => 'عايز أعرف تفاصيل باقات الاستضافة',
        ]);

        $widgetRes->assertStatus(200);
        $this->assertTrue($widgetRes->json('ok'));
        $this->assertNotEmpty($widgetRes->json('reply'));
        $this->assertNotEmpty($widgetRes->json('buttons'));
    }

    public function test_shareable_public_web_call_page_renders_200(): void
    {
        $account = Account::first() ?? Account::create(['name' => 'Test Account', 'slug' => 'test_account']);
        $site = \App\Models\Site::first() ?? \App\Models\Site::create(['account_id' => $account->id, 'name' => 'Test Site', 'slug' => 'test_site']);
        $agent = \App\Models\Agent::first() ?? \App\Models\Agent::create([
            'site_id' => $site->id,
            'name' => 'انجي',
            'voice_name' => 'Zephyr',
            'speed' => 1.0,
            'temperature' => 0.7,
            'system_prompt' => 'test prompt',
            'greeting_message' => 'مرحباً بك',
        ]);

        $response = $this->get('/aivoiceagent/'.$agent->id.'?share=test_token');
        $response->assertStatus(200);
        $response->assertSee('انجي');
        $response->assertSee('wss://app.ontrackegy.com/ws-voice');
        $response->assertSee('callCanvas');
    }
}
