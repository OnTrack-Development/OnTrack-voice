<?php
declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/call_ledger.php';
header('X-Frame-Options: DENY');
session_name('ontrack_mobile_admin');
session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Lax','path'=>'/saas/mobile/']);
session_start();
if (empty($_SESSION['mobile_admin'])) {http_response_code(403);exit('Access denied');}
$rows=ontrack_call_list(MOBILE_STORE.'/calls',100);
function ch(string $v):string{return htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$states=['ringing'=>'رنين','active'=>'تم الرد','ended'=>'انتهت','missed'=>'لم يتم الرد','unknown'=>'غير معروف'];
?><!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>سجل المكالمات | OnTrack</title>
<style>
:root{color-scheme:dark}*{box-sizing:border-box}body{margin:0;background:#0b101b;color:#f4f6fb;font-family:system-ui,"Segoe UI",sans-serif}
main{max-width:1150px;margin:auto;padding:24px 16px}a{color:#ff7088;text-decoration:none}h1{font-size:27px;margin:14px 0}p{color:#afbdd1;line-height:1.8}
section{overflow:auto;border-radius:16px;border:1px solid #30394b;background:#161f2d}table{width:100%;border-collapse:collapse;min-width:760px}
th,td{text-align:right;padding:15px;border-bottom:1px solid #30394b;font-size:13px}th{color:#c4cee0;background:#1d2939}
code{font-size:11px;direction:ltr;display:inline-block;color:#b7c3d7}.empty{padding:36px;text-align:center;color:#a9bacf}
</style></head><body><main><a href="/saas/mobile/">العودة لمركز التحكم</a>
<h1>سجل مكالمات الهاتف</h1>
<p>بداية ربط سجل المكالمات الفعلي مع OnTrack. هذا السجل يلتقط تغيرات حالة الشريحة التي يراها التطبيق، وليس سجل الاتصالات القديم الخاص بنظام Android. الاسم والرقم والتسجيل والملخص ستظل «غير متاح» حتى ربط مصادرها فعليًا.</p>
<section><?php if(!$rows):?><div class="empty">لم تصل أي مكالمة مسجلة من تطبيق OnTrack حتى الآن.</div><?php else:?>
<table><thead><tr><th>المعرف</th><th>وقت أول رصد UTC</th><th>الحالة</th><th>مدة الرصد</th><th>رقم الهاتف</th><th>التقرير</th></tr></thead><tbody>
<?php foreach($rows as $row):?><tr><td><code><?=ch((string)$row['call_id'])?></code></td>
<td><?=ch((string)$row['started_at_utc'])?></td>
<td><?=ch($states[$row['phase']]??'غير معروف')?></td>
<td><?=isset($row['duration_seconds'])&&$row['duration_seconds']!==null?(int)$row['duration_seconds'].' ث':'—'?></td>
<td>غير متاح</td><td>لم يُنشأ بعد</td></tr><?php endforeach;?></tbody></table><?php endif;?></section>
</main></body></html>
