<?php
declare(strict_types=1);
/** CLI-only IO Premium grant check; never prints credentials or private data. */
if (PHP_SAPI !== 'cli') exit(3);
require '/home/appontrack/public_html/_core/Bootstrap.php';
init_schema();
$voice=one("SELECT id,code,name,tier,provider,ready,status FROM voices WHERE code='io.premium'");
$grants=$voice?one("SELECT COUNT(*) AS cnt FROM voice_grants g JOIN accounts a ON a.id=g.account_id
    WHERE g.voice_id=? AND a.kind='platform' AND a.status='active'",[(int)$voice['id']]):null;
$okay=$voice&&$voice['name']==='IO Premium'&&$voice['provider']==='fish_audio'
    &&$voice['tier']==='premium'&&(int)$voice['ready']===1&&$voice['status']==='active'
    &&(int)($grants['cnt']??0)>=1;
echo json_encode(['status'=>$okay?'PASS':'FAIL','voice'=>(string)($voice['name']??'missing'),
   'code'=>(string)($voice['code']??'missing'),'provider'=>(string)($voice['provider']??'missing'),
   'ready'=>(int)($voice['ready']??0),'platform_grant_count'=>(int)($grants['cnt']??0)],
   JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($okay?0:2);
