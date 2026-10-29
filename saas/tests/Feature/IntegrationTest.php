<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Workspace;
use App\Models\BusinessConnectorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_encrypted_connector_in_own_project(): void
    {
        [$user,$workspace,$project] = $this->context('alpha');
        $this->actingAs($user)->post('/app/'.$workspace->slug.'/integrations', ['project_id'=>$project->id,'type'=>'rest','name'=>'متجري','base_url'=>'https://shop.example/api','api_key'=>'project-secret'])->assertRedirect();
        $connector=BusinessConnectorProfile::firstOrFail();
        $this->assertSame('draft',$connector->status);
        $this->assertNotSame('project-secret',$connector->encrypted_credentials);
        $this->assertStringNotContainsString('project-secret',$connector->encrypted_credentials);
    }

    public function test_foreign_project_and_foreign_workspace_are_rejected(): void
    {
        [$user,$workspace] = $this->context('alpha');
        [, , $foreignProject] = $this->context('beta');
        $this->actingAs($user)->post('/app/'.$workspace->slug.'/integrations', ['project_id'=>$foreignProject->id,'type'=>'rest','name'=>'x'])->assertStatus(422);
        [, $foreignWs] = $this->context('gamma');
        $this->actingAs($user)->post('/app/'.$foreignWs->slug.'/integrations', ['project_id'=>$foreignProject->id,'type'=>'rest','name'=>'x'])->assertForbidden();
        $this->assertDatabaseCount('business_connector_profiles',0);
    }

    private function context(string $slug): array
    {
        $user=User::factory()->create(); $tenant=Tenant::create(['name'=>$slug,'slug'=>$slug,'status'=>'active','created_by'=>$user->id]); $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>$slug.'-main']); $project=Project::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'name'=>'Project','slug'=>'project-'.$slug]); TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$user->id,'role'=>'owner','status'=>'active']); return [$user,$workspace,$project];
    }
}