<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Database\Seeders\PlatformAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_create_admin_and_demo_tenant(): void
    {
        (new PlatformAdminSeeder())->run();
        (new DemoSeeder())->run();

        $this->assertDatabaseHas('users', ['email' => 'platform.admin@ontrack.local']);
        $this->assertDatabaseHas('platform_admin_grants', ['role' => 'platform_owner']);
        $this->assertDatabaseHas('users', ['email' => 'demo@ontrack.local']);
        $this->assertDatabaseHas('tenants', ['slug' => 'demo-company']);
        $this->assertDatabaseHas('conversations', ['channel' => 'web']);
    }
}