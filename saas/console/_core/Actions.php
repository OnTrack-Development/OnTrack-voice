<?php
declare(strict_types=1);

function field(string $key,int $max=200):string{
    $v=trim((string)($_POST[$key]??''));
    if(mb_strlen($v)>$max)throw new InvalidArgumentException('النص أطول من المسموح');
    return $v;
}
function required(string $key,int $max=190):string {
    $v=field($key,$max);if($v==='')throw new InvalidArgumentException('حقل مطلوب: '.$key);return $v;
}
function intfield(string $k):int{return max(0,(int)($_POST[$k]??0));}
function confirm_account(int $id):array{
    $a=account($id);if(!$a)throw new RuntimeException('الحساب غير موجود');return $a;
}
function scoped_customer(int $id):array{
    $a=mine();if(!$a)deny();
    if($a['kind']==='platform')return confirm_account($id);
    if($a['kind']==='reseller' && is_reseller_child((int)$a['account_id'],$id))return confirm_account($id);
    deny();
}
function plans_for(string $kind):array{return all("SELECT * FROM plans WHERE kind=? AND status='active' ORDER BY id",[$kind]);}
function act():void {
    require_login();require_csrf();
    $a=mine();if(!$a)deny();
    $action=(string)($_POST['action']??'');
    if($a['role']==='support_viewer' && $action!=='support_leave')deny(403,'وضع الدعم للقراءة فقط');
    $id=(int)$a['account_id'];$kind=$a['kind'];
    try {
        switch($action) {
            case 'switch':
                $target=intfield('account_id');$valid=false;
                foreach(memberships((int)me()['id']) as $m)if((int)$m['account_id']===$target)$valid=true;
                if(!$valid)deny();
                unset($_SESSION['support']);
                $_SESSION['account_id']=$target;unset($_SESSION['site_id']);
                session_regenerate_id(true);go('/');
            case 'support_enter':
                if(!in_array($kind,['platform','reseller'],true) || !in_array($a['role'],['owner','admin','support'],true))deny();
                $target=intfield('account_id');if(!support_access($target))deny();
                $reason=required('reason',200);
                $expiry=gmdate('Y-m-d H:i:s',time()+900);
                insert("INSERT INTO support_grants(actor_user_id,target_account_id,reason,expires_at,created_at) VALUES (?,?,?,?,?)",[me()['id'],$target,$reason,$expiry,now()]);
                $_SESSION['support']=['account_id'=>$target,'origin_id'=>$id,'expires'=>time()+900];
                audit('support.start',$target,['from'=>$id,'reason'=>$reason]);
                session_regenerate_id(true);go('/');
            case 'support_leave':
                if(!empty($_SESSION['support']))audit('support.end',(int)$_SESSION['support']['account_id']);
                unset($_SESSION['support']);session_regenerate_id(true);go('/');
            case 'create_account':
                if(!in_array($kind,['platform','reseller'],true)||!in_array($a['role'],['owner','admin'],true))deny();
                $type=field('kind',20);if($kind==='reseller')$type='customer';
                if(!in_array($type,['reseller','customer'],true))throw new InvalidArgumentException('نوع الحساب');
                $name=required('name',120);$email=required('email',190);
                if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('البريد غير صالح');
                if(one('SELECT id FROM users WHERE email=?',[$email]))throw new RuntimeException('البريد مستخدم بالفعل');
                if($kind==='reseller') {
                    $max=site_limit($id,'reseller_customers');
                    $count=(int)one("SELECT COUNT(*) n FROM accounts WHERE parent_id=? AND kind='customer'",[$id])['n'];
                    if($count >= $max)throw new RuntimeException('تم بلوغ الحد الأقصى لعملاء الريسيلر');
                }
                $parent=$kind==='reseller'?$id:null;
                $slug='acc-'.bin2hex(random_bytes(6));
                $new=insert("INSERT INTO accounts(kind,parent_id,name,slug,status,created_at) VALUES (?,?,?,?,'active',?)",[$type,$parent,$name,$slug,now()]);
                $temp='OT-'.bin2hex(random_bytes(9));
                $uid=insert("INSERT INTO users(name,email,password_hash,status,created_at) VALUES (?,?,?,'active',?)",[$name.' - مالك',$email,hash_password($temp),now()]);
                insert('INSERT INTO memberships(user_id,account_id,role,created_at) VALUES (?,?,?,?)',[$uid,$new,'owner',now()]);
                $plans=plans_for($type==='reseller'?'reseller':'user');
                if($plans)insert("INSERT INTO subscriptions(account_id,plan_id,status,starts_at,ends_at) VALUES (?,?,'active',?,?)",[$new,$plans[0]['id'],now(),gmdate('Y-m-d H:i:s',time()+30*86400)]);
                audit('account.created',$new,['kind'=>$type,'parent'=>$parent]);
                flash('secret','حساب جديد: '.$email.' | كلمة مرور مؤقتة: '.$temp.' — انسخها الآن');
                break;
            case 'add_employee':
                require_permission('staff.manage');
                $name=required('name',120);$email=required('email',190);
                $role=field('role',30);
                if(!in_array($role,['manager','operator','finance','support','viewer'],true))throw new InvalidArgumentException('دور غير معروف');
                $limit=site_limit($id,'staff_limit');
                $count=(int)one('SELECT COUNT(*) n FROM memberships WHERE account_id=?',[$id])['n'];
                if($kind!=='platform'&&$count>=$limit)throw new RuntimeException('وصلت لحد الموظفين في باقتك');
                $existing=one('SELECT id FROM users WHERE email=?',[$email]);
                if($existing) {
                    $uid=(int)$existing['id'];$temp='دعوة عضوية لحساب موجود';
                }else{
                    $temp='OT-'.bin2hex(random_bytes(9));
                    $uid=insert("INSERT INTO users(name,email,password_hash,status,created_at) VALUES (?,?,?,'active',?)",[$name,$email,hash_password($temp),now()]);
                }
                if(one('SELECT id FROM memberships WHERE user_id=? AND account_id=?',[$uid,$id]))throw new RuntimeException('الموظف موجود بالفعل');
                insert('INSERT INTO memberships(user_id,account_id,role,created_at) VALUES (?,?,?,?)',[$uid,$id,$role,now()]);
                audit('staff.created',$id,['user'=>$uid,'role'=>$role]);
                flash('secret',$email.' | '.$temp);
                break;
            case 'add_site':
                require_permission('sites.write');
                if($kind!=='platform' && !can_create($id,'site'))throw new RuntimeException('انتهى حد المواقع في باقتك');
                $name=required('name',140);$domain=field('domain',190);
                insert("INSERT INTO sites(account_id,name,domain,status,created_at) VALUES (?,?,?,'active',?)",[$id,$name,$domain,now()]);
                audit('site.created',$id,['name'=>$name]);break;
            case 'add_agent':
                require_permission('agents.write');
                $sid=intfield('site_id');if(!owned_site($sid,$id))deny();
                if($kind!=='platform'&&!can_create($id,'agent'))throw new RuntimeException('انتهى حد الإيجنتات في باقتك');
                $name=required('name',140);$voice=field('voice',80);
                $v=one("SELECT * FROM voices WHERE code=? AND ready=1 AND status='active' AND id IN (SELECT voice_id FROM voice_grants WHERE account_id=?)",[$voice,$id]);
                if(!$v && $kind!=='platform')throw new RuntimeException('الصوت غير متاح لهذه الباقة');
                if(!$v && $kind==='platform')$voice='google.aoede';
                $flow=json_encode(['nodes'=>[['id'=>'start','type'=>'start','text'=>'أهلاً بك'],['id'=>'end','type'=>'end']],'edges'=>[['from'=>'start','to'=>'end']]],JSON_UNESCAPED_UNICODE);
                insert("INSERT INTO agents(account_id,site_id,name,voice,instructions,knowledge,flow_json,status,created_at) VALUES (?,?,?,?,?,?,?,'draft',?)",[$id,$sid,$name,$voice,field('instructions',5000),field('knowledge',20000),$flow,now()]);
                audit('agent.created',$id,['site'=>$sid,'name'=>$name]);break;
            case 'update_agent':
                require_permission('agents.write');
                $ag=one('SELECT * FROM agents WHERE id=? AND account_id=?',[intfield('agent_id'),$id]);if(!$ag)deny(404);
                $flow=field('flow_json',16000);
                if($flow==='')$flow=(string)$ag['flow_json'];
                $decoded=json_decode($flow,true);
                if(!is_array($decoded)||!isset($decoded['nodes'],$decoded['edges'])||!is_array($decoded['nodes'])||!is_array($decoded['edges'])||count($decoded['nodes'])>100||count($decoded['edges'])>250)throw new InvalidArgumentException('Flow JSON غير صالح');
                execute('UPDATE agents SET instructions=?,knowledge=?,flow_json=?,status=? WHERE id=? AND account_id=?',[field('instructions',5000),field('knowledge',20000),json_encode($decoded,JSON_UNESCAPED_UNICODE), 'draft',(int)$ag['id'],$id]);
                audit('agent.updated',$id,['agent'=>$ag['id']]);break;
            case 'add_campaign':
                require_permission('campaigns.write');$sid=intfield('site_id');if(!owned_site($sid,$id))deny();
                insert("INSERT INTO campaigns(account_id,site_id,name,status,created_at) VALUES (?,?,?,'draft',?)",[$id,$sid,required('name',140),now()]);
                audit('campaign.draft_created',$id);break;
            case 'create_key':
                if(!allowed('integrations.manage')&&$kind!=='platform')deny();
                $sid=intfield('site_id');if($sid&&!owned_site($sid,$id))deny();
                $name=required('name',90);$scope=field('scopes',150);
                $allowedScopes=['agents:invoke','knowledge:read','leads:write','customers:create','plans:read','appointments:write'];
                $parts=array_filter(array_map('trim',explode(',',$scope)));
                if(!$parts||array_diff($parts,$allowedScopes))throw new InvalidArgumentException('صلاحيات API غير معروفة');
                if(in_array('customers:create',$parts,true)&&$kind!=='reseller')deny();
                $token='otv_'.bin2hex(random_bytes(30));
                insert("INSERT INTO api_keys(account_id,site_id,name,prefix,token_hash,scopes,status,created_at) VALUES (?,?,?,?,?,?,'active',?)",[$id,$sid?:null,$name,substr($token,0,12),hash('sha256',$token),implode(',',$parts),now()]);
                audit('api_key.created',$id,['name'=>$name,'scopes'=>$scope,'site'=>$sid]);
                flash('secret','API Key: '.$token.' — سيظهر مرة واحدة فقط');break;
            case 'revoke_key':
                if(!allowed('integrations.manage')&&$kind!=='platform')deny();
                execute("UPDATE api_keys SET status='revoked' WHERE id=? AND account_id=?",[intfield('key_id'),$id]);
                audit('api_key.revoked',$id,['key'=>intfield('key_id')]);break;
            case 'new_integration':
                if(!in_array($kind,['reseller','platform'],true)||!in_array($a['role'],['owner','admin'],true))deny();
                $provider=field('provider',30);if(!in_array($provider,['whmcs','laravel','kashier','webhook'],true))throw new InvalidArgumentException('نوع تكامل غير مدعوم');
                $token='otint_'.bin2hex(random_bytes(32));
                insert("INSERT INTO integrations(account_id,name,provider,token_hash,token_prefix,status,created_at) VALUES (?,?,?,?,?,'active',?)",[$id,required('name',100),$provider,hash('sha256',$token),substr($token,0,12),now()]);
                audit('integration.created',$id,['provider'=>$provider]);
                flash('secret','سر Webhook للتكامل: '.$token.' — انسخه مرة واحدة');break;
            case 'revoke_integration':
                if(!in_array($kind,['reseller','platform'],true)||!in_array($a['role'],['owner','admin'],true))deny();
                execute("UPDATE integrations SET status='revoked' WHERE id=? AND account_id=?",[intfield('integration_id'),$id]);
                audit('integration.revoked',$id);break;
            case 'assign_plan':
                if(!in_array($kind,['reseller','platform'],true)||!in_array($a['role'],['owner','admin'],true))deny();
                $customer=intfield('customer_id');$target=scoped_customer($customer);
                $plan=one("SELECT * FROM plans WHERE id=? AND kind=? AND status='active'",[intfield('plan_id'),$target['kind']==='reseller'?'reseller':'user']);
                if(!$plan)deny();
                if($kind==='reseller'&&$target['kind']!=='customer')deny();
                $spent=(int)one("SELECT COALESCE(SUM(p.call_quota),0) n FROM subscriptions s JOIN plans p ON p.id=s.plan_id JOIN accounts a ON a.id=s.account_id WHERE a.parent_id=? AND s.status='active' AND a.id!=?",[$id,$customer])['n'];
                if($kind==='reseller' && ($spent+(int)$plan['call_quota'])>site_limit($id,'call_quota'))throw new RuntimeException('سعة الباقة تتجاوز حصة الريسيلر');
                execute("UPDATE subscriptions SET status='replaced' WHERE account_id=? AND status='active'",[$customer]);
                insert("INSERT INTO subscriptions(account_id,plan_id,status,starts_at,ends_at) VALUES (?,?,'active',?,?)",[$customer,$plan['id'],now(),gmdate('Y-m-d H:i:s',time()+30*86400)]);
                audit('plan.assigned',$customer,['plan'=>$plan['id']]);break;
            case 'create_plan':
                if($kind!=='platform'||!in_array($a['role'],['owner','admin'],true))deny();
                $type=field('kind',20);if(!in_array($type,['reseller','user'],true))deny();
                insert("INSERT INTO plans(kind,name,call_quota,site_limit,agent_limit,staff_limit,reseller_customers,price_minor,status,created_at) VALUES (?,?,?,?,?,?,?,?,'active',?)",[$type,required('name'),intfield('calls'),intfield('sites'),intfield('agents'),intfield('staff'),intfield('customers'),intfield('price'),now()]);
                audit('plan.created',$id);break;
            case 'toggle_voice':
                if($kind!=='platform'||!in_array($a['role'],['owner','admin'],true))deny();
                execute("UPDATE voices SET status=CASE WHEN status='active' THEN 'hidden' ELSE 'active' END WHERE id=?",[intfield('voice_id')]);
                audit('voice.toggled',$id);break;
            case 'add_voice_grant':
                if(!in_array($kind,['platform','reseller'],true)||!in_array($a['role'],['owner','admin'],true))deny();
                $target=intfield('account_id');
                if($kind==='reseller'&&!is_reseller_child($id,$target))deny();
                if($kind==='platform'&&!account($target))deny();
                $v=one("SELECT * FROM voices WHERE id=? AND ready=1 AND status='active'",[intfield('voice_id')]);
                if(!$v)throw new RuntimeException('الصوت لم يتم إثبات جاهزيته');
                if($kind==='reseller'&&!one('SELECT id FROM voice_grants WHERE account_id=? AND voice_id=?',[$id,$v['id']]))deny();
                if(!one('SELECT id FROM voice_grants WHERE account_id=? AND voice_id=?',[$target,$v['id']]))
                    insert('INSERT INTO voice_grants(account_id,voice_id) VALUES (?,?)',[$target,$v['id']]);
                audit('voice.granted',$target,['voice'=>$v['code']]);break;
            case 'brand':
                if(!in_array($kind,['reseller','platform'],true)||!in_array($a['role'],['owner','admin'],true))deny();
                execute('UPDATE accounts SET brand_name=? WHERE id=?',[required('brand_name',100),$id]);
                if(!empty($_FILES['logo']['tmp_name'])&&is_uploaded_file($_FILES['logo']['tmp_name'])){
                    $f=$_FILES['logo'];if($f['size']>1024*1024||$f['error']!==UPLOAD_ERR_OK)throw new RuntimeException('الصورة أكبر من 1MB');
                    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
                    $ext=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'][$mime]??null;
                    if(!$ext)throw new RuntimeException('نوع الصورة غير مسموح');
                    $dst=private_path().'/logo-'.$id.'-'.bin2hex(random_bytes(6)).'.'.$ext;
                    if(!move_uploaded_file($f['tmp_name'],$dst))throw new RuntimeException('تعذر حفظ اللوجو');
                    chmod($dst,0600);
                    execute('UPDATE accounts SET logo_path=? WHERE id=?',[basename($dst),$id]);
                }
                audit('brand.updated',$id);break;
            case 'add_domain':
                if($kind!=='reseller'||!in_array($a['role'],['owner','admin'],true))deny();
                $host=strtolower(required('hostname',190));
                if(!preg_match('/^(?=.{4,190}$)([a-z0-9-]+\.)+[a-z]{2,}$/D',$host))throw new InvalidArgumentException('دومين غير صالح');
                if(one('SELECT id FROM domains WHERE hostname=?',[$host]))throw new RuntimeException('الدومين مستخدم');
                insert("INSERT INTO domains(account_id,hostname,verification_token,status,created_at) VALUES (?,?,?,'pending',?)",[$id,$host,bin2hex(random_bytes(18)),now()]);
                audit('domain.requested',$id,['host'=>$host]);break;
            case 'manual_approve':
                if(!in_array($kind,['platform','reseller'],true)||!in_array($a['role'],['owner','admin','finance'],true))deny();
                $order=one("SELECT * FROM orders WHERE id=? AND status='pending_manual_review'",[intfield('order_id')]);
                if(!$order)throw new RuntimeException('الطلب غير موجود أو ليس بانتظار المراجعة');
                scoped_customer((int)$order['account_id']);
                db()->beginTransaction();
                try{
                    execute("UPDATE orders SET status='approved' WHERE id=? AND status='pending_manual_review'",[$order['id']]);
                    $existing=one("SELECT id FROM subscriptions WHERE account_id=? AND plan_id=? AND status='active'",[$order['account_id'],$order['plan_id']]);
                    if(!$existing)insert("INSERT INTO subscriptions(account_id,plan_id,status,starts_at,ends_at) VALUES (?,?,'active',?,?)",[$order['account_id'],$order['plan_id'],now(),gmdate('Y-m-d H:i:s',time()+30*86400)]);
                    insert("INSERT INTO outbox(account_id,kind,payload,status,created_at) VALUES (?,?,?,'pending',?)",[$order['account_id'],'subscription.activated',json_encode(['order'=>$order['id'],'source'=>'manual_review']),now()]);
                    db()->commit();
                }catch(Throwable $ex){db()->rollBack();throw $ex;}
                audit('manual_payment.approved',(int)$order['account_id'],['order'=>$order['id']]);break;
            default:throw new InvalidArgumentException('عملية غير معروفة');
        }
        flash('success','تم حفظ العملية بنجاح');
    }catch(Throwable $e){
        flash('error',$e instanceof InvalidArgumentException?$e->getMessage():'تعذر تنفيذ العملية: '.$e->getMessage());
    }
    $return=(string)($_POST['return']??'/');
    if(!preg_match('~^/(?:\?tab=[a-z]+(?:&site=[0-9]+)?)?$~D',$return))$return='/';
    go($return);
}
