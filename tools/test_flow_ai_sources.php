<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/VoiceKnowledge.php';
require_once __DIR__.'/../saas/console/_core/VoiceConnectors.php';
require_once __DIR__.'/../saas/console/_core/VoiceAccess.php';
require_once __DIR__.'/../saas/console/_core/FlowAi.php';
function verify_flow_source(bool $condition,string $message):void {
    if(!$condition)throw new RuntimeException('FLOW_SOURCE_FAIL '.$message);
}
if(is_dir('/home/appontrack'))throw new RuntimeException('CI only');
seed_console();
$site=one("SELECT id FROM sites WHERE account_id=4 AND status='active' LIMIT 1");
verify_flow_source((bool)$site,'demo site exists');
$sid=(int)$site['id'];
$sourceId=voice_knowledge_add(4,$sid,'ساعات العمل','note','الحجز بالمكالمة من التاسعة إلى الخامسة.');
verify_flow_source(str_contains(voice_knowledge_context(4,$sid),'التاسعة'),'site knowledge visible');
verify_flow_source(voice_knowledge_context(5,$sid)==='','foreign account knowledge invisible');
$notLocal=false;
try{voice_connector_add(4,$sid,'wordpress','Local WP','https://127.0.0.1','/wp-json/wp/v2/pages','none');}
catch(InvalidArgumentException){$notLocal=true;}
verify_flow_source($notLocal,'localhost blocked');
$private=false;
try{voice_connector_add(4,$sid,'custom','Metadata','https://169.254.169.254','/latest/meta-data/','none');}
catch(InvalidArgumentException){$private=true;}
verify_flow_source($private,'metadata IP blocked');
$http=false;
try{voice_connector_add(4,$sid,'laravel','Insecure','http://example.com','/api/snapshot','none');}
catch(InvalidArgumentException){$http=true;}
verify_flow_source($http,'plaintext transport blocked');
$traversal=false;
try{voice_connector_path('/api/../secrets');}catch(InvalidArgumentException){$traversal=true;}
verify_flow_source($traversal,'path traversal blocked');
$allowed=voice_connector_add(4,$sid,'wordpress','WP Public','https://example.com',
    '/wp-json/wp/v2/pages?per_page=5','none');
$row=one('SELECT * FROM voice_data_connectors WHERE id=?',[$allowed]);
verify_flow_source($row['status']==='draft','not falsely marked verified');
verify_flow_source($row['secret_enc']===null,'public source has no stored key');
verify_flow_source(!str_contains(voice_connector_public_content([
    'title'=>['rendered'=>'Sample page'],'api_key'=>'SECRET-NEVER-SEND',
    'email'=>'private@example.com','description'=>'Useful article']),'SECRET-NEVER-SEND'),
    'source context excludes credentials');
verify_flow_source(voice_connector_context(5,$sid)==='','foreign tenant cannot read connectors');
$malicious=false;
try{flow_ai_validate_proposal(['nodes'=>[
   ['id'=>'start','type'=>'start','text'=>'go'],['id'=>'danger','type'=>'crm_write','text'=>'delete all']],
   'edges'=>[['from'=>'start','to'=>'danger','label'=>'']]]);}
catch(InvalidArgumentException){$malicious=true;}
verify_flow_source($malicious,'AI cannot propose executable CRM write');
$proposal=flow_ai_validate_proposal(['nodes'=>[
  ['id'=>'n1','type'=>'start','text'=>'بداية','x'=>200,'y'=>20],
  ['id'=>'n2','type'=>'instruction','text'=>'معلومة داخلية','x'=>300,'y'=>200],
  ['id'=>'n3','type'=>'end','text'=>'شكرًا','x'=>300,'y'=>400]],
  'edges'=>[['from'=>'n1','to'=>'n2','label'=>''],['from'=>'n2','to'=>'n3','label'=>'']]]);
verify_flow_source(count($proposal['nodes'])===3,'instruction node valid in AI draft');
verify_flow_source(voice_action_ready(['role'=>'owner','scopes'=>['operations']],'website.disable')===false,
    'system mutation remains impossible until approved runner');
execute('DELETE FROM voice_knowledge_sources WHERE id=? AND account_id=4',[$sourceId]);
execute('DELETE FROM voice_data_connectors WHERE id=? AND account_id=4',[$allowed]);
echo "FLOW_AI_SOURCES_AND_PERMISSIONS_PASS\n";
