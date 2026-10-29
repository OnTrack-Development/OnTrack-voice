<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_is_created_for_current_tenant(): void
    {
        [$user,$workspace,$tenant]=$this->context('alpha');
        $this->actingAs($user)->post('/app/'.$workspace->slug.'/crm', ['display_name'=>'عميل ألفا','email'=>'alpha@example.com','lead_status'=>'new'])->assertRedirect();
        $contact=Contact::firstOrFail();
        $this->assertSame($tenant->id,$contact->tenant_id);
        $this->assertSame('alpha@example.com',$contact->email);
    }

    public function test_foreign_tenant_cannot_list_or_create_contacts(): void
    {
        [$userA,$workspaceA]= $this->context('alpha');
        [, $workspaceB]= $this->context('beta');
        $this->actingAs($userA)->get('/app/'.$workspaceB->slug.'/crm')->assertForbidden();
        $this->actingAs($userA)->post('/app/'.$workspaceB->slug.'/crm', ['display_name'=>'تسلل','lead_status'=>'new'])->assertForbidden();
        $this->assertDatabaseCount('contacts',0);
    }

    private function context(string $slug): array
    {
        $user=User::factory()->create(); $tenant=Tenant::create(['name'=>$slug,'slug'=>$slug,'status'=>'active','created_by'=>$user->id]); $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>$slug.'-main']); TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$user->id,'role'=>'owner','status'=>'active']); return [$user,$workspace,$tenant];
    }
}