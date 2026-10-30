<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentFlow;
use App\Models\AgentFlowEdge;
use App\Models\AgentFlowNode;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlowBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_flow_builder_creates_default_graph_and_saves_nodes_edges(): void
    {
        [$user,$workspace,$project,$agent] = $this->context('alpha');
        $this->actingAs($user)->get('/app/'.$workspace->slug.'/agents/'.$agent->id.'/flow')->assertOk()->assertSee('Flow Builder');
        $flow=AgentFlow::firstOrFail(); $revision=$flow->revisions()->firstOrFail();
        $response=$this->actingAs($user)->postJson('/app/'.$workspace->slug.'/agents/'.$agent->id.'/flow/save',[
            'flow_id'=>$flow->id,'revision_id'=>$revision->id,
            'nodes'=>[
                ['id'=>'start','type'=>'start','title'=>'بداية','x'=>100,'y'=>100],
                ['id'=>'end','type'=>'end','title'=>'نهاية','x'=>100,'y'=>300],
            ],
            'edges'=>[['source'=>'start','target'=>'end','label'=>'الافتراضي']],
        ]);
        $response->assertOk()->assertJsonPath('status','saved');
        $this->assertSame(2,AgentFlowNode::count()); $this->assertSame(1,AgentFlowEdge::count());
    }

    public function test_invalid_graph_cannot_publish_and_valid_graph_can(): void
    {
        [$user,$workspace,,$agent] = $this->context('alpha');
        $this->actingAs($user)->get('/app/'.$workspace->slug.'/agents/'.$agent->id.'/flow'); $flow=AgentFlow::firstOrFail();
        $this->actingAs($user)->postJson('/app/'.$workspace->slug.'/agents/'.$agent->id.'/flow/validate',['nodes'=>[['id'=>'start','type'=>'start']],'edges'=>[]])->assertJsonPath('valid',false);
        $this->actingAs($user)->postJson('/app/'.$workspace->slug.'/agents/'.$agent->id.'/flow/save',['flow_id'=>$flow->id,'revision_id'=>$flow->draft_revision_id,'nodes'=>[['id'=>'start','type'=>'start'],['id'=>'end','type'=>'end']],'edges'=>[['source'=>'start','target'=>'end']]])->assertOk();
        $this->actingAs($user)->postJson('/app/'.$workspace->slug.'/agents/'.$agent->id.'/flow/publish',['flow_id'=>$flow->id])->assertOk()->assertJsonPath('status','published');
        $this->assertSame('published',$flow->fresh()->status);
    }

    public function test_foreign_tenant_cannot_open_flow(): void
    {
        [$user,$workspace] = $this->context('alpha'); [, $foreignWorkspace,, $foreignAgent] = $this->context('beta');
        $this->actingAs($user)->get('/app/'.$foreignWorkspace->slug.'/agents/'.$foreignAgent->id.'/flow')->assertForbidden();
    }

    private function context(string $slug): array
    {
        $user=User::factory()->create(); $tenant=Tenant::create(['name'=>$slug,'slug'=>$slug,'status'=>'active','created_by'=>$user->id]); $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>$slug.'-main']); $project=Project::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'name'=>'P','slug'=>'p-'.$slug]); $agent=Agent::create(['tenant_id'=>$tenant->id,'project_id'=>$project->id,'name'=>'Agent','kind'=>'support','status'=>'draft']); TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$user->id,'role'=>'owner','status'=>'active']); return [$user,$workspace,$project,$agent];
    }
}