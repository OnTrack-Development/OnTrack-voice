<?php
declare(strict_types=1);
require_once __DIR__.'/../_core/Bootstrap.php';

function seed_console(): array {
    init_schema();
    if ((int)one('SELECT COUNT(*) n FROM users')['n'] > 0) {
        return ['status'=>'already_seeded','users'=>(int)one('SELECT COUNT(*) n FROM users')['n']];
    }
    $now=now();
    $plans=[
        ['reseller','ريسيلر ستارت',5000,10,50,5,10,0],
        ['reseller','ريسيلر سكيل',25000,50,250,20,50,0],
        ['user','مستخدم ستارت',300,1,1,3,0,0],
        ['user','مستخدم بيزنس',2000,4,5,15,0,0]
    ];
    foreach($plans as $p)insert("INSERT INTO plans(kind,name,call_quota,site_limit,agent_limit,staff_limit,reseller_customers,price_minor,status,created_at) VALUES (?,?,?,?,?,?,?,?,'active',?)",[...$p,$now]);
    $accountRows=[
        ['platform',null,'OnTrack Platform','ontrack','OnTrack Voice'],
        ['reseller',null,'دلتا فويس','delta-voice','دلتا فويس'],
        ['reseller',null,'نيل فويس','nile-voice','نيل فويس'],
        ['customer',null,'شركة مباشرة','direct-company','OnTrack Voice'],
        ['customer',2,'صيدلية الندى التجريبية','delta-pharmacy','دلتا فويس'],
        ['customer',2,'أزياء القاهرة التجريبية','delta-fashion','دلتا فويس'],
        ['customer',3,'شركة نيل التجريبية','nile-customer','نيل فويس'],
    ];
    foreach($accountRows as $a)insert("INSERT INTO accounts(kind,parent_id,name,slug,status,brand_name,created_at) VALUES (?,?,?,?,'active',?,?)",[$a[0],$a[1],$a[2],$a[3],$a[4],$now]);
    $users=[
        ['مسؤول المنصة','admin@ontrackegy.com','$2y$12$eYzQbcavussbjhhpayi/FOPBgD2R8pYI5Pwy5U1LGlyFy3u10/Z9S',1,'owner'],
        ['مالك دلتا فويس','reseller@delta.test','$2y$12$Fjh7jJiJ0ypPT8S2Ms9g3uGdDDkqEMpHxZpMdI85AAgjEIgDFnKOu',2,'owner'],
        ['مالك الشركة المباشرة','user@direct.test','$2y$12$3zFC2ACVGGbLgJwTD9n3yeujBeSWjWh2vA1TiJhQO0yWbxfIrFzCy',4,'owner'],
        ['مدير دلتا','manager@delta.test','$2y$12$Fjh7jJiJ0ypPT8S2Ms9g3uGdDDkqEMpHxZpMdI85AAgjEIgDFnKOu',2,'manager'],
        ['حساب صيدلية الندى','pharmacy@delta.test','$2y$12$3zFC2ACVGGbLgJwTD9n3yeujBeSWjWh2vA1TiJhQO0yWbxfIrFzCy',5,'owner'],
        ['حساب أزياء القاهرة','fashion@delta.test','$2y$12$3zFC2ACVGGbLgJwTD9n3yeujBeSWjWh2vA1TiJhQO0yWbxfIrFzCy',6,'owner'],
        ['مالك نيل فويس','reseller@nile.test','$2y$12$Fjh7jJiJ0ypPT8S2Ms9g3uGdDDkqEMpHxZpMdI85AAgjEIgDFnKOu',3,'owner'],
        ['شركة نيل','user@nile.test','$2y$12$3zFC2ACVGGbLgJwTD9n3yeujBeSWjWh2vA1TiJhQO0yWbxfIrFzCy',7,'owner'],
        ['دعم المنصة','support@ontrack.test','$2y$12$Fjh7jJiJ0ypPT8S2Ms9g3uGdDDkqEMpHxZpMdI85AAgjEIgDFnKOu',1,'support']
    ];
    foreach($users as $u) {
        $id=insert("INSERT INTO users(name,email,password_hash,status,created_at) VALUES (?,?,?,'active',?)",[$u[0],$u[1],$u[2],$now]);
        insert("INSERT INTO memberships(user_id,account_id,role,created_at) VALUES (?,?,?,?)",[$id,$u[3],$u[4],$now]);
    }
    foreach([[2,1],[3,2],[4,3],[5,4],[6,3],[7,3]] as $p)
        insert("INSERT INTO subscriptions(account_id,plan_id,status,starts_at,ends_at) VALUES (?,?,'active',?,?)",[$p[0],$p[1],$now,gmdate('Y-m-d H:i:s',time()+30*86400)]);
    $sites=[
        [4,'المتجر الرئيسي','store.direct.example'],
        [4,'العيادة','clinic.direct.example'],
        [5,'صيدلية الندى','pharmacy.demo.invalid'],
        [6,'متجر الأزياء','fashion.demo.invalid'],
        [7,'موقع نيل','nile.demo.invalid']
    ];
    foreach($sites as $row)insert("INSERT INTO sites(account_id,name,domain,status,created_at) VALUES (?,?,?,'active',?)",[$row[0],$row[1],$row[2],$now]);
    foreach([
        [4,1,'مساعد المتجر','google.aoede','رد على أسئلة المنتجات والعروض فقط من معرفة المتجر','المتجر يبيع ملابس وإكسسوارات في المثال.'],
        [4,2,'مساعد العيادة','google.kore','رد على حجز المواعيد بدون تشخيص طبي','العيادة تعمل من 9 صباحاً إلى 5 مساءً في المثال.'],
        [5,3,'مساعد صيدلية الندى','google.aoede','ساعد في الاستفسارات غير الطبية ولا تخترع وصفات','صيدلية تجريبية، لا تتعامل مع طلبات طبية حقيقية.'],
        [6,4,'مساعد أزياء القاهرة','google.puck','ساعد في المقاسات وطلبات الملابس','هذا مثال لملابس وأزياء وليس صيدلية.'],
        [7,5,'مساعد نيل','google.kore','مساعد تجريبي عام','بيانات وهمية لا تعبر عن شركة حقيقية.']
    ] as $a) {
        $flow=json_encode(['nodes'=>[['id'=>'start','type'=>'start','text'=>'أهلاً بك'],['id'=>'ask','type'=>'ask','text'=>'أقدر أساعدك في إيه؟'],['id'=>'end','type'=>'end']],'edges'=>[['from'=>'start','to'=>'ask'],['from'=>'ask','to'=>'end']]],JSON_UNESCAPED_UNICODE);
        insert("INSERT INTO agents(account_id,site_id,name,voice,instructions,knowledge,flow_json,status,created_at) VALUES (?,?,?,?,?,?,?,'draft',?)",[$a[0],$a[1],$a[2],$a[3],$a[4],$a[5],$flow,$now]);
    }
    foreach([
        [4,1,'متابعة طلبات تجريبية','draft'],[4,2,'حجز مواعيد تجريبي','paused'],
        [5,3,'متابعة العملاء','draft'],[6,4,'حملة الملابس','draft']
    ] as $c)insert('INSERT INTO campaigns(account_id,site_id,name,status,created_at) VALUES (?,?,?,?,?)',[...$c,$now]);
    foreach([
        [4,1,'عميل تجربة 1','DEMO-NO-DIAL-01'],[4,2,'عميل تجربة 2','DEMO-NO-DIAL-02'],
        [5,3,'عميل صيدلية تجريبي','DEMO-NO-DIAL-03'],[6,4,'عميل ملابس تجريبي','DEMO-NO-DIAL-04']
    ] as $c)insert('INSERT INTO contacts(account_id,site_id,name,phone,dnc,created_at) VALUES (?,?,?,?,1,?)',[...$c,$now]);
    foreach([[4,1,'عميل تجربة 1'],[4,2,'عميل تجربة 2'],[5,3,'عميل صيدلية تجريبي']] as $c)
        insert("INSERT INTO calls(account_id,site_id,phone,name,duration_seconds,status,is_demo,created_at) VALUES (?,?,'DEMO-NO-DIAL',?,70,'completed',1,?)",[$c[0],$c[1],$c[2],$now]);
    foreach([
        ['google.aoede','Aoede','gemini_live','basic',1],
        ['google.kore','Kore','gemini_live','basic',1],
        ['google.puck','Puck','gemini_live','basic',1],
        ['ayo.calm','AYO Calm','chatterbox_multilingual','premium',0],
        ['ayo.excited','AYO Excited','chatterbox_multilingual','premium',0],
        ['ayo.sleepy','AYO Sleepy','chatterbox_multilingual','premium',0]
    ] as $v)insert("INSERT INTO voices(code,name,provider,tier,ready,status) VALUES (?,?,?,?,?,'active')",$v);
    foreach([2,3,4,5,6,7] as $id)
        foreach([1,2,3] as $voice)
            insert('INSERT INTO voice_grants(account_id,voice_id) VALUES (?,?)',[$id,$voice]);
    foreach([
        [5,'whmcs-order-1001',3,'طلب WHMCS مدفوع تجريبياً','paid_demo'],
        [6,'manual-1002',3,'طلب تحويل بنكي تجريبي','pending_manual_review']
    ] as $order)insert("INSERT INTO orders(account_id,external_order_id,plan_id,customer_name,customer_email,amount_minor,status,created_at) VALUES (?,?,?,?,?,0,?,?)",[$order[0],$order[1],$order[2],$order[3],'demo+'.(string)$order[0].'@example.invalid',$order[4],$now]);
    return ['status'=>'seeded','users'=>count($users),'accounts'=>count($accountRows),'sites'=>count($sites)];
}

if(PHP_SAPI==='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__) {
    echo json_encode(seed_console(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
}
