<?php
declare(strict_types=1);
require dirname(__DIR__).'/saas/console/_core/FlowTemplates.php';
require dirname(__DIR__).'/saas/console/_core/FlowPreview.php';
$items=flow_template_catalog();
if(count($items)<20)throw new RuntimeException('insufficient_flow_templates');
foreach($items as $key=>$tpl){
 $g=flow_template_graph($key);$compiled=flow_preview_compile($g);
 $result=flow_preview_step($compiled,flow_preview_initial($compiled));
 for($i=0;$i<count($tpl['questions']);$i++){
   if(!$result['waiting'])throw new RuntimeException('missing template question: '.$key);
   $result=flow_preview_step($compiled,$result['state'],'تجربة');
 }
 if(!$result['done'])throw new RuntimeException('template did not terminate: '.$key);
}
try{flow_template_graph('nonexistent');throw new RuntimeException('unknown template accepted');}
catch(InvalidArgumentException $e){}
echo 'FLOW_TEMPLATE_CATALOG_PASS '.count($items)."\n";
