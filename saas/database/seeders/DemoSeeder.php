<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Conversation;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeSource;
use App\Models\KnowledgeSourceVersion;
use App\Models\Message;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\VoiceCampaign;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    /**
     * Clearly-labeled demo data for a working local preview.
     * No real customers, no fake AI busy numbers, no unapproved pricing.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@ontrack.local'],
            ['name' => 'حساب العرض التجريبي', 'password' => Hash::make('DemoPass123!')]
        );

        $tenant = Tenant::firstOrCreate(['slug' => 'demo-company'], [
            'name' => 'شركة العرض التجريبي', 'industry' => 'other', 'status' => 'active', 'created_by' => $user->id,
        ]);

        $workspace = Workspace::firstOrCreate(['slug' => 'demo-company-main','tenant_id'=>$tenant->id], [
            'name' => 'المساحة الرئيسية',
            'tenant_id' => $tenant->id,
        ]);

        TenantMembership::firstOrCreate(['tenant_id'=>$tenant->id,'user_id'=>$user->id], [
            'workspace_id'=>$workspace->id,'role'=>'owner','status'=>'active',
        ]);

        $project = Project::firstOrCreate(['slug'=>'demo-support','workspace_id'=>$workspace->id], [
            'tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'name'=>'دعم العملاء','kind'=>'service','status'=>'active',
        ]);

        $agent = Agent::firstOrCreate(['tenant_id'=>$tenant->id,'project_id'=>$project->id,'name'=>'مساعد خدمة العملاء'], [
            'kind'=>'support','status'=>'published','instructions'=>'أجب بلغة عربية مصرية واضحة عن أسئلة الخدمة من المعرفة المتاحة فقط. لا تختلق معلومات عملاء.','persona'=>'ودود ومختصر','published_at'=>now(),
        ]);

        $source = KnowledgeSource::firstOrCreate(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'name'=>'أسئلة الخدمة'], [
            'project_id'=>$project->id,'kind'=>'text','scope'=>'project','status'=>'indexed','source_hash'=>hash('sha256','ساعات العمل من 9 إلى 5. الاسترجاع خلال 14 يومًا.'),
        ]);
        $version = KnowledgeSourceVersion::firstOrCreate(['knowledge_source_id'=>$source->id,'version'=>1], [
            'tenant_id'=>$tenant->id,'content'=>'ساعات العمل من 9 إلى 5. الاسترجاع خلال 14 يومًا.','sha256'=>hash('sha256','ساعات العمل من 9 إلى 5. الاسترجاع خلال 14 يومًا.'),'ingestion_status'=>'indexed','chunk_count'=>2,
        ]);
        foreach (['ساعات العمل من 9 إلى 5.', 'الاسترجاع خلال 14 يومًا.'] as $i => $chunk) {
            KnowledgeChunk::firstOrCreate(['source_version_id'=>$version->id,'chunk_index'=>$i], ['tenant_id'=>$tenant->id,'content'=>$chunk]);
        }

        $conversation = Conversation::firstOrCreate(['tenant_id'=>$tenant->id,'contact_key'=>'demo-customer-web','channel'=>'web'], [
            'workspace_id'=>$workspace->id,'project_id'=>$project->id,'agent_id'=>$agent->id,'status'=>'open',
        ]);
        Message::firstOrCreate(['tenant_id'=>$tenant->id,'conversation_id'=>$conversation->id,'provider_message_id'=>'demo-in-1'], [
            'role'=>'customer','body'=>'أهلاً، ممكن أعرف مواعيد الدعم؟','delivery_status'=>'accepted','occurred_at'=>now()->subMinutes(20),
        ]);
        Message::firstOrCreate(['tenant_id'=>$tenant->id,'conversation_id'=>$conversation->id,'provider_message_id'=>'demo-in-2'], [
            'role'=>'ai','body'=>'أهلًا! الدعم متاح من 9 صباحًا حتى 5 مساءً. يحتاج أي حاجة تانية؟','delivery_status'=>'accepted','occurred_at'=>now()->subMinutes(19),
        ]);

        VoiceCampaign::firstOrCreate(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'name'=>'متابعة تجريبية'], [
            'status'=>'draft','timezone'=>'Africa/Cairo','delay_seconds'=>30,'max_attempts'=>1,
        ]);

        $plan = Plan::firstOrCreate(['name'=>'الباقة التجريبية'], ['status'=>'active','monthly_quota_credits'=>1000]);
        Subscription::firstOrCreate(['tenant_id'=>$tenant->id,'plan_id'=>$plan->id], ['status'=>'active','started_at'=>now()]);
    }
}