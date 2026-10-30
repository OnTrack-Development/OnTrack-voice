<?php

namespace App\Http\Middleware;

use App\Models\TenantMembership;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('workspace');
        if ($slug === null) {
            return $next($request);
        }
        $workspace = Workspace::where('slug', (string) $slug)->firstOrFail();
        $allowed = TenantMembership::where('user_id', $request->user()->id)
            ->where('tenant_id', $workspace->tenant_id)
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->exists();
        abort_if(! $allowed, 403, 'غير مصرح لك بالوصول إلى هذه المساحة.');
        return $next($request);
    }
}