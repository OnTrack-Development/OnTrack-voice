<?php

namespace Tests\Feature;

use App\Models\PlatformAdminGrant;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminConsoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_open_control_console_sections(): void
    {
        $admin = User::factory()->create();
        PlatformAdminGrant::create(['user_id'=>$admin->id,'role'=>'platform_owner']);

        $this->actingAs($admin)->get('/control')->assertOk();
        $this->actingAs($admin)->get('/control/ai/models')->assertOk();
        $this->actingAs($admin)->get('/control/ai/audit')->assertOk();
        $this->actingAs($admin)->get('/control/ai/routing')->assertOk();
        $this->actingAs($admin)->get('/control/ai/costs')->assertOk();
        $this->actingAs($admin)->get('/control/plans')->assertOk();
    }

    public function test_tenant_cannot_guess_routing_or_costs_urls(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/control/ai/routing')->assertForbidden();
        $this->actingAs($user)->get('/control/ai/costs')->assertForbidden();
    }

    public function test_tenant_user_cannot_open_control_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/control')->assertForbidden();
    }
}
