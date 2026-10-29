<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlanEntitlement;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantUsageLedger;
use App\Models\User;
use App\Models\Workspace;
use App\Support\UsageLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlansUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorize_respects_entitlement_and_ledger_is_idempotent(): void
    {
        [$user,$workspace,$tenant] = $this->context('alpha');
        $plan=Plan::create(['name'=>'Starter','status'=>'active','monthly_quota_credits'=>10]);
        PlanEntitlement::create(['plan_id'=>$plan->id,'feature'=>'messages','unit'=>'credits','quota'=>10]);
        Subscription::create(['tenant_id'=>$tenant->id,'plan_id'=>$plan->id,'status'=>'active','started_at'=>now()]);

        $this->assertTrue(UsageLedger::authorize($tenant->id,'messages',4));
        $first=UsageLedger::record($tenant->id,'messages',4,'key-1');
        $dup=UsageLedger::record($tenant->id,'messages',4,'key-1');
        $this->assertSame($first->id,$dup->id);
        $this->assertSame(4,UsageLedger::usedThisMonth($tenant->id,'messages'));
        $this->assertTrue(UsageLedger::authorize($tenant->id,'messages',6));
        $this->assertFalse(UsageLedger::authorize($tenant->id,'messages',7));
    }

    public function test_usage_is_tenant_scoped(): void
    {
        [, , $tenantA]=$this->context('alpha');
        [, , $tenantB]=$this->context('beta');
        UsageLedger::record($tenantA->id,'messages',3,'a-key');
        UsageLedger::record($tenantB->id,'messages',9,'b-key');
        $this->assertSame(3,UsageLedger::usedThisMonth($tenantA->id,'messages'));
        $this->assertSame(9,UsageLedger::usedThisMonth($tenantB->id,'messages'));
        $this->assertSame(2,TenantUsageLedger::count());
    }

    public function test_settings_pause_minutes_validated_1_to_1440(): void
    {
        [$user,$workspace,$tenant]=$this->context('alpha');
        $this->actingAs($user)->post("/app/{$workspace->slug}/settings",['ai_reply_pause_minutes'=>0])->assertSessionHasErrors('ai_reply_pause_minutes');
        $this->actingAs($user)->post("/app/{$workspace->slug}/settings",['ai_reply_pause_minutes'=>1441])->assertSessionHasErrors('ai_reply_pause_minutes');
        $this->actingAs($user)->post("/app/{$workspace->slug}/settings",['ai_reply_pause_minutes'=>30])->assertRedirect();
        $this->assertDatabaseHas('tenant_automation_settings',['tenant_id'=>$tenant->id,'ai_reply_pause_minutes'=>30]);
    }

    private function context(string $slug): array
    {
        $user=User::factory()->create(); $tenant=Tenant::create(['name'=>$slug,'slug'=>$slug,'status'=>'active','created_by'=>$user->id]); $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>$slug.'-main']); TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$user->id,'role'=>'owner','status'=>'active']); return [$user,$workspace,$tenant];
    }
}