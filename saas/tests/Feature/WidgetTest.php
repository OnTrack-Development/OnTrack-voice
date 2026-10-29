<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\WidgetKey;
use App\Models\WidgetSession;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_widget_starts_session_and_accepts_message_for_its_tenant(): void
    {
        [$tenant,$workspace,$project] = $this->tenant('alpha');
        $key=WidgetKey::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'project_id'=>$project->id,'public_key'=>'pk_alpha','status'=>'active']);
        $config=$this->get('/api/widget/pk_alpha/config')->assertOk();
        $config->assertJsonPath('public_key','pk_alpha')->assertJsonMissingPath('api_key');
        $start=$this->postJson('/api/widget/pk_alpha/start')->assertCreated();
        $token=$start->json('session_token');
        $this->postJson('/api/widget/pk_alpha/message',['session_token'=>$token,'body'=>'أهلاً'])->assertStatus(202);
        $this->assertDatabaseHas('messages',['role'=>'customer','body'=>'أهلاً','tenant_id'=>$tenant->id]);
    }

    public function test_unknown_key_and_foreign_session_are_rejected(): void
    {
        $this->get('/api/widget/no-such-key/config')->assertNotFound();
        [$tenant,$workspace,$project] = $this->tenant('alpha');
        WidgetKey::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'project_id'=>$project->id,'public_key'=>'pk_alpha','status'=>'active']);
        $this->postJson('/api/widget/pk_alpha/message',['session_token'=>'invalid','body'=>'x'])->assertNotFound();
    }

    private function tenant(string $slug): array
    {
        $user=User::factory()->create(); $tenant=Tenant::create(['name'=>$slug,'slug'=>$slug,'status'=>'active','created_by'=>$user->id]); $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>$slug.'-main']); $project=Project::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'name'=>'Project','slug'=>'project-'.$slug]); TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$user->id,'role'=>'owner','status'=>'active']); return [$tenant,$workspace,$project];
    }
}