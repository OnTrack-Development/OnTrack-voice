<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\TenantMembership;
use App\Models\WhatsappConnection;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class WhatsappConnectionController extends Controller
{
    public function index(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);
        return view('app.channels.whatsapp.index', ['workspace'=>$ws, 'connections'=>WhatsappConnection::where('tenant_id',$ws->tenant_id)->where('workspace_id',$ws->id)->latest()->get()]);
    }

    public function store(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);
        $data=$request->validate([
            'label'=>['required','string','max:120'],
            'method'=>['required','in:embedded_signup,coexistence,manual_cloud_api'],
            'phone_e164'=>['nullable','string','max:32'],
            'waba_id'=>['required_if:method,manual_cloud_api','nullable','string','max:120'],
            'phone_number_id'=>['required_if:method,manual_cloud_api','nullable','string','max:120'],
            'access_token'=>['required_if:method,manual_cloud_api','nullable','string','max:2000'],
            'app_id'=>['nullable','string','max:120'],
            'app_secret'=>['required_if:method,manual_cloud_api','nullable','string','max:2000'],
            'pause_override_minutes'=>['nullable','integer','min:1','max:1440'],
        ]);
        if ($data['method'] !== 'manual_cloud_api') {
            $state='draft'; $reason=$data['method']==='coexistence' ? 'Eligibility must be verified by Meta before activation.' : 'Official Meta callback and token exchange are pending.';
        } else {
            $state='draft'; $reason='Credentials saved as draft; metadata, webhook and send/receive tests are required.';
        }
        $secrets=array_filter(['access_token'=>$data['access_token']??null,'app_id'=>$data['app_id']??null,'app_secret'=>$data['app_secret']??null]);
        WhatsappConnection::create(['tenant_id'=>$ws->tenant_id,'workspace_id'=>$ws->id,'label'=>$data['label'],'public_id'=>Str::uuid()->toString(),'method'=>$data['method'],'phone_e164'=>$data['phone_e164']??null,'waba_id'=>$data['waba_id']??null,'phone_number_id'=>$data['phone_number_id']??null,'encrypted_credentials'=>$secrets?Crypt::encryptString(json_encode($secrets)):null,'state'=>$state,'pause_override_minutes'=>$data['pause_override_minutes']??null,'status_reason_redacted'=>$reason]);
        return back()->with('status','تم حفظ اتصال WhatsApp كمسودة. لا توجد حالة اتصال حي قبل أدلة Meta الفعلية.');
    }

    private function context(Request $request,string $workspace): array
    {
        $ws=Workspace::where('slug',$workspace)->firstOrFail();
        $member=TenantMembership::where('user_id',$request->user()->id)->where('tenant_id',$ws->tenant_id)->where('status','active')->first();
        abort_if(!$member,403,'غير مصرح لك بالوصول إلى هذه المساحة.');
        return [$ws,$member];
    }
}