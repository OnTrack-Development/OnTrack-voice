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

class AgentRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_revisions_are_versioned_and_tenant_scoped(): void
    {
        [$user,$workspace,$tenant]=$this->context('alpha');
        $project=Project::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'name'=>'P','slug'=>'p-alpha']);
        $agent=Agent::create(['tenant_id'=>$tenant->id,'project_id'=>$project->id,'name'=>'A','kind'=>'support','status'=>'draft','instructions'=>'v1']);
        $revision=AgentRevision::create(['tenant_id'=>$tenant->id,'agent_id'=>$agent->id,'version'=>1,'name'=>$agent->name,'kind'=>$agent->kind,'instructions'=>$agent->instructions,'created_by'=>$user->id]);
        $this->assertSame(1,$revision->version);
        $this->assertSame($tenant->id,$revision->agent->tenant_id);
    }

    public function test_foreign_tenant_revision_not_visible_through_agent_relation(): void
    {
        [, , $tenantA]=$this->context('alpha'); [, , $tenantB]=$this->context('beta');
        $project=Project::create(['tenant_id'=>$tenantB->id,'workspace_id'=>Workspace::where('tenant_id',$tenantB->id)->first()->id,'name'=>'P','slug'=>'p-beta']);
        $agent=Agent::create(['tenant_id'=>$tenantB->id,'project_id'=>$project->id,'name'=>'B','kind'=>'custom','status'=>'draft']);
        AgentRevision::create(['tenant_id'=>$tenantB->id,'agent_id'=>$agent->id,'version'=>1,'name'=>'B','kind'=>'custom','created_by'=>null]);
        $this->assertSame(0,AgentRevision::where('tenant_id',$tenantA->id)->count());
        $this->assertSame(1,$agent->revisions()->count());
    }

    private function context(string $slug): array
    {
        $user=User::factory()->create(); $tenant=Tenant::create(['name'=>$slug,'slug'=>$slug,'status'=>'active','created_by'=>$user->id]); $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>$slug.'-main']); TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$user->id,'role'=>'owner','status'=>'active']); return [$user,$workspace,$tenant];
    }
}