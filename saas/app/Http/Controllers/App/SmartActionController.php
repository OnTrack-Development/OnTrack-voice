<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ButtonInvocation;
use App\Models\ButtonRule;
use App\Models\TenantMembership;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SmartActionController extends Controller
{
    public function index(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);
        return view('app.smart-actions.index', [
            'workspace' => $ws,
            'rules' => ButtonRule::where('tenant_id', $ws->tenant_id)->where('workspace_id', $ws->id)->latest()->get(),
        ]);
    }

    public function store(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);
        $data = $request->validate([
            'project_id' => ['required','integer'], 'label_ar' => ['required','string','max:160'],
            'action_key' => ['required','string','max:100','regex:/^[a-z][a-z0-9._-]*$/'],
            'min_identity_level' => ['required','in:guest,verified,staff'],
            'business_state_in' => ['nullable','string','max:1000'],
        ]);
        abort_unless($ws->projects()->whereKey($data['project_id'])->exists(), 422, 'المشروع لا يتبع هذه المساحة.');
        $businessStateIn = $data['business_state_in'] ?? null;
        $states = $businessStateIn ? array_values(array_filter(array_map('trim', explode(',', $businessStateIn)))) : [];
        ButtonRule::create([
            'tenant_id' => $ws->tenant_id, 'workspace_id' => $ws->id, 'project_id' => $data['project_id'],
            'label_ar' => $data['label_ar'], 'action_key' => $data['action_key'],
            'min_identity_level' => $data['min_identity_level'], 'business_state_in' => $states,
            'condition_ast' => ['version' => 1, 'all' => []], 'status' => 'draft',
        ]);
        return back()->with('status', 'تم حفظ قاعدة الزر كمسودة. لا تُنفذ الأفعال من نص العميل أو النموذج مباشرة.');
    }

    public function invoke(Request $request, string $workspace, ButtonRule $rule)
    {
        [$ws] = $this->context($request, $workspace);
        abort_unless($rule->tenant_id === $ws->tenant_id && $rule->workspace_id === $ws->id, 404);
        $data = $request->validate(['idempotency_key' => ['required','string','max:160'], 'state_revision' => ['required','string','max:120']]);
        $existing = ButtonInvocation::where('tenant_id', $ws->tenant_id)->where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing) return response()->json(['status' => 'duplicate', 'invocation_id' => $existing->id]);
        $invocation = ButtonInvocation::create(['tenant_id' => $ws->tenant_id,'button_rule_id' => $rule->id,'idempotency_key' => $data['idempotency_key'],'state_revision' => $data['state_revision'],'status' => 'accepted','result_summary' => 'Accepted for verified backend execution.']);
        return response()->json(['status' => 'accepted', 'invocation_id' => $invocation->id], 202);
    }

    private function context(Request $request, string $workspace): array
    {
        $ws = Workspace::where('slug', $workspace)->firstOrFail();
        $member = TenantMembership::where('user_id', $request->user()->id)->where('tenant_id', $ws->tenant_id)->where('status','active')->first();
        abort_if(! $member, 403, 'غير مصرح لك بالوصول إلى هذه المساحة.');
        return [$ws, $member];
    }
}