<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\WhatsappConnection;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_connection_is_encrypted_and_starts_as_draft(): void
    {
        [$user,$workspace,$tenant]=$this->context('alpha');
        $this->actingAs($user)->post("/app/{$workspace->slug}/channels/whatsapp",['label'=>'الدعم','method'=>'manual_cloud_api','waba_id'=>'waba-a','phone_number_id'=>'phone-a','access_token'=>'secret-token','app_id'=>'app-a','app_secret'=>'app-secret'])->assertRedirect();
        $connection=WhatsappConnection::firstOrFail();
        $this->assertSame('draft',$connection->state);
        $this->assertNotSame('secret-token',$connection->encrypted_credentials);
        $this->assertStringNotContainsString('secret-token',$connection->encrypted_credentials);
        $this->assertSame($tenant->id,$connection->tenant_id);
    }

    public function test_all_three_methods_are_persisted_without_fake_active_state(): void
    {
        [$user,$workspace]= $this->context('alpha');
        foreach(['embedded_signup','coexistence','manual_cloud_api'] as $i=>$method){
            $payload=['label'=>$method,'method'=>$method];
            if($method==='manual_cloud_api') $payload=array_merge($payload,['waba_id'=>'w'.$i,'phone_number_id'=>'p'.$i,'access_token'=>'tok'.$i,'app_secret'=>'sec'.$i]);
            $this->actingAs($user)->post("/app/{$workspace->slug}/channels/whatsapp",$payload)->assertRedirect();
        }
        $this->assertSame(3,WhatsappConnection::count());
        $this->assertSame(3,WhatsappConnection::where('state','draft')->count());
    }

    public function test_foreign_tenant_cannot_list_or_create_connection(): void
    {
        [$userA,$workspaceA]= $this->context('alpha');
        [, $workspaceB]= $this->context('beta');
        $this->actingAs($userA)->get("/app/{$workspaceB->slug}/channels/whatsapp")->assertForbidden();
        $this->actingAs($userA)->post("/app/{$workspaceB->slug}/channels/whatsapp",['label'=>'x','method'=>'embedded_signup'])->assertForbidden();
        $this->assertDatabaseCount('whatsapp_connections',0);
    }

    private function context(string $slug): array
    {
        $user=User::factory()->create(); $tenant=Tenant::create(['name'=>$slug,'slug'=>$slug,'status'=>'active','created_by'=>$user->id]); $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>$slug.'-main']); TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$user->id,'role'=>'owner','status'=>'active']); return [$user,$workspace,$tenant];
    }
}