<?php
declare(strict_types=1);

/**
 * Account-scoped white-label logo display + safe uploads.
 * Store original raster images outside public_html and serve only through
 * authenticated /brand-logo and public reseller /portal-logo routes.
 */
function brand_identity_init():void {
    static $ready=false;
    if($ready)return;
    $engine=driver()==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    db()->exec("CREATE TABLE IF NOT EXISTS brand_identity_settings (
        account_id BIGINT PRIMARY KEY,
        display_mode VARCHAR(8) NOT NULL DEFAULT 'both',
        updated_at VARCHAR(19) NOT NULL
    )".$engine);
    $ready=true;
}
function brand_identity_owner_id(array $a):int {
    if(($a['kind']??'')==='customer' && (int)($a['parent_id']??0)>0){
        $reseller=account((int)$a['parent_id']);
        if($reseller && ($reseller['kind']??'')==='reseller')return (int)$reseller['id'];
    }
    return (int)($a['account_id']??$a['id']??0);
}
function brand_identity_mode(array $a):string {
    brand_identity_init();
    $id=brand_identity_owner_id($a);
    if($id<1)return 'both';
    $row=one('SELECT display_mode FROM brand_identity_settings WHERE account_id=?',[$id]);
    return in_array($row['display_mode']??'', ['image','text','both'],true)
        ?(string)$row['display_mode']:'both';
}
function brand_identity_brand(array $a):array {
    $source=$a;
    if(($a['kind']??'')==='customer' && (int)($a['parent_id']??0)>0){
        $parent=account((int)$a['parent_id']);
        if($parent && ($parent['kind']??'')==='reseller')$source=$parent;
    }
    if(in_array(($source['kind']??''),['platform','reseller'],true)){
        return ['name'=>(string)($source['brand_name']?:$source['name']),
            'logo'=>(string)($source['logo_path']??''),
            'accent'=>'#cb2925'];
    }
    return brand($a);
}
function brand_identity_edit_allowed(array $a):bool {
    return in_array($a['kind']??'',['platform','reseller'],true) &&
        in_array($a['role']??'',['owner','admin'],true) &&
        console_view_mode($a)==='admin' && !active_support() &&
        (int)($a['account_id']??0)>0 && (int)($a['account_id']??0)===(int)($a['id']??0);
}
function brand_identity_save(array $a,string $title,string $mode):void {
    if(!brand_identity_edit_allowed($a))throw new InvalidArgumentException('لا تملك صلاحية تعديل العلامة التجارية.');
    $title=trim($title);
    if($title===''||mb_strlen($title)>100)throw new InvalidArgumentException('اسم الشركة مطلوب (100 حرف بحد أقصى).');
    if(!in_array($mode,['image','text','both'],true))throw new InvalidArgumentException('طريقة عرض الشعار غير صالحة.');
    brand_identity_init();
    $id=(int)$a['account_id'];
    // Image-only requires an actual uploaded logo, never a colored placeholder.
    $row=account($id);
    if($mode==='image' && empty($row['logo_path']))
        throw new InvalidArgumentException('ارفع صورة الشعار أولاً، وبعدها اختار صورة فقط.');
    db()->beginTransaction();
    try{
        execute('UPDATE accounts SET brand_name=? WHERE id=?',[$title,$id]);
        if(one('SELECT account_id FROM brand_identity_settings WHERE account_id=?',[$id])){
            execute('UPDATE brand_identity_settings SET display_mode=?,updated_at=? WHERE account_id=?',
                [$mode,now(),$id]);
        }else{
            insert('INSERT INTO brand_identity_settings(account_id,display_mode,updated_at) VALUES(?,?,?)',
                [$id,$mode,now()]);
        }
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function brand_identity_image_validate(string $bytes):string {
    $length=strlen($bytes);
    if($length<48||$length>3*1024*1024)
        throw new InvalidArgumentException('حجم الصورة يجب ألا يزيد عن 3 ميجابايت. قلّل حجمها وجرّب مرة أخرى.');
    $info=@getimagesizefromstring($bytes);
    if(!is_array($info)||!isset($info[0],$info[1])||
       (int)$info[0]<16||(int)$info[1]<16||
       (int)$info[0]>8192||(int)$info[1]>8192||
       (int)$info[0]*(int)$info[1]>25000000)
        throw new InvalidArgumentException('أبعاد الصورة غير صالحة. الحد الأقصى 8192 بكسل و25 مليون بكسل.');
    $type=(string)($info['mime']??'');
    $extension=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'][$type]??null;
    if($extension===null)throw new InvalidArgumentException('مسموح PNG أو JPEG أو WEBP فقط. SVG غير مدعوم للأمان.');
    // Prevent mismatching extensions and polyglot input being served as HTML.
    $finfo=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    if($finfo!==$type)throw new InvalidArgumentException('نوع الصورة الفعلي لا يطابق محتواها.');
    return $extension;
}
function brand_identity_store_binary(array $a,string $bytes):string {
    if(!brand_identity_edit_allowed($a))
        throw new InvalidArgumentException('لا تملك صلاحية رفع شعار لهذا الحساب.');
    $extension=brand_identity_image_validate($bytes);
    $id=(int)$a['account_id'];
    $private=private_path();
    if(!is_dir($private)||!is_writable($private))
        throw new RuntimeException('مجلد الشعارات الخاص غير قابل للكتابة على الخادم.');
    $filename='logo-'.$id.'-'.bin2hex(random_bytes(6)).'.'.$extension;
    $file=$private.'/'.$filename;
    // Exclusive create: never overwrite anyone's image.
    $handle=@fopen($file,'x+b');
    if(!$handle)throw new RuntimeException('تعذر إنشاء ملف الشعار في المسار الخاص.');
    try{
        @chmod($file,0600);
        $offset=0;$total=strlen($bytes);
        while($offset<$total){
            $written=fwrite($handle,substr($bytes,$offset,262144));
            if($written===false||$written===0)throw new RuntimeException('تعذّرت كتابة الشعار بالكامل.');
            $offset+=$written;
        }
        fflush($handle);
    }catch(Throwable $e){
        fclose($handle);@unlink($file);throw $e;
    }
    fclose($handle);
    if(!is_file($file)||filesize($file)!==strlen($bytes)){
        @unlink($file);throw new RuntimeException('فشل التحقق من حفظ الصورة بعد رفعها.');
    }
    try{
        $previous=account($id);
        execute('UPDATE accounts SET logo_path=? WHERE id=?',[$filename,$id]);
    }catch(Throwable $e){@unlink($file);throw $e;}
    $old=(string)($previous['logo_path']??'');
    if($old!=='' && preg_match('/^logo-'.preg_quote((string)$id,'/').'-[a-f0-9]{12}\\.(png|jpg|webp)$/D',$old)){
        $oldPath=$private.'/'.$old;
        if(is_file($oldPath) && !is_link($oldPath))@unlink($oldPath);
    }
    return $filename;
}
function brand_identity_legacy_upload(array $a,array $upload):?string {
    $error=(int)($upload['error']??UPLOAD_ERR_NO_FILE);
    if($error===UPLOAD_ERR_NO_FILE)return null;
    if($error!==UPLOAD_ERR_OK)throw new InvalidArgumentException(
        'رفع الصورة من المتصفح فشل (الكود '.$error.'). جرّب زر الرفع المباشر بدون multipart.');
    $tmp=(string)($upload['tmp_name']??'');
    if(!is_uploaded_file($tmp))throw new InvalidArgumentException('ملف الرفع غير موثوق.');
    $size=(int)($upload['size']??0);
    if($size>3*1024*1024)throw new InvalidArgumentException('حجم الشعار أكبر من 3MB.');
    $data=@file_get_contents($tmp,false,null,0,3*1024*1024+1);
    if($data===false)throw new RuntimeException('تعذر قراءة الصورة المرفوعة.');
    return brand_identity_store_binary($a,$data);
}
/** Raw image endpoint avoids multipart rules that may reject logo uploads with HTTP 403. */
function brand_identity_upload_http(array $a):never {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private,no-store');
    header('X-Content-Type-Options: nosniff');
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){
        http_response_code(405);echo '{"ok":false,"error":"POST required"}';exit;
    }
    if(!brand_identity_edit_allowed($a)){
        http_response_code(403);echo json_encode(['ok'=>false,'error'=>'غير مصرح برفع شعار هذا الحساب'],JSON_UNESCAPED_UNICODE);exit;
    }
    $clientToken=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
    if($clientToken===''||!hash_equals(csrf(),$clientToken)){
        http_response_code(419);echo json_encode(['ok'=>false,'error'=>'انتهت جلسة الدخول أو الحماية. حدّث الصفحة وأعد المحاولة.'],JSON_UNESCAPED_UNICODE);exit;
    }
    $size=(int)($_SERVER['CONTENT_LENGTH']??0);
    if($size<=0||$size>3*1024*1024){
        http_response_code(413);echo json_encode(['ok'=>false,'error'=>'الصورة كبيرة؛ الحد الأقصى 3 ميجابايت.'],JSON_UNESCAPED_UNICODE);exit;
    }
    try{
        $bytes=file_get_contents('php://input',false,null,0,3*1024*1024+1);
        if(!is_string($bytes)||strlen($bytes)!==$size)
            throw new InvalidArgumentException('لم تصل الصورة كاملة. جرّب شبكة مختلفة أو صورة أصغر.');
        $file=brand_identity_store_binary($a,$bytes);
        audit('brand.logo.uploaded',(int)$a['account_id'],['filename'=>$file,'size'=>$size]);
        echo json_encode(['ok'=>true,'message'=>'تم رفع الشعار بنجاح.','logo_url'=>'/saas/console/brand-logo?v='.rawurlencode($file)],
            JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }catch(Throwable $e){
        $invalid=$e instanceof InvalidArgumentException;
        http_response_code($invalid?422:500);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
    }
    exit;
}
