<?php
declare(strict_types=1);

/**
 * Account-scoped DNC protection for inbound contact ingestion.
 *
 * Phone comparisons deliberately remove presentation characters and support
 * Egyptian 01xxxxxxxxx / +201xxxxxxxxx / 00201xxxxxxxxx equivalence. No
 * cross-country assumptions are made for other national numbers.
 *
 * A prior opt-out must not be overridden by a later "consent=true" lead
 * imported from another site or integration. This is an ingestion guard,
 * NOT a replacement for a persistent DNC ledger and pre-dial enforcement.
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
    if($accountId<=0||$key==='')throw new InvalidArgumentException('invalid_dnc_lookup');
    // Existing SQLite and MySQL schemas lack a normalized-phone index.
    // Query only opted-out rows, not all contacts, and never across tenants.
    $blocked=all('SELECT phone FROM contacts WHERE account_id=? AND dnc=1',[$accountId]);
    foreach($blocked as $row){
        if(dnc_phone_key((string)$row['phone'])===$key)return true;
    }
    return false;
}

/** New/edited contacts may add suppression but cannot bypass an existing one. */
function dnc_effective_flag(int $accountId,string $phone,bool $requestedDnc):int {
    return ($requestedDnc||dnc_is_suppressed($accountId,$phone))?1:0;
}
