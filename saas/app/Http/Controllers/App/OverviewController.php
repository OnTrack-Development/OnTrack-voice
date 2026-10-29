<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\TenantMembership;
use App\Models\Workspace;
use Illuminate\Http\Request;

class OverviewController extends Controller
{
    public function index(Request $request, string $workspace)
    {
        $workspaceModel = Workspace::where('slug', $workspace)->firstOrFail();

        $membership = TenantMembership::query()
            ->where('user_id', $request->user()->id)
            ->where('tenant_id', $workspaceModel->tenant_id)
            ->where('status', 'active')
            ->first();

        abort_if(! $membership, 403, 'غير مصرح لك بالوصول إلى هذه المساحة.');

        $tenant = $workspaceModel->tenant;
        $projects = $workspaceModel->projects()->latest()->get();
        $agents = $tenant->agents()->latest()->get();

        return view('app.overview', [
            'workspace' => $workspaceModel,
            'tenant' => $tenant,
            'membership' => $membership,
            'projects' => $projects,
            'agents' => $agents,
            'stats' => [
                'projects' => $projects->count(),
                'agents' => $agents->count(),
                'published' => $agents->where('status', 'published')->count(),
            ],
        ]);
    }
}