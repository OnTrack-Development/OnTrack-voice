<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\TenantMembership;
use App\Models\Workspace;
use App\Support\UsageLedger;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);
        $subscription = Subscription::where('tenant_id', $ws->tenant_id)->where('status', 'active')->latest('id')->with('plan.entitlements')->first();
        return view('app.plan.index', [
            'workspace' => $ws,
            'subscription' => $subscription,
            'ledger' => $subscription
                ? TenantUsageLedgerRepository::forTenant($ws->tenant_id)
                : collect(),
        ]);
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

final class TenantUsageLedgerRepository
{
    public static function forTenant(int $tenantId): \Illuminate\Support\Collection
    {
        return \App\Models\TenantUsageLedger::where('tenant_id', $tenantId)->latest('occurred_at')->limit(50)->get();
    }
}