<?php

namespace Tests\Feature;

use App\Models\ButtonRule;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\TenantAuditEvent;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_record_is_tenant_scoped_and_redacted_context_is_persisted(): void
    {
        [$user, $workspace, $tenant] = $this->context('alpha');
        $response = $this->actingAs($user)->post('/app/'.$workspace->slug.'/smart-actions', [
            'project_id' => Project::where('tenant_id', $tenant->id)->value('id'),
            'label_ar' => 'تفاصيل',
            'action_key' => 'booking.details',
            'min_identity_level' => 'verified',
        ]);
        $response->assertRedirect();
        TenantAuditEvent::create([
            'tenant_id' => $tenant->id,
            'actor_user_id' => $user->id,
            'action' => 'test.action',
            'resource_type' => 'test',
            'redacted_context' => ['secret' => '[REDACTED]', 'state_revision' => 'v1'],
            'occurred_at' => now(),
        ]);
        $event = TenantAuditEvent::latest('id')->firstOrFail();
        $this->assertSame($tenant->id, $event->tenant_id);
        $this->assertSame('[REDACTED]', $event->redacted_context['secret']);
        $this->assertSame(1, TenantAuditEvent::where('tenant_id', $tenant->id)->count());
    }

    public function test_other_tenant_events_are_not_visible_by_tenant_scope(): void
    {
        [, , $tenantA] = $this->context('alpha');
        [, , $tenantB] = $this->context('beta');
        TenantAuditEvent::create(['tenant_id'=>$tenantA->id,'action'=>'a','occurred_at'=>now()]);
        TenantAuditEvent::create(['tenant_id'=>$tenantB->id,'action'=>'b','occurred_at'=>now()]);
        $this->assertSame(['a'], TenantAuditEvent::where('tenant_id',$tenantA->id)->pluck('action')->all());
    }

    private function context(string $slug): array
    {
        $user=User::factory()->create(); $tenant=Tenant::create(['name'=>$slug,'slug'=>$slug,'status'=>'active','created_by'=>$user->id]); $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>$slug.'-main']); TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$user->id,'role'=>'owner','status'=>'active']); Project::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'name'=>'Project','slug'=>'project-'.$slug]); return [$user,$workspace,$tenant];
    }
}