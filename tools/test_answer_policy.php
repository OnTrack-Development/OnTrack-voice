<?php
declare(strict_types=1);
require dirname(__DIR__).'/saas/console/_core/AgentAnswerPolicy.php';
function policy_test(bool $v,string $m):void {if(!$v)throw new RuntimeException($m);}
$p=answer_policy_defaults();
policy_test($p['auto_answer']===false && $p['answer_delay_seconds']===5,'safe manual default');
$q=answer_policy_normalize(['auto_answer'=>true,'answer_delay_seconds'=>8,'response_style'=>'formal_ar','max_call_minutes'=>12,'fallback'=>'human']);
policy_test($q['response_style']==='formal_ar','valid custom answering policy');
foreach([['auto_answer'=>'yes'],['answer_delay_seconds'=>31],['max_call_minutes'=>0],['response_style'=>'unlisted'],['fallback'=>'wrong']] as $invalid){
 try{answer_policy_normalize($invalid);throw new RuntimeException('accepted invalid answering policy');}
 catch(InvalidArgumentException $expected){}
}
echo "AGENT_ANSWER_POLICY_PASS\n";
