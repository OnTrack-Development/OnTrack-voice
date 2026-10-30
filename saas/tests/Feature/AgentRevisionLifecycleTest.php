<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentRevision;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentRevisionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_update_agent_create_sequential_revisions(): void
    {
        [$user,$workspace,$project] = $this->context('alpha');
        $this->actingAs($user)->post('/app/'.$workspace->slug.'/agents', ['name'=>'مساعد','kind'=>'support','project_id'=>$project->id,'instructions'=>'الأول'])->assertRedirect();
        $agent=Agent::firstOrFail();
        $this->assertDatabaseHas('agent_revisions',['agent_id'=>$agent->id,'version'=>1]);
        $this->actingAs($user)->put('/app/'.$workspace->slug.'/agents/'.$agent->id, ['name'=>'مساعد مطور','kind'=>'support','project_id'=>$project->id,'instructions'=>'الثاني'])->assertRedirect();
        $this->assertDatabaseHas('agent_revisions',['agent_id'=>$agent->id,'version'=>2,'instructions'=>'الثاني']);
        $this->assertSame(2,AgentRevision::where('agent_id',$agent->id)->count());
    }

    public function test_foreign_tenant_cannot_read_revisions_through_agent(): void
    {
        [, $workspaceA] = $this->context('alpha');
        [, $workspaceB,$projectB,$tenantB] = $this->context('beta');
        $agent=Agent::create(['tenant_id'=>$tenantB->id,'project_id'=>$projectB->id,'name'=>'B','kind'=>'custom','status'=>'draft']);
        $userA=User::factory()->create();
        $this->actingAs($userA)->get('/app/'.$workspaceA->slug.'/agents/'.$agent->id)->assertForbidden();
    }

    private function context(string $slug): array
    {
        $user=User::factory()->create(); $tenant=Tenant::create(['name'=>$slug,'slug'=>$slug,'status'=>'active','created_by'=>$user->id]); $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>$slug.'-main']); $project=Project::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'name'=>'P','slug'=>'p-'.$slug]); TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$user->id,'role'=>'owner','status'=>'active']); return [$user,$workspace,$project,$tenant];
    }
}