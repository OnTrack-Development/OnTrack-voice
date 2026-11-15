<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Voice\VoiceCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnterpriseAdminVoiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function platform(): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $user = User::factory()->create();
        $accountId = DB::table('accounts')->insertGetId([
            'kind' => 'platform', 'parent_id' => 0, 'name' => 'IO HQ',
            'slug' => 'io-test-hq', 'status' => 'active', 'created_at' => $now,
        ]);
        $siteId = DB::table('sites')->insertGetId([
            'account_id' => $accountId, 'name' => 'HQ',
            'domain' => 'app.ontrackegy.com', 'status' => 'active', 'created_at' => $now,
        ]);
        DB::table('memberships')->insert([
            'user_id' => $user->id, 'account_id' => $accountId,
            'role' => 'owner', 'created_at' => $now,
        ]);
        return [$user, $accountId, $siteId, $now];
    }

    public function test_platform_admin_can_see_voice_rows_and_enable_or_archive(): void
    {
        [$user, $accountId, $siteId, $now] = $this->platform();
        $voiceId = DB::table('voices')->insertGetId([
            'code' => 'google.kore', 'name' => 'Kore QA',
            'provider' => 'gemini_live', 'tier' => 'basic',
            'ready' => 1, 'status' => 'active', 'gender' => 'female',
            'dialect' => 'عربي',
        ]);
        $this->actingAs($user)->get('/?tab=platform-ai')
            ->assertOk()->assertSee('التحكم في أصوات المنصة')->assertSee('Kore QA');
        $this->actingAs($user)->postJson('/console/action', [
            'action' => 'toggle_voice_ready', 'voice_id' => $voiceId,
        ])->assertOk();
        $this->assertSame(0, (int) DB::table('voices')->where('id', $voiceId)->value('ready'));

        // Archived voices must disappear from the listing, but not destroy
        // historical grants, playback samples or billing records.
        $this->actingAs($user)->postJson('/console/action', [
            'action' => 'archive_voice', 'voice_id' => $voiceId,
        ])->assertOk();
        $this->assertSame('archived', DB::table('voices')->where('id', $voiceId)->value('status'));
        $this->actingAs($user)->get('/?tab=platform-ai')
            ->assertOk()->assertDontSee('Kore QA');

        $this->actingAs($user)->postJson('/console/action', [
            'action' => 'voice_sample_play', 'voice_id' => $voiceId,
        ])->assertForbidden();
    }

    public function test_archiving_voice_used_by_active_agent_is_denied(): void
    {
        [$user, $accountId, $siteId, $now] = $this->platform();
        $voiceId = DB::table('voices')->insertGetId([
            'code' => 'google.aoede', 'name' => 'Aoede QA',
            'provider' => 'gemini_live', 'tier' => 'basic',
            'ready' => 1, 'status' => 'active', 'gender' => 'female',
            'dialect' => 'متعدد اللغات',
        ]);
        DB::table('agents')->insert([
            'account_id' => $accountId, 'site_id' => $siteId,
            'name' => 'Active Voice Agent', 'kind' => 'voice',
            'voice' => 'google.aoede', 'status' => 'draft',
            'instructions' => '', 'knowledge' => '',
            'flow_json' => '{}', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->actingAs($user)->postJson('/console/action', [
            'action' => 'archive_voice', 'voice_id' => $voiceId,
        ])->assertStatus(422);
        $this->assertSame('active', DB::table('voices')->where('id', $voiceId)->value('status'));
    }

    public function test_reseller_archive_requires_suspension_exact_name_and_no_child(): void
    {
        [$user, $accountId, $siteId, $now] = $this->platform();
        $resellerId = DB::table('accounts')->insertGetId([
            'kind' => 'reseller', 'parent_id' => 0,
            'name' => 'Reseller for QA', 'slug' => 'reseller-qa',
            'status' => 'active', 'created_at' => $now,
        ]);
        $this->actingAs($user)->postJson('/console/action', [
            'action' => 'archive_account', 'target_account_id' => $resellerId,
            'confirm_name' => 'Reseller for QA',
        ])->assertStatus(422);
        DB::table('accounts')->where('id', $resellerId)->update(['status' => 'suspended']);
        $this->actingAs($user)->postJson('/console/action', [
            'action' => 'archive_account', 'target_account_id' => $resellerId,
            'confirm_name' => 'Other reseller',
        ])->assertStatus(422);
        $child = DB::table('accounts')->insertGetId([
            'kind' => 'customer', 'parent_id' => $resellerId,
            'name' => 'Child', 'slug' => 'child-reseller-qa',
            'status' => 'active', 'created_at' => $now,
        ]);
        $this->actingAs($user)->postJson('/console/action', [
            'action' => 'archive_account', 'target_account_id' => $resellerId,
            'confirm_name' => 'Reseller for QA',
        ])->assertStatus(422);
        DB::table('accounts')->where('id', $child)->update(['status' => 'archived']);
        $this->actingAs($user)->postJson('/console/action', [
            'action' => 'archive_account', 'target_account_id' => $resellerId,
            'confirm_name' => 'Reseller for QA',
        ])->assertOk();
        $this->assertSame('archived', DB::table('accounts')->where('id', $resellerId)->value('status'));
        $this->assertSame(1, DB::table('audit_events')->where('action', 'account.archived')
            ->where('account_id', $resellerId)->count());
    }

    public function test_voice_fixed_preview_has_wav_header_and_explicit_arabic_line(): void
    {
        $svc = app(VoiceCatalogService::class);
        $this->assertSame(
            'أهلًا بحضرتك في آي أو فويس. يسعدني أساعدك وأجاوب عن استفسارك بوضوح وسرعة.',
            VoiceCatalogService::PREVIEW_TEXT
        );
        $method = new \ReflectionMethod($svc, 'pcm24kToWav');
        $raw = str_repeat("\x12\x34", 256);
        $wav = $method->invoke($svc, $raw);
        $this->assertSame('RIFF', substr($wav, 0, 4));
        $this->assertSame('WAVE', substr($wav, 8, 4));
        $this->assertSame('data', substr($wav, 36, 4));
        $this->assertSame(strlen($wav) - 8, unpack('V', substr($wav, 4, 4))[1]);
        $this->assertSame(strlen($raw), unpack('V', substr($wav, 40, 4))[1]);
    }
}
