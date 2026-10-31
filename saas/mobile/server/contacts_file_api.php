<?php
declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/pilot_workspace.php';
require __DIR__.'/pilot_import_file.php';
mobile_require_device();
if($_SERVER['REQUEST_METHOD']!=='POST')mobile_json(['ok'=>false,'error'=>'method_not_allowed'],405);
if(($_POST['confirmed']??'')!=='1'||($_POST['consent']??'')!=='1')mobile_json(['ok'=>false,'error'=>'explicit_confirmation_required'],422);
$file=$_FILES['contacts']??null;
if(!is_array($file)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||
    !is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name']))mobile_json(['ok'=>false,'error'=>'file_required'],422);
$size=(int)($file['size']??0);
if($size<1||$size>1048576)mobile_json(['ok'=>false,'error'=>'invalid_file_size'],413);
$name=(string)($file['name']??'');
if(strlen($name)>160||!preg_match('/\\.(csv|txt|xlsx)$/iD',$name))mobile_json(['ok'=>false,'error'=>'file_type'],422);
try{
    $rows=pilot_file_rows($file['tmp_name'],$name);
    if(!$rows)mobile_json(['ok'=>false,'error'=>'empty_file'],422);
    $counts=pilot_import_contacts($rows);
    mobile_json(['ok'=>true,'counts'=>$counts]);
}catch(InvalidArgumentException $e){
    mobile_json(['ok'=>false,'error'=>'invalid_file_contents'],422);
}catch(Throwable $e){
    mobile_json(['ok'=>false,'error'=>'file_import_failed'],500);
}
