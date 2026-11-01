<?php
declare(strict_types=1);
$source = file_get_contents(__DIR__ . '/../saas/console/_core/DevicePairing.php');
if ($source === false) { throw new RuntimeException('missing source'); }
foreach (["'agent_id'=>(int)$"+"agent['id']", "'agent_name'=>$"+"agent['name']", "'agent_instructions'=>$"+"agent['instructions']", "'sim_slot'=>(int)$"+"binding['sim_slot']", "'transport'=>'tenant_pairing_preview_no_sim_bridge'"] as $contract) {
    if (!str_contains($source, $contract)) { throw new RuntimeException('Missing profile field: '.$contract); }
}
echo "DEVICE_AGENT_PROFILE_CONTRACT_PASS\n";
