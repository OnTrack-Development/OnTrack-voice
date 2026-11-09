<?php
declare(strict_types=1);
/** SaaS Phase 3: read-only plan rendering and owner-authorized plan selector. */
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/Views.php';
if(is_dir('/home/appontrack') && !getenv('ONTRACK_CI_NAMESPACE'))
    throw new RuntimeException('saas_phase3_fixture_only');
function ux3_assert(bool $cond,string $label):void {
    if(!$cond)throw new RuntimeException('SAAS_PHASE3_PLAN_FAIL '.$label);
}
seed_console();
$admin=one("SELECT id FROM users WHERE email='admin@ontrackegy.com'");
$reseller=one("SELECT id FROM users WHERE email='reseller@delta.test'");
ux3_assert($admin!==null && $reseller!==null,'seeded admin + reseller accounts');
$_SESSION=['uid'=>(int)$admin['id'],'account_id'=>1,'console_view'=>'admin'];
platform_set_resellers_visible(true);
putenv('ONTRACK_DEMO=1');
ob_start();render_plans(mine());$platform=(string)ob_get_clean();
ux3_assert(str_contains($platform,'إدارة اشتراكات الريسيلرز'),
    'platform sees reseller subscription administration');
ux3_assert(str_contains($platform,'name="action" value="assign_plan"') &&
    str_contains($platform,'name="customer_id"') &&
    str_contains($platform,'name="plan_id"'),
    'platform reseller plan selector is wired to existing authorized action');
ux3_assert(str_contains($platform,'دلتا فويس') && str_contains($platform,'نيل فويس'),
    'all existing resellers remain selectable without deleting bindings');
$_SESSION=['uid'=>(int)$reseller['id'],'account_id'=>2,'console_view'=>'admin'];
ob_start();render_plans(mine());$resellerView=(string)ob_get_clean();
ux3_assert(str_contains($resellerView,'باقة حسابك كريسيلر'),
    'reseller can view its own wholesale subscription');
ux3_assert(str_contains($resellerView,'tab=billing') &&
    str_contains($resellerView,'اختيار أو تجديد باقتك'),
    'reseller checkout selector is discoverable and active');
ux3_assert(!str_contains($resellerView,'إدارة اشتراكات الريسيلرز'),
    'reseller cannot see platform plan reassignment form');
$_SESSION=['uid'=>(int)$admin['id'],'account_id'=>1,'console_view'=>'admin'];
$plan=one("SELECT id,price_minor FROM plans WHERE kind='reseller' ORDER BY id LIMIT 1");
ux3_assert($plan!==null,'active wholesale plan fixture');
try{
    execute("UPDATE plans SET price_minor=12345 WHERE kind='reseller'");
    putenv('ONTRACK_DEMO');
    ob_start();render_plans(mine());$paid=(string)ob_get_clean();
    ux3_assert(str_contains($paid,'لا توجد باقة مجانية قابلة للتعيين المباشر') &&
        !str_contains($paid,'name="action" value="assign_plan"'),
        'production cannot directly grant paid wholesale plans without payment');
    putenv('ONTRACK_DEMO=1');
    ob_start();render_plans(mine());$demo=(string)ob_get_clean();
    ux3_assert(str_contains($demo,'name="action" value="assign_plan"'),
        'deliberate Demo mode keeps internal admin plan testing available');
    platform_set_resellers_visible(false);
    ob_start();render_plans(mine());$hidden=(string)ob_get_clean();
    ux3_assert(!str_contains($hidden,'إدارة اشتراكات الريسيلرز'),
        'explicit reseller visibility toggle is respected, not bypassed');
}finally {
    execute("UPDATE plans SET price_minor=? WHERE id=?",[(int)$plan['price_minor'],(int)$plan['id']]);
    platform_set_resellers_visible(true);
    putenv('ONTRACK_DEMO');
}
echo "SAAS_PHASE3_RESELLER_PLANS_OWNER_UI_NONREGRESSION_PASS\n";
