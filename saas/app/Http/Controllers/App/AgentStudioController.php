<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Project;
use App\Models\TenantMembership;
use App\Models\Workspace;
use Illuminate\Http\Request;

class AgentStudioController extends Controller
{
    public function index(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);

        $agents = Agent::where('tenant_id', $ws->tenant_id)->latest()->get();
        $projects = Project::where('workspace_id', $ws->id)->orderBy('name')->get();

        return view('app.agents.index', [
            'workspace' => $ws,
            'agents' => $agents,
            'projects' => $projects,
        ]);
    }

    public function create(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);

        return view('app.agents.form', [
            'workspace' => $ws,
            'agent' => new Agent(['status' => 'draft']),
            'projects' => Project::where('workspace_id', $ws->id)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);

        $data = $this->validated($request, $ws);

        $agent = Agent::create([
            'tenant_id' => $ws->tenant_id,
            'project_id' => $data['project_id'],
            'name' => $data['name'],
            'kind' => $data['kind'],
            'instructions' => $data['instructions'] ?? null,
            'persona' => $data['persona'] ?? null,
            'status' => 'draft',
        ]);

        return redirect()->route('app.agents.show', ['workspace' => $ws->slug, 'agent' => $agent->id])
            ->with('status', 'تم إنشاء الوكيل كمسودة.');
    }

    public function show(Request $request, string $workspace, Agent $agent)
    {
        [$ws] = $this->context($request, $workspace);
        abort_unless($agent->tenant_id === $ws->tenant_id, 404);

        return view('app.agents.show', [
            'workspace' => $ws,
            'agent' => $agent,
            'projects' => Project::where('workspace_id', $ws->id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, string $workspace, Agent $agent)
    {
        [$ws] = $this->context($request, $workspace);
        abort_unless($agent->tenant_id === $ws->tenant_id, 404);

        $data = $this->validated($request, $ws);

        $agent->update([
            'project_id' => $data['project_id'],
            'name' => $data['name'],
            'kind' => $data['kind'],
            'instructions' => $data['instructions'] ?? null,
            'persona' => $data['persona'] ?? null,
        ]);

        return redirect()->route('app.agents.show', ['workspace' => $ws->slug, 'agent' => $agent->id])
            ->with('status', 'تم حفظ تعديلات الوكيل.');
    }

    public function publish(Request $request, string $workspace, Agent $agent)
    {
        [$ws] = $this->context($request, $workspace);
        abort_unless($agent->tenant_id === $ws->tenant_id, 404);

        if (blank($agent->instructions)) {
            return back()->withErrors(['instructions' => 'لا يمكن نشر وكيل بدون تعليمات.']);
        }

        $agent->update(['status' => 'published', 'published_at' => now()]);

        return back()->with('status', 'تم نشر الوكيل.');
    }

    private function validated(Request $request, Workspace $ws): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'string', 'in:support,sales,booking,custom'],
            'project_id' => ['required', 'integer'],
            'persona' => ['nullable', 'string', 'max:120'],
            'instructions' => ['nullable', 'string', 'max:8000'],
        ], [], [
            'name' => 'اسم الوكيل',
            'kind' => 'نوع الوكيل',
            'project_id' => 'المشروع',
            'instructions' => 'التعليمات',
        ]);

        $ownsProject = Project::where('id', $data['project_id'])
            ->where('workspace_id', $ws->id)
            ->where('tenant_id', $ws->tenant_id)
            ->exists();

        abort_unless($ownsProject, 422, 'المشروع المحدد لا يتبع هذه المساحة.');

        return $data;
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