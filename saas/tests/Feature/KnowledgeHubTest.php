<?php

namespace Tests\Feature;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeSource;
use App\Models\KnowledgeSourceVersion;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeHubTest extends TestCase
{
    use RefreshDatabase;

    private function makeWorkspace(string $slug): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'T '.$slug, 'slug' => 't-'.$slug, 'status' => 'active', 'created_by' => $user->id]);
        $workspace = Workspace::create(['tenant_id' => $tenant->id, 'name' => 'W '.$slug, 'slug' => $slug.'-main']);
        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        return [$user, $workspace, $tenant];
    }

    public function test_owner_indexes_text_source_into_chunks(): void
    {
        [$user, $workspace, $tenant] = $this->makeWorkspace('alpha');

        $this->actingAs($user)->post("/app/{$workspace->slug}/knowledge", [
            'name' => 'الأسئلة الشائعة',
            'content' => "ساعات العمل من 9 إلى 5.\n\nالاسترجاع خلال 14 يومًا.\n\nالشحن يستغرق 3 أيام.",
        ])->assertRedirect(route('app.knowledge.index', ['workspace' => $workspace->slug]));

        $source = KnowledgeSource::firstOrFail();
        $this->assertSame($tenant->id, $source->tenant_id);
        $this->assertSame('indexed', $source->status);

        $version = KnowledgeSourceVersion::firstOrFail();
        $this->assertSame('indexed', $version->ingestion_status);
        $this->assertSame(3, $version->chunk_count);
        $this->assertSame(3, KnowledgeChunk::where('source_version_id', $version->id)->count());
    }

    public function test_foreign_user_cannot_write_knowledge(): void
    {
        [$userA, $workspaceA] = $this->makeWorkspace('alpha');
        [, $workspaceB] = $this->makeWorkspace('beta');

        $this->actingAs($userA)->get("/app/{$workspaceB->slug}/knowledge")->assertForbidden();
        $this->actingAs($userA)->post("/app/{$workspaceB->slug}/knowledge", [
            'name' => 'تسلل',
            'content' => 'نص',
        ])->assertForbidden();

        $this->assertDatabaseCount('knowledge_sources', 0);
    }

    public function test_project_scope_must_belong_to_workspace(): void
    {
        [$userA, $workspaceA] = $this->makeWorkspace('alpha');
        [, , $tenantB] = $this->makeWorkspace('beta');
        $workspaceB = Workspace::where('tenant_id', $tenantB->id)->firstOrFail();
        $foreignProject = Project::create([
            'tenant_id' => $tenantB->id,
            'workspace_id' => $workspaceB->id,
            'name' => 'PB',
            'slug' => 'pb',
        ]);

        $this->actingAs($userA)->post("/app/{$workspaceA->slug}/knowledge", [
            'name' => 'مصدر',
            'content' => 'نص',
            'project_id' => $foreignProject->id,
        ])->assertStatus(422);

        $this->assertDatabaseCount('knowledge_sources', 0);
    }
}