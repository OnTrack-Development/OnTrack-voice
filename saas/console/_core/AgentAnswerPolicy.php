<?php
declare(strict_types=1);
/**
 * A published Agent Studio profile drives Gemini Live. Legacy graph snapshots
 * remain stored for backward compatibility, never as conversation routing.
 */
// Auto-answer is deliberate opt-in (false for a new agent), not proof that
// the phone listener is broken. To enable an owner-approved SIM auto-answer,
// save the policy and PUBLISH a new immutable Flow snapshot; Android reads that
// published snapshot, not unsaved admin form state. Regression 2026-11-04:
// separate RINGING detection, ADB 'A' command, and AI/PCM readiness.
function answer_policy_defaults():array {
    return [
        'auto_answer'=>false,'answer_delay_seconds'=>5,
        'response_style'=>'egyptian_short','max_call_minutes'=>10,'fallback'=>'human',
        'conversation_mode'=>'dynamic',
        'scenario_mode'=>'builder','scenario_text'=>'',
        'api_connector_id'=>0,'api_language'=>'auto',
        'goal'=>'افهم طلب المتصل وساعده بوضوح وبأقل عدد أسئلة ممكن.',
        'guardrails'=>'لا تخمّن بيانات أو أسعار أو نتائج عمليات خارجية. تحقق من الصلاحيات وصعّد عند اللزوم.',
        'use_knowledge'=>true,'use_signed_facts'=>true,
        'human_handoff'=>true
    ];
}
function answer_policy_normalize(array $input):array {
    $v=array_replace(answer_policy_defaults(),$input);
    if(!is_bool($v['auto_answer']))throw new InvalidArgumentException('invalid_auto_answer');
    if(!is_int($v['answer_delay_seconds'])||$v['answer_delay_seconds']<0||$v['answer_delay_seconds']>30)throw new InvalidArgumentException('invalid_answer_delay');
    if(!is_int($v['max_call_minutes'])||$v['max_call_minutes']<1||$v['max_call_minutes']>30)throw new InvalidArgumentException('invalid_call_duration');
    if(!in_array($v['response_style'],['egyptian_short','formal_ar','english','bilingual'],true))throw new InvalidArgumentException('invalid_style');
    if(!in_array($v['fallback'],['human','end'],true))throw new InvalidArgumentException('invalid_fallback');
    // Gemini Live remains dynamic; this only selects WHICH owner-authored
    // conversation instructions guide the SAME live session.
    if($v['conversation_mode']!=='dynamic')throw new InvalidArgumentException('invalid_conversation_mode');
    if(!in_array($v['scenario_mode'],['builder','scenario'],true))
        throw new InvalidArgumentException('invalid_agent_scenario_mode');
    if(!is_string($v['scenario_text'])||mb_strlen($v['scenario_text'])>8000||
       !mb_check_encoding($v['scenario_text'],'UTF-8'))
        throw new InvalidArgumentException('invalid_agent_scenario_text');
    $v['scenario_text']=trim($v['scenario_text']);
    if(!is_int($v['api_connector_id'])||$v['api_connector_id']<0)
        throw new InvalidArgumentException('invalid_agent_api_connector');
    if(!in_array($v['api_language'],['auto','ar','en','multilingual'],true))
        throw new InvalidArgumentException('invalid_agent_api_language');
    foreach(['goal'=>1800,'guardrails'=>1800] as $name=>$length){
        if(!is_string($v[$name])||mb_strlen($v[$name])>$length||!mb_check_encoding($v[$name],'UTF-8'))
            throw new InvalidArgumentException('invalid_agent_'.$name);
        $v[$name]=trim($v[$name]);
    }
    foreach(['use_knowledge','use_signed_facts','human_handoff'] as $key)
        if(!is_bool($v[$key]))throw new InvalidArgumentException('invalid_agent_capability');
    return $v;
}
function answer_policy_for_agent(int $accountId,int $agentId):array {
    $row=one('SELECT policy_json FROM agent_answer_policies WHERE account_id=? AND agent_id=?',[$accountId,$agentId]);
    if(!$row)return answer_policy_defaults();
    $data=json_decode((string)$row['policy_json'],true);
    return answer_policy_normalize(is_array($data)?$data:[]);
}
function save_answer_policy(int $accountId,int $agentId,array $policy):void {
    $normalized=answer_policy_normalize($policy);
    $json=json_encode($normalized,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    if(one('SELECT agent_id FROM agent_answer_policies WHERE account_id=? AND agent_id=?',[$accountId,$agentId]))
        execute('UPDATE agent_answer_policies SET policy_json=?,updated_at=? WHERE account_id=? AND agent_id=?',[$json,now(),$accountId,$agentId]);
    else insert('INSERT INTO agent_answer_policies(account_id,agent_id,policy_json,updated_at) VALUES (?,?,?,?)',[$accountId,$agentId,$json,now()]);
}
function answer_policy_form(array $form):array {
    return answer_policy_normalize([
        'auto_answer'=>isset($form['auto_answer']),
        'answer_delay_seconds'=>(int)($form['answer_delay_seconds']??5),
        'response_style'=>(string)($form['response_style']??'egyptian_short'),
        'max_call_minutes'=>(int)($form['max_call_minutes']??10),
        'fallback'=>(string)($form['fallback']??'human'),
        'conversation_mode'=>'dynamic',
        'scenario_mode'=>(string)($form['scenario_mode']??'builder'),
        'scenario_text'=>(string)($form['scenario_text']??''),
        'api_connector_id'=>(int)($form['api_connector_id']??0),
        'api_language'=>(string)($form['api_language']??'auto'),
        'goal'=>(string)($form['agent_goal'??answer_policy_defaults()['goal']),
        'guardrails'=>(string)($form['agent_guardrails']??answer_policy_defaults()['guardrails']),
        'use_knowledge'=>isset($form['use_knowledge']),
        'use_signed_facts'=>isset($form['use_signed_facts']),
        'human_handoff'=>isset($form['human_handoff'])
    ]);
}
