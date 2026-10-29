<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\WhatsappConnection;
use App\Models\WhatsappWebhookEvent;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class WhatsappWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_challenge_and_signed_event_are_accepted_once(): void
    {
        $connection=$this->connection();
        $secrets=['app_secret'=>'app-secret','verify_token'=>'verify-secret'];
        $connection->update(['encrypted_credentials'=>Crypt::encryptString(json_encode($secrets))]);
        $this->get('/api/webhooks/meta/whatsapp/'.$connection->public_id.'?hub.mode=subscribe&hub.verify_token=verify-secret&hub.challenge=abc123')->assertOk()->assertSee('abc123');
        $payload=['object'=>'whatsapp_business_account','entry'=>[['id'=>'entry-a','changes'=>[['value'=>['messages'=>[['id'=>'msg-a']]]]]]]];
        $raw=json_encode($payload, JSON_THROW_ON_ERROR);
        $signature='sha256='.hash_hmac('sha256',$raw,'app-secret');
        $this->withHeaders(['X-Hub-Signature-256'=>$signature])->postJson('/api/webhooks/meta/whatsapp/'.$connection->public_id,$payload)->assertStatus(202);
        $this->withHeaders(['X-Hub-Signature-256'=>$signature])->postJson('/api/webhooks/meta/whatsapp/'.$connection->public_id,$payload)->assertOk()->assertJsonPath('status','duplicate');
        $this->assertSame(1,WhatsappWebhookEvent::count());
    }

    public function test_bad_signature_and_foreign_public_id_are_rejected(): void
    {
        $connection=$this->connection();
        $connection->update(['encrypted_credentials'=>Crypt::encryptString(json_encode(['app_secret'=>'secret','verify_token'=>'verify']))]);
        $payload=['object'=>'x','entry'=>[['id'=>'entry','changes'=>[]]]];
        $this->withHeaders(['X-Hub-Signature-256'=>'sha256=bad'])->postJson('/api/webhooks/meta/whatsapp/'.$connection->public_id,$payload)->assertForbidden();
        $this->get('/api/webhooks/meta/whatsapp/not-this-connection?hub.mode=subscribe&hub.verify_token=verify&hub.challenge=x')->assertNotFound();
    }

    private function connection(): WhatsappConnection
    {
        $user=User::factory()->create(); $tenant=Tenant::create(['name'=>'T','slug'=>'tenant-webhook','status'=>'active','created_by'=>$user->id]); $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>'webhook-main']); TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$user->id,'role'=>'owner','status'=>'active']); return WhatsappConnection::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'label'=>'Test','public_id'=>'connection-test-'.str()->random(8),'method'=>'manual_cloud_api','state'=>'draft']);
    }
}