<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/VoiceKnowledge.php';
if(is_dir('/home/appontrack'))throw new RuntimeException('CI fixture only');
function verify_kb(bool $ok,string $case):void {if(!$ok)throw new RuntimeException('KB_V1_FAIL '.$case);}
seed_console();
$s=one("SELECT id FROM sites WHERE account_id=4 AND status='active' LIMIT 1");
verify_kb((bool)$s,'fixture');$site=(int)$s['id'];
$folder=voice_knowledge_folder_add(4,$site,'سياسات الحجز');
verify_kb(voice_knowledge_folder_add(4,$site,'سياسات الحجز')===$folder,'idempotent folder');
$child=voice_knowledge_folder_add(4,$site,'حجوزات المتجر',$folder);
$nested=false;try{voice_knowledge_folder_add(4,$site,'مستوى ثالث',$child);}catch(InvalidArgumentException){$nested=true;}
verify_kb($nested,'depth guard');
$cross=false;try{voice_knowledge_folder_add(5,$site,'اختراق');}catch(InvalidArgumentException){$cross=true;}
verify_kb($cross,'tenant boundary');
$id=voice_knowledge_add(4,$site,'مواعيد العيادة','md','المواعيد المتاحة من خلال نظام الحجز فقط.','public',$folder);
verify_kb($id>0,'insert');
verify_kb(voice_knowledge_add(4,$site,'نسخة مكررة','md','المواعيد المتاحة من خلال نظام الحجز فقط.','public',$folder)===$id,'dedupe');
verify_kb(str_contains(voice_knowledge_context(4,$site),'نظام الحجز'),'public knowledge');
$private=voice_knowledge_add(4,$site,'مصدر داخلي','note','سر اختبار لا يصل للمكالمات','staff',$folder);
verify_kb(!str_contains(voice_knowledge_context(4,$site),'سر اختبار'),'internal isolation');
verify_kb(voice_knowledge_context(5,$site)==='','cross-tenant context');
$blocked=false;try{voice_knowledge_folder_delete(4,$site,$folder);}catch(InvalidArgumentException){$blocked=true;}
verify_kb($blocked,'nonempty folder');
verify_kb(voice_knowledge_update(4,$site,$id,'المواعيد تؤكد بعد استلام API ID')===2,'version increment');
verify_kb((int)one('SELECT version FROM voice_knowledge_sources WHERE id=?',[$id])['version']===2,'version persisted');
verify_kb(str_contains((string)one('SELECT content FROM voice_knowledge_revisions WHERE source_id=? AND version=1',[$id])['content'],'نظام الحجز'),'old revision retained');
verify_kb(!str_contains(voice_knowledge_context(4,$site),'المواعيد المتاحة من خلال نظام الحجز فقط.'),'current context only');
verify_kb(voice_knowledge_delete(4,$id),'archive');
verify_kb(!str_contains(voice_knowledge_context(4,$site),'المواعيد تؤكد'),'archived excluded');
verify_kb(voice_knowledge_delete(4,$private),'archive private');
verify_kb(voice_knowledge_folder_delete(4,$site,$child),'delete child');
verify_kb(voice_knowledge_folder_delete(4,$site,$folder),'delete empty folder');
$temp=tempnam(sys_get_temp_dir(),'ontrack-kb-');
file_put_contents($temp,"اسم العميل: أحمد\nسياسة الإرجاع: يومين");
try{
    [$kind,$text]=voice_knowledge_extract_file('faq.txt',$temp,(int)filesize($temp));
    verify_kb($kind==='txt'&&str_contains($text,'سياسة الإرجاع'),'plain text parser');
    $bad=false;try{voice_knowledge_extract_file('bad.exe',$temp,(int)filesize($temp));}catch(InvalidArgumentException){$bad=true;}
    verify_kb($bad,'unsupported upload');
}finally{unlink($temp);}
if(class_exists('ZipArchive')){
    $doc=tempnam(sys_get_temp_dir(),'ontrack-docx-');
    try{
        $zip=new ZipArchive();verify_kb($zip->open($doc,ZipArchive::OVERWRITE)===true,'open docx');
        $zip->addFromString('word/document.xml','<w:document xmlns:w="test"><w:p><w:r><w:t>اتصل</w:t></w:r></w:p><w:p><w:r><w:t>بالمكتب</w:t></w:r></w:p></w:document>');
        $zip->close();
        [$kind,$text]=voice_knowledge_extract_file('help.docx',$doc,(int)filesize($doc));
        verify_kb($kind==='docx'&&str_contains($text,'اتصل')&&str_contains($text,'بالمكتب'),'docx extraction');
    }finally{unlink($doc);}
}
echo "VOICE_KNOWLEDGE_V1_PASS\n";
