<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionCrudAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $platformUser;
    private User $customerUser;
    private int $platformAccountId;
    private int $customerAccountId;
    private string $customerSlug;

    protected function setUp(): void
    {
        parent::setUp();

        $now = gmdate('Y-m-d H:i:s');

        // Platform Admin User & Account
        $this->platformUser = User::factory()->create([
            'email' => 'platform@ontrackegy.com',
        ]);
        $this->platformAccountId = DB::table('accounts')->insertGetId([
            'kind' => 'platform',
            'parent_id' => 0,
            'name' => 'Platform HQ',
            'slug' => 'platform-' . uniqid(),
            'status' => 'active',
            'created_at' => $now,
        ]);
        DB::table('sites')->insertGetId([
            'account_id' => $this->platformAccountId,
            'name' => 'Platform Site',
            'domain' => 'platform.test',
            'status' => 'active',
            'created_at' => $now,
        ]);
        DB::table('memberships')->insert([
            'account_id' => $this->platformAccountId,
            'user_id' => $this->platformUser->id,
            'role' => 'owner',
            'created_at' => $now,
        ]);

        // Customer User & Account
        $this->customerSlug = 'client-' . uniqid();
        $this->customerUser = User::factory()->create([
            'email' => 'client@example.com',
        ]);
        $this->customerAccountId = DB::table('accounts')->insertGetId([
            'kind' => 'customer',
            'parent_id' => $this->platformAccountId,
            'name' => 'Acme Hosting Ltd',
            'slug' => $this->customerSlug,
            'status' => 'active',
            'created_at' => $now,
        ]);
        $custSiteId = DB::table('sites')->insertGetId([
            'account_id' => $this->customerAccountId,
            'name' => 'Acme Main Site',
            'domain' => 'acme.test',
            'status' => 'active',
            'created_at' => $now,
        ]);
        DB::table('memberships')->insert([
            'account_id' => $this->customerAccountId,
            'user_id' => $this->customerUser->id,
            'role' => 'owner',
            'created_at' => $now,
        ]);

        // Create default agent for the customer
        DB::table('agents')->insert([
            'account_id' => $this->customerAccountId,
            'site_id' => $custSiteId,
            'name' => 'OnTrack Agent',
            'voice' => 'ar-EG-SalmaNeural',
            'status' => 'published',
            'flow_json' => json_encode(['nodes' => [], 'edges' => []]),
            'created_at' => $now,
        ]);
    }

    public function test_lead_lifecycle_crud(): void
    {
        // 1. Create Lead
        $res = $this->actingAs($this->customerUser)->post('/console/action', [
            'action' => 'create_lead',
            'name' => 'طارق السعيد',
            'phone' => '+201099881122',
            'email' => 'tarek@example.test',
            'company' => 'شركة الدلتا',
            'status' => 'new',
            'source' => 'manual',
            'value' => 750,
        ]);
        $res->assertSessionHas('success');

        $lead = DB::table('leads')
            ->where('account_id', $this->customerAccountId)
            ->where('phone', '+201099881122')
            ->first();
        $this->assertNotNull($lead);
        $this->assertSame('طارق السعيد', $lead->name);
        $this->assertSame('new', $lead->status);

        // 2. Update Lead Status
        $res2 = $this->actingAs($this->customerUser)->post('/console/action', [
            'action' => 'update_lead_status',
            'lead_id' => $lead->id,
            'status' => 'qualified',
        ]);
        $res2->assertSessionHas('success');

        $leadAfter = DB::table('leads')->where('id', $lead->id)->first();
        $this->assertSame('qualified', $leadAfter->status);

        // 3. Delete Lead
        $res3 = $this->actingAs($this->customerUser)->post('/console/action', [
            'action' => 'delete_lead',
            'lead_id' => $lead->id,
        ]);
        $res3->assertSessionHas('success');

        $this->assertNull(DB::table('leads')->where('id', $lead->id)->first());
    }

    public function test_create_lead_from_call(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $callId = DB::table('calls')->insertGetId([
            'account_id' => $this->customerAccountId,
            'phone' => '+201122334455',
            'status' => 'answered',
            'created_at' => $now,
        ]);

        $res = $this->actingAs($this->customerUser)->post('/console/action', [
            'action' => 'create_lead_from_call',
            'call_id' => $callId,
            'phone' => '+201122334455',
        ]);
        $res->assertSessionHas('success');

        $lead = DB::table('leads')
            ->where('account_id', $this->customerAccountId)
            ->where('phone', '+201122334455')
            ->first();
        $this->assertNotNull($lead);
        $this->assertSame('voice_call', $lead->source);
        $this->assertSame('qualified', $lead->status);
    }

    public function test_leads_csv_export(): void
    {
        DB::table('leads')->insert([
            'account_id' => $this->customerAccountId,
            'name' => 'CSV Test Lead',
            'phone' => '+201000112233',
            'status' => 'new',
            'source' => 'manual',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $res = $this->actingAs($this->customerUser)->get('/leads/csv');
        $res->assertOk();
        $res->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('CSV Test Lead', $res->getContent());
        $this->assertStringContainsString('+201000112233', $res->getContent());
    }

    public function test_appointment_lifecycle_crud(): void
    {
        // 1. Create Appointment
        $res = $this->actingAs($this->customerUser)->post('/console/action', [
            'action' => 'create_appointment',
            'client_name' => 'سارة فهمي',
            'client_phone' => '+201299887766',
            'client_email' => 'sara@test.eg',
            'service' => 'استشارة خوادم سحابية',
            'date' => '2026-11-25',
            'time' => '02:00 PM',
        ]);
        $res->assertSessionHas('success');

        $app = DB::table('appointments')
            ->where('account_id', $this->customerAccountId)
            ->where('client_phone', '+201299887766')
            ->first();
        $this->assertNotNull($app);
        $this->assertSame('سارة فهمي', $app->client_name);
        $this->assertSame('confirmed', $app->status);

        // Also verifies lead auto-creation
        $lead = DB::table('leads')
            ->where('account_id', $this->customerAccountId)
            ->where('phone', '+201299887766')
            ->first();
        $this->assertNotNull($lead);

        // 2. Update Status
        $res2 = $this->actingAs($this->customerUser)->post('/console/action', [
            'action' => 'update_appointment_status',
            'appointment_id' => $app->id,
            'status' => 'completed',
        ]);
        $res2->assertSessionHas('success');

        $this->assertSame('completed', DB::table('appointments')->where('id', $app->id)->value('status'));

        // 3. Delete Appointment
        $res3 = $this->actingAs($this->customerUser)->post('/console/action', [
            'action' => 'delete_appointment',
            'appointment_id' => $app->id,
        ]);
        $res3->assertSessionHas('success');
        $this->assertNull(DB::table('appointments')->where('id', $app->id)->first());
    }

    public function test_public_booking_flow(): void
    {
        // 1. GET /book/{slug}
        $res = $this->get('/book/' . $this->customerSlug);
        $res->assertOk();
        $res->assertSee('Acme Hosting Ltd');
        $res->assertSee('حجز موعد استشارة جديدة');

        // 2. POST /book/{slug}
        $res2 = $this->post('/book/' . $this->customerSlug, [
            'client_name' => 'كمال الشناوي',
            'client_phone' => '+201588776655',
            'client_email' => 'kamal@hosting.test',
            'service' => 'عرض توضيحي لمنظومة OnTrack AI',
            'date' => '2026-11-28',
            'time' => '11:30 AM',
            'notes' => 'مهم جداً للربط مع WHMCS',
        ]);
        $res2->assertSessionHas('success');

        $app = DB::table('appointments')
            ->where('account_id', $this->customerAccountId)
            ->where('client_phone', '+201588776655')
            ->first();
        $this->assertNotNull($app);
        $this->assertSame('كمال الشناوي', $app->client_name);

        $lead = DB::table('leads')
            ->where('account_id', $this->customerAccountId)
            ->where('phone', '+201588776655')
            ->first();
        $this->assertNotNull($lead);
        $this->assertSame('website_widget', $lead->source);
    }

    public function test_chat_message_and_human_takeover(): void
    {
        $phone = '+201012399999';

        // 1. Send chat message
        $res = $this->actingAs($this->customerUser)->post('/console/action', [
            'action' => 'send_chat_message',
            'phone' => $phone,
            'message' => 'مرحباً، تم استلام استفسارك وسيتم الرد فوراً.',
        ]);
        $res->assertSessionHas('success');

        $session = DB::table('support_whatsapp_sessions')
            ->where('account_id', $this->customerAccountId)
            ->where('phone', $phone)
            ->first();
        $this->assertNotNull($session);

        // 2. Human Takeover
        $res2 = $this->actingAs($this->customerUser)->post('/console/action', [
            'action' => 'takeover_conversation',
            'phone' => $phone,
        ]);
        $res2->assertSessionHas('success');
        $this->assertEquals(1, DB::table('support_whatsapp_sessions')->where('id', $session->id)->value('is_muted_for_human'));

        // 3. Resume AI
        $res3 = $this->actingAs($this->customerUser)->post('/console/action', [
            'action' => 'resume_ai_conversation',
            'phone' => $phone,
        ]);
        $res3->assertSessionHas('success');
        $this->assertEquals(0, DB::table('support_whatsapp_sessions')->where('id', $session->id)->value('is_muted_for_human'));
    }

    public function test_ticket_ai_scan_and_draft_approval(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $ticketId = DB::table('support_tickets')->insertGetId([
            'account_id' => $this->customerAccountId,
            'subject' => 'مشكلة في استهلاك ذاكرة السيرفر',
            'client_name' => 'م. حسام الدين',
            'status' => 'open',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 1. Scan ticket with AI
        $res = $this->actingAs($this->customerUser)->post('/console/action', [
            'action' => 'scan_ticket_with_ai',
            'ticket_id' => $ticketId,
        ]);
        $res->assertSessionHas('success');

        $draft = DB::table('support_ticket_drafts')
            ->where('ticket_id', $ticketId)
            ->where('account_id', $this->customerAccountId)
            ->first();
        $this->assertNotNull($draft);
        $this->assertSame('pending', $draft->reviewer_verdict);

        // 2. Approve Draft
        $res2 = $this->actingAs($this->customerUser)->post('/console/action', [
            'action' => 'approve_ticket_draft',
            'ticket_id' => $ticketId,
            'draft_id' => $draft->id,
            'approved_text' => 'تم فحص السيرفر واعتماد الحل بنجاح.',
        ]);
        $res2->assertSessionHas('success');

        $this->assertSame('approved', DB::table('support_ticket_drafts')->where('id', $draft->id)->value('reviewer_verdict'));
        $this->assertSame('answered', DB::table('support_tickets')->where('id', $ticketId)->value('status'));
    }

    public function test_persona_rewrite_power_dialer_and_ai_workflow(): void
    {
        // 1. AI Persona rewrite
        $res1 = $this->actingAs($this->customerUser)->post('/console/action', [
            'action' => 'ai_rewrite_persona',
            'role' => 'مستشار مبيعات سحابي',
            'company' => 'Acme Cloud',
        ]);
        $res1->assertSessionHas('success');

        // 2. Launch Power Dialer
        $res2 = $this->actingAs($this->customerUser)->post('/console/action', [
            'action' => 'launch_power_dialer',
            'campaign_name' => 'حملة استعادة العملاء القدامى',
            'concurrent_lines' => 3,
            'sim_slot' => 1,
        ]);
        $res2->assertSessionHas('success');

        // 3. AI Build Workflow
        $res3 = $this->actingAs($this->customerUser)->post('/console/action', [
            'action' => 'ai_build_workflow',
            'prompt' => 'إرسال ملخص واتساب بعد المكالمات المؤهلة',
        ]);
        $res3->assertSessionHas('success');
    }

    public function test_security_hardening_floorplan(): void
    {
        $this->assertFileDoesNotExist(public_path('admin/floorplan.html'));

        // Guest is redirected
        $res = $this->get('/admin/floorplan');
        $res->assertRedirect('/login');

        // Customer is forbidden (403)
        $res2 = $this->actingAs($this->customerUser)->get('/admin/floorplan');
        $res2->assertForbidden();
    }
}
