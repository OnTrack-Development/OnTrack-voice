<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
        $token='secure-token-'.$invitation->id;
        $invitation->update(['token_hash'=>hash('sha256',$token)]);
        $this->actingAs($invitee)->get('/app/invitations/'.$invitation->id.'/'.$token)->assertOk()->assertSee('قبول الدعوة');
        $this->actingAs($invitee)->post('/app/invitations/'.$invitation->id.'/'.$token.'/accept')->assertRedirect();
        $this->assertDatabaseHas('tenant_memberships',['tenant_id'=>$tenant->id,'user_id'=>$invitee->id,'role'=>'member','status'=>'active']);
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_invalid_token_and_wrong_email_cannot_accept(): void
    {
        [$owner,$workspace] = $this->context('alpha');
        $invitee=User::factory()->create(['email'=>'invitee@example.com']);
        $this->actingAs($owner)->post('/app/'.$workspace->slug.'/team/invite',['email'=>$invitee->email,'role'=>'member'])->assertRedirect();
        $invitation=TenantInvitation::firstOrFail();
        $invitation->update(['token_hash'=>hash('sha256','the-real-token')]);
        $this->actingAs($invitee)->get('/app/invitations/'.$invitation->id.'/wrong-token')->assertStatus(410);
        $this->actingAs($invitee)->post('/app/invitations/'.$invitation->id.'/the-real-token/accept')->assertRedirect();
        $other=User::factory()->create(['email'=>'other@example.com']);
        $invitation2=TenantInvitation::create(['tenant_id'=>$invitation->tenant_id,'workspace_id'=>$invitation->workspace_id,'email'=>'other2@example.com','role'=>'member','token_hash'=>hash('sha256','tok'),'expires_at'=>now()->addDay(),'created_by'=>$owner->id]);
        $this->actingAs($other)->post('/app/invitations/'.$invitation2->id.'/tok/accept')->assertForbidden();
    }

    public function test_admin_can_manage_members_but_not_admin_roles(): void
    {
        [$owner,$workspace,$tenant] = $this->context('alpha');
        $admin=User::factory()->create();
        TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$admin->id,'role'=>'admin','status'=>'active']);
        $member=User::factory()->create(['email'=>'managed@example.com']);
        $this->actingAs($admin)->post('/app/'.$workspace->slug.'/team/invite',['email'=>$member->email,'role'=>'member'])->assertRedirect();
        $this->assertDatabaseHas('tenant_invitations',['email'=>$member->email,'role'=>'member']);
        $membership=TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$member->id,'role'=>'member','status'=>'active']);
        $this->actingAs($admin)->post('/app/'.$workspace->slug.'/team/'.$membership->id.'/role',['role'=>'admin'])->assertForbidden();
        $this->actingAs($admin)->post('/app/'.$workspace->slug.'/team/'.$membership->id.'/role',['role'=>'member'])->assertRedirect();
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

    public function test_last_owner_cannot_be_removed_or_demoted(): void
    {
        [$owner,$workspace] = $this->context('alpha');
        $membership=TenantMembership::where('user_id',$owner->id)->firstOrFail();
        $this->actingAs($owner)->post('/app/'.$workspace->slug.'/team/'.$membership->id.'/remove')->assertStatus(422);
        $this->actingAs($owner)->post('/app/'.$workspace->slug.'/team/'.$membership->id.'/role',['role'=>'member'])->assertStatus(422);
    }

    private function context(string $slug): array
    {
        $owner=User::factory()->create(); $tenant=Tenant::create(['name'=>$slug,'slug'=>$slug,'status'=>'active','created_by'=>$owner->id]); $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>$slug.'-main']); TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$owner->id,'role'=>'owner','status'=>'active']); return [$owner,$workspace,$tenant];
    }
}