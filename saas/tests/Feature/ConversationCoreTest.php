<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_reply_sets_timed_ai_pause_and_attributes_message(): void
    {
        [$user,$workspace,$tenant]= $this->context('alpha');
        $conversation=Conversation::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'contact_key'=>'contact-a','channel'=>'web','status'=>'open']);
        $this->actingAs($user)->post("/app/{$workspace->slug}/inbox/{$conversation->id}/reply",['body'=>'سأراجع طلبك الآن.'])->assertRedirect();
        $conversation->refresh();
        $this->assertTrue($conversation->isAiPaused());
        $this->assertNotNull($conversation->last_human_reply_at_utc);
        $this->assertSame('staff',Message::firstOrFail()->role);
        $this->assertSame(1,$conversation->pause_generation);
    }

    public function test_foreign_tenant_cannot_open_or_reply(): void
    {
        [$userA,$workspaceA,$tenantA]=$this->context('alpha');
        [$userB,$workspaceB,$tenantB]=$this->context('beta');
        $conversation=Conversation::create(['tenant_id'=>$tenantB->id,'workspace_id'=>$workspaceB->id,'contact_key'=>'contact-b','channel'=>'web','status'=>'open']);
        $this->actingAs($userA)->get("/app/{$workspaceB->slug}/inbox/{$conversation->id}")->assertForbidden();
        $this->actingAs($userA)->post("/app/{$workspaceB->slug}/inbox/{$conversation->id}/reply",['body'=>'تسلل'])->assertForbidden();
        $this->assertDatabaseCount('messages',0);
    }

    public function test_permanent_human_lock_does_not_expire_without_release(): void
    {
        [$user,$workspace,$tenant]=$this->context('alpha');
        $conversation=Conversation::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'contact_key'=>'contact-a','channel'=>'web','status'=>'open']);
        $this->actingAs($user)->post("/app/{$workspace->slug}/inbox/{$conversation->id}/lock")->assertRedirect();
        $conversation->refresh();
        $this->assertTrue($conversation->isHumanLocked());
        $this->actingAs($user)->post("/app/{$workspace->slug}/inbox/{$conversation->id}/release")->assertRedirect();
        $this->assertFalse($conversation->fresh()->isHumanLocked());
    }

    private function context(string $slug): array
    {
        $user=User::factory()->create(); $tenant=Tenant::create(['name'=>$slug,'slug'=>$slug,'status'=>'active','created_by'=>$user->id]); $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>$slug.'-main']); TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$user->id,'role'=>'owner','status'=>'active']); return [$user,$workspace,$tenant];
    }
}