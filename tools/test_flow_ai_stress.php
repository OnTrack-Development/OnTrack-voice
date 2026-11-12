<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/_core/FlowAi.php';
function check_stress(bool $success,string $desc):void {
    if(!$success)throw new RuntimeException('FLOW_AI_STRESS_FAIL: '.$desc);
}
check_stress(flow_ai_stress_requested('أقصى شبكة عصبية لألف مكالمة')===true,'Arabic extreme request');
check_stress(flow_ai_stress_requested('ONTRACK VOICE EXTREME AI AGENT STRESS TEST')===true,'English stress prompt');
check_stress(flow_ai_stress_requested('مشكلة بريد إلكتروني')===false,'normal question remains lightweight');
$large=['nodes'=>[],'edges'=>[]];
for($i=0;$i<28;$i++){
    $large['nodes'][]=['id'=>'n'.$i,'type'=>$i===0?'start':($i===27?'end':($i%4===0?'condition':'instruction')),
        'text'=>'مراجعة خطوة رقم '.$i,'x'=>250+($i%4)*520,'y'=>90+intdiv($i,4)*310];
}
for($i=0;$i<27;$i++)$large['edges'][]=['from'=>'n'.$i,'to'=>'n'.($i+1),'label'=>''];
for($i=0;$i<25;$i++){
    // Avoid duplicate route labels from a single source.
    $large['edges'][]=['from'=>'n'.$i,'to'=>'n'.($i+2),'label'=>'اختيار بديل'];
}
$large=flow_ai_validate_proposal($large);
$report=flow_ai_stress_verify($large);
check_stress($report['ok'],'connected 28-node 52-edge graph meets quality standard: '.implode('; ',$report['issues']));
check_stress($report['metrics']['branch_points']>=5,'true branching measured');
check_stress($report['metrics']['reconvergence_points']>=4,'real convergences measured');
check_stress($report['metrics']['safe_exit_reachable']===true,'every node reaches safe exit');
$old=['nodes'=>array_slice($large['nodes'],0,10),'edges'=>array_slice($large['edges'],0,9)];
check_stress(flow_ai_stress_verify($large,$old)['ok'],'old IDs preserved in stress upgrade');
$tiny=['nodes'=>array_slice($large['nodes'],0,11),'edges'=>array_slice($large['edges'],0,14)];
$tiny['nodes'][10]['type']='end';
foreach($tiny['edges'] as $edge)if((int)substr($edge['to'],1)>=11){$tiny['edges']=array_values(array_filter($tiny['edges'],
    static fn($e)=> (int)substr($e['to'],1)<11));break;}
$smallReport=flow_ai_stress_verify($tiny);
check_stress(!$smallReport['ok'] && $smallReport['metrics']['nodes']===11,
    '11-node tree is NOT accepted as stress network');
$broken=$large;
$broken['edges']=array_values(array_filter($broken['edges'],
    static fn($e)=>$e['from']!=='n12'&&$e['to']!=='n12'));
$brokenReport=flow_ai_stress_verify($broken);
check_stress(!$brokenReport['ok']&&count($brokenReport['metrics']['unreachable_nodes'])>=1,
    'isolated node is rejected even with plenty of edges');
$noEnd=$large;
$noEnd['edges']=array_values(array_filter($noEnd['edges'],static fn($e)=>$e['to']!=='n27'));
check_stress(!flow_ai_stress_verify($noEnd)['ok'],'dead-end network rejected');
$oversize=$large;
for($i=28;$i<33;$i++)$oversize['nodes'][]=['id'=>'n'.$i,'type'=>'say','text'=>'زيادة','x'=>200,'y'=>200];
$refused=false;
try{flow_ai_validate_proposal($oversize);}catch(InvalidArgumentException){$refused=true;}
check_stress($refused,'bound remains compatible with editor 32 nodes');
$source=(string)file_get_contents(__DIR__.'/../saas/console/_core/FlowAi.php');
check_stress(!str_contains($source,'Use 6 to 12 nodes')&&!str_contains($source,'5-8 short nodes'),
    'no hidden prompt or retry forces an 11-node tree');
check_stress(str_contains($source,'flow_ai_stress_verify')&&str_contains($source,'24-30 compact nodes'),
    'complex requests explicitly enforced in generator');
echo "FLOW_AI_STRESS_NETWORK_QUALITY_PASS\n";
