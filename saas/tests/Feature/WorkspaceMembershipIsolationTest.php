<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceMembershipIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_of_one_workspace_cannot_enter_sibling_workspace_same_tenant(): void
    {
        $owner = User::factory()->create();
        $theta = Tenant::create(['name'=>'Theta','slug'=>'theta','status'=>'active','created_by'=>$owner->id]);
        $wsOne = Workspace::create(['tenant_id'=>$theta->id,'name'=>'One','slug'=>'theta-one']);
        $wsTwo = Workspace::create(['tenant_id'=>$theta->id,'name'=>'Two','slug'=>'theta-two']);
        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();
        TenantMembership::create(['tenant_id'=>$theta->id,'workspace_id'=>$wsOne->id,'user_id'=>$userOne->id,'role'=>'owner','status'=>'active']);
        TenantMembership::create(['tenant_id'=>$theta->id,'workspace_id'=>$wsTwo->id,'user_id'=>$userTwo->id,'role'=>'owner','status'=>'active']);

        $this->actingAs($userOne)->get('/app/'.$wsTwo->slug.'/overview')->assertForbidden();
        $this->actingAs($userTwo)->get('/app/'.$wsOne->slug.'/overview')->assertForbidden();
        $this->actingAs($userOne)->get('/app/'.$wsOne->slug.'/overview')->assertOk();
    }
}