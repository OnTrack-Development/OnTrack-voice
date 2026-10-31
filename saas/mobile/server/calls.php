<?php
declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/call_ledger.php';
header('X-Frame-Options: DENY');
session_name('ontrack_mobile_admin');
session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Lax','path'=>'/saas/mobile/']);
session_start();
if (empty($_SESSION['mobile_admin'])) {http_response_code(403);exit('Access denied');}
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
</style></head><body><main><div class="links"><a href="/saas/mobile/">لوحة التحكم</a><a href="/saas/mobile/ops.php">الحملات وجهات الاتصال</a></div>
<h1>سجل مكالمات الهاتف</h1>
<form class="filters" method="GET"><input type="search" name="search" placeholder="بحث بالمعرف أو التاريخ أو الرقم المتاح" value="<?=ch($filter)?>">
<select name="phase"><option value="all">كل الحالات</option>
<?php foreach(['ringing'=>'رنين','active'=>'تم الرد','ended'=>'انتهت','missed'=>'لم يرد'] as $v=>$label):?>
<option value="<?=ch($v)?>" <?=$phase===$v?'selected':''?>><?=ch($label)?></option>
<?php endforeach;?></select><button type="submit">بحث</button></form>
<p>بداية ربط سجل المكالمات الفعلي مع OnTrack. هذا السجل يلتقط تغيرات حالة الشريحة التي يراها التطبيق، وليس سجل الاتصالات القديم الخاص بنظام Android. الاسم والرقم والتسجيل والملخص ستظل «غير متاح» حتى ربط مصادرها فعليًا.</p>
<section><?php if(!$rows):?><div class="empty">لم تصل أي مكالمة مسجلة من تطبيق OnTrack حتى الآن.</div><?php else:?>
<table><thead><tr><th>المعرف</th><th>وقت أول رصد UTC</th><th>الحالة</th><th>مدة الرصد</th><th>رقم الهاتف</th><th>التقرير</th></tr></thead><tbody>
<?php foreach($rows as $row):?><tr><td><code><?=ch((string)$row['call_id'])?></code></td>
<td><?=ch((string)$row['started_at_utc'])?></td>
<td><?=ch($states[$row['phase']]??'غير معروف')?></td>
<td><?=isset($row['duration_seconds'])&&$row['duration_seconds']!==null?(int)$row['duration_seconds'].' ث':'—'?></td>
<td>غير متاح</td><td>لم يُنشأ بعد</td></tr><?php endforeach;?></tbody></table><?php endif;?></section>
</main></body></html>
