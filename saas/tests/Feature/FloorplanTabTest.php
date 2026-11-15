<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FloorplanTabTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        if (! file_exists(public_path('admin/floorplan.html'))) {
            @mkdir(public_path('admin'), 0755, true);
            file_put_contents(public_path('admin/floorplan.html'), '<!DOCTYPE html><html><body><h1>Floorplan Test</h1></body></html>');
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->adminUser = User::factory()->create([
            'email' => 'admin_floorplan@ontrackegy.com',
        ]);

        $accountId = DB::table('accounts')->insertGetId([
            'kind' => 'platform',
            'parent_id' => 0,
            'name' => 'OnTrack Platform Admin',
            'slug' => 'platform-' . uniqid(),
            'status' => 'active',
            'created_at' => $now,
        ]);

        $siteId = DB::table('sites')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Default Site',
            'domain' => 'default.example.test',
            'status' => 'active',
            'created_at' => $now,
        ]);

        DB::table('memberships')->insert([
            'account_id' => $accountId,
            'user_id' => $this->adminUser->id,
            'role' => 'owner',
            'created_at' => $now,
        ]);
    }

    public function test_admin_floorplan_route_requires_auth(): void
    {
        $response = $this->get('/admin/floorplan');
        $response->assertRedirect('/login');
    }

    public function test_admin_floorplan_serves_html_when_authenticated(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/floorplan');
        $response->assertOk();
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
    }

    public function test_floorplan_tab_renders_in_workspace(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/?tab=floorplan');
        $response->assertOk();
        $response->assertSee('Architecture Floorplan');
        $response->assertSee('floorplanIframe');
        $response->assertSee('Zero-Orphans Codebase Map');
    }
}
