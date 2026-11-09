<?php
declare(strict_types=1);
/** Protect the once-hidden customer staff UI from PHP 8 strict-type failures. */
if(PHP_SAPI!=='cli'||is_dir('/home/appontrack')||
   !preg_match('/^[A-Za-z0-9_-]{8,60}$/D',(string)getenv('ONTRACK_CI_NAMESPACE')))
    throw new RuntimeException('isolated_staff_ui_fixture_only');
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/Views.php';
function staff_ui_ok(bool $condition,string $why):void {
    if(!$condition)throw new RuntimeException('STAFF_UI_FAIL '.$why);
}
seed_console();
$integerSelect=select_html('ثوانٍ','delay',[10=>'10 seconds',20=>'20 seconds'],20);
staff_ui_ok(str_contains($integerSelect,'value="20" selected'),
    'numeric IDs and time values work under strict typed select_html');
$owner=one("SELECT id FROM users WHERE email='user@direct.test'");
staff_ui_ok($owner!==null,'customer owner exists');
$_SESSION=['uid'=>(int)$owner['id'],'account_id'=>4];
ob_start();
render_staff(mine());
$html=(string)ob_get_clean();
staff_ui_ok(str_contains($html,'التحويل البشري وإبلاغ المدير بعد المكالمة'),
    'customer owner can load staff management and human escalation settings');
staff_ui_ok(substr_count($html,'value="human_route_save"')===2,
    'customer can configure both existing site agents independently');
staff_ui_ok(str_contains($html,'name="handoff_delay_seconds"'),
    'handoff delay is editable rather than inert');
staff_ui_ok(!str_contains($html,'</form></form>') &&
            !str_contains($html,'</form></details>'),
    'staff markup has no orphaned form or details closing tags');
staff_ui_ok(str_contains($html,'دعوة موظف') && str_contains($html,'حفظ مسار التحويل'),
    'staff invitations and route forms remain actionable');
echo "SAAS_CUSTOMER_STAFF_AND_HUMAN_HANDOFF_RENDER_PASS\n";
