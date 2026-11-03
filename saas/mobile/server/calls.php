<?php
declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/call_ledger.php';
require __DIR__.'/call_report_store.php';
header('X-Frame-Options: DENY');
session_name('ontrack_mobile_admin');
session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Lax','path'=>'/saas/mobile/']);
session_start();
if (empty($_SESSION['mobile_admin'])) {http_response_code(403);exit('Access denied');}
$_SESSION['csrf']??=bin2hex(random_bytes(24));
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals((string)$_SESSION['csrf'],(string)($_POST['csrf']??''))){http_response_code(403);exit('Invalid CSRF');}
    $id=(string)($_POST['call_id']??'');
    $kind=(string)($_POST['kind']??'customer_note');
    $value=trim((string)($_POST['text']??''));
    try{
        $bytes=random_bytes(16);$bytes[6]=chr((ord($bytes[6])&15)|64);$bytes[8]=chr((ord($bytes[8])&63)|128);
        $hex=bin2hex($bytes);$eid=substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
        ontrack_report_append(['call_id'=>$id,'entry_id'=>$eid,'kind'=>$kind,'role'=>'employee','text'=>$value],MOBILE_STORE.'/calls','admin');
        header('Location: /saas/mobile/calls.php?saved=1',true,303);exit;
    }catch(InvalidArgumentException $e){
        http_response_code(422);$reportError='تعذر حفظ التقرير: '.$e->getMessage();
    }catch(Throwable $e){
        error_log('ontrack call report UI: '.$e->getMessage());
        http_response_code(503);$reportError='تعذر حفظ التقرير في التخزين الخاص';
    }
}
$rows=ontrack_call_list(MOBILE_STORE.'/calls',200);
$filter=trim((string)($_GET['search']??''));
$phase=(string)($_GET['phase']??'all');
if(strlen($filter)>100)$filter=substr($filter,0,100);
if(!in_array($phase,['all','ringing','active','ended','missed'],true))$phase='all';
$rows=array_values(array_filter($rows,static function(array $r)use($filter,$phase):bool{
    if($phase!=='all' && ($r['phase']??'')!==$phase)return false;
    if($filter==='')return true;
    $hay=implode(' ',array_map('strval',array_filter([
        $r['call_id']??null,$r['started_at_utc']??null,
        $r['contact_name']??null,$r['phone_number']??null,$r['agent_name']??null
    ],static fn($x)=>$x!==null)));
    return stripos($hay,$filter)!==false;
}));
function ch(string $v):string{return htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function call_duration_ar($seconds):string {
    if($seconds===null||!is_numeric($seconds))return '—';
    $total=max(0,(int)$seconds);$min=intdiv($total,60);$sec=$total%60;
    return $min>0?$min.' دقيقة'.($sec?' و'.$sec.' ثانية':''):$sec.' ثانية';
}
$states=['ringing'=>'رنين','active'=>'تم الرد','ended'=>'انتهت','missed'=>'لم يتم الرد','unknown'=>'غير معروف'];
?><!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>سجل المكالمات | OnTrack</title>
<style>
:root{color-scheme:dark}*{box-sizing:border-box}body{margin:0;background:#0b101b;color:#f4f6fb;font-family:system-ui,"Segoe UI",sans-serif}
main{max-width:1150px;margin:auto;padding:24px 16px}a{color:#ff7088;text-decoration:none}h1{font-size:27px;margin:14px 0}p{color:#afbdd1;line-height:1.8}
section{overflow:auto;border-radius:16px;border:1px solid #30394b;background:#161f2d}table{width:100%;border-collapse:collapse;min-width:760px}
th,td{text-align:right;padding:15px;border-bottom:1px solid #30394b;font-size:13px}th{color:#c4cee0;background:#1d2939}
code{font-size:11px;direction:ltr;display:inline-block;color:#b7c3d7}.empty{padding:36px;text-align:center;color:#a9bacf}
.filters{display:flex;gap:10px;flex-wrap:wrap;margin:16px 0}.filters input,.filters select{flex:1;min-width:160px;border:1px solid #38465c;border-radius:12px;background:#111c2c;color:#fff;padding:12px;font:inherit}.filters button{background:#e63d58;color:#fff;border:0;padding:12px 20px;border-radius:12px;font-weight:700;cursor:pointer}
.links{display:flex;gap:20px;align-items:center;flex-wrap:wrap}
.report-details{min-width:245px}.report-details summary{cursor:pointer;color:#ff7088;font-weight:700}.report-details p{white-space:pre-wrap;color:#e6eaf2;margin:8px 0}.report-details small{color:#96a4b8}.report-form{display:grid;gap:8px;margin-top:12px}.report-form textarea,.report-form select{width:100%;background:#101927;color:#fff;border:1px solid #43516b;border-radius:8px;padding:8px;font:inherit}.report-form button{padding:8px;background:#e63d58;color:#fff;border:0;border-radius:8px;cursor:pointer}.report-note{border-bottom:1px solid #30394b;padding:6px 0}
</style></head><body><main><div class="links"><a href="/saas/mobile/">لوحة التحكم</a><a href="/saas/mobile/ops.php">الحملات وجهات الاتصال</a></div>
<h1>سجل مكالمات الهاتف</h1>
<?php if(isset($reportError)):?><p role="alert"><?=ch($reportError)?></p><?php elseif(isset($_GET['saved'])):?><p role="status">تم حفظ الملاحظة في تقرير المكالمة.</p><?php endif;?>
<form class="filters" method="GET"><input type="search" name="search" placeholder="بحث بالمعرف أو التاريخ أو الرقم المتاح" value="<?=ch($filter)?>">
<select name="phase"><option value="all">كل الحالات</option>
<?php foreach(['ringing'=>'رنين','active'=>'تم الرد','ended'=>'انتهت','missed'=>'لم يرد'] as $v=>$label):?>
<option value="<?=ch($v)?>" <?=$phase===$v?'selected':''?>><?=ch($label)?></option>
<?php endforeach;?></select><button type="submit">بحث</button></form>
<p>بداية ربط سجل المكالمات الفعلي مع OnTrack. هذا السجل يلتقط تغيرات حالة الشريحة التي يراها التطبيق، وليس سجل الاتصالات القديم الخاص بنظام Android. الاسم والرقم يظهران بعد إذن قراءة سجلات المكالمات وجهات الاتصال ومطابقة السجل فعليًا. التفريغ يظهر فقط لو وصل نص فعلي من جلسة الصوت. الملخص والملاحظات البشرية تُحفظ بنوعها ومصدرها، ولا تُنسب للذكاء الاصطناعي بدون توليد حقيقي.</p>
<section><?php if(!$rows):?><div class="empty">لم تصل أي مكالمة مسجلة من تطبيق OnTrack حتى الآن.</div><?php else:?>
<table><thead><tr><th>المعرف</th><th>وقت أول رصد UTC</th><th>الحالة</th><th>مدة المكالمة</th><th>اسم جهة الاتصال</th><th>رقم الهاتف</th><th>التقرير</th></tr></thead><tbody>
<?php foreach($rows as $row):?><tr><td><code><?=ch((string)$row['call_id'])?></code></td>
<td><?=ch((string)$row['started_at_utc'])?></td>
<td><?=ch($states[$row['phase']]??'غير معروف')?></td>
<td><?=ch(call_duration_ar($row['duration_seconds']??null))?></td>
<td><?=ch((string)($row['contact_name']??'غير معروف'))?></td>
<td dir="ltr"><?=ch((string)($row['phone_number']??'غير متاح'))?></td>
<td>
<?php $report=ontrack_report_read((string)$row['call_id'],MOBILE_STORE.'/calls');$entries=$report['entries']; ?>
<details class="report-details"><summary>التقرير (<?=count($entries)?>)</summary>
<?php if(!$entries):?><p>لم يصل تفريغ أو ملخص بعد.</p><?php endif;?>
<?php foreach($entries as $entry):?>
<div class="report-note"><small><?=ch(match($entry['kind']){'transcript'=>'تفريغ • '.($entry['role']==='caller'?'العميل':'الإيجنت'),'handoff'=>'تحويل لموظف','summary'=>'ملخص','disposition'=>'النتيجة',default=>'ملاحظة للعميل'})?> · <?=ch((string)$entry['created_at_utc'])?></small><p><?=ch((string)$entry['text'])?></p></div>
<?php endforeach;?>
<form class="report-form" method="post"><input type="hidden" name="csrf" value="<?=ch($_SESSION['csrf'])?>"><input type="hidden" name="call_id" value="<?=ch((string)$row['call_id'])?>">
<label>إضافة تقرير أو ملاحظة</label><select name="kind"><option value="customer_note">ملاحظة عن العميل</option><option value="handoff">سبب تحويل لموظف</option><option value="summary">ملخص بشري</option><option value="disposition">نتيجة المكالمة</option></select>
<textarea name="text" rows="3" maxlength="1500" required placeholder="اكتب ملاحظة حقيقية مرتبطة بهذه المكالمة"></textarea><button type="submit">حفظ الملاحظة</button></form>
</details></td></tr><?php endforeach;?></tbody></table><?php endif;?></section>
</main></body></html>
