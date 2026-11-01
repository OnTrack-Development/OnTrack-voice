<?php
declare(strict_types=1);

/**
 * Browser federation for existing OnTrack identities only.
 * A signed assertion authenticates an external SUBJECT, not an email address,
 * order, payment, account membership or the external system's display name.
 * New bindings require explicit consent from an already authenticated OnTrack
 * user with an existing membership in the integration's tenant scope.
 */
const SSO_AUDIENCE = 'ontrack-voice-console';

function verify_external_sso_ticket(string $ticket):array {
    if(strlen($ticket)>1800 ||
       !preg_match('/^([A-Za-z0-9_-]{20,1500})\.([a-f0-9]{64})$/D',$ticket,$parts))
        throw new InvalidArgumentException('sso_ticket_invalid');
    $encoded=$parts[1];
    $raw=base64_decode(strtr($encoded,'-_','+/').str_repeat('=',(4-strlen($encoded)%4)%4),true);
    if(!is_string($raw)||strlen($raw)>1100)
        throw new InvalidArgumentException('sso_ticket_invalid');
    $claims=json_decode($raw,true);
    if(!is_array($claims)||array_is_list($claims) ||
       ($claims['v']??null)!==1 || !is_int($claims['integration_id']??null) ||
       $claims['integration_id']<=0 || !is_int($claims['iat']??null) ||
       !is_int($claims['exp']??null) || !is_string($claims['sub']??null) ||
       !is_string($claims['iss']??null) || !is_string($claims['aud']??null) ||
       !is_string($claims['jti']??null) || !is_string($claims['nonce']??null))
        throw new InvalidArgumentException('sso_ticket_invalid');
    $now=time();
    if($claims['aud']!==SSO_AUDIENCE ||
       !preg_match('/^[A-Za-z0-9._:-]{1,128}$/D',$claims['sub']) ||
       !preg_match('/^[a-f0-9]{32,64}$/D',$claims['jti']) ||
       !preg_match('/^[a-f0-9]{32,64}$/D',$claims['nonce']) ||
       $claims['iat']>$now+30 || $claims['iat']<$now-180 ||
       $claims['exp']<=$now || $claims['exp']>$claims['iat']+180 ||
       $claims['exp']<=$claims['iat'])
        throw new InvalidArgumentException('sso_ticket_expired_or_invalid');
    $integration=one("SELECT * FROM integrations WHERE id=? AND status='active'",[$claims['integration_id']]);
    if(!$integration || !in_array($integration['provider'],['whmcs','laravel'],true) ||
       $claims['iss']!==$integration['provider'])
        throw new InvalidArgumentException('sso_issuer_not_trusted');
    $tenant=account((int)$integration['account_id']);
    if(!$tenant||$tenant['status']!=='active')
        throw new InvalidArgumentException('sso_issuer_not_trusted');
    // Hash-only legacy integration secrets cannot sign SSO; rotate to vault key.
    $key=unseal_secret($integration['secret_enc']??null);
    if($key===null||!hash_equals((string)$integration['token_hash'],hash('sha256',$key)) ||
       !hash_equals(hash_hmac('sha256',$encoded,$key),$parts[2]))
        throw new InvalidArgumentException('sso_signature_invalid');
    return ['claims'=>$claims,'integration'=>$integration];
}

/** Only accounts belonging to this integration may federate through it. */
function sso_account_for_user(int $userId,array $integration):?int {
    $issuer=account((int)$integration['account_id']);
    if(!$issuer||$issuer['status']!=='active')return null;
    $members=all("SELECT m.account_id,a.kind,a.parent_id FROM memberships m
        JOIN accounts a ON a.id=m.account_id JOIN users u ON u.id=m.user_id
        WHERE m.user_id=? AND u.status='active' AND a.status='active'
        ORDER BY m.account_id",[$userId]);
    foreach($members as $m){
        if((int)$m['account_id']===(int)$issuer['id'] ||
           ($issuer['kind']==='reseller' && $m['kind']==='customer' &&
             (int)$m['parent_id']===(int)$issuer['id']) ||
           $issuer['kind']==='platform')
            return (int)$m['account_id'];
    }
    return null;
}

function external_sso_preview(array $verified):array {
    $i=$verified['integration'];$c=$verified['claims'];
    $link=one('SELECT user_id FROM external_identity_links WHERE integration_id=? AND external_subject=?',
        [$i['id'],$c['sub']]);
    $uid=$link?(int)$link['user_id']:null;
    if($uid!==null && sso_account_for_user($uid,$i)===null)
        throw new InvalidArgumentException('sso_link_not_authorized');
    if(one('SELECT id FROM sso_used_tickets WHERE integration_id=? AND jti_hash=?',
        [$i['id'],hash('sha256',$c['jti'])]))
        throw new InvalidArgumentException('sso_ticket_replayed');
    return ['linked_user_id'=>$uid,'issuer'=>(string)$i['provider'],
        'external_subject'=>(string)$c['sub'],'integration_id'=>(int)$i['id']];
}

/**
 * Consume exactly once. Binding is permitted only for a local logged-in user
 * who explicitly confirmed the link and is already a member of the issuer
 * tenant or its reseller children. No account, membership or order is created.
 */
function finish_external_sso(string $ticket,?int $localUserId):array {
    $verified=verify_external_sso_ticket($ticket);
    $i=$verified['integration'];$c=$verified['claims'];
    if(active_support())throw new InvalidArgumentException('sso_support_session_forbidden');
    // Defense in depth: caller cannot assert an arbitrary local user id.
    if($localUserId!==null && (int)(me()['id']??0)!==$localUserId)
        throw new InvalidArgumentException('sso_local_login_required');
    db()->beginTransaction();
    try {
        $link=one('SELECT user_id FROM external_identity_links WHERE integration_id=? AND external_subject=?',
            [$i['id'],$c['sub']]);
        $linked=$link!==null;
        if($linked){
            $uid=(int)$link['user_id'];
            if($localUserId!==null && $localUserId!==$uid)
                throw new InvalidArgumentException('sso_account_mismatch');
        }else{
            if($localUserId===null||$localUserId<=0)
                throw new InvalidArgumentException('sso_local_login_required');
            $uid=$localUserId;
            if(one('SELECT id FROM external_identity_links WHERE integration_id=? AND user_id=?',
                [$i['id'],$uid]))
                throw new InvalidArgumentException('sso_user_already_linked');
        }
        $accountId=sso_account_for_user($uid,$i);
        if($accountId===null)throw new InvalidArgumentException('sso_tenant_denied');
        // Database unique key is the final replay guard even across workers.
        insert('INSERT INTO sso_used_tickets(integration_id,jti_hash,expires_at,created_at) VALUES (?,?,?,?)',
            [$i['id'],hash('sha256',$c['jti']),gmdate('Y-m-d H:i:s',$c['exp']),now()]);
        if(!$linked){
            insert('INSERT INTO external_identity_links(integration_id,external_subject,user_id,created_at) VALUES (?,?,?,?)',
                [$i['id'],$c['sub'],$uid,now()]);
        }
        db()->commit();
        return ['user_id'=>$uid,'account_id'=>$accountId,'new_link'=>!$linked,
            'integration_id'=>(int)$i['id']];
    }catch(PDOException $e){
        if(db()->inTransaction())db()->rollBack();
        if((string)$e->getCode()==='23000'||str_contains(strtolower($e->getMessage()),'unique'))
            throw new InvalidArgumentException('sso_ticket_replayed_or_identity_conflict',0,$e);
        throw $e;
    }catch(Throwable $e){
        if(db()->inTransaction())db()->rollBack();
        throw $e;
    }
}
