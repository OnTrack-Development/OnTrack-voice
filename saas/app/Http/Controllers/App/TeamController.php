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
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'role' => ['required', Rule::in(['owner', 'admin', 'member'])],
        ], [], ['email' => 'البريد', 'role' => 'الدور']);

        // Owner may invite any role; admin may invite member only.
        if ($membership->role === 'admin' && $data['role'] !== 'member') {
            abort(403, 'المشرف يدعو أعضاء فقط؛ تغيير الأدوار الإدارية لمالك الشركة.');
        }
        $this->authorizeAdminOrOwner($membership);

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

        return back()->with('status', 'أُرسلت دعوة. رابط القبول: '.route('app.team.invitation', ['invitation' => $invitation->id, 'token' => $token], false));
    }

    public function showInvitation(Request $request, TenantInvitation $invitation, string $token)
    {
        abort_if(! $this->validInvitation($invitation, $token), 410, 'الدعوة منتهية أو غير صالحة.');
        return view('app.team.accept', ['invitation' => $invitation, 'token' => $token]);
    }

    public function accept(Request $request, TenantInvitation $invitation, string $token)
    {
        abort_if(! $this->validInvitation($invitation, $token), 410, 'الدعوة منتهية أو غير صالحة.');
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
        abort_unless($membership->tenant_id === $ws->tenant_id && $membership->workspace_id === $ws->id, 404);

        $newRole = $request->validate(['role' => ['required', Rule::in(['owner', 'admin', 'member'])]], [], ['role' => 'الدور'])['role'];
        if ($grant->role === 'admin') {
            abort_unless($newRole === 'member', 403, 'المشرف يعدّل أعضاءً فقط؛ الإدارة لمالك الشركة.');
        }
        $this->authorizeAdminOrOwner($grant);
        if ($membership->role === 'owner' && $newRole !== 'owner') {
            $owners = TenantMembership::where('tenant_id', $ws->tenant_id)->where('workspace_id', $ws->id)->where('role', 'owner')->count();
            abort_if($owners <= 1, 422, 'لا يمكن تحويل المالك الوحيد إلى دور أقل.');
        }
        $membership->update(['role' => $newRole]);

        return back()->with('status', 'تم تحديث دور العضو.');
    }

    public function remove(Request $request, string $workspace, TenantMembership $membership)
    {
        [$ws, $grant] = $this->context($request, $workspace);
        abort_unless($membership->tenant_id === $ws->tenant_id && $membership->workspace_id === $ws->id, 404);
        if ($grant->role === 'admin') {
            abort_unless($membership->role === 'member', 403, 'المشرف يزيل أعضاءً فقط؛ إدارة المالك والمشرفين لمالك الشركة.');
        }
        $this->authorizeAdminOrOwner($grant);

        $owners = TenantMembership::where('tenant_id', $ws->tenant_id)->where('role', 'owner')->count();
        abort_if($membership->role === 'owner' && $owners <= 1, 422, 'لا يمكن إزالة المالك الوحيد للشركة.');

        $membership->delete();

        return back()->with('status', 'تمت إزالة العضو من الشركة.');
    }

    private function validInvitation(TenantInvitation $invitation, string $token): bool
    {
        return $invitation->isPending() && hash_equals($invitation->token_hash, hash('sha256', $token));
    }

    private function authorizeOwner(TenantMembership $membership): void
    {
        abort_unless($membership->role === 'owner', 403, 'إدارة الفريق متاحة لمالك الشركة فقط.');
    }

    private function authorizeAdminOrOwner(TenantMembership $membership): void
    {
        abort_unless(in_array($membership->role, ['owner', 'admin'], true), 403, 'إدارة الفريق متاحة للمالك أو مشرف الشركة.');
    }

    private function context(Request $request, string $workspace): array
    {
        $ws = Workspace::where('slug', $workspace)->firstOrFail();
        $membership = TenantMembership::where('user_id', $request->user()->id)
            ->where('tenant_id', $ws->tenant_id)->where('workspace_id', $ws->id)->where('status', 'active')->first();
        abort_if(! $membership, 403, 'غير مصرح لك بالوصول إلى هذه المساحة.');
        return [$ws, $membership];
    }
}