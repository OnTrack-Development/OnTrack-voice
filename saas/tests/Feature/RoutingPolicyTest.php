<?php

namespace Tests\Feature;

use App\Models\PlatformAdminGrant;
use App\Models\PlatformAiRoutingPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutingPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_saves_routing_policy_with_exact_ids(): void
    {
        $admin = User::factory()->create();
        PlatformAdminGrant::create(['user_id'=>$admin->id,'role'=>'platform_ai_ops']);
        $this->actingAs($admin)->post('/control/ai/routing', [
            'plan_id'=>null, 'feature'=>'messages', 'risk_class'=>'high',
            'writer_model_id'=>'google/gemini-3-pro-preview', 'reviewer_model_id'=>'google/gemini-3-sentry', 'fallback_model_id'=>'openai-compatible/gpt-oss-120b',
        ])->assertRedirect();
        $policy = PlatformAiRoutingPolicy::firstOrFail();
        $this->assertSame('google/gemini-3-pro-preview', $policy->writer_model_id);
        $this->assertSame('google/gemini-3-sentry', $policy->reviewer_model_id);
        $this->assertSame('openai-compatible/gpt-oss-120b', $policy->fallback_model_id);
    }

    public function test_duplicate_policy_is_upserted_not_duplicated(): void
    {
        $admin = User::factory()->create();
        PlatformAdminGrant::create(['user_id'=>$admin->id,'role'=>'platform_ai_ops']);
        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($admin)->post('/control/ai/routing', [
                'plan_id'=>null, 'feature'=>'messages', 'risk_class'=>'low', 'writer_model_id'=>'m1', 'reviewer_model_id'=>null, 'fallback_model_id'=>null,
            ])->assertRedirect();
        }
        $this->assertSame(1, PlatformAiRoutingPolicy::where('feature','messages')->where('risk_class','low')->count());
    }
}