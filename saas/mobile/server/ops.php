<?php
declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/pilot_workspace.php';
require __DIR__.'/pilot_import_file.php';
header('X-Frame-Options: DENY');
session_name('ontrack_mobile_admin');
session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Lax','path'=>'/saas/mobile/']);
session_start();
if(empty($_SESSION['mobile_admin'])){http_response_code(403);exit('Access denied');}
$_SESSION['csrf']??=bin2hex(random_bytes(24));
$flash='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    if(!hash_equals((string)$_SESSION['csrf'],(string)($_POST['csrf']??''))){http_response_code(403);exit('csrf');}
    try {
        $action=(string)($_POST['action']??'');
        if($action==='import') {
            if(empty($_POST['consent']))throw new InvalidArgumentException('يجب تأكيد موافقة أصحاب الأرقام على التواصل.');
            $text=(string)($_POST['numbers']??'');
            if(strlen($text)>180000)throw new InvalidArgumentException('قائمة كبيرة جدًا');
            $rows=[];
            foreach(preg_split('/\R/u',$text)?:[] as $line) {
                if(trim($line)==='')continue;
                if(str_contains($line,','))[$name,$phone]=array_pad(str_getcsv($line),2,'');
                else [$name,$phone]=['',$line];
                $rows[]=['name'=>$name,'phone'=>$phone];
            }
            if(isset($_FILES['upload']) && ($_FILES['upload']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
                if($_FILES['upload']['error']!==UPLOAD_ERR_OK || !is_uploaded_file((string)$_FILES['upload']['tmp_name']))
                    throw new InvalidArgumentException('لم يكتمل رفع الملف');
                $rows=array_merge($rows,pilot_file_rows((string)$_FILES['upload']['tmp_name'],(string)$_FILES['upload']['name']));
            }
            if(!$rows)throw new InvalidArgumentException('اختر ملفًا أو أدخل أرقامًا');
            $result=pilot_import_contacts($rows);
            $flash=sprintf('تم إضافة %d، مكرر %d، غير صالح %d',$result['added'],$result['duplicates'],$result['invalid']);
        } elseif($action==='dnc') {
            if(!preg_match('/^[0-9a-f]{24}$/D',(string)($_POST['id']??'')))throw new InvalidArgumentException('معرف غير صالح');
            $found=pilot_dnc((string)$_POST['id'],($_POST['blocked']??'')==='1');
            if(!$found)throw new InvalidArgumentException('جهة الاتصال غير موجودة');
            $flash='تم تحديث حالة عدم الاتصال بالرقم.';
        } elseif($action==='campaign') {
            $row=pilot_campaign_create((string)($_POST['name']??''),(string)($_POST['starts']??''),
                (string)($_POST['ends']??''),(string)($_POST['scenario']??''));
            $flash='تم إنشاء حملة مسودة. لا يتم الاتصال تلقائيًا قبل تفعيل منفذ Android المعتمد.';
        } elseif($action==='campaign_control') {
            $id=(string)($_POST['id']??'');
            $operation=(string)($_POST['operation']??'');
            if(!preg_match('/^[a-f0-9]{24}$/D',$id))throw new InvalidArgumentException('معرّف غير صالح');
            $campaign=$operation==='arm'?pilot_campaign_arm($id):pilot_campaign_transition($id,$operation);
            $flash='تم تحديث الحملة: '.$campaign['status'].' — لا يتم الاتصال تلقائيًا';
        } else throw new InvalidArgumentException('عملية غير مدعومة');
    }catch(Throwable $e){http_response_code(422);$flash='تعذر التنفيذ: '.$e->getMessage();}
}
$contacts=pilot_read('contacts');$campaigns=pilot_read('campaigns');
function safe(string $v):string{return htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
?><!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>إدارة المكالمات | OnTrack Voice</title><style>
:root{color-scheme:dark;--bg:#0b101b;--card:#182337;--line:#304057;--red:#e63d58;--muted:#a6b8ce}
*{box-sizing:border-box}body{margin:0;font-family:system-ui,sans-serif;background:radial-gradient(ellipse at 85% 0,#233550,var(--bg) 65%);color:#f3f6fb}
main{max-width:1180px;margin:auto;padding:24px 16px 50px}a{color:#ff8798}header{display:flex;align-items:center;justify-content:space-between;gap:15px;flex-wrap:wrap}
h1{font-size:clamp(24px,4vw,36px);margin:22px 0 6px}h2{font-size:19px;margin:0 0 14px}p{color:var(--muted);line-height:1.8}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(310px,1fr));gap:18px;margin:20px 0}
.card{border:1px solid var(--line);border-radius:20px;padding:23px;background:linear-gradient(145deg,#1c2940,var(--card));box-shadow:0 20px 60px #02060f22}
input,textarea,button{font:inherit;border-radius:12px;width:100%;padding:12px;margin:8px 0}
input,textarea{background:#0d1726;border:1px solid var(--line);color:#fff}textarea{min-height:170px}
button{background:var(--red);border:0;color:#fff;cursor:pointer;font-weight:700}button:focus-visible{outline:2px solid white}
small{color:#a9bbcf}table{width:100%;border-collapse:collapse}td,th{border-bottom:1px solid var(--line);padding:11px;text-align:right;font-size:13px}
.wrap{overflow:auto}.flag{display:inline-block;border-radius:8px;padding:5px 9px;background:#493e29;color:#ffd897}
</style></head><body><main><header><strong>OnTrack <span style="color:#f27690">VOICE OPS</span></strong><nav><a href="/saas/mobile/">لوحة التحكم</a> · <a href="/saas/mobile/calls.php">سجل المكالمات</a></nav></header>
<h1>مركز حملات الاتصال</h1><p>الإدارة التجريبية لجهات الاتصال والجداول. الحملة تبقى مسودة حتى إثبات مُنفّذ الاتصال على شريحة Android.</p>
<?php if($flash!==''):?><p role="status" style="color:#f8b2bd"><?=safe($flash)?></p><?php endif;?>
<div class="grid">
<section class="card"><h2>استيراد جهات الاتصال</h2><p>الصق رقمًا في كل سطر أو اسم،رقم. يقبل أرقام مصر والدولي بصيغة E.164، ويحذف المكرر. لن نرفع جهات الاتصال من الهاتف دون تأكيدك.</p>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=safe($_SESSION['csrf'])?>"><input type="hidden" name="action" value="import">
<textarea name="numbers" placeholder="أحمد,+2010XXXXXXXX&#10;+2011XXXXXXXX" maxlength="180000"></textarea>
<label>أو ارفع ملف CSV / TXT / XLSX حتى 1 MB<input type="file" name="upload" accept=".csv,.txt,.xlsx"></label>
<label><input style="width:auto" type="checkbox" name="consent" value="1" required> أؤكد وجود موافقة على التواصل مع الأرقام المضافة.</label>
<button>استيراد الأرقام</button></form></section>
<section class="card"><h2>جدولة حملة جديدة</h2><p>تحديد الاسم وبداية ونهاية الجدولة بتوقيت القاهرة والسيناريو. لن يتم طلب اتصال حتى تفعيل وإثبات منفذ المكالمات.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=safe($_SESSION['csrf'])?>"><input type="hidden" name="action" value="campaign">
<label>اسم الحملة<input name="name" maxlength="160" required></label><label>بداية الحملة<input type="datetime-local" name="starts" required></label>
<label>نهاية الحملة<input type="datetime-local" name="ends" required></label><label>نص السيناريو<textarea name="scenario" maxlength="8000" required></textarea></label>
<button>حفظ مسودة الحملة</button></form></section>
</div>
<div class="grid"><section class="card"><h2>جهات الاتصال (<?=count($contacts)?>)</h2>
<div class="wrap"><table><thead><tr><th>الاسم</th><th>الهاتف</th><th>الاتصال</th></tr></thead><tbody>
<?php foreach(array_slice(array_reverse($contacts),0,100) as $c):?><tr><td><?=safe((string)$c['name'])?></td><td dir="ltr"><?=safe((string)$c['phone'])?></td><td><form method="post">
<input type="hidden" name="csrf" value="<?=safe($_SESSION['csrf'])?>"><input type="hidden" name="action" value="dnc">
<input type="hidden" name="id" value="<?=safe((string)$c['id'])?>"><input type="hidden" name="blocked" value="<?=empty($c['do_not_call'])?'1':'0'?>">
<button style="padding:7px;font-size:11px;background:<?=empty($c['do_not_call'])?'#783448':'#246455'?>"><?=empty($c['do_not_call'])?'لا تتصل مجددًا':'إلغاء الحظر'?></button>
</form></td></tr><?php endforeach;?></tbody></table></div></section>
<section class="card"><h2>الحملات (<?=count($campaigns)?>)</h2><div class="wrap"><table><thead><tr><th>الحملة</th><th>البدء</th><th>الحالة</th></tr></thead><tbody>
<?php foreach(array_reverse($campaigns) as $c):?><tr><td><?=safe((string)$c['name'])?></td><td><?=safe((string)$c['starts_at'])?></td><td><span class="flag"><?=safe((string)$c['status'])?></span>
<?php if(($c['status']??'')!=='cancelled'):?>
<form method="post" style="display:flex;gap:5px;flex-wrap:wrap">
<input type="hidden" name="csrf" value="<?=safe($_SESSION['csrf'])?>">
<input type="hidden" name="action" value="campaign_control">
<input type="hidden" name="id" value="<?=safe((string)$c['id'])?>">
<?php foreach((($c['status']??'')==='paused'?['resume','cancel']:(($c['status']??'')==='draft'?['arm','pause','cancel']:['pause','cancel'])) as $operation):?>
<button type="submit" name="operation" value="<?=safe($operation)?>" style="padding:7px 10px;width:auto;font-size:11px"><?=safe($operation)?></button>
<?php endforeach;?>
</form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div>
<p><small>إجراء مكالمة SIM تلقائيًا لم يُفعَّل في هذه النسخة. لا توجد اتصالات مخفية أو مكررة.</small></p></section>
<section class="card" style="margin-top:20px"><h2>نتائج محاولات الاتصال من الشريحة</h2>
<p>حالات فعلية حسب إقرارات Android، بدون افتراض الرد أو رضا العميل.</p><div class="wrap"><table>
<thead><tr><th>الحملة</th><th>اسم العميل</th><th>الرقم</th><th>الحالة</th><th>وقت المحاولة</th></tr></thead><tbody>
<?php foreach(array_slice(array_reverse(pilot_read('attempts')),0,100) as $attempt):?>
<tr><td><?=safe((string)$attempt['campaign_id'])?></td>
<td><?=safe((string)$attempt['name'])?></td>
<td dir="ltr"><?=safe((string)$attempt['phone'])?></td>
<td><?=safe((string)$attempt['status'])?></td>
<td><?=safe((string)$attempt['created_at'])?></td></tr>
<?php endforeach;?></tbody></table></div></section>
</div></main></body></html>
