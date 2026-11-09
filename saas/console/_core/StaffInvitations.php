<?php
declare(strict_types=1);

/**
 * Staff invitations are consent-based. An account owner cannot attach another
 * existing user by knowing their email. No user or membership is created here.
 * Existing authenticated users accept invitations themselves; new addresses
 * remain pending until the separate verified-registration flow is delivered.
 */
function create_staff_invitation(int $accountId,string $name,string $email,string $role,int $creatorId):int {
    $email=strtolower(trim($email));$name=trim($name);
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190||
       $name===''||mb_strlen($name)>120||
       !in_array($role,['manager','operator','reception','finance','support','viewer'],true))
        throw new InvalidArgumentException('invalid_staff_invitation');
    $account=account($accountId);
    if(!$account||$account['status']!=='active')throw new InvalidArgumentException('account_inactive');
    // A direct invocation of this service must not bypass controller RBAC.
    // Only a real, active owner/admin membership may issue invitations for
    // this exact tenant; another platform account or staff member is not enough.
    $actor=one("SELECT m.user_id FROM memberships m
        JOIN users u ON u.id=m.user_id AND u.status='active'
        WHERE m.user_id=? AND m.account_id=? AND m.role IN ('owner','admin')",
        [$creatorId,$accountId]);
    if(!$actor)throw new InvalidArgumentException('invalid_inviter');
    db()->beginTransaction();
    try{
        $existingMember=one("SELECT m.id FROM memberships m JOIN users u ON u.id=m.user_id
            WHERE m.account_id=? AND LOWER(u.email)=?",[$accountId,$email]);
        if($existingMember)throw new InvalidArgumentException('already_a_member');
        $previous=one('SELECT id,status,expires_at FROM staff_invitations WHERE account_id=? AND email=?',[$accountId,$email]);
        if($previous && $previous['status']==='pending' && $previous['expires_at']>now())
            throw new InvalidArgumentException('invitation_already_pending');
        if($account['kind']!=='platform'){
            $limit=site_limit($accountId,'staff_limit');
            $members=(int)one('SELECT COUNT(*) n FROM memberships WHERE account_id=?',[$accountId])['n'];
            $pending=(int)one("SELECT COUNT(*) n FROM staff_invitations
                WHERE account_id=? AND status='pending' AND expires_at>?",[$accountId,now()])['n'];
            if($members+$pending >= $limit)throw new InvalidArgumentException('staff_limit_reached');
        }
        $expires=gmdate('Y-m-d H:i:s',time()+7*86400);
        if($previous){
            execute("UPDATE staff_invitations SET name=?,role=?,status='pending',created_by=?,created_at=?,expires_at=?,accepted_by=NULL,accepted_at=NULL WHERE id=?",
                [$name,$role,$creatorId,now(),$expires,$previous['id']]);
            $id=(int)$previous['id'];
        }else{
            $id=insert("INSERT INTO staff_invitations(account_id,email,name,role,status,created_by,created_at,expires_at)
                VALUES (?,?,?,?,'pending',?,?,?)",[$accountId,$email,$name,$role,$creatorId,now(),$expires]);
        }
        db()->commit();
        return $id;
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}

function pending_staff_invitations_for_user(int $userId):array {
    $user=one("SELECT email FROM users WHERE id=? AND status='active'",[$userId]);
    if(!$user)return [];
    return all("SELECT i.id,i.account_id,i.role,i.name,i.expires_at,a.name account_name
        FROM staff_invitations i JOIN accounts a ON a.id=i.account_id
        WHERE i.email=? AND i.status='pending' AND i.expires_at>? AND a.status='active'
        ORDER BY i.id DESC", [strtolower((string)$user['email']),now()]);
}

/** Only the authenticated addressee can accept; email in POST is ignored. */
function accept_staff_invitation(int $invitationId,int $userId):int {
    if($invitationId<=0||$userId<=0)throw new InvalidArgumentException('invalid_invitation');
    if(active_support())throw new InvalidArgumentException('support_session_forbidden');
    db()->beginTransaction();
    try{
        $user=one("SELECT id,email FROM users WHERE id=? AND status='active'",[$userId]);
        $invite=one("SELECT i.*,a.kind,a.status account_status FROM staff_invitations i
            JOIN accounts a ON a.id=i.account_id WHERE i.id=?",[$invitationId]);
        if(!$user||!$invite||$invite['status']!=='pending'||$invite['expires_at']<=now()||
           $invite['account_status']!=='active'||
           !hash_equals((string)$invite['email'],strtolower((string)$user['email'])))
            throw new InvalidArgumentException('invitation_not_available');
        $accountId=(int)$invite['account_id'];
        if(one('SELECT id FROM memberships WHERE user_id=? AND account_id=?',[$userId,$accountId]))
            throw new InvalidArgumentException('already_a_member');
        if($invite['kind']!=='platform'){
            $limit=site_limit($accountId,'staff_limit');
            $count=(int)one('SELECT COUNT(*) n FROM memberships WHERE account_id=?',[$accountId])['n'];
            if($count>=$limit)throw new InvalidArgumentException('staff_limit_reached');
        }
        $changed=execute("UPDATE staff_invitations SET status='accepted',accepted_by=?,accepted_at=?
            WHERE id=? AND status='pending' AND email=? AND expires_at>?",
            [$userId,now(),$invitationId,strtolower((string)$user['email']),now()]);
        if($changed!==1)throw new RuntimeException('invitation_race');
        insert("INSERT INTO memberships(user_id,account_id,role,created_at) VALUES (?,?,?,?)",
            [$userId,$accountId,$invite['role'],now()]);
        db()->commit();
        return $accountId;
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}

function cancel_staff_invitation(int $invitationId,int $accountId):void {
    if(execute("UPDATE staff_invitations SET status='cancelled'
        WHERE id=? AND account_id=? AND status='pending'",[$invitationId,$accountId])!==1)
        throw new InvalidArgumentException('invitation_not_available');
}
