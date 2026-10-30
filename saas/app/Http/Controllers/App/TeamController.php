<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index(Request $request, string $workspace)
    {
        [$ws, $membership] = $this->context($request, $workspace);
        return view('app.team.index', [
            'workspace' => $ws,
            'membership' => $membership,
            'members' => TenantMembership::with('user')->where('tenant_id', $ws->tenant_id)->where('workspace_id', $ws->id)->get(),
            'invitations' => TenantInvitation::where('tenant_id', $ws->tenant_id)->where('workspace_id', $ws->id)->latest()->get(),
        ]);
    }

    public function invite(Request $request, string $workspace)
    {
        [$ws, $membership] = $this->context($request, $workspace);
        $this->authorizeOwner($membership);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'role' => ['required', Rule::in(['owner', 'admin', 'member'])],
        ], [], ['email' => 'البريد', 'role' => 'الدور']);

        $exists = TenantMembership::where('tenant_id', $ws->tenant_id)
            ->whereHas('user', fn ($q) => $q->where('email', $data['email']))->exists();
        if ($exists) {
            return back()->with('status', 'هذا المستخدم عضو بالفعل في الشركة.');
        }

        $invitation = TenantInvitation::updateOrCreate(
            [
                'tenant_id' => $ws->tenant_id,
                'workspace_id' => $ws->id,
                'email' => $data['email'],
            ],
            [
                'role' => $data['role'],
                'token_hash' => hash('sha256', $token = Str::random(48)),
                'expires_at' => now()->addDays(7),
                'accepted_at' => null,
                'created_by' => $request->user()->id,
            ]
        );

        return back()->with('status', 'أُرسلت دعوة. رابط القبول: '.route('app.team.accept', ['invitation' => $invitation->id], false));
    }

    public function accept(Request $request, TenantInvitation $invitation)
    {
        abort_if(! $invitation->isPending(), 410, 'الدعوة منتهية أو مستخدمة.');
        abort_unless(strtolower($request->user()->email) === strtolower($invitation->email), 403, 'الدعوة موجهة لبريد آخر.');

        $membership = TenantMembership::firstOrCreate(
            ['tenant_id' => $invitation->tenant_id, 'user_id' => $request->user()->id],
            ['workspace_id' => $invitation->workspace_id, 'role' => $invitation->role, 'status' => 'active']
        );

        $invitation->update(['accepted_at' => now()]);

        return redirect()->route('app.overview', ['workspace' => $invitation->workspace->slug])
            ->with('status', 'انضممت إلى '.$invitation->workspace->name.' بدور '.$membership->role.'.');
    }

    public function updateRole(Request $request, string $workspace, TenantMembership $membership)
    {
        [$ws, $grant] = $this->context($request, $workspace);
        $this->authorizeOwner($grant);
        abort_unless($membership->tenant_id === $ws->tenant_id, 404);

        $membership->update([
            'role' => $request->validate(['role' => ['required', Rule::in(['owner', 'admin', 'member'])]], [], ['role' => 'الدور'])['role'],
        ]);

        return back()->with('status', 'تم تحديث دور العضو.');
    }

    public function remove(Request $request, string $workspace, TenantMembership $membership)
    {
        [$ws, $grant] = $this->context($request, $workspace);
        $this->authorizeOwner($grant);
        abort_unless($membership->tenant_id === $ws->tenant_id, 404);

        $owners = TenantMembership::where('tenant_id', $ws->tenant_id)->where('role', 'owner')->count();
        abort_if($membership->role === 'owner' && $owners <= 1, 422, 'لا يمكن إزالة المالك الوحيد للشركة.');

        $membership->delete();

        return back()->with('status', 'تمت إزالة العضو من الشركة.');
    }

    private function authorizeOwner(TenantMembership $membership): void
    {
        abort_unless($membership->role === 'owner', 403, 'إدارة الفريق متاحة لمالك الشركة فقط.');
    }

    private function context(Request $request, string $workspace): array
    {
        $ws = Workspace::where('slug', $workspace)->firstOrFail();
        $membership = TenantMembership::where('user_id', $request->user()->id)
            ->where('tenant_id', $ws->tenant_id)->where('status', 'active')->first();
        abort_if(! $membership, 403, 'غير مصرح لك بالوصول إلى هذه المساحة.');
        return [$ws, $membership];
    }
}