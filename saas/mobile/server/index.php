<?php
declare(strict_types=1);
require __DIR__.'/common.php';
header('X-Frame-Options: DENY');
session_name('ontrack_mobile_admin');
session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Strict','path'=>'/saas/mobile/']);
session_start();
$error='';$saved=false;
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['key'])) {
    if (mobile_authorized((string)$_POST['key'])) {
        session_regenerate_id(true); $_SESSION['mobile_admin']=true;
    } else { http_response_code(401);$error='مفتاح الاتصال غير صحيح'; }
}
$allowed=!empty($_SESSION['mobile_admin']);
$_SESSION['csrf']??=bin2hex(random_bytes(24));
if ($allowed && $_SERVER['REQUEST_METHOD']==='POST' && (isset($_POST['save']) || isset($_POST['toggle']))) {
    if (!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))) {http_response_code(403);exit('csrf');}
    $old=mobile_settings(); $data=$old;
    if(isset($_POST['toggle'])) {
        if(!in_array($_POST['toggle'],['on','off'],true)){http_response_code(400);exit('invalid action');}
        $data['enabled']=$_POST['toggle']==='on';
    } else {
        $data['enabled']=isset($_POST['enabled']);
        $data['auto_answer']=isset($_POST['auto_answer']);
        $data['answer_delay_seconds']=max(0,min(30,(int)($_POST['answer_delay_seconds']??5)));
        $data['agent_name']=mb_substr(trim((string)($_POST['agent_name']??'OnTrack')),0,80);
        $data['instruction']=mb_substr(trim((string)($_POST['instruction']??'')),0,12000);
        $data['voice']=in_array($_POST['voice']??'',['Aoede','Kore','Puck','Charon','Fenrir'],true)?$_POST['voice']:'Aoede';
        $data['input_channel']=in_array($_POST['input_channel']??'',['right','left','mix'],true)?$_POST['input_channel']:$old['input_channel'];
        $data['response_style']='short_ar_egyptian';
    }
    $data['revision']=(int)$old['revision']+1;
    mobile_store($data);$saved=true;
}
$s=mobile_settings();
$seen=json_decode((string)@file_get_contents(MOBILE_STORE.'/device-seen.json'),true);
$ts=strtotime((string)($seen['seen_at']??''));$online=$ts!==false && time()-$ts<45;
function e(string $s):string{return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
?><!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>OnTrack Voice • مركز التحكم</title>
<style>
:root{color-scheme:dark;--bg:#0b101b;--panel:#141d2d;--line:#28364a;--text:#f3f5fa;--muted:#a6b5cc;--red:#e43d59;--green:#2ad3a4}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(ellipse at 80% -20%,#20324c 0,transparent 48%),var(--bg);font-family:system-ui,'Segoe UI',sans-serif;color:var(--text)}
.shell{max-width:1180px;margin:auto;padding:22px}.nav{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:15px 0 24px;border-bottom:1px solid var(--line);flex-wrap:wrap}
.brand{display:flex;align-items:center;gap:12px;font-weight:850}.logo{background:var(--red);padding:11px;border-radius:15px;color:white;letter-spacing:1px}.sub{color:var(--muted);font-weight:400;font-size:13px}
.pill{border:1px solid var(--line);background:#152334;border-radius:999px;padding:8px 13px;font-size:13px;color:var(--muted)}
.dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--green);margin-left:6px}.off{background:#f4ac50}
h1{font-size:clamp(24px,4vw,38px);margin:14px 0 8px;letter-spacing:-.5px}h2{font-size:18px;margin:0 0 6px}p{color:var(--muted);line-height:1.8;margin:6px 0 14px}
.hero{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;align-items:center;padding:30px 0 18px}
.actions{display:flex;flex-wrap:wrap;gap:10px}.btn,button{appearance:none;font:inherit;border:0;cursor:pointer;border-radius:11px;padding:12px 18px;background:#29384e;color:white;font-weight:650}
.primary{background:var(--red)}.danger{background:#57253a}.positive{background:#126e58}.btn:focus-visible,button:focus-visible,input:focus-visible,textarea:focus-visible,select:focus-visible{outline:2px solid #87bdf5;outline-offset:2px}
.cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.card{border:1px solid var(--line);background:linear-gradient(165deg,#1a2639,var(--panel));padding:21px;border-radius:18px;min-width:0}
.metric{font-size:23px;font-weight:800;margin:7px 0}.muted{font-size:13px;color:var(--muted)}.content{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(275px,.85fr);gap:16px;margin:18px 0}
label{display:block;color:#ced9e9;font-size:13px;font-weight:600;margin:18px 0 8px}
input[type=text],input[type=password],input[type=number],textarea,select{width:100%;background:#0e1624;border:1px solid #34455c;border-radius:11px;color:var(--text);padding:13px 14px;font:inherit;outline-offset:2px}
textarea{min-height:245px;resize:vertical;line-height:1.8}select{cursor:pointer}.check{display:flex;gap:12px;align-items:center;margin:16px 0}.check input{width:20px;height:20px;accent-color:var(--red)}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}.info{padding:15px;background:#102339;border:1px solid #2e4965;border-radius:13px;margin:15px 0}
.note{font-size:12px;color:var(--muted);line-height:1.8}.footer{padding:22px 0;color:var(--muted);font-size:12px}
.toast{border:1px solid #21765e;background:#153c34;padding:12px 18px;border-radius:12px;margin-top:15px}
.login{max-width:440px;margin:75px auto;background:var(--panel);padding:28px;border:1px solid var(--line);border-radius:18px}
@media(max-width:850px){.cards,.content{grid-template-columns:1fr}.grid2{grid-template-columns:1fr}.shell{padding:15px}}
</style></head><body><main class="shell">
<nav class="nav"><div class="brand"><span class="logo">OT</span><div>OnTrack Voice<div class="sub">Operations & AI Call Control</div></div></div>
<span class="pill">Single-device pilot · ليست لوحة SaaS متعددة الشركات بعد</span></nav>
<?php if(!$allowed):?>
<div class="login"><h1>تسجيل الدخول</h1><p>دخول مشرف تجربة ربط الشريحة</p>
<form method="post"><label for="key">مفتاح الإدارة</label><input id="key" name="key" type="password" autocomplete="current-password" required>
<p role="alert" style="color:#fb98a8"><?=e($error)?></p><button class="primary">دخول اللوحة</button></form></div>
<?php else:?>
<div class="hero"><div><p class="muted">مركز التحكم • المكالمات الصوتية</p><h1>لوحة تشغيل الإيجنت</h1><p>إعدادات المكالمات، سيناريو الحوار، الصوت، وحالة الاتصال الفعلية</p></div>
<div class="actions"><form method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>">
<input type="hidden" name="toggle" value="<?=$s['enabled']?'off':'on'?>">
<button class="<?=$s['enabled']?'danger':'positive'?>"><?=$s['enabled']?'إيقاف الإيجنت':'تشغيل الإيجنت'?></button></form></div></div>
<?php if($saved):?><div class="toast" role="status">تم حفظ الإعدادات. سيجلب الهاتف التغيير في دورة التحديث القادمة، وإعدادات المحادثة الجديدة تبدأ من المكالمة التالية.</div><?php endif?>
<section class="cards">
<div class="card"><div class="muted">حالة الهاتف</div><div class="metric"><span class="dot <?=$online?'':'off'?>"></span><?=$online?'متصل':'غير متصل'?></div><div class="muted">آخر اتصال: <?=e((string)($seen['seen_at']??'لم يتصل بعد'))?></div></div>
<div class="card"><div class="muted">حالة AI Agent</div><div class="metric"><?=$s['enabled']?'مفعّل':'متوقف'?></div><div class="muted">يمكن إيقافه من هنا أو من التطبيق</div></div>
<div class="card"><div class="muted">سياسة الوارد</div><div class="metric"><?=$s['auto_answer']?'رد تلقائي':'يدوي'?></div><div class="muted">بعد <?=intval($s['answer_delay_seconds']??5)?> ثوانٍ على النسخة الداعمة • Revision <?=intval($s['revision'])?></div></div>
</section>
<div class="content">
<form class="card" method="post">
<input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><input type="hidden" name="save" value="1">
<h2>إعدادات الإيجنت والصوت</h2><p>تُحفظ عند OnTrack وتصل للهاتف بأمان. تغييرات الحوار تُطبق في بداية الجلسة التالية.</p>
<label class="check"><input type="checkbox" name="enabled" <?=$s['enabled']?'checked':''?>>الإيجنت متاح لاستقبال المكالمات</label>
<label class="check"><input type="checkbox" name="auto_answer" <?=$s['auto_answer']?'checked':''?>>الرد التلقائي على الوارد</label>
<div class="grid2"><div><label for="delay">انتظار الرد (بالثواني)</label><select id="delay" name="answer_delay_seconds">
<?php foreach([0,3,5,10,15,20,30] as $n):?><option value="<?=$n?>" <?=$n===(int)($s['answer_delay_seconds']??5)?'selected':''?>><?=$n===0?'فوري':$n.' ثوانٍ'?></option><?php endforeach?></select></div>
<div><label for="voice">صوت الإيجنت</label><select id="voice" name="voice">
<?php foreach(['Aoede','Kore','Puck','Charon','Fenrir'] as $v):?><option <?=$s['voice']===$v?'selected':''?>><?=e($v)?></option><?php endforeach?></select></div></div>
<label for="agent">اسم الإيجنت / الهوية</label><input id="agent" type="text" name="agent_name" maxlength="80" value="<?=e((string)$s['agent_name'])?>">
<label for="instruction">السيناريو والتعليمات</label><textarea id="instruction" name="instruction" maxlength="12000" spellcheck="true"><?=e((string)$s['instruction'])?></textarea>
<div class="info"><b>نمط المحادثة القصيرة</b><p class="note">العربية المصرية، جملة أو جملتين في الرد، مقاطعة بدون إعادة السيناريو من البداية، إجابة مرتبطة بالسؤال ثم العودة لنقطة الحوار. قواعد النظام الأساسية تُطبق على جلسات النسخة الداعمة.</p></div>
<label for="input">قناة التقاط صوت العميل</label><select id="input" name="input_channel">
<option value="mix" <?=$s['input_channel']==='mix'?'selected':''?>>القناتان Mix — الإعداد الناجح على الهاتف</option>
<option value="right" <?=$s['input_channel']==='right'?'selected':''?>>القناة اليمنى</option>
<option value="left" <?=$s['input_channel']==='left'?'selected':''?>>القناة اليسرى</option></select>
<div style="margin-top:20px"><button class="primary" type="submit">حفظ وتطبيق الإعدادات</button></div>
</form>
<aside><div class="card"><h2>التحكم أثناء المكالمة</h2>
<p>التطبيق يوفر شريط حالة عائم باختيار صاحب الهاتف، وإشعارًا دائمًا كبديل. من خلالهما يمكن استلام المكالمة بشريًا أو إيقاف الإيجنت أو تجربة تغيير السماعة.</p>
<div class="info"><b>استلام بشري</b><p class="note">يفصل صوت الـAI فقط عن المكالمة الحالية ويُعيد مسار الميكروفون الطبيعي؛ لا يغلق الخط. لا يتدخل الإيجنت مجددًا قبل انتهاء المكالمة.</p></div>
<div class="info"><b>صلاحية النافذة العائمة</b><p class="note">تُمنح مرة واحدة من إعدادات Android باختيار المستخدم. لا توجد Accessibility أو صلاحية لقراءة الشاشة. يظل إشعار المكالمة ظاهرًا بدون هذا الإذن.</p></div>
<div class="info"><b>السماعة الخارجية</b><p class="note">اختيار تجريبي حسب دعم Android والجهاز، ولا يعرض نجاح التغيير قبل تأكيد مسار الصوت.</p></div>
</div>
<div class="card" style="margin-top:16px"><h2>حالة التطبيق</h2><p class="note">التجربة الحالية لجهاز واحد. نظام الموظفين والصلاحيات وMulti-Tenant SaaS وAgent Builder سيُبنى في طبقة مستقلة بعد اعتماد اختبار المكالمات.</p><div class="muted">محرك المكالمات الحالي: OnTrack SIM Voice Connector</div></div></aside>
</div>
<?php endif?>
<footer class="footer">OnTrack Development · AI Voice Pilot · لا تحفظ المفاتيح أو التسجيلات في هذه الصفحة</footer>
</main></body></html>
