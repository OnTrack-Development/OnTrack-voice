<?php
declare(strict_types=1);

/** Real HTTP session/CSRF/tenant regression; localhost only, synthetic data. */
function flow_http_request(string $url,string $method='POST',array $params=[],string $cookie=''):array {
    $headers="Accept: application/json\r\n";
    if($cookie!=='')$headers.="Cookie: OTVOICESESSION=".$cookie."\r\n";
    if($method==='POST')$headers.="Content-Type: application/x-www-form-urlencoded\r\n";
    $ctx=stream_context_create(['http'=>[
        'method'=>$method,'header'=>$headers,'content'=>$method==='POST'?http_build_query($params):'',
        'ignore_errors'=>true,'timeout'=>5
    ]]);
    $raw=@file_get_contents($url,false,$ctx);
    if($raw===false)throw new RuntimeException('flow_http_transport');
    $status=0;$session='';
    foreach($http_response_header??[] as $line){
        if(preg_match('/^HTTP\/\S+ (\d{3})/',$line,$m))$status=(int)$m[1];
        if(preg_match('/^Set-Cookie:\s*OTVOICESESSION=([^;]+)/i',$line,$m))$session=$m[1];
    }
    $json=json_decode($raw,true);
    if($status===0||!is_array($json))throw new RuntimeException('flow_http_invalid_response');
    return [$status,$json,$session];
}
/** Exercise the real console POST without following its 303 redirect. */
function flow_http_form(string $url,array $params,string $cookie):int {
    $ctx=stream_context_create(['http'=>[
        'method'=>'POST','header'=>"Content-Type: application/x-www-form-urlencoded\r\nCookie: OTVOICESESSION=".$cookie."\r\n",
        'content'=>http_build_query($params),'follow_location'=>0,'ignore_errors'=>true,'timeout'=>5
    ]]);
    @file_get_contents($url,false,$ctx);
    foreach($http_response_header??[] as $line)
        if(preg_match('/^HTTP\/\S+ (\d{3})/',$line,$m))return (int)$m[1];
    throw new RuntimeException('flow_form_no_status');
}
function test_agent_flow_preview_http():void {
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);
    if($socket===false)throw new RuntimeException('flow_http_socket');
    $address=stream_socket_get_name($socket,false);fclose($socket);
    $port=(int)substr(strrchr($address,':'),1);
    $root=realpath(__DIR__.'/../saas/console');
    $router=__DIR__.'/flow_preview_http_router.php';
    $process=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',$root,$router],
        [0=>['pipe','r'],1=>['file','/dev/null','a'],2=>['file','/dev/null','a']],
        $pipes,dirname(__DIR__));
    if(!is_resource($process))throw new RuntimeException('flow_http_server');
    fclose($pipes[0]);
    $base='http://127.0.0.1:'.$port.'/saas/console/';
    try{
        $ready=false;
        for($i=0;$i<40;$i++){
            $conn=@fsockopen('127.0.0.1',$port,$err,$msg,0.15);
            if($conn!==false){fclose($conn);$ready=true;break;}
            usleep(100000);
        }
        if(!$ready)throw new RuntimeException('flow_http_start_timeout');
        $url=$base.'flow-preview.php';
        $graph=json_encode(['nodes'=>[
            ['id'=>'start','type'=>'start','text'=>''],
            ['id'=>'ask','type'=>'ask','text'=>'اختار نعم أو لا'],
            ['id'=>'yes','type'=>'end','text'=>'مسار نعم'],
            ['id'=>'no','type'=>'end','text'=>'مسار لا']
        ],'edges'=>[
            ['from'=>'start','to'=>'ask'],
            ['from'=>'ask','to'=>'yes','label'=>'نعم'],
            ['from'=>'ask','to'=>'no','label'=>'لا']
        ]],JSON_UNESCAPED_UNICODE);
        $before=(int)one('SELECT COUNT(*) n FROM contacts')['n'];
        [$status]=flow_http_request($url,'GET');
        ok($status===405,'real preview endpoint denies GET');
        [$status,$body]=flow_http_request($url,'POST',['operation'=>'start','agent_id'=>1,'flow_json'=>$graph]);
        ok($status===403&&$body['error']==='not_authorized','anonymous preview denied');
        [$status,$login,$cookie]=flow_http_request($base.'__fixture_login?account=4','GET');
        ok($status===200&&$cookie!==''&&!empty($login['csrf']),'synthetic customer session established');
        $params=['operation'=>'start','agent_id'=>1,'flow_json'=>$graph,'csrf'=>$login['csrf']];
        [$status,$body]=flow_http_request($url,'POST',array_diff_key($params,['csrf'=>true]),$cookie);
        ok($status===419&&$body['error']==='csrf_expired','CSRF enforced over real HTTP');
        [$status,$body]=flow_http_request($url,'POST',array_replace($params,['agent_id'=>3]),$cookie);
        ok($status===404&&$body['error']==='agent_not_found','cross-tenant agent denied over real HTTP');
        [$status,$body]=flow_http_request($url,'POST',$params,$cookie);
        $id=$body['preview_id']??'';
        ok($status===200&&$body['waiting']===true&&strlen($id)===32,
            'authenticated user can start own unsaved draft preview');
        [$status,$bad]=flow_http_request($url,'POST',[
            'operation'=>'reply','csrf'=>$login['csrf'],'agent_id'=>1,
            'flow_json'=>str_replace('مسار نعم','معدل',$graph),
            'preview_id'=>$id,'reply'=>'نعم'],$cookie);
        ok($status===409&&$bad['error']==='preview_expired_or_changed',
            'graph changes invalidate preview session');
        [$status,$answer]=flow_http_request($url,'POST',array_replace($params,[
            'operation'=>'reply','preview_id'=>$id,'reply'=>'لا']),$cookie);
        ok($status===200&&$answer['done']===true&&$answer['events'][0]['text']==='مسار لا',
            'real HTTP preview follows selected branch');
        [$status,$foreign,$otherCookie]=flow_http_request($base.'__fixture_login?account=5','GET');
        ok($status===200&&$otherCookie!==''&&$otherCookie!==$cookie,
            'independent second tenant session established');
        [$status,$body]=flow_http_request($url,'POST',array_replace($params,[
            'operation'=>'reply','preview_id'=>$id,'reply'=>'نعم','csrf'=>$foreign['csrf']]),$otherCookie);
        ok($status===404,'other tenant cannot reuse first account preview');
        ok((int)one('SELECT COUNT(*) n FROM contacts')['n']===$before,
            'real HTTP preview did not mutate customer records');
        // Real saved-draft preview -> publish through the authenticated console form.
        $original=one('SELECT flow_json FROM agents WHERE id=1 AND account_id=4');
        execute('UPDATE agents SET flow_json=? WHERE id=1 AND account_id=4',[$graph]);
        $publishForm=['action'=>'publish_agent_flow','csrf'=>$login['csrf'],
            'agent_id'=>1,'return'=>'/?tab=agents&site=1'];
        $beforeRelease=(int)one('SELECT COUNT(*) n FROM agent_flow_revisions WHERE account_id=4 AND agent_id=1')['n'];
        ok(flow_http_form($base,$publishForm,$cookie)===303,'real publish form uses POST redirect');
        ok((int)one('SELECT COUNT(*) n FROM agent_flow_revisions WHERE account_id=4 AND agent_id=1')['n']===$beforeRelease,
            'publishing without completing a saved-draft test is blocked');
        [$status,$start]=flow_http_request($url,'POST',$params,$cookie);
        ok($status===200&&$start['waiting'],'saved draft starts in authenticated HTTP preview');
        [$status,$finished]=flow_http_request($url,'POST',array_replace($params,[
            'operation'=>'reply','preview_id'=>$start['preview_id'],'reply'=>'نعم']),$cookie);
        ok($status===200&&$finished['done'],'saved draft test completes before publish');
        ok(flow_http_form($base,$publishForm,$cookie)===303,'real publish form accepts tested draft');
        ok((int)one('SELECT COUNT(*) n FROM agent_flow_revisions WHERE account_id=4 AND agent_id=1')['n']===$beforeRelease+1,
            'real HTTP flow publish persists a revision');
        execute('UPDATE agents SET flow_json=? WHERE id=1 AND account_id=4',[$original['flow_json']]);
        echo "AGENT_FLOW_HTTP_PASS\n";
    }finally{
        proc_terminate($process);proc_close($process);
    }
}
