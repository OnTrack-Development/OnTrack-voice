<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\WhatsappConnection;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappPauseOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_save_valid_per_number_pause_override(): void
    {
        [$user,$workspace,$tenant] = $this->context('alpha');
        $this->actingAs($user)->post('/app/'.$workspace->slug.'/channels/whatsapp', [
            'label'=>'الدعم','method'=>'manual_cloud_api','waba_id'=>'w1','phone_number_id'=>'p1',
            'access_token'=>'token','app_secret'=>'secret','pause_override_minutes'=>45,
        ])->assertRedirect();
        $this->assertDatabaseHas('whatsapp_connections', ['tenant_id'=>$tenant->id,'pause_override_minutes'=>45]);
    }

    public function test_override_is_limited_to_one_thousand_four_hundred_forty_minutes(): void
    {
        [$user,$workspace] = $this->context('alpha');
        foreach ([0,1441] as $minutes) {
            $this->actingAs($user)->post('/app/'.$workspace->slug.'/channels/whatsapp', [
                'label'=>'x'.$minutes,'method'=>'manual_cloud_api','waba_id'=>'w'.$minutes,
                'phone_number_id'=>'p'.$minutes,'access_token'=>'token','app_secret'=>'secret',
                'pause_override_minutes'=>$minutes,
            ])->assertSessionHasErrors('pause_override_minutes');
        }
    }

    private function context(string $slug): array
    {
        $user=User::factory()->create();
        $tenant=Tenant::create(['name'=>$slug,'slug'=>$slug,'status'=>'active','created_by'=>$user->id]);
        $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>$slug.'-main']);
        TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$user->id,'role'=>'owner','status'=>'active']);
        return [$user,$workspace,$tenant];
    }
}