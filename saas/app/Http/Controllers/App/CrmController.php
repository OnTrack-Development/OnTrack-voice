<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\TenantMembership;
use App\Models\Workspace;
use Illuminate\Http\Request;

class CrmController extends Controller
{
    public function index(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);
        return view('app.crm.index', [
            'workspace' => $ws,
            'contacts' => Contact::where('tenant_id', $ws->tenant_id)->latest()->paginate(25),
        ]);
    }

    public function store(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);
        $data = $request->validate([
            'display_name' => ['required','string','max:160'],
            'email' => ['nullable','email','max:190'],
            'phone_e164' => ['nullable','string','max:32'],
            'lead_status' => ['required','in:new,qualified,customer,unqualified'],
            'notes' => ['nullable','string','max:5000'],
        ]);
        Contact::create(['tenant_id'=>$ws->tenant_id, ...$data]);
        return back()->with('status', 'تم حفظ جهة الاتصال داخل شركتك.');
    }

    private function context(Request $request, string $workspace): array
    {
        $ws=Workspace::where('slug',$workspace)->firstOrFail();
        $member=TenantMembership::where('user_id',$request->user()->id)->where('tenant_id',$ws->tenant_id)->where('status','active')->first();
        abort_if(!$member,403,'غير مصرح لك بالوصول إلى هذه المساحة.');
        return [$ws,$member];
    }
}