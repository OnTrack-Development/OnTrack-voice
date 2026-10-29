<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\ButtonInvocation;
use App\Models\Conversation;
use App\Models\KnowledgeSource;
use App\Models\TenantAuditEvent;
use App\Models\TenantMembership;
use App\Models\TenantUsageLedger;
use App\Models\Workspace;
use Illuminate\Http\Request;

class OperationsController extends Controller
{
    public function index(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);
        $tenantId = $ws->tenant_id;

        return view('app.operations.index', [
            'workspace' => $ws,
            'counts' => [
                'agents' => Agent::where('tenant_id', $tenantId)->count(),
                'published_agents' => Agent::where('tenant_id', $tenantId)->where('status', 'published')->count(),
                'conversations' => Conversation::where('tenant_id', $tenantId)->count(),
                'open_conversations' => Conversation::where('tenant_id', $tenantId)->where('status', 'open')->count(),
                'human_locked' => Conversation::where('tenant_id', $tenantId)->whereNotNull('human_locked_at')->count(),
                'knowledge_sources' => KnowledgeSource::where('tenant_id', $tenantId)->count(),
                'button_invocations' => ButtonInvocation::where('tenant_id', $tenantId)->count(),
                'usage_units_month' => (int) TenantUsageLedger::where('tenant_id', $tenantId)
                    ->where('occurred_at', '>=', now()->startOfMonth())->sum('units'),
            ],
            'recentAudit' => TenantAuditEvent::where('tenant_id', $tenantId)->latest('occurred_at')->limit(15)->get(),
        ]);
    }

    private function context(Request $request, string $workspace): array
    {
        $ws=Workspace::where('slug',$workspace)->firstOrFail();
        $member=TenantMembership::where('user_id',$request->user()->id)->where('tenant_id',$ws->tenant_id)->where('status','active')->first();
        abort_if(!$member,403,'غير مصرح لك بالوصول إلى هذه المساحة.');
        return [$ws,$member];
    }
}