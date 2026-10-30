<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\BusinessConnectorProfile;
use App\Models\ConnectorEndpoint;
use App\Models\TenantMembership;
use App\Models\Workspace;
use App\Support\UrlPolicy;
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
            'base_url' => ['nullable','string','max:500'],
            'api_key' => ['nullable','string','max:2000'],
        ], [], ['name'=>'اسم التكامل','base_url'=>'عنوان الخدمة','api_key'=>'مفتاح المشروع']);
        abort_unless($ws->projects()->whereKey($data['project_id'])->exists(), 422, 'المشروع لا يتبع هذه المساحة.');
        abort_unless(UrlPolicy::isValidBaseUrl($data['base_url']), 422, 'عنوان الخدمة يجب أن يكون HTTPS لعنوان عام فقط (يُرفض loopback/private/metadata).');

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

    public function show(Request $request, string $workspace, BusinessConnectorProfile $connector)
    {
        [$ws] = $this->context($request, $workspace);
        $this->guard($connector, $ws);
        return view('app.integrations.show', [
            'workspace' => $ws,
            'connector' => $connector,
            'endpoints' => $connector->endpoints()->latest()->get(),
        ]);
    }

    public function update(Request $request, string $workspace, BusinessConnectorProfile $connector)
    {
        [$ws] = $this->context($request, $workspace);
        $this->guard($connector, $ws);

        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'base_url' => ['nullable','string','max:500'],
            'api_key' => ['nullable','string','max:2000'],
        ], [], ['name'=>'اسم التكامل','base_url'=>'عنوان الخدمة','api_key'=>'مفتاح المشروع']);
        abort_unless(UrlPolicy::isValidBaseUrl($data['base_url']), 422, 'عنوان الخدمة يجب أن يكون HTTPS لعنوان عام فقط.');

        $update = ['name'=>$data['name'], 'base_url'=>$data['base_url']];
        if (! blank($data['api_key'])) {
            $update['encrypted_credentials'] = Crypt::encryptString(json_encode(['api_key'=>$data['api_key'], 'token_type'=>$connector->type], JSON_UNESCAPED_SLASHES));
        }
        $connector->update($update);

        return back()->with('status','تم تحديث التكامل.');
    }

    public function destroy(Request $request, string $workspace, BusinessConnectorProfile $connector)
    {
        [$ws] = $this->context($request, $workspace);
        $this->guard($connector, $ws);
        $connector->delete();
        return back()->with('status','تم حذف التكامل ونقاط نهايته.');
    }

    public function endpointStore(Request $request, string $workspace, BusinessConnectorProfile $connector)
    {
        [$ws] = $this->context($request, $workspace);
        $this->guard($connector, $ws);

        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'method' => ['required','in:GET,POST,PUT,PATCH,DELETE'],
            'path' => ['required','string','max:500'],
        ], [], ['name'=>'اسم النقطة','method'=>'الطريقة','path'=>'المسار']);
        abort_unless(UrlPolicy::isValidPath($data['path']), 422, 'المسار يجب أن يكون نسبيًا يبدأ بـ / بلا traversal أو بروتوكول.');

        ConnectorEndpoint::create(['tenant_id'=>$ws->tenant_id,'connector_profile_id'=>$connector->id,'name'=>$data['name'],'method'=>$data['method'],'path'=>$data['path'],'status'=>'draft']);
        return back()->with('status','أُضيفت نقطة النهاية كمسودة. لا يُنفَّذ أي طلب قبل تفويض وفحص SSRF عند التنفيذ.');
    }

    public function endpointDestroy(Request $request, string $workspace, BusinessConnectorProfile $connector, ConnectorEndpoint $endpoint)
    {
        [$ws] = $this->context($request, $workspace);
        $this->guard($connector, $ws);
        abort_unless($endpoint->connector_profile_id === $connector->id && $endpoint->tenant_id === $ws->tenant_id, 404);
        $endpoint->delete();
        return back()->with('status','حُذفت نقطة النهاية.');
    }

    private function guard(BusinessConnectorProfile $connector, Workspace $ws): void
    {
        abort_unless($connector->tenant_id === $ws->tenant_id && $connector->workspace_id === $ws->id, 404, 'التكامل لا ينتمي إلى هذه المساحة.');
    }

    private function context(Request $request, string $workspace): array
    {
        $ws=Workspace::where('slug',$workspace)->firstOrFail();
        $member=TenantMembership::where('user_id',$request->user()->id)
            ->where('tenant_id',$ws->tenant_id)->where('workspace_id',$ws->id)->where('status','active')->first();
        abort_if(!$member,403,'غير مصرح لك بالوصول إلى هذه المساحة.');
        return [$ws,$member];
    }
}