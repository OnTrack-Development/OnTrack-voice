<?php

namespace Tests\Feature;

use App\Models\SocialConnection;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class SocialChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_telegram_draft_is_encrypted_and_scoped_to_tenant(): void
    {
        [$user,$workspace,$tenant] = $this->context('alpha');
        $this->actingAs($user)->post('/app/'.$workspace->slug.'/channels/social', [
            'channel'=>'telegram','account_label'=>'بوت الدعم','bot_token'=>'12345:secret-bot-token',
        ])->assertRedirect();
        $connection=SocialConnection::firstOrFail();
        $this->assertSame('draft',$connection->state);
        $this->assertSame($tenant->id,$connection->tenant_id);
        $this->assertNotSame('12345:secret-bot-token',$connection->encrypted_config);
        $this->assertStringNotContainsString('secret-bot-token',$connection->encrypted_config);
    }

    public function test_unsupported_channel_and_foreign_workspace_are_rejected(): void
    {
        [$user,$workspace] = $this->context('alpha');
        $this->actingAs($user)->post('/app/'.$workspace->slug.'/channels/social', ['channel'=>'instagram_pro','account_label'=>'x','page_token'=>'t'])->assertStatus(422);
        [, $foreign] = $this->context('beta');
        $this->actingAs($user)->get('/app/'.$foreign->slug.'/channels/social')->assertForbidden();
        $this->assertDatabaseCount('social_connections',0);
    }

    public function test_widget_and_telegram_register_capabilities_truthfully(): void
    {
        $all = \App\Support\ChannelCapabilities::all();
        $this->assertTrue($all['telegram']['enabled']);
        $this->assertTrue($all['telegram']['capabilities']['send_private_dm']);
        $this->assertFalse($all['telegram']['capabilities']['delivery_status']);
        $this->assertFalse($all['facebook_messenger']['enabled']);
        $this->assertFalse($all['instagram_pro']['enabled']);
    }

    private function context(string $slug): array
    {
        $user=User::factory()->create(); $tenant=Tenant::create(['name'=>$slug,'slug'=>$slug,'status'=>'active','created_by'=>$user->id]); $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>$slug.'-main']); TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$user->id,'role'=>'owner','status'=>'active']); return [$user,$workspace,$tenant];
    }
}