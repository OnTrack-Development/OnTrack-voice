<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FloorplanTabTest extends TestCase
{
    use RefreshDatabase;

    private User $platformUser;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure floorplan is in storage/app/floorplan/floorplan.html
        $storageDir = storage_path('app/floorplan');
        if (! file_exists($storageDir)) {
            @mkdir($storageDir, 0755, true);
        }
        file_put_contents($storageDir . '/floorplan.html', '<!DOCTYPE html><html><body><h1>Floorplan Secure Test</h1></body></html>');

        // Ensure public file does not exist
        if (file_exists(public_path('admin/floorplan.html'))) {
            @unlink(public_path('admin/floorplan.html'));
        }

        $now = gmdate('Y-m-d H:i:s');

        // Platform Admin User
        $this->platformUser = User::factory()->create([
            'email' => 'platform_admin@ontrackegy.com',
        ]);
        $platformAccountId = DB::table('accounts')->insertGetId([
            'kind' => 'platform',
            'parent_id' => 0,
            'name' => 'OnTrack Platform Admin',
            'slug' => 'platform-' . uniqid(),
            'status' => 'active',
            'created_at' => $now,
        ]);
        $siteId = DB::table('sites')->insertGetId([
            'account_id' => $platformAccountId,
            'name' => 'Default Site',
            'domain' => 'default.example.test',
            'status' => 'active',
            'created_at' => $now,
        ]);
        DB::table('memberships')->insert([
            'account_id' => $platformAccountId,
            'user_id' => $this->platformUser->id,
            'role' => 'owner',
            'created_at' => $now,
        ]);

        // Regular Customer User
        $this->regularUser = User::factory()->create([
            'email' => 'customer@example.com',
        ]);
        $customerAccountId = DB::table('accounts')->insertGetId([
            'kind' => 'customer',
            'parent_id' => $platformAccountId,
            'name' => 'Demo Customer Account',
            'slug' => 'customer-' . uniqid(),
            'status' => 'active',
            'created_at' => $now,
        ]);
        DB::table('memberships')->insert([
            'account_id' => $customerAccountId,
            'user_id' => $this->regularUser->id,
            'role' => 'owner',
            'created_at' => $now,
        ]);
    }

    public function test_admin_floorplan_route_requires_auth(): void
    {
        $response = $this->get('/admin/floorplan');
        $response->assertRedirect('/login');
    }

    public function test_public_floorplan_file_is_not_exposed(): void
    {
        $this->assertFileDoesNotExist(public_path('admin/floorplan.html'));
    }

    public function test_non_platform_user_is_forbidden_from_admin_floorplan(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/admin/floorplan');
        $response->assertForbidden();
    }

    public function test_non_platform_user_is_forbidden_from_floorplan_tab(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/?tab=floorplan');
        $response->assertForbidden();
    }

    public function test_platform_owner_can_access_admin_floorplan(): void
    {
        $response = $this->actingAs($this->platformUser)->get('/admin/floorplan');
        $response->assertOk();
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Floorplan Secure Test', file_get_contents($response->getFile()->getPathname()));
    }

    public function test_platform_owner_can_view_floorplan_tab(): void
    {
        $response = $this->actingAs($this->platformUser)->get('/?tab=floorplan');
        $response->assertOk();
        $response->assertSee('Architecture Floorplan');
        $response->assertSee('floorplanIframe');
    }
}
