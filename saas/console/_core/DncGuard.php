<?php
declare(strict_types=1);
require_once __DIR__.'/DncLedger.php';

/**
 * Account-scoped DNC protection for inbound contact ingestion.
 *
 * Phone comparisons deliberately remove presentation characters and support
 * Egyptian 01xxxxxxxxx / +201xxxxxxxxx / 00201xxxxxxxxx equivalence. No
 * cross-country assumptions are made for other national numbers.
 *
 * A prior opt-out must not be overridden by a later "consent=true" lead
 * imported from another site or integration. Suppression now survives contact
 * deletion in a durable ledger; Android/campaign pre-dial enforcement is OPEN.
 */
function dnc_phone_key(string $phone):string {
    $digits=preg_replace('/[^0-9]/','',$phone);
    if(!is_string($digits)||$digits==='')return '';
    if(str_starts_with($digits,'00'))$digits=substr($digits,2);
    if(preg_match('/^01[0-9]{9}$/D',$digits))$digits='20'.substr($digits,1);
    return $digits;
}

/** Returns true only for opt-outs belonging to the SAME account. */
function dnc_is_suppressed(int $accountId,string $phone):bool {
    $key=dnc_phone_key($phone);
    if($accountId<=0||strlen($key)<8||strlen($key)>40)
        throw new InvalidArgumentException('invalid_dnc_lookup');
    if(dnc_ledger_contains($accountId,$key))return true;
    // Legacy direct imports may have created opted-out contacts after the
    // initial backfill. Preserve the old fail-closed lookup and heal the ledger.
    $blocked=all('SELECT phone FROM contacts WHERE account_id=? AND dnc=1',[$accountId]);
    foreach($blocked as $row){
        if(dnc_phone_key((string)$row['phone'])===$key){
            dnc_record_suppression($accountId,$phone,'legacy_contact');
            return true;
        }
    }
    return false;
}

/** Explicit opt-out persists independently of the contact row. */
function dnc_effective_flag(int $accountId,string $phone,bool $requestedDnc,
                            string $source='contact_optout'):int {
    if($requestedDnc){
        dnc_record_suppression($accountId,$phone,$source);
        return 1;
    }
    return dnc_is_suppressed($accountId,$phone)?1:0;
}
