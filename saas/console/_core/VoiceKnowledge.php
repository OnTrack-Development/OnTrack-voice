<?php
declare(strict_types=1);
/** Only content explicitly approved for the selected tenant/site. */
function voice_knowledge_add(int $accountId,int $siteId,string $name,string $kind,string $content):int {
    if(!in_array($kind,['txt','md','csv','json','note'],true)||
       trim($name)===''||mb_strlen($name)>150||trim($content)===''||
       strlen($content)>180000||!mb_check_encoding($content,'UTF-8'))
        throw new InvalidArgumentException('knowledge_invalid_content');
    if(!one("SELECT id FROM sites WHERE id=? AND account_id=? AND status='active'",[$siteId,$accountId]))
        throw new InvalidArgumentException('site_unavailable');
    return insert("INSERT INTO voice_knowledge_sources
        (account_id,site_id,name,kind,content,status,sha256,created_at)
        VALUES (?,?,?,?,?,'active',?,?)",
        [$accountId,$siteId,$name,$kind,$content,hash('sha256',$content),now()]);
}
function voice_knowledge_context(int $accountId,int $siteId,int $max=7000):string {
    $rows=all("SELECT name,content FROM voice_knowledge_sources
        WHERE account_id=? AND site_id=? AND status='active' ORDER BY id DESC LIMIT 12",[$accountId,$siteId]);
    $output='';$max=max(0,min(12000,$max));
    foreach($rows as $row){
        $item="\n[مصدر معرفة: ".mb_substr((string)$row['name'],0,90)."]\n".(string)$row['content'];
        $room=$max-mb_strlen($output);
        if($room<=0)break;
        $output.=mb_substr($item,0,$room);
    }
    return $output;
}
function voice_knowledge_delete(int $accountId,int $sourceId):bool {
    return execute('DELETE FROM voice_knowledge_sources WHERE id=? AND account_id=?',[$sourceId,$accountId])===1;
}
