<?php
declare(strict_types=1);

/**
 * Local, tenant-bound Arabic/English concept-and-keyword index for approved
 * knowledge. It is deterministic lexical retrieval with a small synonym
 * map, NOT an external embedding model or an unverified vector service.
 * Never indexes staff-only or unapproved material for public retrieval.
 */
function voice_kb_search_init():void {
    $marker=private_path().'/.schema.voice-kb-search.v1';
    if(is_file($marker))return;
    $int=driver()==='mysql'?'BIGINT':'INTEGER';
    $engine=driver()==='mysql'
       ?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    db()->exec("CREATE TABLE IF NOT EXISTS voice_kb_search_index (
        source_id $int PRIMARY KEY,account_id $int NOT NULL,site_id $int NOT NULL,
        content_hash CHAR(64) NOT NULL,terms_json TEXT NOT NULL,
        indexed_at VARCHAR(19) NOT NULL
    )".$engine);
    file_put_contents($marker,now(),LOCK_EX);@chmod($marker,0600);
}
function voice_kb_search_normalize(string $s):string {
    $s=mb_strtolower($s,'UTF-8');
    $s=preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u','',$s)??$s;
    return str_replace(['أ','إ','آ','ٱ','ى','ؤ','ئ'],['ا','ا','ا','ا','ي','و','ي'],$s);
}
function voice_kb_search_terms(string $text):array {
    $text=voice_kb_search_normalize($text);
    preg_match_all('/[\p{Arabic}a-z0-9]{2,}/u',$text,$matches);
    $aliases=[
      'اسعار'=>'price','السعر'=>'price','سعر'=>'price','تكلفه'=>'price','تكلفة'=>'price',
      'رسوم'=>'price','الرسوم'=>'price','cost'=>'price','price'=>'price',
      'مواعيد'=>'time','المواعيد'=>'time','موعد'=>'time','ساعات'=>'time',
      'ساعه'=>'time','اوقات'=>'time','الوقت'=>'time','time'=>'time','hours'=>'time',
      'حجوزات'=>'booking','الحجز'=>'booking','حجز'=>'booking','booking'=>'booking',
      'استرجاع'=>'return','استرداد'=>'return','ارجاع'=>'return','return'=>'return',
      'شحن'=>'delivery','توصيل'=>'delivery','الشحن'=>'delivery','delivery'=>'delivery',
    ];
    $stop=array_fill_keys([
      'في','من','عن','علي','على','الي','الى','ان','انها','كان','هو','هي',
      'هذه','هذا','كيف','ماذا','ما','التي','الذي','او','و','مع','هل',
      'the','and','for','are','what','how','you','your','with','from'
    ],true);
    $terms=[];
    foreach(array_slice($matches[0]??[],0,1800) as $term){
        if(isset($stop[$term]))continue;
        $term=$aliases[$term]??$term;
        $terms[$term]=min(8,($terms[$term]??0)+1);
    }
    return $terms;
}
function voice_kb_search_reindex(int $accountId,int $siteId,int $limit=200):array {
    voice_knowledge_site($accountId,$siteId);
    if($limit<1||$limit>200)throw new InvalidArgumentException('knowledge_reindex_limit');
    voice_kb_search_init();
    $rows=all("SELECT id,name,content,sha256 FROM voice_knowledge_sources
        WHERE account_id=? AND site_id=? AND status='active' AND visibility='public'
        ORDER BY id DESC LIMIT ".(int)$limit,[$accountId,$siteId]);
    $written=0;
    foreach($rows as $row){
        $terms=voice_kb_search_terms((string)$row['name'].' '.(string)$row['content']);
        $json=json_encode($terms,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $id=(int)$row['id'];
        $old=one('SELECT source_id,content_hash FROM voice_kb_search_index
            WHERE source_id=? AND account_id=? AND site_id=?',[$id,$accountId,$siteId]);
        if($old && hash_equals((string)$old['content_hash'],(string)$row['sha256']))continue;
        if($old)execute('UPDATE voice_kb_search_index SET content_hash=?,terms_json=?,indexed_at=?
            WHERE source_id=? AND account_id=? AND site_id=?',
            [$row['sha256'],$json,now(),$id,$accountId,$siteId]);
        else insert('INSERT INTO voice_kb_search_index
            (source_id,account_id,site_id,content_hash,terms_json,indexed_at)
            VALUES (?,?,?,?,?,?)',[$id,$accountId,$siteId,$row['sha256'],$json,now()]);
        $written++;
    }
    // Archived/review/staff rows cannot remain in the searchable index.
    execute("DELETE FROM voice_kb_search_index
        WHERE account_id=? AND site_id=? AND source_id NOT IN
          (SELECT id FROM voice_knowledge_sources
           WHERE account_id=? AND site_id=? AND status='active' AND visibility='public')",
        [$accountId,$siteId,$accountId,$siteId]);
    return ['scanned'=>count($rows),'indexed'=>$written];
}
/** Re-rank ONLY pre-authorized, active, public sources selected by caller SQL. */
function voice_kb_search_rank_rows(array $rows,string $query):array {
    $terms=voice_kb_search_terms(mb_substr($query,0,500));
    if(!$terms)return $rows;
    voice_kb_search_init();
    foreach($rows as &$row){
        $indexed=one('SELECT content_hash,terms_json FROM voice_kb_search_index
            WHERE source_id=? AND account_id=? AND site_id=?',
            [(int)$row['source_id'],(int)$row['account_id'],(int)$row['site_id']]);
        $sourceTerms=($indexed && hash_equals((string)$indexed['content_hash'],(string)$row['sha256']))
            ?json_decode((string)$indexed['terms_json'],true):null;
        if(!is_array($sourceTerms))
            $sourceTerms=voice_kb_search_terms((string)$row['name'].' '.(string)$row['content']);
        $titleTerms=voice_kb_search_terms((string)$row['name']);
        $score=0;
        foreach($terms as $term=>$frequency)
            if(isset($sourceTerms[$term]))
                $score+=1+min(4,(int)$sourceTerms[$term])+
                    (isset($titleTerms[$term])?8:0);
        $row['_search_score']=$score;
    }
    unset($row);
    $matches=array_values(array_filter($rows,static fn(array $r):bool=>$r['_search_score']>0));
    usort($matches,static fn(array $a,array $b):int=>
        ($b['_search_score']<=>$a['_search_score'])
        ?:((int)$b['priority']<=>(int)$a['priority'])
        ?:((int)$b['source_id']<=>(int)$a['source_id']));
    return $matches;
}
/**
 * Resolve explicit key: value conflicts in public material, without approving
 * or altering any source. Higher priority wins; equal-priority disagreement
 * is suppressed from the LLM context rather than returning two false facts.
 * Freeform semantic disagreements still require human review.
 */
function voice_kb_search_resolve_facts(array $rows):array {
    $entries=[];$keys=[];
    foreach($rows as $i=>$row){
        $lines=preg_split('/\R/u',(string)$row['content'])?:[];
        $entries[$i]=$lines;
        foreach($lines as $j=>$line){
            if(!preg_match('/^\s*([^:\x{FF1A}\n]{2,90})\s*[:\x{FF1A}]\s*(.{1,300})\s*$/u',$line,$m))
                continue;
            $key=trim(voice_kb_search_normalize($m[1]));
            $value=trim(voice_kb_search_normalize($m[2]));
            $keys[$key][]=[$i,$j,(int)$row['priority'],$value];
        }
    }
    foreach($keys as $occurrences){
        if(count($occurrences)<2)continue;
        $high=max(array_column($occurrences,2));
        $best=array_values(array_filter($occurrences,static fn($o)=>$o[2]===$high));
        $values=array_unique(array_column($best,3));
        $ambiguous=count($values)>1;
        foreach($occurrences as [$i,$j,$priority,$value])
            if($ambiguous||$priority<$high)unset($entries[$i][$j]);
    }
    foreach($rows as $i=>&$row)$row['content']=trim(implode("\n",$entries[$i]));
    unset($row);
    return array_values(array_filter($rows,static fn(array $r):bool=>$r['content']!==''));
}

/** Observable index state; counts only approved, tenant/site-owned public sources. */
function voice_kb_search_status(int $accountId,int $siteId):array {
    voice_knowledge_site($accountId,$siteId);
    voice_kb_search_init();
    $counts=one("SELECT COUNT(*) total,
        COALESCE(SUM(CASE WHEN i.source_id IS NOT NULL AND i.content_hash=k.sha256
          THEN 1 ELSE 0 END),0) ready
      FROM voice_knowledge_sources k
      LEFT JOIN voice_kb_search_index i ON i.source_id=k.id
        AND i.account_id=k.account_id AND i.site_id=k.site_id
      WHERE k.account_id=? AND k.site_id=?
        AND k.status='active' AND k.visibility='public'",[$accountId,$siteId]);
    $total=(int)($counts['total']??0);
    $ready=(int)($counts['ready']??0);
    $review=(int)(one("SELECT COUNT(*) n FROM voice_knowledge_sources
        WHERE account_id=? AND site_id=? AND status='review' AND visibility='public'",
        [$accountId,$siteId])['n']??0);
    return ['total'=>$total,'ready'=>$ready,'pending'=>max(0,$total-$ready),
      'review'=>$review];
}
