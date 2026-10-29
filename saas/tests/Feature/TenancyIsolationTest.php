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

class TenancyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_tenant_workspace_and_owner_membership(): void
    {
        $response = $this->post('/register', [
            'name' => 'مؤسس التجربة',
            'company' => 'شركة الأفق',
            'email' => 'owner@example.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
        ]);

        $response->assertRedirect(route('app.home'));

        $tenant = Tenant::where('name', 'شركة الأفق')->first();
        $this->assertNotNull($tenant);
        $this->assertSame('active', $tenant->status);

        $membership = TenantMembership::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($membership);
        $this->assertSame('owner', $membership->role);
        $this->assertSame($membership->user_id, auth()->id());

        $this->assertDatabaseHas('workspaces', ['tenant_id' => $tenant->id, 'name' => 'المساحة الرئيسية']);
    }

    public function test_tenant_b_cannot_open_tenant_a_workspace(): void
    {
        $creator = User::factory()->create();
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active', 'created_by' => $creator->id]);
        $workspaceA = Workspace::create(['tenant_id' => $tenantA->id, 'name' => 'Workspace A', 'slug' => 'tenant-a-main']);
        $userA = User::factory()->create();
        TenantMembership::create([
            'tenant_id' => $tenantA->id,
            'workspace_id' => $workspaceA->id,
            'user_id' => $userA->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => 'active', 'created_by' => $creator->id]);
        $workspaceB = Workspace::create(['tenant_id' => $tenantB->id, 'name' => 'Workspace B', 'slug' => 'tenant-b-main']);
        $userB = User::factory()->create();
        TenantMembership::create([
            'tenant_id' => $tenantB->id,
            'workspace_id' => $workspaceB->id,
            'user_id' => $userB->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $this->actingAs($userA)->get("/app/{$workspaceB->slug}/overview")->assertForbidden();
        $this->actingAs($userB)->get("/app/{$workspaceA->slug}/overview")->assertForbidden();
        $this->actingAs($userA)->get("/app/{$workspaceA->slug}/overview")->assertOk();
    }

    public function test_agent_belongs_to_tenant_and_project(): void
    {
        $creator = User::factory()->create();
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'status' => 'active', 'created_by' => $creator->id]);
        $workspace = Workspace::create(['tenant_id' => $tenant->id, 'name' => 'W1', 'slug' => 'w1-main']);
        $project = Project::create(['tenant_id' => $tenant->id, 'workspace_id' => $workspace->id, 'name' => 'P1', 'slug' => 'p1']);
        $agent = Agent::create(['tenant_id' => $tenant->id, 'project_id' => $project->id, 'name' => 'A1', 'kind' => 'custom', 'status' => 'draft']);

        $this->assertSame($tenant->id, $agent->tenant_id);
        $this->assertSame($project->id, $agent->project_id);
        $this->assertSame('مسودة', $agent->statusLabel());
    }
}