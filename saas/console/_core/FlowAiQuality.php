<?php
declare(strict_types=1);
require_once __DIR__.'/FlowPreview.php';

/** Large graph planning quality, NOT proof of real model execution or call-load testing. */
function flow_ai_stress_requested(string $request):bool {
    return preg_match('/(?:extreme|stress[ -]?test|max(?:imum)?[ -]?(?:load|complexity)|(?:أقصى|اعلى|أعلى|اكبر|أكبر|تعجيزي|شبكة عصبية|شبكه عصبيه|ضغط)[^\n]{0,95}(?:شبكة|شبكه|ضغط|تعقيد|عقد|روابط|مخطط|سيناريو|تصميم|graph|load)|(?:1000|١٠٠٠)[^\n]{0,60}(?:محادثة|مكالمة|اختبار))/iu',$request)===1;
}
function flow_ai_graph_quality(array $graph):array {
    $compiled=flow_preview_compile($graph);
    $nodes=$compiled['nodes'];$edges=$compiled['edges'];$start=$compiled['start'];
    $incoming=[];$reverse=[];$branches=0;$merges=0;$linked=0;
    foreach($edges as $source=>$routes){
        if(count($routes)>=2)$branches++;
        foreach($routes as $edge){
            $incoming[$edge['to']]=($incoming[$edge['to']]??0)+1;
            $reverse[$edge['to']][]=$source;
            $linked++;
        }
    }
    foreach($incoming as $count)if($count>=2)$merges++;
    $seen=[];$queue=[$start];
    while($queue){
        $id=array_shift($queue);
        if(isset($seen[$id]))continue;
        $seen[$id]=true;
        foreach($edges[$id]??[] as $edge)if(!isset($seen[$edge['to']]))$queue[]=$edge['to'];
    }
    $ending=[];
    foreach($nodes as $id=>$n)if($n['type']==='end')$ending[]=$id;
    $canFinish=[];$queue=$ending;
    while($queue){
        $id=array_shift($queue);
        if(isset($canFinish[$id]))continue;
        $canFinish[$id]=true;
        foreach($reverse[$id]??[] as $prev)if(!isset($canFinish[$prev]))$queue[]=$prev;
    }
    $missing=array_values(array_diff(array_keys($nodes),array_keys($seen)));
    $dead=array_values(array_diff(array_keys($nodes),array_keys($canFinish)));
    $size=strlen(json_encode($graph,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    return ['nodes'=>count($nodes),'edges'=>$linked,'branch_points'=>$branches,'reconvergence_points'=>$merges,
        'unreachable_nodes'=>$missing,'no_exit_nodes'=>$dead,'byte_size'=>$size,'safe_exit_reachable'=>count($dead)===0];
}
function flow_ai_stress_verify(array $graph,?array $original=null):array {
    $quality=flow_ai_graph_quality($graph);
    $issues=[];
    if($quality['nodes']<24)$issues[]='محتاج 24 عقدة على الأقل بدل '.$quality['nodes'];
    if($quality['edges']<38)$issues[]='محتاج 38 رابطًا على الأقل بدل '.$quality['edges'];
    if($quality['branch_points']<5)$issues[]='محتاج 5 عقد تشعب على الأقل';
    if($quality['reconvergence_points']<4)$issues[]='محتاج 4 نقاط التقاء للمسارات على الأقل';
    if($quality['unreachable_nodes'])$issues[]='فيه '.count($quality['unreachable_nodes']).' عقد غير متصلة بالبداية';
    if($quality['no_exit_nodes'])$issues[]='فيه '.count($quality['no_exit_nodes']).' عقدة لا تصل لنهاية آمنة';
    if($original!==null && is_array($original['nodes']??null)){
        $oldIds=array_column($original['nodes'],'id');$newIds=array_column($graph['nodes'],'id');
        $retained=count(array_intersect($oldIds,$newIds));
        if($retained<min(8,count($oldIds)))
            $issues[]='المقترح فقد عقدًا أصلية أكثر من اللازم (المحفوظ '.$retained.')';
    }
    return ['ok'=>$issues===[],'issues'=>$issues,'metrics'=>$quality];
}
