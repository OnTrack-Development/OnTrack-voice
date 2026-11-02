<?php
declare(strict_types=1);
require __DIR__.'/../saas/console/database/Seed.php';
require __DIR__.'/../saas/console/_core/Views.php';
function verify(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
seed_console();
$columns=driver()==='sqlite'?array_column(all('PRAGMA table_info(plans)'),'name'):
    array_column(all('SHOW COLUMNS FROM plans'),'Field');
verify(in_array('overage_minor',$columns,true),'overage migration available');
$plan=one("SELECT * FROM plans ORDER BY id LIMIT 1");
verify($plan!==null,'test plan present');
$previousPrice=(int)$plan['price_minor'];$previousRate=(int)$plan['overage_minor'];
try{
    execute('UPDATE plans SET price_minor=?,overage_minor=? WHERE id=?',[15999,75,$plan['id']]);
    $updated=one('SELECT price_minor,overage_minor FROM plans WHERE id=?',[$plan['id']]);
    verify((int)$updated['price_minor']===15999 && (int)$updated['overage_minor']===75,'piaster prices persist');
    $admin=one("SELECT * FROM users WHERE email='admin@ontrackegy.com'");
    $_SESSION=['uid'=>(int)$admin['id'],'account_id'=>1];
    ob_start();render_plans(mine());$html=(string)ob_get_clean();
    verify(str_contains($html,'0.75 ج.م / مكالمة'),'overage price renders in plan table');
    verify(str_contains($html,'name="price"')&&str_contains($html,'name="overage"'),'both plan price fields editable');
    verify(str_contains($html,'plan-editor'),'pricing forms are compact disclosures');
    ob_start();render_keys(mine());$keys=(string)ob_get_clean();
    verify(!str_contains($keys,'&lt;span class='),'card title does not expose escaped HTML');
    $css=(string)file_get_contents(__DIR__.'/../saas/console/assets/css/app.css');
    verify(str_contains($css,'input[type="checkbox"]'),'checkbox styles explicitly scoped');
    $ui=(string)file_get_contents(__DIR__.'/../saas/mobile/ota/ui.html');
    verify(!str_contains($ui,'id="key"') && str_contains($ui,"if(!last.tenantLinked)"),'SaaS SIM activation never asks for pilot key');
}finally{
    execute('UPDATE plans SET price_minor=?,overage_minor=? WHERE id=?',[$previousPrice,$previousRate,$plan['id']]);
}
echo "CONSOLE_PRICING_UI_PASS\n";
