<?php
declare(strict_types=1);
require dirname(__DIR__).'/saas/console/_core/FlowTemplates.php';
require dirname(__DIR__).'/saas/console/_core/FlowPreview.php';
$items=flow_template_catalog();
if(count($items)<20)throw new RuntimeException('insufficient_flow_templates');
foreach($items as $key=>$tpl){
 $g=flow_template_graph($key);$compiled=flow_preview_compile($g);
 $types=array_column($g['nodes'],'type');
 if(!in_array('instruction',$types,true)||!in_array('ask',$types,true)
     ||in_array('collect_field',$types,true))throw new RuntimeException('not a knowledge-led conversation: '.$key);
 $result=flow_preview_step($compiled,flow_preview_initial($compiled));
 if(!$result['waiting'])throw new RuntimeException('needs user interest: '.$key);
 foreach(['محتاج شرح','ممكن تفاصيل أخرى','لا'] as $answer){
     $result=flow_preview_step($compiled,$result['state'],$answer);
 }
 if(!$result['done'])throw new RuntimeException('branch path not finished: '.$key);
 $all=implode(" ",array_column($result['events'],'text'));
 if(str_contains($all,'قاعدة المعرفة'))throw new RuntimeException('internal instructions leaked to user');
 // A positive sales follow-up must also terminate and not auto-book anything.
 $positive=flow_preview_step($compiled,flow_preview_initial($compiled));
 foreach(['عايز معلومات','وضح السعر','نعم'] as $answer)$positive=flow_preview_step($compiled,$positive['state'],$answer);
 if(!$positive['done'])throw new RuntimeException('positive branch not terminating');
}
try{flow_template_graph('nonexistent');throw new RuntimeException('unknown template accepted');}
catch(InvalidArgumentException $expected){}
echo "KNOWLEDGE_CONVERSATIONAL_TEMPLATES_PASS ".count($items)."\n";
