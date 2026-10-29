<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\BusinessConnectorProfile;
use App\Models\TenantMembership;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class IntegrationController extends Controller
{
    public function index(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);
        return view('app.integrations.index', [
            'workspace' => $ws,
            'connectors' => BusinessConnectorProfile::where('tenant_id', $ws->tenant_id)->where('workspace_id', $ws->id)->withCount('endpoints')->latest()->get(),
            'projects' => $ws->projects()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);
        $data = $request->validate([
            'project_id' => ['required','integer'],
            'type' => ['required','in:rest,laravel,wordpress,whmcs,custom'],
            'name' => ['required','string','max:120'],
            'base_url' => ['nullable','url','max:500'],
            'api_key' => ['nullable','string','max:2000'],
        ], [], ['name'=>'اسم التكامل','base_url'=>'عنوان الخدمة','api_key'=>'مفتاح المشروع']);
        abort_unless($ws->projects()->whereKey($data['project_id'])->exists(), 422, 'المشروع لا يتبع هذه المساحة.');

        $credentials = ['api_key' => $data['api_key'] ?? null, 'token_type' => $data['type']];
        $credentials = array_filter($credentials, fn ($v) => $v !== null);

        BusinessConnectorProfile::create([
            'tenant_id'=>$ws->tenant_id, 'workspace_id'=>$ws->id, 'project_id'=>$data['project_id'],
            'type'=>$data['type'], 'name'=>$data['name'], 'base_url'=>$data['base_url'],
            'encrypted_credentials'=>$credentials ? Crypt::encryptString(json_encode($credentials, JSON_UNESCAPED_SLASHES)) : null,
            'status'=>'draft',
        ]);

        return back()->with('status','تم حفظ تكامل المشروع كمسودة. لا تُنفَّذ أي URL/أوامر من نص العميل مباشرة.');
    }

    private function context(Request $request, string $workspace): array
    {
        $ws=Workspace::where('slug',$workspace)->firstOrFail();
        $member=TenantMembership::where('user_id',$request->user()->id)->where('tenant_id',$ws->tenant_id)->where('status','active')->first();
        abort_if(!$member,403,'غير مصرح لك بالوصول إلى هذه المساحة.');
        return [$ws,$member];
    }
}