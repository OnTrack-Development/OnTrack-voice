<?php

namespace Tests\Feature;

use App\Models\ButtonInvocation;
use App\Models\ButtonRule;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_rule_is_tenant_scoped_and_duplicate_invocation_is_idempotent(): void
    {
        [$user, $workspace, $project] = $this->context('alpha');
        $this->actingAs($user)->post("/app/{$workspace->slug}/smart-actions", ['project_id'=>$project->id,'label_ar'=>'تفاصيل الحجز','action_key'=>'booking.details','min_identity_level'=>'verified','business_state_in'=>'booking.pending'])->assertRedirect();
        $rule = ButtonRule::firstOrFail();
        $url = "/app/{$workspace->slug}/smart-actions/{$rule->id}/invoke";
        $first = $this->actingAs($user)->postJson($url, ['idempotency_key'=>'idem-1','state_revision'=>'booking:v1'])->assertStatus(202);
        $first->assertJsonPath('status', 'accepted');
        $this->actingAs($user)->postJson($url, ['idempotency_key'=>'idem-1','state_revision'=>'booking:v1'])->assertOk()->assertJsonPath('status', 'duplicate');
        $this->assertSame(1, ButtonInvocation::count());
    }

    public function test_foreign_project_and_rule_are_rejected(): void
    {
        [$user, $workspace] = $this->context('alpha');
        [, , $foreignProject] = $this->context('beta');
        $this->actingAs($user)->post("/app/{$workspace->slug}/smart-actions", ['project_id'=>$foreignProject->id,'label_ar'=>'تسلل','action_key'=>'bad.action','min_identity_level'=>'guest'])->assertStatus(422);
        $foreignRule = ButtonRule::create(['tenant_id'=>$foreignProject->tenant_id,'workspace_id'=>$foreignProject->workspace_id,'project_id'=>$foreignProject->id,'label_ar'=>'B','action_key'=>'b.action','status'=>'draft']);
        $this->actingAs($user)->postJson("/app/{$workspace->slug}/smart-actions/{$foreignRule->id}/invoke", ['idempotency_key'=>'x','state_revision'=>'v1'])->assertNotFound();
    }

    private function context(string $slug): array
    {
        $user=User::factory()->create(); $tenant=Tenant::create(['name'=>$slug,'slug'=>$slug,'status'=>'active','created_by'=>$user->id]); $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>$slug.'-main']); TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$user->id,'role'=>'owner','status'=>'active']); $project=Project::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'name'=>'Project','slug'=>'project-'.$slug]); return [$user,$workspace,$project];
    }
}