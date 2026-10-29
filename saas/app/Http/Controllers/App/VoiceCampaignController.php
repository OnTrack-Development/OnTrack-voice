<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\TenantMembership;
use App\Models\VoiceCampaign;
use App\Models\Workspace;
use Illuminate\Http\Request;

class VoiceCampaignController extends Controller
{
    public function index(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);
        return view('app.voice.index', ['workspace'=>$ws,'campaigns'=>VoiceCampaign::where('tenant_id',$ws->tenant_id)->where('workspace_id',$ws->id)->withCount('entries')->latest()->get()]);
    }

    public function store(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);
        $data=$request->validate(['name'=>['required','string','max:160'],'timezone'=>['required','string','max:64'],'delay_seconds'=>['required','integer','min:0','max:86400'],'max_attempts'=>['required','integer','min:1','max:10']]);
        VoiceCampaign::create(['tenant_id'=>$ws->tenant_id,'workspace_id'=>$ws->id,'status'=>'draft',...$data]);
        return back()->with('status','تم حفظ مهمة الاتصال كمسودة. نقل المكالمات الحقيقي ما زال يحتاج إثبات جهاز/قناة.');
    }

    private function context(Request $request,string $workspace): array
    {
        $ws=Workspace::where('slug',$workspace)->firstOrFail(); $member=TenantMembership::where('user_id',$request->user()->id)->where('tenant_id',$ws->tenant_id)->where('status','active')->first(); abort_if(!$member,403,'غير مصرح لك بالوصول إلى هذه المساحة.'); return [$ws,$member];
    }
}