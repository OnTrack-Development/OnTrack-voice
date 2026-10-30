<?php
declare(strict_types=1);
require __DIR__.'/common.php';
header('X-Frame-Options: DENY');
session_name('ontrack_mobile_admin');
session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Strict','path'=>'/saas/mobile/']);
session_start();
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['key'])) {
    if (mobile_authorized((string)$_POST['key'])) { session_regenerate_id(true); $_SESSION['mobile_admin']=true; }
    else { http_response_code(401); $error='مفتاح الاتصال غير صحيح'; }
}
$allowed=!empty($_SESSION['mobile_admin']);
$_SESSION['csrf']??=bin2hex(random_bytes(24));
if ($allowed && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save'])) {
    if (!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))) { http_response_code(403); exit('csrf'); }
    $old=mobile_settings();
    $data=['revision'=>(int)$old['revision']+1,'enabled'=>isset($_POST['enabled']),
        'auto_answer'=>isset($_POST['auto_answer']),
        'voice'=>in_array($_POST['voice']??'', ['Aoede','Kore','Puck','Charon','Fenrir'],true)?$_POST['voice']:'Aoede',
        'input_channel'=>in_array($_POST['input_channel']??'', ['right','left','mix'],true)?$_POST['input_channel']:'right',
        'agent_name'=>mb_substr(trim((string)($_POST['agent_name']??'OnTrack')),0,80),
        'instruction'=>mb_substr(trim((string)($_POST['instruction']??'')),0,12000)];
    mobile_store($data);
}
$s=mobile_settings();
function e(string $v):string{return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
?><!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OnTrack — اتصال الموبايل</title>
<style>body{font-family:system-ui;background:#f4f5f8;color:#202531;max-width:760px;margin:40px auto;padding:20px}form{background:white;padding:28px;border-radius:20px}label{display:block;margin:18px 0}input,textarea,select,button{font:inherit;padding:12px;border:1px solid #ccd1dc;border-radius:9px;box-sizing:border-box}input[type=text],input[type=password],textarea{width:100%}button{background:#c92837;color:white;cursor:pointer}textarea{min-height:180px}small{color:#667085}</style>
<h1>ربط الموبايل بالإيجنت</h1><p>نسخة ربط تجريبية مع محرك الموقع الحالي. الشريحة موجودة في الهاتف؛ الصوت يمر من التطبيق.</p>
<?php if(!$allowed):?><form method="post"><p><?=e($error)?></p><label>مفتاح الاتصال<input type="password" name="key" required autocomplete="current-password"></label><button>دخول</button></form>
<?php else:?><form method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><input type="hidden" name="save" value="1">
<label><input type="checkbox" name="enabled" <?=$s['enabled']?'checked':''?>> تشغيل الربط الصوتي</label>
<label><input type="checkbox" name="auto_answer" <?=$s['auto_answer']?'checked':''?>> الرد على المكالمات الواردة أثناء تشغيل التطبيق</label>
<label>اسم الإيجنت<input name="agent_name" type="text" value="<?=e($s['agent_name'])?>"></label>
<label>المهمة وتعليمات الحوار<textarea name="instruction"><?=e($s['instruction'])?></textarea></label>
<label>صوت محرك الموقع<select name="voice"><?php foreach(['Aoede','Kore','Puck','Charon','Fenrir'] as $v):?><option <?=$s['voice']===$v?'selected':''?>><?=e($v)?></option><?php endforeach?></select></label>
<label>قناة التقاط صوت العميل<select name="input_channel"><?php foreach(['right'=>'اليمنى','left'=>'اليسرى','mix'=>'مزج القناتين'] as $v=>$label):?><option value="<?=$v?>" <?=$s['input_channel']===$v?'selected':''?>><?=$label?></option><?php endforeach?></select></label>
<button>حفظ الإعدادات</button><p><small>إصدار الإعدادات <?=intval($s['revision'])?>. يتلقاها التطبيق كل خمس ثوانٍ؛ المهمة والصوت يُثبتان عند بداية الجلسة التالية. صوت الملف المستورد للاختبار منفصل عن أصوات Gemini المعروضة هنا.</small></p></form><?php endif?></html>
