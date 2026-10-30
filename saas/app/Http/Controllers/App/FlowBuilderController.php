<?php
namespace App\Http\Controllers\App;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentFlow;
use App\Models\AgentFlowEdge;
use App\Models\AgentFlowNode;
use App\Models\AgentFlowRevision;
use App\Models\TenantMembership;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class FlowBuilderController extends Controller
{
    public function show(Request $request,string $workspace,Agent $agent)
    {
        [$ws]=$this->context($request,$workspace); abort_unless($agent->tenant_id===$ws->tenant_id,404);
        $flow=AgentFlow::firstOrCreate(['tenant_id'=>$ws->tenant_id,'workspace_id'=>$ws->id,'project_id'=>$agent->project_id,'agent_id'=>$agent->id,'name'=>'المسار الرئيسي'],['status'=>'draft']);
        $revision=$flow->draft_revision_id?AgentFlowRevision::find($flow->draft_revision_id):null;
        if(!$revision){$revision=AgentFlowRevision::create(['tenant_id'=>$ws->tenant_id,'flow_id'=>$flow->id,'version'=>1,'status'=>'draft','created_by'=>$request->user()->id]);$flow->update(['draft_revision_id'=>$revision->id]);}
        return view('app.agents.flow-builder',['workspace'=>$ws,'agent'=>$agent,'flow'=>$flow,'revision'=>$revision->load(['nodes','edges.source','edges.target'])]);
    }
    public function save(Request $request,string $workspace,Agent $agent)
    {
        [$ws]=$this->context($request,$workspace); abort_unless($agent->tenant_id===$ws->tenant_id,404);
        $data=$request->validate(['flow_id'=>['required','integer'],'revision_id'=>['required','integer'],'nodes'=>['array'],'edges'=>['array']]);
        $flow=AgentFlow::where('id',$data['flow_id'])->where('tenant_id',$ws->tenant_id)->where('agent_id',$agent->id)->firstOrFail();
        $revision=AgentFlowRevision::where('id',$data['revision_id'])->where('flow_id',$flow->id)->firstOrFail();
        DB::transaction(function()use($data,$ws,$flow,$revision,$request){
            $revision->nodes()->delete(); $revision->edges()->delete();
            foreach($data['nodes']??[] as $node){$revision->nodes()->create(['tenant_id'=>$ws->tenant_id,'stable_key'=>$node['id'],'type'=>$node['type'],'title'=>$node['title']??$node['type'],'description'=>$node['description']??null,'position_x'=>$node['x']??0,'position_y'=>$node['y']??0,'config_json'=>$node['config']??[],'status'=>'valid']);}
            $map=$revision->nodes()->pluck('id','stable_key');
            foreach($data['edges']??[] as $edge){if(!$map->has($edge['source'])||!$map->has($edge['target']))abort(422,'انتقال يشير إلى عقدة غير موجودة.');$revision->edges()->create(['tenant_id'=>$ws->tenant_id,'source_node_id'=>$map[$edge['source']],'target_node_id'=>$map[$edge['target']],'flow_revision_id'=>$revision->id,'label'=>$edge['label']??null,'type'=>$edge['type']??'default','condition_json'=>$edge['condition']??null,'priority'=>$edge['priority']??100,'is_default'=>$edge['isDefault']??false]);}
        });
        return response()->json(['status'=>'saved','revision_id'=>$revision->id,'version'=>$revision->version]);
    }
    public function validateGraph(Request $request,string $workspace,Agent $agent)
    {
        [$ws]=$this->context($request,$workspace); abort_unless($agent->tenant_id===$ws->tenant_id,404);
        $data=$request->validate(['nodes'=>'array','edges'=>'array']); $nodes=collect($data['nodes']??[]); $ids=$nodes->pluck('id'); $errors=[];
        if($nodes->where('type','start')->count()!==1)$errors[]='يجب وجود عقدة Start واحدة بالضبط.';
        if($nodes->where('type','end')->count()<1)$errors[]='يجب وجود عقدة End واحدة على الأقل.';
        foreach($data['edges']??[] as $edge){if(!$ids->contains($edge['source']??null)||!$ids->contains($edge['target']??null))$errors[]='يوجد انتقال إلى عقدة غير موجودة.';}
        return response()->json(['valid'=>$errors===[],'errors'=>$errors]);
    }
    public function publish(Request $request,string $workspace,Agent $agent)
    {
        [$ws]=$this->context($request,$workspace); abort_unless($agent->tenant_id===$ws->tenant_id,404);
        $flow=AgentFlow::where('id',$request->input('flow_id'))->where('tenant_id',$ws->tenant_id)->where('agent_id',$agent->id)->firstOrFail(); $revision=AgentFlowRevision::findOrFail($flow->draft_revision_id); $errors=[]; if(!$revision->nodes()->where('type','start')->exists())$errors[]='Start مفقودة'; if(!$revision->nodes()->where('type','end')->exists())$errors[]='End مفقودة'; if($errors)return response()->json(['valid'=>false,'errors'=>$errors],422); $revision->update(['status'=>'published','published_at'=>now()]);$flow->update(['status'=>'published','published_revision_id'=>$revision->id]);return response()->json(['status'=>'published','revision_id'=>$revision->id]);
    }
    private function context(Request $request,string $workspace):array{$ws=Workspace::where('slug',$workspace)->firstOrFail();$member=TenantMembership::where('user_id',$request->user()->id)->where('tenant_id',$ws->tenant_id)->where('workspace_id',$ws->id)->where('status','active')->first();abort_if(!$member,403,'غير مصرح لك بالوصول إلى هذه المساحة.');return[$ws,$member];}
}