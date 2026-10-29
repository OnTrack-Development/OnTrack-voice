<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeSource;
use App\Models\KnowledgeSourceVersion;
use App\Models\TenantMembership;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KnowledgeController extends Controller
{
    public function index(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);

        return view('app.knowledge.index', [
            'workspace' => $ws,
            'sources' => KnowledgeSource::where('tenant_id', $ws->tenant_id)->latest()->get(),
        ]);
    }

    public function store(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'content' => ['required', 'string', 'max:200000'],
            'project_id' => ['nullable', 'integer'],
        ]);

        if (! empty($data['project_id'])) {
            abort_unless($ws->projects()->whereKey($data['project_id'])->exists(), 422, 'المشروع لا يتبع هذه المساحة.');
        }

        $source = KnowledgeSource::create([
            'tenant_id' => $ws->tenant_id,
            'workspace_id' => $ws->id,
            'project_id' => $data['project_id'] ?? null,
            'name' => $data['name'],
            'kind' => 'text',
            'scope' => 'project',
            'status' => 'processing',
            'source_hash' => hash('sha256', $data['content']),
        ]);

        $version = KnowledgeSourceVersion::create([
            'tenant_id' => $ws->tenant_id,
            'knowledge_source_id' => $source->id,
            'version' => 1,
            'content' => $data['content'],
            'sha256' => hash('sha256', $data['content']),
            'ingestion_status' => 'processing',
        ]);

        $chunks = collect(preg_split('/\R{2,}/u', trim($data['content'])) ?: [])
            ->map(fn ($chunk) => trim($chunk))
            ->filter()
            ->values();

        foreach ($chunks as $index => $chunk) {
            KnowledgeChunk::create([
                'tenant_id' => $ws->tenant_id,
                'source_version_id' => $version->id,
                'chunk_index' => $index,
                'content' => Str::limit($chunk, 12000, ''),
            ]);
        }

        $version->update(['ingestion_status' => 'indexed', 'chunk_count' => $chunks->count()]);
        $source->update(['status' => 'indexed']);

        return redirect()->route('app.knowledge.index', ['workspace' => $ws->slug])
            ->with('status', 'تم حفظ المصدر وفهرسته نصيًا. لا يوجد بحث متجهي مُعلن قبل إعداد محرك فعلي.');
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