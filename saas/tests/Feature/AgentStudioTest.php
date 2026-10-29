<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentStudioTest extends TestCase
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
        $project = Project::create([
            'tenant_id' => $tenant->id,
            'workspace_id' => $workspace->id,
            'name' => 'P '.$slug,
            'slug' => 'p-'.$slug,
        ]);

        return [$user, $workspace, $project];
    }

    public function test_owner_creates_agent_inside_own_workspace(): void
    {
        [$user, $workspace, $project] = $this->makeWorkspace('alpha');

        $this->actingAs($user)->post("/app/{$workspace->slug}/agents", [
            'name' => 'مساعد الدعم',
            'kind' => 'support',
            'project_id' => $project->id,
            'persona' => 'ودود ومختصر',
            'instructions' => 'أجب عن أسئلة العملاء من المعرفة المتاحة فقط.',
        ])->assertRedirect();

        $this->assertDatabaseHas('agents', [
            'tenant_id' => $workspace->tenant_id,
            'project_id' => $project->id,
            'name' => 'مساعد الدعم',
            'status' => 'draft',
        ]);
    }

    public function test_agent_cannot_be_created_in_foreign_project(): void
    {
        [$user, $workspace] = $this->makeWorkspace('alpha');
        [, , $foreignProject] = $this->makeWorkspace('beta');

        $this->actingAs($user)->post("/app/{$workspace->slug}/agents", [
            'name' => 'تسلل',
            'kind' => 'custom',
            'project_id' => $foreignProject->id,
        ])->assertStatus(422);

        $this->assertDatabaseCount('agents', 0);
    }

    public function test_publish_requires_instructions_and_sets_status(): void
    {
        [$user, $workspace, $project] = $this->makeWorkspace('alpha');

        $agent = Agent::create([
            'tenant_id' => $workspace->tenant_id,
            'project_id' => $project->id,
            'name' => 'وكيل',
            'kind' => 'support',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->post("/app/{$workspace->slug}/agents/{$agent->id}/publish")
            ->assertSessionHasErrors('instructions');

        $agent->update(['instructions' => 'تعليمات واضحة.']);

        $this->actingAs($user)
            ->post("/app/{$workspace->slug}/agents/{$agent->id}/publish")
            ->assertRedirect();

        $agent->refresh();
        $this->assertSame('published', $agent->status);
        $this->assertNotNull($agent->published_at);
    }

    public function test_foreign_agent_is_not_reachable(): void
    {
        [$userA, $workspaceA] = $this->makeWorkspace('alpha');
        [, $workspaceB, $projectB] = $this->makeWorkspace('beta');

        $foreign = Agent::create([
            'tenant_id' => $workspaceB->tenant_id,
            'project_id' => $projectB->id,
            'name' => 'وكيل بيتا',
            'kind' => 'custom',
            'status' => 'draft',
        ]);

        $this->actingAs($userA)
            ->get("/app/{$workspaceA->slug}/agents/{$foreign->id}")
            ->assertNotFound();
    }
}