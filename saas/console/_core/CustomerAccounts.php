<?php
declare(strict_types=1);
/** Tenant-isolated customer administration. Never erase financial evidence. */
function customer_accounts_init():void {
    static $ready=false;
    if($ready)return;
    $engine=driver()==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    db()->exec("CREATE TABLE IF NOT EXISTS customer_profiles (
        account_id BIGINT PRIMARY KEY,
        contact_name VARCHAR(190) NOT NULL DEFAULT '',
        contact_email VARCHAR(190) NOT NULL DEFAULT '',
        phone VARCHAR(45) NOT NULL DEFAULT '',
        address TEXT NOT NULL,
        notes TEXT NOT NULL,
        updated_at VARCHAR(19) NOT NULL
    )".$engine);
    $ready=true;
}
function customer_accounts_scope(array $actor,int $customerId):array {
    $own=(int)($actor['account_id']??0);
    $customer=account($customerId);
    if(!$customer||$customerId<1||$customerId===$own||
       ($customer['kind']??'')==='platform')
        throw new InvalidArgumentException('customer_scope_denied');
    if(($actor['kind']??'')==='platform'){
        if(!in_array($customer['kind'],['customer','reseller'],true)||
          ($customer['kind']==='reseller' && !platform_resellers_visible()))
            throw new InvalidArgumentException('customer_scope_denied');
        return $customer;
    }
    if(($actor['kind']??'')==='reseller' &&
       $customer['kind']==='customer' &&
       (int)($customer['parent_id']??0)===$own)
        return $customer;
    throw new InvalidArgumentException('customer_scope_denied');
}
function customer_accounts_profile(int $customerId):array {
    customer_accounts_init();
    return one('SELECT * FROM customer_profiles WHERE account_id=?',[$customerId])
        ??['account_id'=>$customerId,'contact_name'=>'','contact_email'=>'',
            'phone'=>'','address'=>'','notes'=>'','updated_at'=>''];
}
function customer_accounts_update(array $actor,int $id,array $data):void {
    $target=customer_accounts_scope($actor,$id);
    if(($target['status']??'')==='archived')
        throw new InvalidArgumentException('archived_customer_not_editable');
    $name=trim((string)($data['name']??''));
    $contact=trim((string)($data['contact_name']??''));
    $email=strtolower(trim((string)($data['contact_email']??'')));
    $phone=trim((string)($data['phone']??''));
    $address=trim((string)($data['address']??''));
    $notes=trim((string)($data['notes']??''));
    if($name===''||mb_strlen($name)>120 ||
       mb_strlen($contact)>190 || ($email!==''&&(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>190))||
       mb_strlen($phone)>45||mb_strlen($address)>800||mb_strlen($notes)>2000)
        throw new InvalidArgumentException('بيانات العميل غير صالحة');
    customer_accounts_init();
    db()->beginTransaction();
    try {
        execute('UPDATE accounts SET name=? WHERE id=?',[$name,$id]);
        if(one('SELECT account_id FROM customer_profiles WHERE account_id=?',[$id])){
            execute('UPDATE customer_profiles SET contact_name=?,contact_email=?,phone=?,address=?,notes=?,updated_at=? WHERE account_id=?',
               [$contact,$email,$phone,$address,$notes,now(),$id]);
        }else{
            insert('INSERT INTO customer_profiles(account_id,contact_name,contact_email,phone,address,notes,updated_at) VALUES (?,?,?,?,?,?,?)',
                [$id,$contact,$email,$phone,$address,$notes,now()]);
        }
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
/** Owner email is a login identity, not merely invoice contact email. */
function customer_accounts_change_login(array $actor,int $id,string $email):void {
    $target=customer_accounts_scope($actor,$id);
    if(($target['status']??'')==='archived')
        throw new InvalidArgumentException('archived_customer_not_editable');
    $email=strtolower(trim($email));
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190)
        throw new InvalidArgumentException('البريد الجديد غير صالح');
    $owner=one("SELECT u.id,u.email FROM users u JOIN memberships m ON m.user_id=u.id
        WHERE m.account_id=? AND m.role='owner' ORDER BY m.id LIMIT 1",[$id]);
    if(!$owner)throw new InvalidArgumentException('لم يتم العثور على مالك الحساب');
    if((string)$owner['email']===$email)return;
    if(one('SELECT id FROM users WHERE email=? AND id<>?',[$email,$owner['id']]))
        throw new InvalidArgumentException('البريد الإلكتروني مستخدم بالفعل');
    // A user who owns multiple tenant memberships must not be renamed silently.
    if((int)one('SELECT COUNT(*) n FROM memberships WHERE user_id=?',[$owner['id']])['n']!==1)
        throw new InvalidArgumentException('هذا المالك مرتبط بعدة حسابات؛ يلزم تغيير البريد من ملفه الشخصي');
    execute('UPDATE users SET email=? WHERE id=?',[$email,$owner['id']]);
}
/** Enumerate all tenant-scoped tables, not just the first few known relations. */
function customer_accounts_has_dependencies(int $id):bool {
    $tables=driver()==='mysql'?all('SHOW TABLES'):
        all("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
    foreach($tables as $t){
        $table=(string)array_values($t)[0];
        if(!preg_match('/^[A-Za-z_][A-Za-z_0-9]*$/D',$table)||
           in_array($table,['accounts','memberships','customer_profiles','audit_events'],true))
            continue;
        $cols=driver()==='mysql'?array_column(all('SHOW COLUMNS FROM `'.$table.'`'),'Field'):
            array_column(all('PRAGMA table_info("'.$table.'")'),'name');
        foreach(['account_id','reseller_account_id','issuer_account_id','owner_account_id'] as $col){
            if(in_array($col,$cols,true) &&
                (int)(one('SELECT COUNT(*) n FROM `'.$table.'` WHERE `'.$col.'`=?',[$id])['n']??0)>0)
                return true;
        }
    }
    return false;
}
/** Suspend or reactivate; only a deliberate action may reactivate. */
function customer_accounts_status(array $actor,int $id,string $status):void {
    $target=customer_accounts_scope($actor,$id);
    if(!in_array($status,['active','suspended'],true)||
       ($target['status']??'')==='archived')throw new InvalidArgumentException('invalid_customer_status');
    if($status==='active' && (int)($target['parent_id']??0)>0){
        $parent=account((int)$target['parent_id']);
        if(!$parent||$parent['status']!=='active')
            throw new InvalidArgumentException('لا يمكن تفعيل عميل ريسيلر موقوف');
    }
    // Suspending a reseller pauses all descendants at the service gate,
    // without cancelling customer subscriptions or changing child statuses.
    // Reactivation restores only customers still independently active/paid.
    execute('UPDATE accounts SET status=? WHERE id=?',[$status,$id]);
}
/** Delete empty accounts, archive accounts with any billing/operational history. */
function customer_accounts_delete(array $actor,int $id):string {
    $target=customer_accounts_scope($actor,$id);
    if(($target['status']??'')==='archived')return 'archived';
    $children=(int)(one('SELECT COUNT(*) n FROM accounts WHERE parent_id=?',[$id])['n']??0);
    if($children>0)throw new InvalidArgumentException('لا يمكن حذف ريسيلر لديه حسابات تابعة؛ عالجها أولًا');
    if(customer_accounts_has_dependencies($id)){
        execute("UPDATE accounts SET status='archived' WHERE id=?",[$id]);
        return 'archived';
    }
    // An empty account has no purchases, calls, devices, services or invoices.
    // Keep security audit events and shared users, but revoke the deleted membership.
    db()->beginTransaction();
    try{
        $members=all('SELECT user_id FROM memberships WHERE account_id=?',[$id]);
        execute('DELETE FROM memberships WHERE account_id=?',[$id]);
        execute('DELETE FROM customer_profiles WHERE account_id=?',[$id]);
        execute('DELETE FROM accounts WHERE id=?',[$id]);
        foreach($members as $member){
            $uid=(int)$member['user_id'];
            if((int)(one('SELECT COUNT(*) n FROM memberships WHERE user_id=?',[$uid])['n']??0)===0)
                execute("UPDATE users SET status='suspended' WHERE id=? AND status='active'",[$uid]);
        }
        db()->commit();
        return 'deleted';
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
/** Archive is reversible and never erases receipts or subscriptions. */
function customer_accounts_restore(array $actor,int $id):void {
    $target=customer_accounts_scope($actor,$id);
    if(($target['status']??'')!=='archived')
        throw new InvalidArgumentException('customer_not_archived');
    execute("UPDATE accounts SET status='suspended' WHERE id=?",[$id]);
}
function customer_accounts_metrics(int $id):array {
    require_once __DIR__.'/BillingInvoices.php';
    require_once __DIR__.'/BillingUsage.php';
    $sub=one("SELECT s.id,p.name,p.price_minor,p.call_quota,s.starts_at,s.ends_at
        FROM subscriptions s JOIN plans p ON p.id=s.plan_id
        WHERE s.account_id=? AND s.status='active' AND s.starts_at<=? AND s.ends_at>?
        ORDER BY s.id DESC LIMIT 1",[$id,now(),now()]);
    $counts=[];
    foreach(['sites','agents','devices','calls','subscriptions','orders'] as $table)
        $counts[$table]=(int)(one('SELECT COUNT(*) n FROM `'.$table.'` WHERE account_id=?',[$id])['n']??0);
    billing_invoice_init();
    $counts['invoices']=(int)(one('SELECT COUNT(*) n FROM billing_invoices WHERE account_id=?',[$id])['n']??0);
    $counts['paid_minor']=(int)(one("SELECT COALESCE(SUM(amount_minor),0) n FROM billing_invoices
        WHERE account_id=? AND status='paid'",[$id])['n']??0);
    return ['subscription'=>$sub,'counts'=>$counts,'usage'=>voice_usage_summary($id)];
}
