<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\Plan;
use App\Models\PlanEntitlement;
use App\Models\Subscription;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\VoiceCampaign;
use App\Models\VoiceCampaignEntry;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoiceImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_preserves_original_and_flags_invalid_and_duplicate(): void
    {
        [$user,$workspace,$tenant]=$this->context('alpha');
        $campaign=VoiceCampaign::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'name'=>'Campaign','status'=>'draft','timezone'=>'Africa/Cairo','delay_seconds'=>30,'max_attempts'=>1]);
        $this->actingAs($user)->post('/app/'.$workspace->slug.'/voice/'.$campaign->id.'/import',['rows'=>"Ahmed,+201001234567\nBad,not-a-number\nDuplicate,+201001234567"])->assertRedirect();
        $this->assertDatabaseHas('voice_campaign_entries',['campaign_id'=>$campaign->id,'phone_e164'=>'+201001234567','queue_status'=>'pending']);
        $this->assertDatabaseHas('voice_campaign_entries',['campaign_id'=>$campaign->id,'phone_e164'=>'not-a-number','queue_status'=>'invalid']);
        $this->assertSame(2,VoiceCampaignEntry::count());
    }

    public function test_foreign_tenant_cannot_import_into_campaign(): void
    {
        [$user,$workspace]=$this->context('alpha'); [, $foreignWorkspace,$foreignTenant]=$this->context('beta');
        $campaign=VoiceCampaign::create(['tenant_id'=>$foreignTenant->id,'workspace_id'=>$foreignWorkspace->id,'name'=>'Foreign','status'=>'draft','timezone'=>'UTC','delay_seconds'=>0,'max_attempts'=>1]);
        $this->actingAs($user)->post('/app/'.$workspace->slug.'/voice/'.$campaign->id.'/import',['rows'=>'+201001234567'])->assertNotFound();
    }

    private function context(string $slug): array
    {
        $user=User::factory()->create(); $tenant=Tenant::create(['name'=>$slug,'slug'=>$slug,'status'=>'active','created_by'=>$user->id]); $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>$slug.'-main']); TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$user->id,'role'=>'owner','status'=>'active']); $plan=Plan::create(['name'=>'Voice Test Plan','status'=>'active','monthly_quota_credits'=>100]); foreach(['voice.campaigns','voice.contact_import'] as $feature)PlanEntitlement::create(['plan_id'=>$plan->id,'feature'=>$feature,'unit'=>'enabled','quota'=>1]); Subscription::create(['tenant_id'=>$tenant->id,'plan_id'=>$plan->id,'status'=>'active','started_at'=>now(),'renews_at'=>now()->addMonth()]); return [$user,$workspace,$tenant];
    }
}