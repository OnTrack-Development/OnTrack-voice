<?php
declare(strict_types=1);
require_once __DIR__.'/StaffInvitations.php';
require_once __DIR__.'/DncGuard.php';
require_once __DIR__.'/DevicePairing.php';
require_once __DIR__.'/PublishedFlow.php';
require_once __DIR__.'/FlowTemplates.php';
require_once __DIR__.'/AgentAnswerPolicy.php';
require_once __DIR__.'/Checkout.php';

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
        // Migrate DNC outside a transaction (MySQL CREATE TABLE auto-commits).
        if(in_array($action,['add_contact','edit_contact','delete_contact'],true))dnc_init_ledger();
        switch($action) {
            case 'set_view':
                if($kind!=='platform'||!in_array($a['role'],['owner','admin'],true)||active_support())deny();
                $view=(string)($_POST['view']??'');
                if(!in_array($view,['admin','user'],true))throw new InvalidArgumentException('وضع عرض غير صالح');
                $_SESSION['console_view']=$view;
                audit('console.view.changed',$id,['view'=>$view]);
                session_regenerate_id(true);
                go($view==='user'?'/?tab=agents':'/');
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
                $reason=field('reason',200);if($reason==='')$reason='طلب دعم من لوحة الإدارة';
                $expiry=gmdate('Y-m-d H:i:s',time()+900);
                insert("INSERT INTO support_grants(actor_user_id,target_account_id,reason,expires_at,created_at) VALUES (?,?,?,?,?)",[me()['id'],$target,$reason,$expiry,now()]);
                $_SESSION['support']=['account_id'=>$target,'origin_id'=>$id,'expires'=>time()+1800];
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
                // Support impersonation is not authorization to invite principals.
                if(active_support()||!in_array($a['role'],['owner','admin'],true))deny();
                $email=strtolower(required('email',190));
                $name=required('name',120);
                $role=field('role',30);
                $inviteId=create_staff_invitation($id,$name,$email,$role,(int)me()['id']);
                audit('staff.invitation.created',$id,['invitation_id'=>$inviteId,'role'=>$role]);
                flash('success','الدعوة بانتظار قبول صاحب البريد بعد تسجيل دخوله. لم تُمنح أي صلاحيات بعد.');
                break;
            case 'cancel_staff_invitation':
                require_permission('staff.manage');
                if(active_support()||!in_array($a['role'],['owner','admin'],true))deny();
                $inviteId=intfield('invitation_id');
                cancel_staff_invitation($inviteId,$id);
                audit('staff.invitation.cancelled',$id,['invitation_id'=>$inviteId]);
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
                $template=(string)($_POST['flow_template']??'appointment_general');
                $templateGraph=flow_template_graph($template);
                $flow=json_encode($templateGraph,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
                $publishNew=($_POST['publish_now']??'')==='1';
                if($publishNew)flow_preview_compile($templateGraph);
                $started=false;
                try {
                    if($publishNew){db()->beginTransaction();$started=true;}
                    $newAgent=insert("INSERT INTO agents(account_id,site_id,name,voice,instructions,knowledge,flow_json,status,created_at) VALUES (?,?,?,?,?,?,?,'draft',?)",
                        [$id,$sid,$name,$voice,field('instructions',5000),field('knowledge',20000),$flow,now()]);
                    if($publishNew){
                        $published=publish_agent_flow($id,$newAgent,(int)me()['id']);
                        db()->commit();$started=false;
                        audit('agent.flow.published',$id,['agent_id'=>$newAgent,'revision'=>$published['revision'],'template'=>$template]);
                        $success='تم إنشاء الإيجنت ونشر السيناريو v'.$published['revision'].' مباشرة.';
                    }
                }catch(Throwable $ex){if($started&&db()->inTransaction())db()->rollBack();throw $ex;}
                audit('agent.created',$id,['site'=>$sid,'name'=>$name,'template'=>$template]);break;
            case 'update_agent':
                require_permission('agents.write');
                $ag=one('SELECT * FROM agents WHERE id=? AND account_id=?',[intfield('agent_id'),$id]);if(!$ag)deny(404);
                $flow=field('flow_json',16000);
                if($flow==='')$flow=(string)$ag['flow_json'];
                $decoded=json_decode($flow,true);
                if(!is_array($decoded)||!isset($decoded['nodes'],$decoded['edges'])||!is_array($decoded['nodes'])||!is_array($decoded['edges'])||count($decoded['nodes'])>100||count($decoded['edges'])>250)throw new InvalidArgumentException('Flow JSON غير صالح');
                $newName=required('name',140);
                $voice=field('voice',80);
                if($voice!==(string)$ag['voice']){
                    $allowedVoice=one("SELECT id FROM voices WHERE code=? AND ready=1 AND status='active' AND id IN (SELECT voice_id FROM voice_grants WHERE account_id=?)",[$voice,$id]);
                    if(!$allowedVoice && $kind!=='platform')throw new InvalidArgumentException('الصوت غير متاح لهذا الحساب');
                }
                $ids=[];
                foreach($decoded['nodes'] as $n){
                    if(!is_array($n)||!preg_match('/^[a-zA-Z0-9_-]{1,60}$/D',(string)($n['id']??''))||isset($ids[$n['id']]))throw new InvalidArgumentException('معرّف عقدة Flow مكرر أو غير صالح');
                    $ids[$n['id']]=true;
                    if(!in_array($n['type']??'',[
                       'start','say','listen','ask','condition','knowledge_lookup','collect_field','crm_lookup','crm_write','schedule','handoff','end'],true))
                       throw new InvalidArgumentException('نوع عقدة غير صالح');
                }
                foreach($decoded['edges'] as $edge){
                    if(!is_array($edge)||!isset($ids[$edge['from']??''],$ids[$edge['to']??''])||
                       strlen((string)($edge['label']??''))>280)
                       throw new InvalidArgumentException('رابط Flow يشير إلى عقدة غير موجودة');
                }
                $answerPolicy=answer_policy_form($_POST);
                $publishNow=($_POST['publish_now']??'')==='1';
                $normalized=json_encode($decoded,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
                if($publishNow)flow_preview_compile($decoded);
                $started=false;
                try{
                    if($publishNow){db()->beginTransaction();$started=true;}
                    execute('UPDATE agents SET name=?,voice=?,instructions=?,knowledge=?,flow_json=?,status=? WHERE id=? AND account_id=?',
                        [$newName,$voice,field('instructions',5000),field('knowledge',20000),$normalized,'draft',(int)$ag['id'],$id]);
                    save_answer_policy($id,(int)$ag['id'],$answerPolicy);
                    if($publishNow){
                        $result=publish_agent_flow($id,(int)$ag['id'],(int)me()['id']);
                        db()->commit();$started=false;
                        audit('agent.flow.published',$id,['agent_id'=>$ag['id'],'revision'=>$result['revision'],'duplicate'=>$result['duplicate']]);
                        $success='تم حفظ ونشر Flow v'.(int)$result['revision'].' مباشرة. الأجهزة تتلقى النسخة المنشورة عند المزامنة.';
                    }
                }catch(Throwable $ex){if($started&&db()->inTransaction())db()->rollBack();throw $ex;}
                audit('agent.updated',$id,['agent'=>$ag['id'],'published'=>$publishNow]);break;
            case 'publish_agent_flow':
                require_permission('agents.write');
                $agentId=intfield('agent_id');
                $result=publish_agent_flow($id,$agentId,(int)me()['id']);
                audit('agent.flow.published',$id,['agent_id'=>$agentId,'revision'=>$result['revision'],
                    'duplicate'=>$result['duplicate']]);
                flash('success','تم نشر Flow نسخة '.(int)$result['revision'].' — الاتصال الصوتي يحتاج تفعيل محرك الهاتف واختباره.');
                break;
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
                insert("INSERT INTO api_keys(account_id,site_id,name,prefix,token_hash,scopes,status,created_at,secret_enc) VALUES (?,?,?,?,?,?,'active',?,?)",[$id,$sid?:null,$name,substr($token,0,12),hash('sha256',$token),implode(',',$parts),now(),seal_secret($token)]);
                audit('api_key.created',$id,['name'=>$name,'scopes'=>$scope,'site'=>$sid]);
                flash('secret','API Key: '.$token.' — سيظهر مرة واحدة فقط');break;
            case 'revoke_key':
                if(!allowed('integrations.manage')&&$kind!=='platform')deny();
                execute("UPDATE api_keys SET status='revoked' WHERE id=? AND account_id=?",[intfield('key_id'),$id]);
                audit('api_key.revoked',$id,['key'=>intfield('key_id')]);break;
            case 'new_integration':
                if(!in_array($a['role'],['owner','admin'],true))deny();
                $provider=field('provider',30);if(!in_array($provider,['whmcs','laravel','kashier','webhook'],true))throw new InvalidArgumentException('نوع تكامل غير مدعوم');
                $token='otint_'.bin2hex(random_bytes(32));
                insert("INSERT INTO integrations(account_id,name,provider,token_hash,token_prefix,status,created_at,secret_enc) VALUES (?,?,?,?,?,'active',?,?)",[$id,required('name',100),$provider,hash('sha256',$token),substr($token,0,12),now(),seal_secret($token)]);
                audit('integration.created',$id,['provider'=>$provider]);
                flash('secret','سر Webhook للتكامل: '.$token.' — انسخه مرة واحدة');break;
            case 'revoke_integration':
                if(!in_array($a['role'],['owner','admin'],true))deny();
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
            case 'checkout_plan':
                require_permission('billing.write');
                if(!in_array($a['role'],['owner','admin'],true)||active_support())deny();
                $url=checkout_start($id,intfield('plan_id'),me());
                header('Location: '.$url,true,303);exit;
            case 'manual_approve':
                if(!in_array($kind,['platform','reseller'],true)||!in_array($a['role'],['owner','admin','finance'],true))deny();
                $proof=required('payment_reference',160);
                if(!preg_match('/^[a-zA-Z0-9_.:-]{8,160}$/D',$proof))throw new InvalidArgumentException('مرجع إثبات الدفع اليدوي غير صالح');
                $order=one("SELECT * FROM orders WHERE id=? AND status='pending_manual_review'",[intfield('order_id')]);
                if(!$order)throw new RuntimeException('الطلب غير موجود أو ليس بانتظار المراجعة');
                scoped_customer((int)$order['account_id']);
                db()->beginTransaction();
                try{
                    execute("UPDATE orders SET status='approved',payment_reference=? WHERE id=? AND status='pending_manual_review'",[$proof,$order['id']]);
                    $existing=one("SELECT id FROM subscriptions WHERE account_id=? AND plan_id=? AND status='active'",[$order['account_id'],$order['plan_id']]);
                    if(!$existing)insert("INSERT INTO subscriptions(account_id,plan_id,status,starts_at,ends_at) VALUES (?,?,'active',?,?)",[$order['account_id'],$order['plan_id'],now(),gmdate('Y-m-d H:i:s',time()+30*86400)]);
                    insert("INSERT INTO outbox(account_id,kind,payload,status,created_at) VALUES (?,?,?,'pending',?)",[$order['account_id'],'subscription.activated',json_encode(['order'=>$order['id'],'source'=>'manual_review']),now()]);
                    db()->commit();
                }catch(Throwable $ex){db()->rollBack();throw $ex;}
                audit('manual_payment.approved',(int)$order['account_id'],['order'=>$order['id'],'proof_ref'=>$proof]);break;
            case 'support_policy':
                if($kind!=='platform'&&$kind!=='reseller')deny();
                if(!in_array($a['role'],['owner','admin'],true))deny();
                $target=$kind==='platform'?intfield('reseller_id'):$id;
                if($target && $kind==='platform'){
                    $res=account($target);
                    if(!$res||$res['kind']!=='reseller')deny();
                }
                $mode=field('access_mode',12);
                if(!in_array($mode,['full','read_only'],true))throw new InvalidArgumentException('وضع غير صالح');
                $exists=one('SELECT account_id FROM support_policies WHERE account_id=?',[$target]);
                if($exists)execute('UPDATE support_policies SET access_mode=? WHERE account_id=?',[$mode,$target]);
                else insert('INSERT INTO support_policies(account_id,access_mode) VALUES (?,?)',[$target,$mode]);
                audit('support.policy.updated',$target,['mode'=>$mode]);break;
            case 'reveal_secret':
                if(!in_array($a['role'],['owner','admin'],true) || active_support())deny();
                $kindKey=field('secret_type',20);
                $tbl=$kindKey==='api'?'api_keys':($kindKey==='integration'?'integrations':null);
                if(!$tbl)deny();
                // Authenticated + CSRF-verified owner/admin, audit every secret reveal.
                $keyRow=one('SELECT * FROM '.$tbl.' WHERE id=? AND account_id=? AND status=?',[intfield('secret_id'),$id,'active']);
                if(!$keyRow)deny(404);
                $plain=unseal_secret($keyRow['secret_enc']??null);
                if($plain===null)throw new RuntimeException('مفتاح قديم غير قابل للاسترجاع. استخدم زر التدوير');
                audit('secret.viewed',$id,['type'=>$kindKey,'id'=>$keyRow['id']]);
                flash('secret','المفتاح المطلوب: '.$plain);break;
            case 'rotate_secret':
                if(!in_array($a['role'],['owner','admin'],true)||active_support())deny();
                $kindKey=field('secret_type',20);
                $tbl=$kindKey==='api'?'api_keys':($kindKey==='integration'?'integrations':null);
                if(!$tbl)deny();
                $row=one('SELECT * FROM '.$tbl.' WHERE id=? AND account_id=? AND status=?',[intfield('secret_id'),$id,'active']);
                if(!$row)deny(404);
                $token=($kindKey==='api'?'otv_':'otint_').bin2hex(random_bytes(32));
                $prefix=substr($token,0,12);
                $col=$kindKey==='api'?'prefix':'token_prefix';
                execute('UPDATE '.$tbl.' SET token_hash=?,'.$col.'=?,secret_enc=? WHERE id=? AND account_id=?',[hash('sha256',$token),$prefix,seal_secret($token),$row['id'],$id]);
                audit('secret.rotated',$id,['type'=>$kindKey,'id'=>$row['id']]);
                flash('secret','تم إلغاء المفتاح السابق، المفتاح الجديد: '.$token);break;
            case 'rename_secret':
                if(!in_array($a['role'],['owner','admin'],true)||active_support())deny();
                $tbl=field('secret_type',20)==='api'?'api_keys':'integrations';
                execute('UPDATE '.$tbl.' SET name=? WHERE id=? AND account_id=?',[required('name',100),intfield('secret_id'),$id]);
                audit('secret.renamed',$id,['type'=>$tbl]);break;
            case 'update_site':
                require_permission('sites.write');
                $site=owned_site(intfield('site_id'),$id);if(!$site)deny(404);
                execute('UPDATE sites SET name=?,domain=? WHERE id=? AND account_id=?',[required('name',140),field('domain',190),$site['id'],$id]);
                audit('site.updated',$id,['site'=>$site['id']]);break;
            case 'archive_site':
                require_permission('sites.write');
                $site=owned_site(intfield('site_id'),$id);if(!$site)deny(404);
                execute("UPDATE sites SET status='archived' WHERE id=? AND account_id=?",[$site['id'],$id]);
                execute("UPDATE agents SET status='archived' WHERE site_id=? AND account_id=?",[$site['id'],$id]);
                audit('site.archived',$id,['site'=>$site['id']]);break;
            case 'archive_agent':
                require_permission('agents.write');
                $ag=one('SELECT id FROM agents WHERE id=? AND account_id=?',[intfield('agent_id'),$id]);if(!$ag)deny(404);
                execute("UPDATE agents SET status='archived' WHERE id=? AND account_id=?",[$ag['id'],$id]);
                audit('agent.archived',$id,['agent'=>$ag['id']]);break;
            case 'add_contact':
                require_permission('contacts.write');
                $site=owned_site(intfield('site_id'),$id);if(!$site||$site['status']!=='active')deny();
                $name=required('name',120);$phone=required('phone',32);
                if(!preg_match('/^\\+?[0-9 ()-]{8,30}$/D',$phone))throw new InvalidArgumentException('رقم هاتف غير صالح');
                if(!isset($_POST['consent']))throw new InvalidArgumentException('يجب إثبات موافقة التواصل قبل إضافة جهة الاتصال');
                $dnc=dnc_effective_flag($id,$phone,isset($_POST['dnc']));
                insert('INSERT INTO contacts(account_id,site_id,name,phone,dnc,created_at) VALUES (?,?,?,?,?,?)',
                    [$id,$site['id'],$name,$phone,$dnc,now()]);
                audit('contact.created',$id,['site'=>$site['id']]);break;
            case 'edit_contact':
                require_permission('contacts.write');
                $contact=one('SELECT id,phone,dnc FROM contacts WHERE id=? AND account_id=?',[intfield('contact_id'),$id]);if(!$contact)deny(404);
                $name=required('name',120);
                $phone=required('phone',32);
                if(!preg_match('/^\\+?[0-9 ()-]{8,30}$/D',$phone))throw new InvalidArgumentException('رقم هاتف غير صالح');
                // An opt-out must not be silently erased by editing the same row.
                // Changing its number would erase the only DNC evidence for the old number.
                if((int)$contact['dnc']===1 && dnc_phone_key((string)$contact['phone'])!==dnc_phone_key($phone))
                    throw new InvalidArgumentException('لا يمكن تغيير رقم محظور؛ احتفظ بسجل عدم الاتصال');
                $dnc=dnc_effective_flag($id,$phone,isset($_POST['dnc'])||(int)$contact['dnc']===1);
                execute('UPDATE contacts SET name=?,phone=?,dnc=? WHERE id=? AND account_id=?',
                    [$name,$phone,$dnc,$contact['id'],$id]);
                audit('contact.updated',$id,['contact'=>$contact['id']]);break;
            case 'delete_contact':
                require_permission('contacts.write');
                $row=one('SELECT id,dnc FROM contacts WHERE id=? AND account_id=?',[intfield('contact_id'),$id]);if(!$row)deny(404);
                // Until the separate durable suppression/retention workflow exists,
                // deletion of the only DNC record would silently restore dial eligibility.
                if((int)$row['dnc']===1)throw new InvalidArgumentException('رقم عدم الاتصال محمي؛ يلزم مسار إزالة موثق مستقل');
                execute('DELETE FROM contacts WHERE id=? AND account_id=?',[$row['id'],$id]);
                audit('contact.deleted',$id,['contact'=>$row['id']]);break;
            case 'update_campaign':
                require_permission('campaigns.write');
                $camp=one('SELECT id FROM campaigns WHERE id=? AND account_id=?',[intfield('campaign_id'),$id]);if(!$camp)deny(404);
                $status=field('status',20);
                if(!in_array($status,['draft','paused','archived'],true))throw new InvalidArgumentException('حالة غير آمنة، لا يتم تشغيل مكالمة من هذه اللوحة التجريبية');
                execute('UPDATE campaigns SET name=?,status=? WHERE id=? AND account_id=?',[required('name',140),$status,$camp['id'],$id]);
                audit('campaign.updated',$id,['campaign'=>$camp['id'],'status'=>$status]);break;
            case 'edit_account':
                if(!in_array($kind,['platform','reseller'],true)||!in_array($a['role'],['owner','admin'],true))deny();
                $target=scoped_customer(intfield('account_id'));
                if($target['kind']==='platform'||($kind==='reseller'&&$target['kind']!=='customer'))deny();
                execute('UPDATE accounts SET name=? WHERE id=?',[required('name',120),$target['id']]);
                audit('account.renamed',$target['id']);break;
            case 'set_account_status':
                if(!in_array($kind,['platform','reseller'],true)||!in_array($a['role'],['owner','admin'],true))deny();
                $target=scoped_customer(intfield('account_id'));
                if($target['kind']==='platform')deny();
                $status=field('status',12);
                if(!in_array($status,['active','suspended'],true))deny();
                execute('UPDATE accounts SET status=? WHERE id=?',[$status,$target['id']]);
                audit('account.status',$target['id'],['status'=>$status]);break;
            case 'edit_plan':
                if($kind!=='platform'||!in_array($a['role'],['owner','admin'],true))deny();
                $plan=one('SELECT id FROM plans WHERE id=?',[intfield('plan_id')]);if(!$plan)deny();
                execute('UPDATE plans SET name=?,call_quota=?,site_limit=?,agent_limit=?,staff_limit=? WHERE id=?',
                   [required('name',120),intfield('calls'),intfield('sites'),intfield('agents'),intfield('staff'),$plan['id']]);
                audit('plan.updated',$id,['plan'=>$plan['id']]);break;
            case 'archive_plan':
                if($kind!=='platform'||!in_array($a['role'],['owner','admin'],true))deny();
                execute("UPDATE plans SET status='archived' WHERE id=?",[intfield('plan_id')]);
                audit('plan.archived',$id);break;
            case 'edit_staff':
                if(!in_array($a['role'],['owner','admin'],true)||active_support())deny();
                $uid=intfield('user_id');$role=field('role',25);
                if(!in_array($role,['manager','operator','finance','support','viewer'],true))deny();
                $target=one('SELECT id,role FROM memberships WHERE user_id=? AND account_id=?',[$uid,$id]);
                if(!$target||in_array($target['role'],['owner','admin'],true))deny();
                execute('UPDATE memberships SET role=? WHERE id=?',[$role,$target['id']]);
                audit('staff.role.updated',$id,['user'=>$uid,'role'=>$role]);break;
            case 'remove_staff':
                if(!in_array($a['role'],['owner','admin'],true)||active_support())deny();
                $uid=intfield('user_id');
                $target=one('SELECT id,role FROM memberships WHERE user_id=? AND account_id=?',[$uid,$id]);
                if(!$target||in_array($target['role'],['owner','admin'],true))deny();
                execute('DELETE FROM memberships WHERE id=?',[$target['id']]);
                audit('staff.removed',$id,['user'=>$uid]);break;
            case 'sync_google_voices':
                if($kind!=='platform'||!in_array($a['role'],['owner','admin'],true))deny();
                require_once __DIR__.'/VoiceCatalog.php';
                $synced=sync_google_live_voices();
                audit('voices.catalog_synced',$id,['count'=>$synced]);
                flash('success','تم تحديث كتالوج Google بعدد '.$synced.' صوتاً، والتفعيل من الإدارة');break;
            case 'fetch_google_voices':
                if($kind!=='platform'||!in_array($a['role'],['owner','admin'],true)||active_support())deny();
                require_once __DIR__.'/VoiceCatalog.php';
                $supplied=field('google_api_key',256);
                if($supplied===''){
                    $profile=one("SELECT secret_enc FROM integrations WHERE account_id=? AND provider='google_live' AND status='active' ORDER BY id DESC LIMIT 1",[$id]);
                    $supplied=$profile?(string)(unseal_secret($profile['secret_enc']??null)??''):'';
                }
                if($supplied===''){
                    $count=sync_google_live_voices();
                    $success='اتعرض '.$count.' صوتًا من الكتالوج الرسمي المدمج. جلب الأصوات الخاصة بحسابك يحتاج ربط Google من الإعدادات.';
                }else{
                    try{
                        $count=fetch_google_provider_voices($supplied);
                        $success='تم جلب '.$count.' صوتًا من Google. تقدر تختار المتاح وتختبره قبل منحه للعملاء.';
                    }catch(RuntimeException $voiceError){
                        $count=sync_google_live_voices();
                        $success='تعذر جلب كتالوج الأصوات عبر Google: '.$voiceError->getMessage().' تم إظهار '.$count.' صوتًا مدمجًا بدلًا منه. ده لا يعني بالضرورة إن المفتاح غلط.';
                    }
                }
                audit('voices.catalog_synced',$id,['count'=>$count,'used_live_api'=>$supplied!=='']);
                break;
            case 'set_voice_ready':
                if($kind!=='platform'||!in_array($a['role'],['owner','admin'],true))deny();
                $voice=one('SELECT id FROM voices WHERE id=?',[intfield('voice_id')]);if(!$voice)deny(404);
                $ready=field('ready',1)==='1'?1:0;
                if($ready && (string)($_POST['confirmed']??'')!=='1')
                   throw new InvalidArgumentException('يجب إثبات صلاحية الصوت على مزود الاتصال أولاً');
                execute('UPDATE voices SET ready=? WHERE id=?',[$ready,$voice['id']]);
                audit('voice.ready.updated',$id,['voice'=>$voice['id'],'ready'=>$ready]);break;
            case 'create_device_pairing':
                if(!in_array($a['role'],['owner','admin'],true)||active_support())deny();
                $siteId=intfield('site_id');$agentId=intfield('agent_id');
                $requestedSlot=$_POST['sim_slot']??null;
                if(!in_array($requestedSlot,['0','1'],true))throw new InvalidArgumentException('اختر شريحة SIM 1 أو SIM 2');
                $simSlot=(int)$requestedSlot;
                $code=device_pairing_create($id,$siteId,$agentId,(int)me()['id'],$simSlot);
                audit('device.pairing.created',$id,['site_id'=>$siteId,'agent_id'=>$agentId,'sim_slot'=>$simSlot]);
                flash('secret','رمز ربط SIM '.($simSlot+1).' — صالح 10 دقائق ولمرة واحدة: '.$code);
                flash('device_pair_qr',$code);
                break;
            case 'cancel_device_pairing':
                if(!in_array($a['role'],['owner','admin'],true)||active_support())deny();
                if(!device_pairing_cancel($id,intfield('pairing_id')))throw new InvalidArgumentException('طلب الربط غير متاح');
                audit('device.pairing.cancelled',$id,['pairing_id'=>intfield('pairing_id')]);
                break;
            case 'revoke_device_binding':
                if(!in_array($a['role'],['owner','admin'],true)||active_support())deny();
                if(!device_binding_revoke($id,intfield('device_id')))throw new InvalidArgumentException('الجهاز غير موجود أو مفصول مسبقًا');
                audit('device.binding.revoked',$id,['device_id'=>intfield('device_id')]);
                break;
            case 'save_live_provider':
                if($kind!=='platform'||!in_array($a['role'],['owner','admin'],true)||active_support())deny();
                // Authenticated administrator with CSRF token; no repeated password prompt.
                $model=required('model',120);
                if(!preg_match('/^[A-Za-z0-9._-]{5,120}$/D',$model))
                    throw new InvalidArgumentException('معرف موديل Google غير صالح');
                $apiKey=field('google_key',256);
                require_once __DIR__.'/VoiceCatalog.php';
                google_api_probe($apiKey);
                // API-key validity and Live entitlement are two different checks.
                require_once __DIR__.'/TenantVoiceSession.php';
                $liveReady=false;
                try{tenant_voice_ephemeral_token($apiKey,1);$liveReady=true;}
                catch(RuntimeException $liveError){$liveReady=false;}
                db()->beginTransaction();
                try{
                    execute("UPDATE integrations SET status='revoked' WHERE account_id=? AND provider='google_live' AND status='active'",[$id]);
                    insert("INSERT INTO integrations(account_id,name,provider,token_hash,token_prefix,status,created_at,secret_enc)
                        VALUES (?,?,?, ?,?,'active',?,?)",
                        [$id,$model,'google_live',hash('sha256',$apiKey),substr(hash('sha256',$apiKey),0,12),now(),seal_secret($apiKey)]);
                    db()->commit();
                }catch(Throwable $ex){if(db()->inTransaction())db()->rollBack();throw $ex;}
                audit('voice.google_live.configured',$id,['model'=>$model]);
                $success=$liveReady
                    ?'تم اختبار Google API وإصدار رمز Live مؤقت وحفظ المفتاح مشفرًا. الاختبار الصوتي على الشريحة لسه مطلوب.'
                    :'المفتاح اتقبل من Google واتحفظ مشفرًا. اختبار Gemini Live لم ينجح بعد؛ راجع صلاحية Live والموديل، مش لازم يكون المفتاح غلط.';
                break;
            case 'pilot_settings':
                if($kind!=='platform'||!in_array($a['role'],['owner','admin'],true)||active_support())deny();
                require_once __DIR__.'/MobilePilot.php';
                $name=required('agent_name',120);$voice=required('voice',40);
                if(!preg_match('/^[A-Za-z0-9_-]{2,40}$/D',$voice))throw new InvalidArgumentException('صيغة الصوت غير صالحة');
                $delay=intfield('answer_delay_seconds');
                if($delay>30)throw new InvalidArgumentException('مهلة الرد لا تتجاوز 30 ثانية');
                $changes=['agent_name'=>$name,'voice'=>$voice,'instruction'=>field('instruction',5000),
                  'auto_answer'=>isset($_POST['auto_answer']),
                  'enabled'=>isset($_POST['enabled']),'answer_delay_seconds'=>$delay];
                $revision=pilot_write_policy($changes);
                audit('pilot.policy.updated',$id,['revision'=>$revision,'enabled'=>$changes['enabled']]);
                flash('success','تم إرسال سياسة الهاتف إلى ملف المزامنة، Revision '.$revision.'؛ ظهورها على الجهاز يحتاج تأكيد ACK');break;
            default:throw new InvalidArgumentException('عملية غير معروفة');
        }
        flash('success',$success??'تم حفظ العملية بنجاح');
    }catch(Throwable $e){
        flash('error',$e instanceof InvalidArgumentException?$e->getMessage():'تعذر تنفيذ العملية: '.$e->getMessage());
    }
    $return=(string)($_POST['return']??'/');
    if(!preg_match('~^/(?:\?tab=[a-z]+(?:&site=[0-9]+)?)?$~D',$return))$return='/';
    go($return);
}
