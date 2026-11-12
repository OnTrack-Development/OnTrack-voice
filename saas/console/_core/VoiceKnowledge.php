<?php
declare(strict_types=1);

/** Knowledge is tenant/site scoped. No staff-only source may reach a public call. */
function voice_knowledge_site(int $accountId,int $siteId):void {
    if($accountId<=0||$siteId<=0||!one("SELECT s.id FROM sites s
        JOIN accounts a ON a.id=s.account_id AND a.status='active'
        WHERE s.id=? AND s.account_id=? AND s.status='active'",[$siteId,$accountId]))
        throw new InvalidArgumentException('site_unavailable');
}
function voice_knowledge_folder(int $accountId,int $siteId,int $folderId):void {
    if($folderId===0)return;
    if($folderId<0||!one('SELECT id FROM voice_knowledge_folders WHERE id=? AND account_id=? AND site_id=?',
         [$folderId,$accountId,$siteId]))throw new InvalidArgumentException('knowledge_folder_unavailable');
}
function voice_knowledge_folder_add(int $accountId,int $siteId,string $name,int $parentId=0):int {
    voice_knowledge_site($accountId,$siteId);
    $name=trim($name);
    if($name===''||mb_strlen($name)>100)throw new InvalidArgumentException('knowledge_folder_name_invalid');
    voice_knowledge_folder($accountId,$siteId,$parentId);
    if($parentId>0){
        $parent=one('SELECT parent_id FROM voice_knowledge_folders WHERE id=? AND account_id=? AND site_id=?',
            [$parentId,$accountId,$siteId]);
        if((int)$parent['parent_id']!==0)throw new InvalidArgumentException('knowledge_folder_depth_limit');
    }
    $prior=one('SELECT id FROM voice_knowledge_folders WHERE account_id=? AND site_id=? AND parent_id=? AND name=?',
         [$accountId,$siteId,$parentId,$name]);
    if($prior)return (int)$prior['id'];
    return insert('INSERT INTO voice_knowledge_folders(account_id,site_id,parent_id,name,created_at) VALUES (?,?,?,?,?)',
        [$accountId,$siteId,$parentId,$name,now()]);
}
function voice_knowledge_folder_delete(int $accountId,int $siteId,int $folderId):bool {
    voice_knowledge_folder($accountId,$siteId,$folderId);
    if($folderId===0)throw new InvalidArgumentException('knowledge_root_folder');
    if(one('SELECT id FROM voice_knowledge_folders WHERE account_id=? AND site_id=? AND parent_id=? LIMIT 1',
        [$accountId,$siteId,$folderId])||
       one("SELECT id FROM voice_knowledge_sources WHERE account_id=? AND site_id=? AND folder_id=? AND status='active' LIMIT 1",
        [$accountId,$siteId,$folderId]))throw new InvalidArgumentException('knowledge_folder_not_empty');
    return execute('DELETE FROM voice_knowledge_folders WHERE id=? AND account_id=? AND site_id=?',
        [$folderId,$accountId,$siteId])===1;
}
function voice_knowledge_validate(string $name,string $kind,string $content,string $visibility):string {
    if(!in_array($visibility,['public','staff'],true)||
       !in_array($kind,['txt','md','csv','json','note','pdf','docx'],true)||
       trim($name)===''||mb_strlen($name)>150||
       !mb_check_encoding($content,'UTF-8'))
        throw new InvalidArgumentException('knowledge_invalid_content');
    $content=trim(str_replace("\0",'',$content));
    if($content===''||strlen($content)>180000)
        throw new InvalidArgumentException('knowledge_invalid_content');
    return $content;
}
/**
 * Intentionally truthful capability check for the Linux production host.
 * Poppler's pdftotext is required for reliable multi-page text PDF support.
 */
function voice_knowledge_pdf_extractor():?string {
    if(!function_exists('proc_open'))return null;
    foreach(['/usr/bin/pdftotext','/bin/pdftotext'] as $candidate)
        if(is_file($candidate)&&is_executable($candidate))return $candidate;
    return null;
}
/** Extract text only; never store/execute the uploaded original binary. */
function voice_knowledge_extract_file(string $filename,string $path,int $size):array {
    if($size<1||$size>2097152||!is_file($path)||!is_readable($path))
        throw new InvalidArgumentException('knowledge_file_size_invalid');
    $kind=strtolower(pathinfo($filename,PATHINFO_EXTENSION));
    if(!in_array($kind,['txt','md','csv','json','pdf','docx'],true))
        throw new InvalidArgumentException('knowledge_file_type_invalid');
    if(in_array($kind,['txt','md','csv','json'],true)){
        if($size>180000)throw new InvalidArgumentException('knowledge_text_file_too_large');
        $content=(string)file_get_contents($path);
    }elseif($kind==='docx'){
        if(!class_exists('ZipArchive'))throw new RuntimeException('knowledge_docx_requires_zip');
        $zip=new ZipArchive();
        if($zip->open($path)!==true)throw new InvalidArgumentException('knowledge_docx_invalid');
        try{
            $index=$zip->locateName('word/document.xml');
            if($index===false)throw new InvalidArgumentException('knowledge_docx_missing_document');
            $info=$zip->statIndex($index);
            if(!is_array($info)||($info['size']??0)>1500000||($info['size']??0)<1)
                throw new InvalidArgumentException('knowledge_docx_text_too_large');
            $xml=$zip->getFromIndex($index);
            if(!is_string($xml)||strlen($xml)>1500000)throw new InvalidArgumentException('knowledge_docx_invalid');
            $xml=preg_replace('~</w:p>~i',"\n",$xml);
            $xml=preg_replace('~<w:(?:tab|br)\b[^>]*/>~i',' ',$xml);
            $content=html_entity_decode(strip_tags((string)$xml),ENT_QUOTES|ENT_XML1,'UTF-8');
        }finally{$zip->close();}
    }else{
        // Only approved executable paths are used; never execute a caller's
        // filename as a shell command. Scanned/image PDFs require OCR and are
        // deliberately not described as searchable until real text exists.
        $binary=voice_knowledge_pdf_extractor();
        if($binary===null)throw new RuntimeException('knowledge_pdf_extractor_unavailable');
        $pipes=[];
        $process=@proc_open([$binary,'-f','1','-l','50','-enc','UTF-8','-nopgbrk',$path,'-'],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
        if(!is_resource($process))throw new RuntimeException('knowledge_pdf_extractor_unavailable');
        fclose($pipes[0]);
        $content=(string)stream_get_contents($pipes[1],250000);
        fclose($pipes[1]);
        $error=(string)stream_get_contents($pipes[2],4000);
        fclose($pipes[2]);
        if(proc_close($process)!==0)throw new InvalidArgumentException('knowledge_pdf_unreadable');
    }
    $content=trim(preg_replace('/[ \t]+/u',' ',$content)??$content);
    $content=voice_knowledge_validate($filename,$kind,$content,'public');
    return [$kind,$content];
}
function voice_knowledge_add(int $accountId,int $siteId,string $name,string $kind,string $content,
    string $visibility='public',int $folderId=0):int {
    voice_knowledge_site($accountId,$siteId);
    voice_knowledge_folder($accountId,$siteId,$folderId);
    $content=voice_knowledge_validate($name,$kind,$content,$visibility);
    $hash=hash('sha256',$content);
    $prior=one("SELECT id FROM voice_knowledge_sources WHERE account_id=? AND site_id=? AND folder_id=? AND sha256=? AND visibility=? AND status='active'",
        [$accountId,$siteId,$folderId,$hash,$visibility]);
    if($prior)return (int)$prior['id'];
    // Same title and folder, changed facts: require explicit approval before calls see it.
    $different=one("SELECT id FROM voice_knowledge_sources WHERE account_id=? AND site_id=?
        AND folder_id=? AND name=? AND sha256<>? AND visibility=? AND status='active' LIMIT 1",
        [$accountId,$siteId,$folderId,trim($name),$hash,$visibility]);
    $status=$different?'review':'active';
    return insert("INSERT INTO voice_knowledge_sources
        (account_id,site_id,name,kind,content,visibility,status,sha256,created_at,folder_id,version,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,1,?)",
        [$accountId,$siteId,trim($name),$kind,$content,$visibility,$status,$hash,now(),$folderId,now()]);
}
function voice_knowledge_update(int $accountId,int $siteId,int $sourceId,string $content):int {
    voice_knowledge_site($accountId,$siteId);
    $source=one("SELECT * FROM voice_knowledge_sources WHERE id=? AND account_id=? AND site_id=? AND status='active'",
        [$sourceId,$accountId,$siteId]);
    if(!$source)throw new InvalidArgumentException('knowledge_source_unavailable');
    $content=voice_knowledge_validate((string)$source['name'],(string)$source['kind'],$content,(string)$source['visibility']);
    $hash=hash('sha256',$content);
    if(hash_equals((string)$source['sha256'],$hash))return (int)($source['version']??1);
    db()->beginTransaction();
    try{
        $current=one("SELECT * FROM voice_knowledge_sources WHERE id=? AND account_id=? AND site_id=? AND status='active'",
            [$sourceId,$accountId,$siteId]);
        if(!$current||(string)$current['sha256']!==(string)$source['sha256'])
            throw new RuntimeException('knowledge_concurrent_edit');
        $oldVersion=(int)($current['version']??1);
        insert("INSERT INTO voice_knowledge_revisions
            (source_id,account_id,site_id,version,content,sha256,created_at) VALUES (?,?,?,?,?,?,?)",
            [$sourceId,$accountId,$siteId,$oldVersion,$current['content'],$current['sha256'],now()]);
        $updated=execute("UPDATE voice_knowledge_sources SET content=?,sha256=?,version=?,updated_at=?
            WHERE id=? AND account_id=? AND site_id=? AND sha256=? AND status='active'",
            [$content,$hash,$oldVersion+1,now(),$sourceId,$accountId,$siteId,$current['sha256']]);
        if($updated!==1)throw new RuntimeException('knowledge_concurrent_edit');
        db()->commit();
        return $oldVersion+1;
    }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();throw $error;}
}
function voice_knowledge_context(int $accountId,int $siteId,int $max=7000):string {
    $rows=all("SELECT name,content,version FROM voice_knowledge_sources
        WHERE account_id=? AND site_id=? AND status='active' AND visibility='public' ORDER BY id DESC LIMIT 12",
        [$accountId,$siteId]);
    $output='';$max=max(0,min(12000,$max));
    foreach($rows as $row){
        $item="\n[مصدر معرفة: ".mb_substr((string)$row['name'],0,90)." · revision ".(int)($row['version']??1)."]\n".(string)$row['content'];
        $room=$max-mb_strlen($output);
        if($room<=0)break;
        $output.=mb_substr($item,0,$room);
    }
    return $output;
}
/** A pending contradictory source is never sent to the agent until owner approval. */
function voice_knowledge_approve(int $accountId,int $siteId,int $sourceId):bool {
    voice_knowledge_site($accountId,$siteId);
    if(function_exists('voice_kb_web_init'))voice_kb_web_init();
    db()->beginTransaction();
    try{
        $pending=one("SELECT * FROM voice_knowledge_sources WHERE id=? AND account_id=? AND site_id=? AND status='review'",
            [$sourceId,$accountId,$siteId]);
        if(!$pending){db()->rollBack();return false;}
        $webOrigin='';
        if(function_exists('voice_kb_web_init')){
            $meta=one("SELECT origin_url FROM voice_kb_meta
              WHERE source_id=? AND account_id=? AND site_id=?",
              [$sourceId,$accountId,$siteId]);
            $webOrigin=(string)($meta['origin_url']??'');
        }
        if($webOrigin!==''){
            // Approving a URL revision must not archive an unrelated source
            // with the same title from another URL or a manual upload.
            execute("UPDATE voice_knowledge_sources SET status='archived',updated_at=?
              WHERE account_id=? AND site_id=? AND folder_id=? AND name=?
                AND visibility=? AND status='active'
                AND id IN (SELECT source_id FROM voice_kb_meta
                  WHERE account_id=? AND site_id=? AND origin_url=?)",
              [now(),$accountId,$siteId,$pending['folder_id'],$pending['name'],
               $pending['visibility'],$accountId,$siteId,$webOrigin]);
        }else{
            execute("UPDATE voice_knowledge_sources SET status='archived',updated_at=?
              WHERE account_id=? AND site_id=? AND folder_id=? AND name=? AND visibility=? AND status='active'",
              [now(),$accountId,$siteId,$pending['folder_id'],$pending['name'],$pending['visibility']]);
        }
        $count=execute("UPDATE voice_knowledge_sources SET status='active',updated_at=?
            WHERE id=? AND account_id=? AND site_id=? AND status='review'",
            [now(),$sourceId,$accountId,$siteId]);
        if($count!==1)throw new RuntimeException('knowledge_approval_race');
        if($webOrigin!=='')
            execute("UPDATE voice_kb_web_links SET status='current',last_error=NULL
              WHERE account_id=? AND site_id=? AND source_id=? AND last_hash=?",
              [$accountId,$siteId,$sourceId,$pending['sha256']]);
        db()->commit();return true;
    }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();throw $error;}
}
function voice_knowledge_delete(int $accountId,int $sourceId):bool {
    return execute("UPDATE voice_knowledge_sources SET status='archived',updated_at=? WHERE id=? AND account_id=? AND status IN ('active','review')",
        [now(),$sourceId,$accountId])===1;
}
