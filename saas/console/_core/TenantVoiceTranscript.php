<?php
declare(strict_types=1);

/**
 * Explicit owner-requested transcript: only actual Gemini Live text reported by
 * the authenticated device. A report may be analyzed later; provider outages
 * cannot erase or fabricate the underlying utterances.
 */
function tenant_voice_transcript_store(array $b,string $callId,string $text):array {
    if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$callId))
        throw new InvalidArgumentException('invalid_transcript_call');
    if(mb_strlen($text,'UTF-8')<18 || mb_strlen($text,'UTF-8')>12000 ||
       preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$text))
        throw new InvalidArgumentException('invalid_call_transcript');
    $args=[(int)$b['account_id'],(int)$b['site_id'],(int)$b['id'],(int)$b['sim_slot'],$callId];
    $call=one('SELECT id FROM calls WHERE account_id=? AND site_id=? AND device_binding_id=? AND sim_slot=? AND external_call_id=?',$args);
    if(!$call)throw new InvalidArgumentException('call_not_found_for_sim');
    $existing=one('SELECT transcript,created_at FROM tenant_call_transcripts WHERE account_id=? AND site_id=? AND device_binding_id=? AND sim_slot=? AND call_id=?',$args);
    if($existing) {
        // Older plaintext records are encrypted in place on first authenticated
        // access. An update cannot change call ownership or overwrite fresh data.
        $before=(string)$existing['transcript'];
        if(!str_starts_with($before,'enc:v1:'))
            execute('UPDATE tenant_call_transcripts SET transcript=? WHERE account_id=? AND site_id=? AND device_binding_id=? AND sim_slot=? AND call_id=? AND transcript=?',
                ['enc:v1:'.seal_secret($before),...$args,$before]);
        return ['saved'=>true,'duplicate'=>true,'call_id'=>$callId];
    }
    try {
        insert('INSERT INTO tenant_call_transcripts
            (account_id,site_id,device_binding_id,sim_slot,call_id,transcript,source,created_at)
            VALUES (?,?,?,?,?,?,?,?)',[...$args,'enc:v1:'.seal_secret(trim($text)),'gemini_live_device',now()]);
    }catch(PDOException $e){
        if(!one('SELECT call_id FROM tenant_call_transcripts WHERE device_binding_id=? AND call_id=?',[$b['id'],$callId]))throw $e;
        return ['saved'=>true,'duplicate'=>true,'call_id'=>$callId];
    }
    return ['saved'=>true,'duplicate'=>false,'call_id'=>$callId];
}
function tenant_voice_transcript_find(array $b,string $callId):?array {
    $args=[$b['account_id'],$b['site_id'],$b['id'],$b['sim_slot'],$callId];
    $row=one('SELECT transcript,source,created_at FROM tenant_call_transcripts
        WHERE account_id=? AND site_id=? AND device_binding_id=? AND sim_slot=? AND call_id=?',$args);
    if(!$row)return null;
    $stored=(string)$row['transcript'];
    if(str_starts_with($stored,'enc:v1:')){
        $clear=unseal_secret(substr($stored,7));
        if(!is_string($clear))throw new RuntimeException('tenant_transcript_decryption_failed');
        $row['transcript']=$clear;
    }else{
        // Encrypt legacy plaintext without changing the existing staff-visible
        // transcript. No transcript is exposed across another tenant or SIM.
        execute('UPDATE tenant_call_transcripts SET transcript=? WHERE account_id=? AND site_id=? AND device_binding_id=? AND sim_slot=? AND call_id=? AND transcript=?',
            ['enc:v1:'.seal_secret($stored),...$args,$stored]);
    }
    return $row;
}
function tenant_workspace_call_transcript(array $b,array $payload):array {
    $callId=$payload['call_id']??null;
    if(!is_string($callId)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$callId))
        throw new InvalidArgumentException('invalid_transcript_call');
    // Enforce the same tenant/binding ownership even when no transcript exists.
    if(!one('SELECT id FROM calls WHERE account_id=? AND site_id=? AND device_binding_id=? AND sim_slot=? AND external_call_id=?',
        [$b['account_id'],$b['site_id'],$b['id'],$b['sim_slot'],$callId]))
        throw new InvalidArgumentException('call_not_found_for_sim');
    return ['call_id'=>$callId,'transcript'=>tenant_voice_transcript_find($b,$callId)['transcript']??null,
        'source'=>'gemini_live_device'];
}
