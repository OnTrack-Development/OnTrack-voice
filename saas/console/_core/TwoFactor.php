<?php
declare(strict_types=1);

/**
 * Server-side TOTP second factor for OnTrack console administrators.
 * No provider credentials, OTP secrets or recovery codes reach Git/audit logs.
 * Never trust an email, webhook or SSO assertion as a substitute for this step.
 */
function twofa_required(int $userId):bool {
    return one("SELECT m.user_id FROM memberships m JOIN accounts a ON a.id=m.account_id
        WHERE m.user_id=? AND m.role IN ('owner','admin') AND a.status='active' LIMIT 1",[$userId])!==null;
}
function twofa_record(int $userId):?array {
    return one('SELECT * FROM user_totp WHERE user_id=?',[$userId]);
}
function twofa_enabled(int $userId):bool {
    return twofa_record($userId)!==null;
}
function twofa_base32(string $raw):string {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits=0;$value=0;$out='';
    foreach(unpack('C*',$raw) as $byte) {
        $value=($value<<8)|$byte;$bits+=8;
        while($bits>=5){$bits-=5;$out.=$alphabet[($value>>$bits)&31];}
        $value&=(1<<$bits)-1;
    }
    if($bits>0)$out.=$alphabet[($value<<(5-$bits))&31];
    return $out;
}
function twofa_unbase32(string $encoded):string {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$encoded=strtoupper($encoded);
    if(!preg_match('/^[A-Z2-7]{16,64}$/D',$encoded))throw new InvalidArgumentException('invalid_totp_secret');
    $bits=0;$value=0;$out='';
    foreach(str_split($encoded) as $char){
        $index=strpos($alphabet,$char);
        if($index===false)throw new InvalidArgumentException('invalid_totp_secret');
        $value=($value<<5)|$index;$bits+=5;
        if($bits>=8){$bits-=8;$out.=chr(($value>>$bits)&255);}
        $value&=(1<<$bits)-1;
    }
    return $out;
}
function twofa_new_secret():string {return twofa_base32(random_bytes(20));}
function twofa_code(string $secret,int $step):string {
    if($step<0)throw new InvalidArgumentException('invalid_totp_step');
    $key=twofa_unbase32($secret);
    $digest=hash_hmac('sha1',pack('N2',($step>>32)&0xffffffff,$step&0xffffffff),$key,true);
    $offset=ord($digest[19])&15;
    $number=((ord($digest[$offset])&127)<<24)|
        ((ord($digest[$offset+1])&255)<<16)|
        ((ord($digest[$offset+2])&255)<<8)|(ord($digest[$offset+3])&255);
    return str_pad((string)($number%1000000),6,'0',STR_PAD_LEFT);
}
/** Return the accepted 30-second step; never accept a used or older step. */
function twofa_match_step(string $secret,string $code,int $lastStep,int $at):?int {
    if(!preg_match('/^[0-9]{6}$/D',$code))return null;
    $step=intdiv($at,30);
    for($i=-1;$i<=1;$i++){
        $candidate=$step+$i;
        if($candidate>$lastStep && $candidate>=0 &&
            hash_equals(twofa_code($secret,$candidate),$code))return $candidate;
    }
    return null;
}
function twofa_recovery_codes():array {
    $codes=[];
    for($i=0;$i<8;$i++)$codes[]=strtoupper(bin2hex(random_bytes(12)));
    return $codes;
}
function twofa_enroll(int $userId,string $secret,string $code,?int $at=null):array {
    if(!twofa_required($userId)||twofa_enabled($userId))
        throw new InvalidArgumentException('totp_enrollment_unavailable');
    $at=$at??time();
    $step=twofa_match_step($secret,$code,-1,$at);
    if($step===null)throw new InvalidArgumentException('totp_invalid');
    $codes=twofa_recovery_codes();
    $hashes=array_map(static fn(string $v):string=>hash('sha256',$v),$codes);
    insert('INSERT INTO user_totp(user_id,secret_enc,last_step,recovery_hashes,failed_attempts,locked_until,created_at)
        VALUES (?,?,?,?,0,NULL,?)',
        [$userId,seal_secret($secret),$step,json_encode($hashes,JSON_THROW_ON_ERROR),now()]);
    return $codes;
}
/** Database-wide lockout; one-time recovery codes and OTP replay prevention. */
function twofa_check(int $userId,string $submitted,?int $at=null):bool {
    $at=$at??time();
    $submitted=strtoupper(trim($submitted));
    if(!preg_match('/^(?:[0-9]{6}|[A-F0-9]{24})$/D',$submitted))return false;
    db()->beginTransaction();
    try {
        $query='SELECT * FROM user_totp WHERE user_id=?';
        if(driver()==='mysql')$query.=' FOR UPDATE';
        $row=one($query,[$userId]);
        if(!$row){db()->rollBack();return false;}
        if((int)($row['locked_until']??0)>$at){db()->rollBack();return false;}
        $secret=unseal_secret((string)$row['secret_enc']);
        if($secret===null)throw new RuntimeException('totp_secret_unavailable');
        $step=twofa_match_step($secret,$submitted,(int)$row['last_step'],$at);
        $recovery=json_decode((string)$row['recovery_hashes'],true);
        if(!is_array($recovery))throw new RuntimeException('totp_recovery_unavailable');
        $recoveryIndex=null;
        if($step===null && preg_match('/^[A-F0-9]{24}$/D',$submitted)){
            $digest=hash('sha256',$submitted);
            foreach($recovery as $index=>$hash) {
                if(is_string($hash)&&hash_equals($hash,$digest)){$recoveryIndex=$index;break;}
            }
        }
        if($step!==null || $recoveryIndex!==null){
            if($recoveryIndex!==null){unset($recovery[$recoveryIndex]);$recovery=array_values($recovery);}
            execute('UPDATE user_totp SET last_step=?,recovery_hashes=?,failed_attempts=0,locked_until=NULL WHERE user_id=?',
                [$step??(int)$row['last_step'],json_encode($recovery,JSON_THROW_ON_ERROR),$userId]);
            db()->commit();return true;
        }
        $fails=(int)$row['failed_attempts']+1;
        execute('UPDATE user_totp SET failed_attempts=?,locked_until=? WHERE user_id=?',
            [$fails>=5?0:$fails,$fails>=5?$at+900:null,$userId]);
        db()->commit();return false;
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function twofa_begin(int $userId):void {
    session_regenerate_id(true);
    unset($_SESSION['uid'],$_SESSION['account_id'],$_SESSION['support'],$_SESSION['twofa_verified_uid'],
        $_SESSION['pending_2fa_secret'],$_SESSION['twofa_recovery_display']);
    $_SESSION['pending_2fa_uid']=$userId;
    $_SESSION['pending_2fa_at']=time();
    $_SESSION['pending_2fa_fails']=0;
}
function twofa_pending():?array {
    $uid=(int)($_SESSION['pending_2fa_uid']??0);
    $started=(int)($_SESSION['pending_2fa_at']??0);
    if($uid<=0||$started<=0||time()-$started>300){
        unset($_SESSION['pending_2fa_uid'],$_SESSION['pending_2fa_secret']);
        return null;
    }
    return one("SELECT id,name,email FROM users WHERE id=? AND status='active'",[$uid]);
}
function twofa_complete(int $userId):void {
    $members=memberships($userId);
    unset($_SESSION['pending_2fa_uid'],$_SESSION['pending_2fa_at'],$_SESSION['pending_2fa_secret'],
        $_SESSION['pending_2fa_fails'],$_SESSION['support']);
    session_regenerate_id(true);
    $_SESSION['uid']=$userId;
    $_SESSION['twofa_verified_uid']=$userId;
    $_SESSION['account_id']=$members[0]['account_id']??null;
    $_SESSION['bad_logins']=0;
    audit('login.success',$_SESSION['account_id']);
}
