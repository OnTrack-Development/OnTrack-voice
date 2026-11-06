<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/VoiceOpenApi.php';
if(is_dir('/home/appontrack'))throw new RuntimeException('CI fixture only');
function openapi_check(bool $condition,string $name):void {
    if(!$condition)throw new RuntimeException('SITE_OPENAPI_FAIL '.$name);
}
seed_console();
$site=(int)one("SELECT id FROM sites WHERE account_id=4 AND status='active' LIMIT 1")['id'];
$conn=voice_connector_add(4,$site,'laravel','Booking API','https://example.com','/api/status','none');
execute("UPDATE voice_data_connectors SET status='verified' WHERE id=? AND account_id=4",[$conn]);
$spec=json_encode(['openapi'=>'3.0.3','info'=>['title'=>'Booking Demo','version'=>'1'],
    'servers'=>[['url'=>'http://127.0.0.1/secrets']],
    'paths'=>[
        '/api/slots'=>['get'=>['operationId'=>'listSlots','summary'=>'Find appointments']],
        '/api/bookings'=>['post'=>['operationId'=>'createBooking','summary'=>'Book slot']],
        '/api/users/{id}'=>['get'=>['operationId'=>'GetSecrets']],
        '/admin/secrets'=>['post'=>['operationId'=>'DestroySecret']],
        '/api/other'=>['delete'=>['operationId'=>'DeleteAccount']],
        '/admin/../bad'=>['get'=>['operationId'=>'BadHost']]
    ]],JSON_THROW_ON_ERROR);
$result=voice_openapi_discover(4,$site,$conn,'/openapi.json',static fn()=> $spec);
openapi_check($result['count']===2&&!$result['writes_enabled'],'two restricted candidate endpoints');
openapi_check($result['candidates'][0]['operation']==='rest.slots','GET mapped to safe slots');
openapi_check($result['candidates'][1]['operation']==='rest.booking.create','POST mapped to booking only');
$found=voice_openapi_candidates(4,$site);
openapi_check(count($found)===2,'saved site-scoped candidates');
$policy=voice_openapi_draft_policy(4,$site,$conn,'rest.booking.create','/api/bookings',$result['sha256']);
openapi_check((int)one('SELECT enabled FROM voice_tool_policies WHERE id=?',[$policy])['enabled']===0,
    'discovered write remains disabled');
$notActive=false;try{voice_tool_policy(4,$site,$policy);}catch(InvalidArgumentException){$notActive=true;}
openapi_check($notActive,'no enabled tool without independent approval');
$foreign=false;try{voice_openapi_discover(5,$site,$conn,'/openapi.json',static fn()=> $spec);}
catch(InvalidArgumentException){$foreign=true;}
openapi_check($foreign,'cannot import another tenant connector');
$badPath=false;try{voice_openapi_discover(4,$site,$conn,'/admin/openapi.json',static fn()=> $spec);}
catch(InvalidArgumentException){$badPath=true;}
openapi_check($badPath,'allowlisted schema URLs only');
$badHash=false;try{voice_openapi_draft_policy(4,$site,$conn,'rest.booking.create','/api/bookings',str_repeat('0',64));}
catch(InvalidArgumentException){$badHash=true;}
openapi_check($badHash,'schema hash required');
$badOperation=false;try{voice_openapi_draft_policy(4,$site,$conn,'rest.slots','/admin/secrets',$result['sha256']);}
catch(InvalidArgumentException){$badOperation=true;}
openapi_check($badOperation,'no arbitrary schema path tool');
$notDocument=false;try{voice_openapi_parse('{"openapi":"2.0","paths":{}}');}
catch(InvalidArgumentException){$notDocument=true;}
openapi_check($notDocument,'reject invalid version');
$notDocument=false;try{voice_openapi_parse(str_repeat('x',200000));}
catch(InvalidArgumentException){$notDocument=true;}
openapi_check($notDocument,'reject huge document');
$headers=voice_openapi_headers(['auth_mode'=>'bearer',
    'secret_enc'=>seal_secret('readonly-ci-secret')]);
openapi_check(in_array('Authorization: Bearer readonly-ci-secret',$headers,true),
    'private connector bearer auth supported for schema fetch');
$headers=voice_openapi_headers(['auth_mode'=>'wp_application',
    'secret_enc'=>seal_secret('reader:password')]);
openapi_check(in_array('Authorization: Basic '.base64_encode('reader:password'),$headers,true),
    'basic auth restricted to verified WP connector credentials');
$blocked=false;
try{voice_openapi_headers(['auth_mode'=>'whmcs','secret_enc'=>seal_secret('id:secret')]);}
catch(RuntimeException){$blocked=true;}
openapi_check($blocked,'unsupported schema auth refused');
$missing=false;
try{voice_openapi_headers(['auth_mode'=>'bearer','secret_enc'=>null]);}
catch(RuntimeException){$missing=true;}
openapi_check($missing,'schema credential missing fails closed');
echo "VOICE_OPENAPI_DISCOVERY_SAFETY_PASS\n";
