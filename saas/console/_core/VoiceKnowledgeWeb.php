<?php
declare(strict_types=1);
require_once __DIR__.'/VoiceKnowledge.php';
require_once __DIR__.'/VoiceKnowledgeSearch.php';
require_once __DIR__.'/VoiceConnectors.php';
/** Public HTTPS knowledge source with explicit owner review. No private URL fetching. */
function voice_kb_web_init():void {
    $marker=private_path().'/.schema.voice-kb-web-v1';
    if(is_file($marker))return;
    $mysql=driver()==='mysql';
    $pk=$mysql?'BIGINT AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $int=$mysql?'BIGINT':'INTEGER';
    $engine=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    db()->exec("CREATE TABLE IF NOT EXISTS voice_kb_meta (
      source_id $int PRIMARY KEY,account_id $int NOT NULL,site_id $int NOT NULL,
      scope_mode VARCHAR(10) NOT NULL DEFAULT 'all',
      priority INTEGER NOT NULL DEFAULT 100,expires_at VARCHAR(19) NULL,
      origin_url VARCHAR(500) NULL,updated_at VARCHAR(19) NOT NULL)".$engine);
    db()->exec("CREATE TABLE IF NOT EXISTS voice_kb_agent_scope (
      source_id $int NOT NULL,agent_id $int NOT NULL,
      account_id $int NOT NULL,site_id $int NOT NULL,
      PRIMARY KEY(source_id,agent_id))".$engine);
    db()->exec("CREATE TABLE IF NOT EXISTS voice_kb_web_links (
      id $pk,account_id $int NOT NULL,site_id $int NOT NULL,
      folder_id $int NOT NULL DEFAULT 0,
      url VARCHAR(500) NOT NULL,name VARCHAR(150) NOT NULL,
      visibility VARCHAR(10) NOT NULL DEFAULT 'public',
      source_id $int NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'draft',
      last_hash CHAR(64) NULL,last_error VARCHAR(90) NULL,
      last_fetched_at VARCHAR(19) NULL,next_check_at VARCHAR(19) NULL,
      interval_hours INTEGER NOT NULL DEFAULT 24,
      created_at VARCHAR(19) NOT NULL,
      UNIQUE(account_id,site_id,url))".$engine);
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}
function voice_kb_origin_url(string $url):string {
    $details=voice_connector_url(trim($url));
    $full=(string)$details['url'];
    if(strlen($full)>500||str_contains($full,'#'))throw new InvalidArgumentException('knowledge_url_invalid');
    $p=parse_url($full);
    $path=(string)($p['path']??'/');
    if(str_contains($path,'..')||str_contains($path,'//')||
      !preg_match('~^/[A-Za-z0-9._/%+-]*$~D',$path))
        throw new InvalidArgumentException('knowledge_url_path_invalid');
    return $full;
}
function voice_kb_web_add(int $accountId,int $siteId,string $name,string $url,
    int $folderId=0,string $visibility='public',int $intervalHours=24):int {
    voice_kb_web_init();
    voice_knowledge_site($accountId,$siteId);
    voice_knowledge_folder($accountId,$siteId,$folderId);
    $name=trim($name);$url=voice_kb_origin_url($url);
    if($name===''||mb_strlen($name)>150||
       !in_array($visibility,['public','staff'],true)||
       $intervalHours<1||$intervalHours>720)
        throw new InvalidArgumentException('knowledge_link_settings_invalid');
    $existing=one('SELECT id FROM voice_kb_web_links WHERE account_id=? AND site_id=? AND url=?',
        [$accountId,$siteId,$url]);
    if($existing)return (int)$existing['id'];
    return insert("INSERT INTO voice_kb_web_links(account_id,site_id,folder_id,url,name,visibility,
        status,interval_hours,created_at) VALUES (?,?,?,?,?,?,'draft',?,?)",
        [$accountId,$siteId,$folderId,$url,$name,$visibility,$intervalHours,now()]);
}
/** Reject unsafe HTTP, redirects, huge bodies and any non-HTML or non-text response. */
function voice_kb_web_fetch(string $url):string {
    $url=voice_kb_origin_url($url);
    $host=voice_connector_url($url)['host'];
    $ip=voice_connector_public_ip($host);
    if(!function_exists('curl_init'))throw new RuntimeException('knowledge_link_curl_missing');
    $ch=curl_init($url);
    curl_setopt_array($ch,[
      CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,
      CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10,
      CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_SSL_VERIFYPEER=>true,
      CURLOPT_HTTPHEADER=>['Accept: text/html,text/plain'],
      CURLOPT_PROXY=>'',CURLOPT_RESOLVE=>[$host.':443:'.$ip],
      CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_USERAGENT=>'OnTrack-Knowledge-Indexer/1.0'
    ]);
    try{$body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        $mime=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);}
    finally{curl_close($ch);}
    if(!is_string($body)||strlen($body)>180000||$status!==200)
        throw new RuntimeException('knowledge_link_http_unverified');
    $mime=strtolower(trim(explode(';',$mime)[0]));
    if(!in_array($mime,['text/plain','text/html'],true))
        throw new RuntimeException('knowledge_link_not_text');
    if($mime==='text/plain')$text=$body;
    else{
        if(!class_exists('DOMDocument'))throw new RuntimeException('knowledge_html_parser_unavailable');
        $doc=new DOMDocument();
        $previous=libxml_use_internal_errors(true);
        try{
            if(!@$doc->loadHTML('<?xml encoding="utf-8"?>'.$body,
                LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING))
                throw new RuntimeException('knowledge_html_invalid');
            foreach(['script','style','nav','footer','noscript','form','head'] as $tag){
                $items=$doc->getElementsByTagName($tag);
                for($i=$items->length-1;$i>=0;$i--){
                    $node=$items->item($i);if($node&&$node->parentNode)$node->parentNode->removeChild($node);
                }
            }
            $text=(string)($doc->getElementsByTagName('body')->item(0)?->textContent??'');
        }finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
    }
    $text=preg_replace('/[ \t\r\n]+/u',' ',$text);
    if(!is_string($text)||mb_strlen(trim($text))<30)
        throw new RuntimeException('knowledge_link_no_public_text');
    return mb_substr(trim($text),0,110000);
}
function voice_kb_web_sync(int $accountId,int $siteId,int $linkId,?callable $transport=null):array {
    voice_kb_web_init();voice_knowledge_site($accountId,$siteId);
    $link=one('SELECT * FROM voice_kb_web_links WHERE id=? AND account_id=? AND site_id=?',
        [$linkId,$accountId,$siteId]);
    if(!$link)throw new InvalidArgumentException('knowledge_link_not_owned');
    try{
        // Injectable transport for automated CI fixtures; production validates HTTPS DNS.
        $content=$transport!==null?$transport((string)$link['url']):voice_kb_web_fetch((string)$link['url']);
        if(!is_string($content))throw new RuntimeException('knowledge_link_invalid_content');
        $content=voice_knowledge_validate((string)$link['name'],'txt',$content,(string)$link['visibility']);
        $hash=hash('sha256',$content);
        // A refetch must never turn an unapproved draft into a "current" fact.
        // An archived/missing source is not current even when its hash matches.
        $linkedId=(int)($link['source_id']??0);
        $linked=$linkedId>0?one("SELECT k.status FROM voice_knowledge_sources k
            JOIN voice_kb_meta m ON m.source_id=k.id AND m.account_id=k.account_id AND m.site_id=k.site_id
            WHERE k.id=? AND k.account_id=? AND k.site_id=? AND m.origin_url=?",
            [$linkedId,$accountId,$siteId,$link['url']]):null;
        if(hash_equals((string)($link['last_hash']??''),$hash) &&
           $linked && in_array($linked['status'],['active','review'],true)){
            $linkStatus=$linked['status']==='active'?'current':'review';
            execute("UPDATE voice_kb_web_links SET status=?,last_fetched_at=?,
                next_check_at=?,last_error=NULL WHERE id=? AND account_id=? AND site_id=?",
                [$linkStatus,now(),gmdate('Y-m-d H:i:s',time()+(int)$link['interval_hours']*3600),
                 $linkId,$accountId,$siteId]);
            return ['status'=>$linkStatus==='current'?'unchanged':'review','source_id'=>$linkedId];
        }
        db()->beginTransaction();
        try{
            // Revisions only belong to the same URL, folder and visibility.
            // Reusing a same-named manual or foreign URL source can leak scope.
            $previous=$linkedId>0?one("SELECT m.scope_mode,m.priority,m.expires_at
                FROM voice_kb_meta m JOIN voice_knowledge_sources k
                  ON k.id=m.source_id AND k.account_id=m.account_id AND k.site_id=m.site_id
                WHERE m.source_id=? AND m.account_id=? AND m.site_id=?
                  AND m.origin_url=? AND k.status IN ('active','review')",
                [$linkedId,$accountId,$siteId,$link['url']]):null;
            $duplicate=one("SELECT k.id FROM voice_knowledge_sources k
                JOIN voice_kb_meta m ON m.source_id=k.id
                  AND m.account_id=k.account_id AND m.site_id=k.site_id
                WHERE k.account_id=? AND k.site_id=? AND k.folder_id=? AND k.name=?
                  AND k.visibility=? AND k.sha256=? AND m.origin_url=?
                  AND k.status IN ('review','active')",
                [$accountId,$siteId,$link['folder_id'],$link['name'],
                 $link['visibility'],$hash,$link['url']]);
            if($duplicate)$source=(int)$duplicate['id'];
            else $source=insert("INSERT INTO voice_knowledge_sources
                (account_id,site_id,name,kind,content,visibility,status,sha256,created_at,folder_id,version,updated_at)
                VALUES (?,?,?,'txt',?,?,'review',?,?,?,1,?)",
                [$accountId,$siteId,$link['name'],$content,$link['visibility'],$hash,
                  now(),$link['folder_id'],now()]);
            $meta=one('SELECT source_id FROM voice_kb_meta WHERE source_id=? AND account_id=?',
                [$source,$accountId]);
            if(!$meta){
                // A refreshed public source must inherit the reviewed Agent scope,
                // priority and expiry. Never silently widen 'agent' to 'all'.
                $scope=(string)($previous['scope_mode']??'all');
                $priority=(int)($previous['priority']??100);
                $expiresAt=$previous['expires_at']??null;
                insert("INSERT INTO voice_kb_meta
                  (source_id,account_id,site_id,scope_mode,priority,expires_at,origin_url,updated_at)
                  VALUES (?,?,?,?,?,?,?,?)",
                  [$source,$accountId,$siteId,$scope,$priority,$expiresAt,$link['url'],now()]);
                if($scope==='agent' && $linkedId>0){
                    $allowedAgents=all("SELECT agent_id FROM voice_kb_agent_scope
                      WHERE source_id=? AND account_id=? AND site_id=?",
                      [$linkedId,$accountId,$siteId]);
                    foreach($allowedAgents as $allowed)
                        insert("INSERT INTO voice_kb_agent_scope
                           (source_id,agent_id,account_id,site_id) VALUES (?,?,?,?)",
                           [$source,(int)$allowed['agent_id'],$accountId,$siteId]);
                }
            }
            execute("UPDATE voice_kb_web_links SET source_id=?,status='review',
                last_hash=?,last_fetched_at=?,next_check_at=?,last_error=NULL
                WHERE id=? AND account_id=? AND site_id=?",
                [$source,$hash,now(),gmdate('Y-m-d H:i:s',time()+(int)$link['interval_hours']*3600),
                 $linkId,$accountId,$siteId]);
            db()->commit();
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
        return ['status'=>'review','source_id'=>$source,
            'message'=>'Owner must approve before this content is used by an agent.'];
    }catch(Throwable $error){
        // Do not overwrite a previously reviewed live fact on failed refresh.
        // A failed URL must wait before retrying; otherwise it starves other
        // tenants/sites on every scheduled run. The approved source remains live.
        $retrySeconds=max(3600,min(86400,(int)$link['interval_hours']*3600));
        execute("UPDATE voice_kb_web_links SET status='error',last_error=?,last_fetched_at=?,
            next_check_at=? WHERE id=? AND account_id=? AND site_id=?",
            [mb_substr($error->getMessage(),0,90),now(),
             gmdate('Y-m-d H:i:s',time()+$retrySeconds),$linkId,$accountId,$siteId]);
        throw $error;
    }
}
function voice_kb_scope_set(int $accountId,int $siteId,int $sourceId,
    string $scopeMode,int $agentId=0,int $priority=100,int $ttlHours=0):void {
    voice_kb_web_init();voice_knowledge_site($accountId,$siteId);
    $src=one("SELECT id FROM voice_knowledge_sources WHERE id=? AND account_id=? AND site_id=? AND status IN ('active','review')",
        [$sourceId,$accountId,$siteId]);
    if(!$src||!in_array($scopeMode,['all','agent'],true)||$priority<1||$priority>1000||
        $ttlHours<0||$ttlHours>8760)throw new InvalidArgumentException('knowledge_scope_invalid');
    if($scopeMode==='agent'&&!one("SELECT id FROM agents WHERE id=? AND account_id=? AND site_id=? AND status!='archived'",
        [$agentId,$accountId,$siteId]))throw new InvalidArgumentException('knowledge_agent_not_owned');
    $expiry=$ttlHours===0?null:gmdate('Y-m-d H:i:s',time()+$ttlHours*3600);
    $meta=one('SELECT source_id FROM voice_kb_meta WHERE source_id=? AND account_id=?',[$sourceId,$accountId]);
    if($meta)execute("UPDATE voice_kb_meta SET scope_mode=?,priority=?,expires_at=?,updated_at=?
        WHERE source_id=? AND account_id=? AND site_id=?",
        [$scopeMode,$priority,$expiry,now(),$sourceId,$accountId,$siteId]);
    else insert("INSERT INTO voice_kb_meta
        (source_id,account_id,site_id,scope_mode,priority,expires_at,updated_at)
        VALUES (?,?,?,?,?,?,?)",
        [$sourceId,$accountId,$siteId,$scopeMode,$priority,$expiry,now()]);
    execute('DELETE FROM voice_kb_agent_scope WHERE source_id=? AND account_id=? AND site_id=?',
        [$sourceId,$accountId,$siteId]);
    if($scopeMode==='agent')
        insert('INSERT INTO voice_kb_agent_scope(source_id,agent_id,account_id,site_id)
            VALUES (?,?,?,?)',[$sourceId,$agentId,$accountId,$siteId]);
}
function voice_kb_agent_context(int $accountId,int $siteId,int $agentId,int $max=7000,string $query=''):string {
    voice_kb_web_init();
    // Fail closed: a missing, archived or foreign agent must receive no context,
    // even if a source is otherwise visible to all agents of this site.
    if($agentId<=0||!one("SELECT ag.id FROM agents ag
          JOIN sites s ON s.id=ag.site_id AND s.account_id=ag.account_id
          JOIN accounts a ON a.id=ag.account_id
          WHERE ag.id=? AND ag.account_id=? AND ag.site_id=?
          AND ag.status!='archived' AND s.status='active' AND a.status='active'",
          [$agentId,$accountId,$siteId]))return '';
    $rows=all("SELECT k.id source_id,k.account_id,k.site_id,k.sha256,
        k.name,k.content,k.version,m.origin_url,COALESCE(m.priority,100) priority
      FROM voice_knowledge_sources k
      LEFT JOIN voice_kb_meta m ON m.source_id=k.id AND m.account_id=k.account_id AND m.site_id=k.site_id
      WHERE k.account_id=? AND k.site_id=? AND k.status='active' AND k.visibility='public'
        AND (m.expires_at IS NULL OR m.expires_at>?)
        AND (m.scope_mode IS NULL OR m.scope_mode='all'
            OR EXISTS(SELECT 1 FROM voice_kb_agent_scope s
              WHERE s.source_id=k.id AND s.account_id=k.account_id
                AND s.site_id=k.site_id AND s.agent_id=?))
      ORDER BY COALESCE(m.priority,100) DESC,k.id DESC LIMIT 40",
      [$accountId,$siteId,now(),$agentId]);
    if(trim($query)!=='')$rows=voice_kb_search_rank_rows($rows,$query);
    $rows=voice_kb_search_resolve_facts(array_slice($rows,0,12));
    $out='';$max=max(0,min(12000,$max));
    foreach($rows as $row){
        $intro="\n[معرفة موثقة: ".mb_substr((string)$row['name'],0,90).
            " v".(int)$row['version'].(!empty($row['origin_url'])?" | ".mb_substr((string)$row['origin_url'],0,150):'')."]\n";
        $room=$max-mb_strlen($out);
        if($room<=0)break;
        $out.=mb_substr($intro.(string)$row['content'],0,$room);
    }
    return $out;
}


/**
 * Bounded, explicit operator-triggered synchronization of DUE public links.
 * This is not a background cron promise. Changed content remains REVIEW.
 */
function voice_kb_sync_due(int $accountId,int $siteId,int $limit=3,?callable $transport=null):array {
    voice_kb_web_init();voice_knowledge_site($accountId,$siteId);
    if($limit<1||$limit>5)throw new InvalidArgumentException('knowledge_due_limit_invalid');
    $rows=all("SELECT id FROM voice_kb_web_links
       WHERE account_id=? AND site_id=?
       AND status IN ('draft','current','review','error')
       AND (next_check_at IS NULL OR next_check_at<=?)
       ORDER BY COALESCE(next_check_at,created_at),id LIMIT ".(int)$limit,
       [$accountId,$siteId,now()]);
    $summary=['checked'=>0,'unchanged'=>0,'review'=>0,'failed'=>0,'errors'=>[]];
    foreach($rows as $row){
        $linkId=(int)$row['id'];$summary['checked']++;
        try{
            $result=voice_kb_web_sync($accountId,$siteId,$linkId,$transport);
            if($result['status']==='unchanged')$summary['unchanged']++;
            else $summary['review']++;
        }catch(Throwable $error){
            $summary['failed']++;
            $summary['errors'][]=['link_id'=>$linkId,'error'=>'source_sync_failed'];
        }
    }
    return $summary;
}


/**
 * Cron-ready bounded scan across active tenants. Each link is fetched through
 * the same HTTPS policy and owner approval process as manual synchronization.
 * This function does not grant or promote a pending source automatically.
 */
function voice_kb_sync_due_all(int $limit=12,?callable $transport=null):array {
    voice_kb_web_init();
    if($limit<1||$limit>12)throw new InvalidArgumentException('knowledge_global_due_limit_invalid');
    $rows=all("SELECT l.id,l.account_id,l.site_id FROM voice_kb_web_links l
        INNER JOIN sites s ON s.id=l.site_id AND s.account_id=l.account_id
        INNER JOIN accounts a ON a.id=l.account_id AND a.status='active'
        WHERE s.status='active'
          AND l.status IN ('draft','current','review','error')
          AND (l.next_check_at IS NULL OR l.next_check_at<=?)
        ORDER BY COALESCE(l.next_check_at,l.created_at),l.id LIMIT ".(int)$limit,[now()]);
    $summary=['checked'=>0,'unchanged'=>0,'review'=>0,'failed'=>0];
    foreach($rows as $row){
        $summary['checked']++;
        try{
            $result=voice_kb_web_sync((int)$row['account_id'],(int)$row['site_id'],
                (int)$row['id'],$transport);
            if($result['status']==='unchanged')$summary['unchanged']++;
            else $summary['review']++;
        }catch(Throwable $ignored){$summary['failed']++;}
    }
    return $summary;
}
