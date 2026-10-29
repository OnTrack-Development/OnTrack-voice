<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_page_shows_real_counts_for_own_tenant(): void
    {
        [$user,$workspace,$tenant]=$this->context('alpha');
        Conversation::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'contact_key'=>'c1','channel'=>'web','status'=>'open']);
        Conversation::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'contact_key'=>'c2','channel'=>'web','status'=>'open','human_locked_at'=>now()]);
        $this->actingAs($user)->get('/app/'.$workspace->slug.'/operations')->assertOk()
            ->assertSee('2')
            ->assertSee('1');
    }

    public function test_operations_page_is_forbidden_for_foreign_tenant(): void
    {
        [$userA,$workspaceA]=$this->context('alpha');
        [, $workspaceB]=$this->context('beta');
        $this->actingAs($userA)->get('/app/'.$workspaceB->slug.'/operations')->assertForbidden();
    }

    private function context(string $slug): array
    {
        $user=User::factory()->create(); $tenant=Tenant::create(['name'=>$slug,'slug'=>$slug,'status'=>'active','created_by'=>$user->id]); $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>$slug.'-main']); TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$user->id,'role'=>'owner','status'=>'active']); return [$user,$workspace,$tenant];
    }
}