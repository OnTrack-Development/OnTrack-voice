<?php
declare(strict_types=1);
/** Configuration is scoped to one agent, and only published snapshots reach paired devices. */
function answer_policy_defaults():array {
    return ['auto_answer'=>false,'answer_delay_seconds'=>5,'response_style'=>'egyptian_short','max_call_minutes'=>10,'fallback'=>'human'];
}
function answer_policy_normalize(array $input):array {
    $v=array_replace(answer_policy_defaults(),$input);
    if(!is_bool($v['auto_answer']))throw new InvalidArgumentException('invalid_auto_answer');
    if(!is_int($v['answer_delay_seconds'])||$v['answer_delay_seconds']<0||$v['answer_delay_seconds']>30)throw new InvalidArgumentException('invalid_answer_delay');
    if(!is_int($v['max_call_minutes'])||$v['max_call_minutes']<1||$v['max_call_minutes']>30)throw new InvalidArgumentException('invalid_call_duration');
    if(!in_array($v['response_style'],['egyptian_short','formal_ar','english','bilingual'],true))throw new InvalidArgumentException('invalid_style');
    if(!in_array($v['fallback'],['human','end'],true))throw new InvalidArgumentException('invalid_fallback');
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
    $json=json_encode($normalized,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
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
        'fallback'=>(string)($form['fallback']??'human')
    ]);
}
