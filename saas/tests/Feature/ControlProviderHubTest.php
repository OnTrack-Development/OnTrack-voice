<?php

namespace Tests\Feature;

use App\Models\PlatformAdminGrant;
use App\Models\PlatformAiProviderProfile;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlProviderHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_user_is_forbidden_from_provider_hub(): void
    {
        [$user] = $this->tenantUser('tenant-owner');

        $this->actingAs($user)->get('/control/ai/providers')->assertForbidden();
        $this->actingAs($user)->post('/control/ai/providers', [
            'type' => 'google',
            'name' => 'غير مسموح',
            'api_key' => 'secret',
        ])->assertForbidden();
    }

    public function test_platform_admin_can_create_provider_without_plaintext_secret(): void
    {
        $user = User::factory()->create();
        PlatformAdminGrant::create(['user_id' => $user->id, 'role' => 'platform_ai_ops']);

        $this->actingAs($user)->post('/control/ai/providers', [
            'type' => 'openai_compatible',
            'name' => 'Internal Custom',
            'base_url' => 'https://ai.internal.example/v1',
            'api_key' => 'super-secret-key',
        ])->assertRedirect(route('control.ai.providers.index'));

        $profile = PlatformAiProviderProfile::firstOrFail();
        $this->assertNotSame('super-secret-key', $profile->encrypted_credentials);
        $this->assertStringNotContainsString('super-secret-key', $profile->encrypted_credentials);
        $this->assertSame('pending_test', $profile->connection_status);

        $response = $this->actingAs($user)->get('/control/ai/providers');
        $response->assertOk();
        $response->assertSee('Internal Custom');
        $response->assertDontSee('super-secret-key');
    }

    public function test_provider_profile_has_no_tenant_id_column(): void
    {
        $columns = \Schema::getColumnListing('platform_ai_provider_profiles');
        $this->assertNotContains('tenant_id', $columns);
    }

    private function tenantUser(string $slug): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::create([
            'name' => $slug,
            'slug' => $slug,
            'status' => 'active',
            'created_by' => $user->id,
        ]);
        $workspace = Workspace::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main',
            'slug' => $slug.'-main',
        ]);
        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        return [$user, $tenant, $workspace];
    }
}