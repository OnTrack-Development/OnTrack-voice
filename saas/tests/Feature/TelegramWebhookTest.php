<?php

namespace Tests\Feature;

use App\Models\SocialConnection;
use App\Models\SocialWebhookEvent;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_telegram_update_creates_conversation_and_dedupes(): void
    {
        [$connection] = $this->connection();
        $connection->update(['encrypted_webhook_secret'=>Crypt::encryptString('telegram-secret')]);
        $payload=['update_id'=>1001,'message'=>['chat'=>['id'=>777],'text'=>'أهلاً من Telegram']];
        $uri='/api/webhooks/telegram/'.$connection->public_id;
        $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token'=>'telegram-secret'])->postJson($uri,$payload)->assertStatus(202)->assertJsonPath('status','accepted');
        $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token'=>'telegram-secret'])->postJson($uri,$payload)->assertOk()->assertJsonPath('status','duplicate');
        $this->assertSame(1,SocialWebhookEvent::count());
        $this->assertDatabaseHas('conversations',['tenant_id'=>$connection->tenant_id,'channel'=>'telegram']);
        $this->assertDatabaseHas('messages',['role'=>'customer','body'=>'أهلاً من Telegram']);
    }

    public function test_wrong_telegram_secret_is_rejected(): void
    {
        [$connection] = $this->connection();
        $connection->update(['encrypted_webhook_secret'=>Crypt::encryptString('right')]);
        $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token'=>'wrong'])->postJson('/api/webhooks/telegram/'.$connection->public_id,['update_id'=>1,'message'=>['chat'=>['id'=>1],'text'=>'x']])->assertForbidden();
    }

    private function connection(): array
    {
        $user=User::factory()->create(); $tenant=Tenant::create(['name'=>'T','slug'=>'telegram-test','status'=>'active','created_by'=>$user->id]); $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>'telegram-main']); TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$user->id,'role'=>'owner','status'=>'active']); $connection=SocialConnection::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'public_id'=>'telegram-test-conn','channel'=>'telegram','account_label'=>'Bot','state'=>'draft']); return [$connection];
    }
}