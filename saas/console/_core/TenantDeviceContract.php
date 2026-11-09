<?php
declare(strict_types=1);
require_once __DIR__.'/DevicePairing.php';

/**
 * Read-only, authenticated SaaS↔Android contract probe. This describes
 * server-side support, NOT telephony/PCM/Gemini readiness or proof of a call.
 * The existing heartbeat, pairing and voice_session responses remain intact.
 */
function tenant_device_contract_probe(array $authorizedBinding):array {
    $device=(int)($authorizedBinding['id']??0);
    $account=(int)($authorizedBinding['account_id']??0);
    $site=(int)($authorizedBinding['site_id']??0);
    $slot=(int)($authorizedBinding['sim_slot']??-1);
    if($device<1||$account<1||$site<1||!in_array($slot,[0,1],true))
        throw new InvalidArgumentException('device_contract_scope_invalid');
    // Re-read the current binding even if a pairing was revoked or an agent
    // was reassigned after bearer authorization.
    $live=one("SELECT d.* FROM device_bindings d
      JOIN accounts a ON a.id=d.account_id AND a.status='active'
      JOIN sites s ON s.id=d.site_id AND s.account_id=d.account_id AND s.status='active'
      JOIN agents ag ON ag.id=d.agent_id AND ag.account_id=d.account_id
        AND ag.site_id=d.site_id AND ag.status!='archived'
      WHERE d.id=? AND d.account_id=? AND d.site_id=? AND d.sim_slot=?
        AND d.status='active'",[$device,$account,$site,$slot]);
    if(!$live)throw new InvalidArgumentException('device_contract_binding_revoked');
    $flow=published_agent_flow($account,$site,(int)$live['agent_id']);
    return [
        'contract'=>'tenant_device_v1',
        'control_plane_supported'=>true,
        'carrier_audio_tested_by_probe'=>false, // Not measured by this endpoint.
        'device_id'=>$device,
        'account_id'=>$account,
        'site_id'=>$site,
        'sim_slot'=>$slot,
        'agent_id'=>(int)$live['agent_id'],
        'agent_revision'=>(int)($live['agent_revision']??1),
        'published_flow_ready'=>$flow!==null,
        'published_flow_revision'=>$flow?(int)$flow['revision']:null,
        // Not a command to dial, update software or rotate a device token.
        'supported_operations'=>['heartbeat','published_flow',
           'voice_preflight','voice_session','voice_failover',
           'workspace_call_event','workspace_call_report','workspace_call_timeline']
    ];
}
