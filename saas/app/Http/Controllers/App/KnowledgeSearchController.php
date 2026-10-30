<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeChunk;
use App\Models\TenantMembership;
use App\Models\Workspace;
use Illuminate\Http\Request;

class KnowledgeSearchController extends Controller
{
    public function search(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);
        $data = $request->validate(['q' => ['required', 'string', 'max:300']]);

        $chunks = KnowledgeChunk::where('tenant_id', $ws->tenant_id)
            ->where('content', 'like', '%'.$data['q'].'%')
            ->with('version.source')
            ->limit(20)
            ->get();

        return view('app.knowledge.search', [
            'workspace' => $ws,
            'q' => $data['q'],
            'chunks' => $chunks,
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