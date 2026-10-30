<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_invites_existing_user_and_invitee_accepts(): void
    {
        [$owner,$workspace,$tenant] = $this->context('alpha');
        $invitee=User::factory()->create(['email'=>'invitee@example.com']);
        $this->actingAs($owner)->post('/app/'.$workspace->slug.'/team/invite',['email'=>$invitee->email,'role'=>'member'])->assertRedirect();
        $invitation=TenantInvitation::firstOrFail();
        $this->actingAs($invitee)->get('/app/invitations/'.$invitation->id.'/accept')->assertRedirect();
        $this->assertDatabaseHas('tenant_memberships',['tenant_id'=>$tenant->id,'user_id'=>$invitee->id,'role'=>'member','status'=>'active']);
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_non_owner_cannot_invite_or_change_roles(): void
    {
        [$owner,$workspace,$tenant] = $this->context('alpha');
        $member=User::factory()->create();
        TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$member->id,'role'=>'member','status'=>'active']);
        $this->actingAs($member)->post('/app/'.$workspace->slug.'/team/invite',['email'=>'other@example.com','role'=>'member'])->assertForbidden();
        $membership=TenantMembership::where('user_id',$member->id)->firstOrFail();
        $this->actingAs($member)->post('/app/'.$workspace->slug.'/team/'.$membership->id.'/role',['role'=>'admin'])->assertForbidden();
    }

    public function test_last_owner_cannot_be_removed(): void
    {
        [$owner,$workspace] = $this->context('alpha');
        $membership=TenantMembership::where('user_id',$owner->id)->firstOrFail();
        $this->actingAs($owner)->post('/app/'.$workspace->slug.'/team/'.$membership->id.'/remove')->assertStatus(422);
    }

    private function context(string $slug): array
    {
        $owner=User::factory()->create(); $tenant=Tenant::create(['name'=>$slug,'slug'=>$slug,'status'=>'active','created_by'=>$owner->id]); $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>$slug.'-main']); TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$owner->id,'role'=>'owner','status'=>'active']); return [$owner,$workspace,$tenant];
    }
}