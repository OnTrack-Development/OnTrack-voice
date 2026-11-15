<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ZoieOmnichannelTabsTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $now = gmdate('Y-m-d H:i:s');
        $this->adminUser = User::factory()->create([
            'email' => 'admin_test@ontrackegy.com',
        ]);

        $accountId = DB::table('accounts')->insertGetId([
            'kind' => 'platform',
            'parent_id' => 0,
            'name' => 'OnTrack Enterprise Test',
            'slug' => 'ontrack-test-' . uniqid(),
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

    public function test_all_zoie_tabs_render_uniquely_without_fallback_to_overview(): void
    {
        $tabsToCheck = [
            'overview' => 'نظرة عامة على التشغيل',
            'leads' => 'العملاء المحتملون (Leads CRM)',
            'appointments' => 'المواعيد وجدولة الاتصالات',
            'reports' => 'التقارير والتحليلات الشاملة',
            'calls' => 'المكالمات (Calls)',
            'messages' => 'Messages',
            'tickets' => 'تذاكر الدعم ومحرك الوكلاء المتعددين',
            'widget' => 'مُنشئ ومُخصص ودجت الموقع',
            'power_dialer' => 'المتصل الآلي السريع',
            'workflows' => 'التدفقات ومحرك الأتمتة',
            'integrations' => 'التكاملات وموديول الربط الخفيف',
            'activity' => 'النشاط والتعلم الذاتي المستمر',
        ];

        foreach ($tabsToCheck as $tabKey => $uniqueMarker) {
            $resp = $this->actingAs($this->adminUser)->get("/?tab={$tabKey}");
            $resp->assertOk();

            // Assert the tab contains its unique marker
            $resp->assertSee($uniqueMarker, false);

            // Assert "نظرة عامة على التشغيل" ONLY appears on tab=overview
            if ($tabKey !== 'overview') {
                $resp->assertDontSee('نظرة عامة على التشغيل');
            }
        }
    }
}
