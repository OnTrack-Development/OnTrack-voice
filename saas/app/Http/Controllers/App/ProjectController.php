<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\TenantMembership;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function index(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);

        return view('app.projects.index', [
            'workspace' => $ws,
            'projects' => Project::where('workspace_id', $ws->id)->latest()->get(),
        ]);
    }

    public function create(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);

        return view('app.projects.form', [
            'workspace' => $ws,
            'project' => new Project(['status' => 'active']),
        ]);
    }

    public function store(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'string', 'max:40'],
        ], [], [
            'name' => 'اسم المشروع',
            'kind' => 'النوع',
        ]);

        Project::create([
            'tenant_id' => $ws->tenant_id,
            'workspace_id' => $ws->id,
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($ws->id, $data['name']),
            'kind' => $data['kind'],
            'status' => 'active',
        ]);

        return redirect()->route('app.projects.index', ['workspace' => $ws->slug])
            ->with('status', 'تم إنشاء المشروع.');
    }

    private function uniqueSlug(int $workspaceId, string $name): string
    {
        $base = substr(Str::slug($name, '-', null) ?: 'project', 0, 48);
        $slug = $base;
        $i = 1;

        while (Project::where('workspace_id', $workspaceId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    private function context(Request $request, string $workspace): array
    {
        $ws = Workspace::where('slug', $workspace)->firstOrFail();

        $membership = TenantMembership::query()
            ->where('user_id', $request->user()->id)
            ->where('tenant_id', $ws->tenant_id)
            ->where('status', 'active')
            ->first();

        abort_if(! $membership, 403, 'غير مصرح لك بالوصول إلى هذه المساحة.');

        return [$ws, $membership];
    }
}