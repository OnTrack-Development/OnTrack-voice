<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\TenantAutomationSetting;
use App\Models\TenantMembership;
use App\Models\Workspace;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);
        return view('app.settings', [
            'workspace' => $ws,
            'setting' => TenantAutomationSetting::firstOrCreate(
                ['tenant_id' => $ws->tenant_id],
                ['ai_reply_pause_minutes' => 15]
            ),
        ]);
    }

    public function update(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);
        $data = $request->validate([
            'ai_reply_pause_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ], [], ['ai_reply_pause_minutes' => 'مدة إيقاف الرد الآلي']);

        TenantAutomationSetting::updateOrCreate(
            ['tenant_id' => $ws->tenant_id],
            ['ai_reply_pause_minutes' => $data['ai_reply_pause_minutes']]
        );

        return back()->with('status', 'تم حفظ إعدادات التشغيل الآلي.');
    }

    private function context(Request $request, string $workspace): array
    {
        $ws = Workspace::where('slug', $workspace)->firstOrFail();
        $member = TenantMembership::where('user_id', $request->user()->id)
            ->where('tenant_id', $ws->tenant_id)->where('status', 'active')->first();
        abort_if(! $member, 403, 'غير مصرح لك بالوصول إلى هذه المساحة.');
        return [$ws, $member];
    }
}