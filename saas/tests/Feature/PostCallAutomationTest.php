<?php

namespace Tests\Feature;

use App\Services\Automation\PostCallAutomationService;
use App\Services\Security\VaultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class PostCallAutomationTest extends TestCase
{
    use RefreshDatabase;

    private string $tempVault;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempVault = sys_get_temp_dir().'/iovoice-auto-vault-'.bin2hex(random_bytes(4));
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

    public function test_ssrf_blocks_private_loopback_and_non_https_webhooks(): void
    {
        $svc = app(PostCallAutomationService::class);

        foreach ([
            'http://example.com/hook',
            'https://localhost/hook',
            'https://127.0.0.1/hook',
            'https://192.168.1.50/hook',
            'https://169.254.169.254/latest/meta-data',
            'https://service.internal/webhook',
        ] as $badUrl) {
            try {
                $svc->assertSafeWebhookUrl($badUrl);
                $this->fail("Expected SSRF rejection for {$badUrl}");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        // DNS resolution returning private IP must be rejected even for HTTPS public domain
        $this->expectException(InvalidArgumentException::class);
        $svc->assertSafeWebhookUrl('https://webhook.evil-internal.com/hook', fn () => ['10.0.2.15']);
    }

    public function test_post_call_webhook_signs_hmac_sha256_and_updates_crm_and_dnc(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $accountId = DB::table('accounts')->insertGetId([
            'kind' => 'customer',
            'parent_id' => 0,
            'name' => 'Auto Test Co',
            'slug' => 'auto-test',
            'status' => 'active',
            'created_at' => $now,
        ]);
        $contactId = DB::table('contacts')->insertGetId([
            'account_id' => $accountId,
            'site_id' => 1,
            'display_name' => 'عميل الحملة',
            'name' => 'عميل الحملة',
            'phone_e164' => '+201012345678',
            'phone' => '+201012345678',
            'lead_status' => 'new',
            'dnc' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        /** @var PostCallAutomationService $svc */
        $svc = app(PostCallAutomationService::class);
        $dnsPublic = fn () => ['93.184.216.34'];

        $secret = 'my_signing_secret_2026';
        $webhookRuleId = $svc->createRule($accountId, [
            'name' => 'إرسال الحجز إلى CRM',
            'trigger_event' => 'report.saved',
            'condition_field' => 'outcome',
            'condition_operator' => 'equals',
            'condition_value' => 'booked',
            'action_type' => 'webhook',
            'webhook_url' => 'https://crm.example.com/iovoice/webhook',
            'webhook_secret' => $secret,
        ], $dnsPublic);

        $crmRuleId = $svc->createRule($accountId, [
            'name' => 'ترقية الحالة في CRM',
            'trigger_event' => 'report.saved',
            'condition_field' => 'outcome',
            'condition_operator' => 'equals',
            'condition_value' => 'booked',
            'action_type' => 'crm_update',
            'crm_status' => 'booked_confirmed',
        ], $dnsPublic);

        $dncRuleId = $svc->createRule($accountId, [
            'name' => 'حظر رقم رفض التواصل',
            'trigger_event' => 'report.saved',
            'condition_field' => 'outcome',
            'condition_operator' => 'equals',
            'condition_value' => 'do_not_call',
            'action_type' => 'dnc_suppress',
        ], $dnsPublic);

        $captured = null;
        $results = $svc->evaluateAndExecute($accountId, 'report.saved', [
            'call_id' => 'call-auto-77',
            'phone' => '+201012345678',
            'outcome' => 'booked',
            'ai_rating' => 5,
            'summary' => 'تم حجز موعد غداً الساعة 3 عصراً.',
        ], function (string $url, array $headers, string $body) use (&$captured) {
            $captured = ['url' => $url, 'headers' => $headers, 'body' => $body];

            return ['status' => 202, 'body' => '{"accepted":true}'];
        }, $dnsPublic);

        $this->assertCount(2, $results); // webhook + crm_update (dnc did not match 'booked')
        $this->assertNotNull($captured);
        $expectedSig = 'sha256='.hash_hmac('sha256', $captured['body'], $secret);
        $this->assertSame($expectedSig, $captured['headers']['X-IOVoice-Signature']);
        $this->assertSame('report.saved', $captured['headers']['X-IOVoice-Event']);

        // Secret itself is sealed encrypted at rest in automation_rules
        $rawRule = DB::table('automation_rules')->where('id', $webhookRuleId)->first();
        $this->assertNotSame($secret, $rawRule->webhook_secret_enc);
        $this->assertSame($secret, app(VaultService::class)->unseal($rawRule->webhook_secret_enc));

        // Webhook log stored cleanly with redacted header
        $this->assertDatabaseHas('webhook_logs', [
            'account_id' => $accountId,
            'automation_rule_id' => $webhookRuleId,
            'http_status' => 202,
            'status' => 'delivered',
        ]);

        // CRM update rule transitioned contact status
        $this->assertSame('booked_confirmed', DB::table('contacts')->where('id', $contactId)->value('lead_status'));
        $this->assertSame(1, (int) DB::table('automation_rules')->where('id', $crmRuleId)->value('trigger_count'));
        $this->assertSame(0, (int) DB::table('automation_rules')->where('id', $dncRuleId)->value('trigger_count'));
    }
}
