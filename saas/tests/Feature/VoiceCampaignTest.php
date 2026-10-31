<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\Plan;
use App\Models\PlanEntitlement;
use App\Models\Subscription;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\VoiceCampaign;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoiceCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_is_saved_as_draft_for_current_tenant(): void
    {
        [$user,$workspace,$tenant]=$this->context('alpha');
        $this->actingAs($user)->post('/app/'.$workspace->slug.'/voice',['name'=>'حملة متابعة','timezone'=>'Africa/Cairo','delay_seconds'=>30,'max_attempts'=>2])->assertRedirect();
        $campaign=VoiceCampaign::firstOrFail();
        $this->assertSame($tenant->id,$campaign->tenant_id);
        $this->assertSame('draft',$campaign->status);
        $this->assertSame(2,$campaign->max_attempts);
    }

    public function test_foreign_tenant_cannot_access_voice_campaigns(): void
    {
        [$user,$workspace]= $this->context('alpha'); [, $foreign]=$this->context('beta');
        $this->actingAs($user)->get('/app/'.$foreign->slug.'/voice')->assertForbidden();
        $this->actingAs($user)->post('/app/'.$foreign->slug.'/voice',['name'=>'x','timezone'=>'UTC','delay_seconds'=>0,'max_attempts'=>1])->assertForbidden();
        $this->assertDatabaseCount('voice_campaigns',0);
    }

    public function test_without_active_plan_campaign_route_is_locked(): void
    {
        [$user,$workspace,$tenant]=$this->context('locked');
        Subscription::where('tenant_id',$tenant->id)->delete();
        $this->actingAs($user)->get('/app/'.$workspace->slug.'/voice')->assertForbidden();
        $this->actingAs($user)->post('/app/'.$workspace->slug.'/voice',[
            'name'=>'x','timezone'=>'Africa/Cairo','delay_seconds'=>10,'max_attempts'=>1
        ])->assertForbidden();
        $this->assertDatabaseCount('voice_campaigns',0);
    }

    private function context(string $slug): array
    {
        $user=User::factory()->create(); $tenant=Tenant::create(['name'=>$slug,'slug'=>$slug,'status'=>'active','created_by'=>$user->id]); $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>$slug.'-main']); TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$user->id,'role'=>'owner','status'=>'active']); $plan=Plan::create(['name'=>'Voice Test Plan','status'=>'active','monthly_quota_credits'=>100]); foreach(['voice.campaigns','voice.contact_import'] as $feature)PlanEntitlement::create(['plan_id'=>$plan->id,'feature'=>$feature,'unit'=>'enabled','quota'=>1]); Subscription::create(['tenant_id'=>$tenant->id,'plan_id'=>$plan->id,'status'=>'active','started_at'=>now(),'renews_at'=>now()->addMonth()]); return [$user,$workspace,$tenant];
    }
}