<?php

namespace Tests\Feature;

use App\Models\BusinessConnectorProfile;
use App\Models\ConnectorEndpoint;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Workspace;
use App\Support\UrlPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectorEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_endpoint_on_own_connector(): void
    {
        [$user,$workspace,$project] = $this->context('alpha');
        $connector = BusinessConnectorProfile::create(['tenant_id'=>$project->tenant_id,'workspace_id'=>$workspace->id,'project_id'=>$project->id,'type'=>'rest','name'=>'متجري','base_url'=>'https://shop.example/api','status'=>'draft']);
        $this->actingAs($user)->post('/app/'.$workspace->slug.'/integrations/'.$connector->id.'/endpoints',['name'=>'الطلبات','method'=>'GET','path'=>'/api/orders'])->assertRedirect();
        $this->assertDatabaseHas('connector_endpoints',['connector_profile_id'=>$connector->id,'path'=>'/api/orders','status'=>'draft']);
    }

    public function test_unsafe_url_and_path_are_rejected(): void
    {
        [$user,$workspace,$project] = $this->context('alpha');
        $this->actingAs($user)->post('/app/'.$workspace->slug.'/integrations',['project_id'=>$project->id,'type'=>'rest','name'=>'x','base_url'=>'http://localhost'])->assertStatus(422);
        $this->actingAs($user)->post('/app/'.$workspace->slug.'/integrations',['project_id'=>$project->id,'type'=>'rest','name'=>'x','base_url'=>'https://169.254.169.254'])->assertStatus(422);
        $connector = BusinessConnectorProfile::create(['tenant_id'=>$project->tenant_id,'workspace_id'=>$workspace->id,'project_id'=>$project->id,'type'=>'rest','name'=>'c','base_url'=>'https://shop.example','status'=>'draft']);
        $this->actingAs($user)->post('/app/'.$workspace->slug.'/integrations/'.$connector->id.'/endpoints',['name'=>'x','method'=>'GET','path'=>'https://evil.example/x'])->assertStatus(422);
        $this->actingAs($user)->post('/app/'.$workspace->slug.'/integrations/'.$connector->id.'/endpoints',['name'=>'x','method'=>'GET','path'=>'/../admin'])->assertStatus(422);
        $this->assertDatabaseCount('connector_endpoints',0);
    }

    public function test_foreign_connector_and_endpoint_are_not_reachable(): void
    {
        [$userA,$workspaceA,$projectA] = $this->context('alpha');
        [, $workspaceB,$projectB] = $this->context('beta');
        $connectorB = BusinessConnectorProfile::create(['tenant_id'=>$projectB->tenant_id,'workspace_id'=>$workspaceB->id,'project_id'=>$projectB->id,'type'=>'rest','name'=>'B','status'=>'draft']);
        $this->actingAs($userA)->get('/app/'.$workspaceA->slug.'/integrations/'.$connectorB->id)->assertNotFound();
        $this->actingAs($userA)->post('/app/'.$workspaceA->slug.'/integrations/'.$connectorB->id.'/endpoints',['name'=>'x','method'=>'GET','path'=>'/api'])->assertNotFound();
        $endpoint = ConnectorEndpoint::create(['tenant_id'=>$projectB->tenant_id,'connector_profile_id'=>$connectorB->id,'name'=>'e','method'=>'GET','path'=>'/api','status'=>'draft']);
        $this->actingAs($userA)->delete('/app/'.$workspaceA->slug.'/integrations/'.$connectorB->id.'/endpoints/'.$endpoint->id)->assertNotFound();
    }

    public function test_url_policy_rejects_private_and_dns_unresolvable_hosts(): void
    {
        $this->assertTrue(UrlPolicy::isValidBaseUrl(null));
        $this->assertTrue(UrlPolicy::isValidBaseUrl('https://example.com/v1'));
        $this->assertFalse(UrlPolicy::isValidBaseUrl('http://example.com'));
        $this->assertFalse(UrlPolicy::isValidBaseUrl('https://127.0.0.1'));
        $this->assertFalse(UrlPolicy::isValidBaseUrl('https://metadata.google.internal'));
        $this->assertFalse(UrlPolicy::isValidBaseUrl('https://user:pass@example.com'));
        $this->assertTrue(UrlPolicy::isValidPath('/api/orders'));
        $this->assertFalse(UrlPolicy::isValidPath('//evil.com/x'));
        $this->assertFalse(UrlPolicy::isValidPath('orders'));
        $this->assertFalse(UrlPolicy::isValidPath('/a\\b'));
    }

    private function context(string $slug): array
    {
        $user=User::factory()->create(); $tenant=Tenant::create(['name'=>$slug,'slug'=>$slug,'status'=>'active','created_by'=>$user->id]); $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>$slug.'-main']); TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$user->id,'role'=>'owner','status'=>'active']); $project=Project::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'name'=>'P','slug'=>'p-'.$slug]); return [$user,$workspace,$project];
    }
}