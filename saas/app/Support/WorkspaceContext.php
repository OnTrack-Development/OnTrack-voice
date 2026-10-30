<?php

namespace App\Support;

use App\Models\TenantMembership;
use App\Models\Workspace;
use Illuminate\Http\Request;

final class WorkspaceContext
{
    /**
     * Resolve a workspace only through the current user's active membership.
     * Tenant membership alone is not sufficient for a workspace-scoped route.
     */
    public static function resolve(Request $request, string $slug): array
    {
        $workspace = Workspace::where('slug', $slug)->firstOrFail();
        $membership = TenantMembership::where('user_id', $request->user()->id)
            ->where('tenant_id', $workspace->tenant_id)
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->first();
        abort_if(! $membership, 403, 'غير مصرح لك بالوصول إلى هذه المساحة.');
        return [$workspace, $membership];
    }
}