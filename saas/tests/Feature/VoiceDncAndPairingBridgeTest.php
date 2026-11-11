<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlanEntitlement;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\VoiceCampaign;
use App\Models\VoiceCampaignEntry;
use App\Models\VoiceDevicePairing;
use App\Models\VoiceDncEntry;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoiceDncAndPairingBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dnc_entry_blocks_campaign_import_before_queueing(): void
    {
        [$user,$workspace,$tenant]=$this->context('dnc-one');
        VoiceDncEntry::create([
            'tenant_id'=>$tenant->id,'phone_normalized'=>'201001234567',
            'reason'=>'opt_out','source'=>'contact_optout','created_by'=>$user->id,
        ]);
        $campaign=VoiceCampaign::create([
            'tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'name'=>'DNC test',
            'status'=>'draft','timezone'=>'Africa/Cairo','delay_seconds'=>0,'max_attempts'=>1,
        ]);
        $this->actingAs($user)->post('/app/'.$workspace->slug.'/voice/'.$campaign->id.'/import',
            ['rows'=>"+201001234567\n+201009876543"])->assertRedirect();
        $this->assertDatabaseHas('voice_campaign_entries',[
            'tenant_id'=>$tenant->id,'campaign_id'=>$campaign->id,
            'phone_e164'=>'+201001234567','queue_status'=>'invalid',
        ]);
        $this->assertDatabaseHas('voice_campaign_entries',[
            'tenant_id'=>$tenant->id,'campaign_id'=>$campaign->id,
            'phone_e164'=>'+201009876543','queue_status'=>'pending',
        ]);
        $this->assertSame('dnc',VoiceCampaignEntry::where('phone_e164','+201001234567')->firstOrFail()->source_fields['blocked']);
    }

    public function test_pairing_mirror_never_exposes_another_tenant(): void
    {
        [,,$alpha]=$this->context('pair-alpha');
        [,,$beta]=$this->context('pair-beta');
        $pair=VoiceDevicePairing::create([
            'tenant_id'=>$alpha->id,'device_id'=>'console:501',
            'pairing_token_hash'=>hash('sha256','six-digit-code-fixture'),
            'status'=>'active','last_seen_at'=>now(),
        ]);
        $this->assertSame($pair->id,VoiceDevicePairing::observedForTenant($alpha->id,'console:501')?->id);
        $this->assertNull(VoiceDevicePairing::observedForTenant($beta->id,'console:501'));
        $this->assertSame(0,VoiceDncEntry::forTenant($beta->id)->count());
        $this->assertArrayNotHasKey('pairing_token_hash',$pair->toArray());
    }

    private function context(string $slug): array
    {
        $user=User::factory()->create();
        $tenant=Tenant::create(['name'=>$slug,'slug'=>$slug,'status'=>'active','created_by'=>$user->id]);
        $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>$slug.'-main']);
        TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$user->id,'role'=>'owner','status'=>'active']);
        $plan=Plan::create(['name'=>'Voice Test Plan '.$slug,'status'=>'active','monthly_quota_credits'=>100]);
        foreach(['voice.campaigns','voice.contact_import'] as $feature)
            PlanEntitlement::create(['plan_id'=>$plan->id,'feature'=>$feature,'unit'=>'enabled','quota'=>1]);
        Subscription::create(['tenant_id'=>$tenant->id,'plan_id'=>$plan->id,'status'=>'active','started_at'=>now(),'renews_at'=>now()->addMonth()]);
        return [$user,$workspace,$tenant];
    }
}
