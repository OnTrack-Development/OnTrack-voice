<?php

namespace Tests\Feature;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeSource;
use App\Models\KnowledgeSourceVersion;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_chunks_in_own_tenant_only(): void
    {
        [$user,$workspace,$tenant] = $this->context('alpha');
        $source=KnowledgeSource::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'name'=>'دليل','kind'=>'text','scope'=>'project','status'=>'indexed']);
        $version=KnowledgeSourceVersion::create(['tenant_id'=>$tenant->id,'knowledge_source_id'=>$source->id,'version'=>1,'content'=>'ساعات العمل من 9 إلى 5','sha256'=>hash('sha256','x'),'ingestion_status'=>'indexed','chunk_count'=>1]);
        KnowledgeChunk::create(['tenant_id'=>$tenant->id,'source_version_id'=>$version->id,'chunk_index'=>0,'content'=>'ساعات العمل من 9 إلى 5']);

        $this->actingAs($user)->get('/app/'.$workspace->slug.'/knowledge/search?q=ساعات')->assertOk()->assertSee('ساعات العمل من 9 إلى 5');
        $this->actingAs($user)->get('/app/'.$workspace->slug.'/knowledge/search?q=غير-موجود')->assertOk()->assertDontSee('ساعات العمل');
    }

    public function test_foreign_tenant_search_is_forbidden(): void
    {
        [$user] = $this->context('alpha'); [, $foreign]=$this->context('beta');
        $this->actingAs($user)->get('/app/'.$foreign->slug.'/knowledge/search?q=x')->assertForbidden();
    }

    private function context(string $slug): array
    {
        $user=User::factory()->create(); $tenant=Tenant::create(['name'=>$slug,'slug'=>$slug,'status'=>'active','created_by'=>$user->id]); $workspace=Workspace::create(['tenant_id'=>$tenant->id,'name'=>'Main','slug'=>$slug.'-main']); TenantMembership::create(['tenant_id'=>$tenant->id,'workspace_id'=>$workspace->id,'user_id'=>$user->id,'role'=>'owner','status'=>'active']); return [$user,$workspace,$tenant];
    }
}