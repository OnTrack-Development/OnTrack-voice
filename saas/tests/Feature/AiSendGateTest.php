<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageOutbox;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Workspace;
use App\Support\AiSendGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiSendGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbox_idempotency_creates_one_ai_message(): void
    {
        [,,$tenant,$conversation] = $this->context('alpha');
        $row=MessageOutbox::create(['tenant_id'=>$tenant->id,'conversation_id'=>$conversation->id,'kind'=>'ai_reply','idempotency_key'=>'idem-1','body'=>'رد تجريبي','scheduled_at'=>now()]);
        $first=AiSendGate::processDue($row);
        $second=AiSendGate::processDue($row->fresh());
        $this->assertSame('sent',$first['status']);
        $this->assertSame('duplicate',$second['status']);
        $this->assertSame(1,Message::where('role','ai')->count());
    }

    public function test_human_lock_and_timed_pause_block_final_send(): void
    {
        [,,$tenant,$conversation] = $this->context('alpha');
        $conversation->update(['human_locked_at'=>now()]);
        $row=MessageOutbox::create(['tenant_id'=>$tenant->id,'conversation_id'=>$conversation->id,'idempotency_key'=>'lock-1','body'=>'لا ترسل','scheduled_at'=>now()]);
        $this->assertSame('blocked_human_locked',AiSendGate::processDue($row)['status']);
        $conversation->update(['human_locked_at'=>null,'ai_pause_until_utc'=>now()->addMinutes(15)]);
        $row2=MessageOutbox::create(['tenant_id'=>$tenant->id,'conversation_id'=>$conversation->id,'idempotency_key'=>'pause-1','body'=>'ليس الآن','scheduled_at'=>now()]);
        $this->assertSame('blocked_ai_paused',AiSendGate::processDue($row2)['status']);
    }

    public function test_unknown_channel_is_blocked(): void
    {
        [,,$tenant,$conversation] = $this->context('alpha');
        $conversation->update(['channel'=>'unknown']);
        $row=MessageOutbox::create(['tenant_id'=>$tenant->id,'conversation_id'=>$conversation->id,'idempotency_key'=>'channel-1','body'=>'blocked','scheduled_at'=>now()]);
        $this->assertSame('blocked_channel_unavailable',AiSendGate::processDue($row)['status']);
    }

    private function context(string $slug): array
    {
        $user=User::factory()->create(); $tenant=Tenant::create(['name'=>$slug,'slug'=>$slug,'status'=>'active','created_by'=>$user->id]); $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>$slug.'-main']); TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$user->id,'role'=>'owner','status'=>'active']); $conversation=Conversation::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'contact_key'=>'contact','channel'=>'web','status'=>'open']); return [$user,$workspace,$tenant,$conversation];
    }
}