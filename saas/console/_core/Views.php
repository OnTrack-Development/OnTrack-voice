<?php
declare(strict_types=1);

function form_start(string $action,string $return='/',string $extra=''):string{
    return '<form method="post" '.$extra.' class="stack"><input type="hidden" name="csrf" value="'.e(csrf()).'"><input type="hidden" name="action" value="'.e($action).'"><input type="hidden" name="return" value="'.e($return).'">';
}
function field_html(string $label,string $name,string $type='text',string $placeholder='',bool $required=false):string{
    return '<label class="field">'.e($label).'<input name="'.e($name).'" type="'.e($type).'" placeholder="'.e($placeholder).'" '.($required?'required':'').' maxlength="190"></label>';
}
function submit_btn(string $label):string{return '<button type="submit" class="btn primary">'.e($label).'</button></form>';}
function select_html(string $label,string $key,array $options):string{
    $html='<label class="field">'.e($label).'<select name="'.e($key).'">';
    foreach($options as $val=>$desc)$html.='<option value="'.e((string)$val).'">'.e((string)$desc).'</option>';
    return $html.'</select></label>';
}
function card(string $title,string $body,string $meta=''):string{
    return '<section class="card"><div class="card-head"><h3>'.e($title).'</h3>'.($meta?'<span class="dim">'.e($meta).'</span>':'').'</div>'.$body.'</section>';
}
function counter(string $caption,int|string $n,string $icon):string{
    return '<div class="metric"><div class="metric-title"><span>'.e($caption).'</span><i>'.e($icon).'</i></div><strong>'.e((string)$n).'</strong></div>';
}
function tbl(array $columns,array $rows):string{
    $html='<div class="table-wrap"><table><thead><tr>';
    foreach($columns as $name)$html.='<th>'.e((string)$name).'</th>';
    $html.='</tr></thead><tbody>';
    if(!$rows)$html.='<tr><td colspan="'.count($columns).'" class="empty">لا توجد بيانات بعد</td></tr>';
    foreach($rows as $cells){
        $html.='<tr>';
        foreach($cells as $cell)$html.='<td>'.(string)$cell.'</td>';
        $html.='</tr>';
    }
    return $html.'</tbody></table></div>';
}
function pill(string $v):string{
    $state=strtolower($v);
    $good=in_array($state,['active','paid','online','completed','ready','success'],true);
    $bad=in_array($state,['revoked','suspended','failed','offline','cancelled'],true);
    return '<span class="pill '.($good?'ok':($bad?'bad':'neutral')).'">'.e($v).'</span>';
}
function fmt_duration(int $seconds):string{
    $m=intdiv(max(0,$seconds),60);$s=max(0,$seconds)%60;
    return $m?($m.' دقيقة'.($s?' و'.$s.' ثانية':'')):($s.' ثانية');
}
function nav_entries(string $type):array{
    $common=[
        'overview'=>['⌂','الرئيسية'],
        'staff'=>['♙','الموظفون'],
        'billing'=>['▣','الباقات والمدفوعات'],
        'settings'=>['⚙','الإعدادات']
    ];
    if($type==='platform')return [
        'overview'=>['⌂','نظرة عامة'],'accounts'=>['♧','الحسابات والريسيلرز'],
        'plans'=>['◫','الخطط والحدود'],'voices'=>['♫','إدارة الأصوات'],
        'staff'=>['♙','فريق المنصة'],'integrations'=>['⇄','التكاملات'],'keys'=>['⌘','مفاتيح API'],
        'sites'=>['◈','مواقعنا'],'agents'=>['✧','إيجنتاتنا'],
        'devices'=>['▣','مركز الهاتف'],'calls'=>['☏','سجل المكالمات'],
        'contacts'=>['♧','جهات الاتصال'],'campaigns'=>['▤','الحملات'],
        'billing'=>['▣','الفوترة'],'releases'=>['↓','تطبيق Android'],
        'settings'=>['⚙','إعدادات المنصة']
    ];
    if($type==='reseller')return [
        'overview'=>['⌂','نظرة عامة'],'accounts'=>['♧','عملائي'],
        'plans'=>['◫','باقات العملاء'],'voices'=>['♫','الأصوات المتاحة'],
        'staff'=>['♙','الفريق والأدوار'],'domains'=>['◎','الدومينات والبراند'],
        'sites'=>['◈','المواقع'],'agents'=>['✧','الإيجنتات'], 'devices'=>['▣','الأجهزة'],
        'integrations'=>['⇄','Laravel / WHMCS'],
        'keys'=>['⌘','مفاتيح API'],'billing'=>['▣','الاشتراكات'],
        'releases'=>['↓','تطبيق Android'],'settings'=>['⚙','هوية الريسيلر']
    ];
    return [
        'overview'=>['⌂','الرئيسية'],'sites'=>['◈','مواقعي'],
        'agents'=>['✧','AI Agent Builder'],'campaigns'=>['▤','الحملات'],
        'calls'=>['☏','سجل المكالمات'],'contacts'=>['♧','جهات الاتصال'],'devices'=>['▣','الأجهزة والخطوط'],
        'voices'=>['♫','الأصوات'],'staff'=>['♙','الموظفون'],
        'keys'=>['⌘','مفاتيح API'],'integrations'=>['⇄','التكاملات'],'billing'=>['▣','الباقة والرصيد'],
        'releases'=>['↓','Android'],'settings'=>['⚙','الإعدادات']
    ];
}
function layout_open(array $a,string $tab):void{
    $u=me();$brand=brand($a);$items=nav_entries((string)$a['kind']);$heading=$items[$tab][1]??'الرئيسية';
    $role=match($a['kind']){'platform'=>'ADMIN CONTROL','reseller'=>'RESELLER HUB',default=>'USER WORKSPACE'};
    $support=active_support()!==null;
    header('Content-Type: text/html; charset=utf-8');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Cache-Control: private,no-store');
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#121215"><title>'.e($heading).' | '.e($brand['name']).'</title><link rel="stylesheet" href="/saas/console/assets/css/app.css"><script src="/saas/console/assets/js/app.js" defer></script><script src="/saas/console/assets/js/flow-graph.js" defer></script><script src="/saas/console/assets/js/qrcode.js" defer></script><script src="/saas/console/assets/js/pairing-qr.js" defer></script></head><body>';
    echo '<div class="app-shell"><aside class="sidebar" id="mainNavigation" aria-label="القائمة الرئيسية"><button type="button" class="nav-close" id="navClose" aria-label="إغلاق القائمة">×</button><a href="/saas/console/" class="brand"><span class="brand-logo">';
    if(!empty($brand['logo'])) echo '<img src="/saas/console/brand-logo" alt="">';else echo '<b>'.e(mb_substr($brand['name'],0,1)).'</b>';
    echo '</span><span class="brand-lines"><strong>'.e($brand['name']).'</strong><small>'.e($role).'</small></span></a>';
    echo '<div class="nav-caption">مساحة العمل</div><nav class="nav-list">';
    foreach($items as $key=>$v){
        $active=$tab===$key?' selected':'';
        echo '<a href="/saas/console/?tab='.e($key).'" class="nav-link'.$active.'"><span class="nav-icon">'.e($v[0]).'</span><span>'.e($v[1]).'</span></a>';
    }
    echo '</nav><div class="side-footer"><span class="live-dot"></span> مركز إدارة الاتصال الذكي <small>OnTrack Voice SaaS · Demo</small></div></aside><button type="button" class="nav-backdrop" id="navBackdrop" aria-label="إغلاق القائمة" tabindex="-1"></button><div class="main-area">';
    echo '<header class="topbar"><button type="button" class="icon-button nav-toggle" id="navToggle" aria-controls="mainNavigation" aria-expanded="false" aria-label="فتح القائمة">☰</button><div class="headline"><span class="eyebrow">'.e($role).'</span><h1>'.e($heading).'</h1></div><div class="header-actions">';
    $members=memberships((int)$u['id']);
    if(count($members)>1&&!$support){
        echo form_start('switch');$options=[];
        foreach($members as $m)$options[$m['account_id']]=$m['name'].' · '.$m['kind'];
        echo select_html('المساحة','account_id',$options).submit_btn('تبديل');
    }
    echo '<button type="button" class="icon-button" id="themeToggle" aria-label="تغيير المظهر" title="تغيير المظهر">☀</button>';
    echo '<div class="profile"><span class="avatar">'.e(mb_substr($u['name'],0,1)).'</span><span><strong>'.e($u['name']).'</strong><small>'.e($a['role']??'').'</small></span></div>';
    echo form_start('logout','','class="compact"').'<button type="submit" class="logout-btn" title="تسجيل الخروج">↪</button></form>';
    echo '</div></header><main class="content">';
    $myInvites=pending_staff_invitations_for_user((int)$u['id']);
    if($myInvites && !$support)echo '<div class="alert success">لديك '.count($myInvites).' دعوة انضمام تنتظر قرارك. <a href="/saas/console/invitations">مراجعة الدعوات ←</a></div>';
    if($support)echo '<div class="support-context"><span>حساب '.e($a['name']).' · '.(support_mode((int)($a['support_origin']??0))==='read_only'?'قراءة فقط':'صلاحيات كاملة').'</span>'.form_start('support_leave').'<button class="btn small" type="submit">العودة لحسابي</button></form></div>';
    if($v=flash('success'))echo '<div class="alert success">'.e($v).'</div>';
    if($v=flash('error'))echo '<div class="alert danger">'.e($v).'</div>';
    if($v=flash('secret'))echo '<div class="alert secret"><b>بيانات تظهر مرة واحدة:</b> <code>'.e($v).'</code></div>';
    if($pairCode=flash('device_pair_qr')){
        // The code is rendered only on the post-create session response.
        // QR is generated in-browser from bundled MIT JS; never a third-party QR API.
        // The first hex digit encodes the only slot the server will accept.
        // Never offer a QR for the other slot using the same single-use code.
        $slot=(int)$pairCode[0];
        echo '<section class="alert secret pair-qr-panel"><strong>رمز ربط SIM '.($slot+1).' فقط — لا يصلح للشريحة الأخرى</strong><div class="pair-qr-grid">';
        if(in_array($pairCode[0],['0','1'],true)){
            echo '<div class="pair-qr-card"><b>SIM '.($slot+1).'</b><div class="pair-qr-code" data-pair-code="'.e($pairCode).'" data-pair-slot="'.$slot.'"></div><button type="button" class="btn small" data-pair-copy="'.$slot.'">نسخ رابط الشريحة</button></div>';
        }
        echo '</div><p class="dim">QR يحتوي رمزًا مؤقتًا؛ لا تشاركه مع أحد. المسح يفتح شاشة تأكيد داخل التطبيق ولا يربط تلقائيًا.</p></section>';
    }
    // Synthetic rows retain explicit DEMO marks; no persistent warning bars.
}
function layout_close():void{
    echo '</main><footer class="footer">© '.date('Y').' · منصة الاتصال الذكي · العزل والصلاحيات يفرضها السيرفر</footer></div></div></body></html>';
}
function login_page(?string $error=null):void{
    header('Content-Type: text/html; charset=utf-8');
    header('X-Frame-Options: DENY');header('Cache-Control: no-store');
    $brandName='OnTrack Voice';
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>تسجيل الدخول | '.e($brandName).'</title><link rel="stylesheet" href="/saas/console/assets/css/app.css"><script src="/saas/console/assets/js/app.js" defer></script></head><body class="login-body"><div class="login-backdrop"></div><div class="login-panel"><div class="login-brand"><span class="brand-icon">OT</span><div><strong>OnTrack Voice</strong><small>INTELLIGENT CALLING PLATFORM</small></div></div><div class="login-intro"><span class="eyebrow">YOUR VOICE. YOUR BUSINESS.</span><h1>أهلاً برجوعك</h1><p>منصة واحدة لإدارة الإيجنتات والمكالمات والعملاء والباقات بأمان.</p></div>';
    if($error)echo '<div class="alert danger">'.e($error).'</div>';
    echo '<form method="post" action="/saas/console/login" class="stack">'.'<input type="hidden" name="csrf" value="'.e(csrf()).'"><label class="field">البريد الإلكتروني<input required type="email" name="email" autocomplete="username" placeholder="name@company.com"></label><label class="field">كلمة المرور<div class="password-wrap"><input required type="password" name="password" autocomplete="current-password" id="loginPassword" placeholder="••••••••••"><button type="button" class="password-toggle" data-toggle-password>إظهار</button></div></label><button class="btn primary wide" type="submit">تسجيل الدخول <span>←</span></button></form><p class="login-foot">تجربة أولية · يتم تحديد الصلاحيات حسب الحساب</p></div></body></html>';
}
function render_overview(array $a):void{
    $id=(int)$a['account_id'];$kind=$a['kind'];
    if($kind==='platform'){
        $n1=(int)one("SELECT COUNT(*) n FROM accounts WHERE kind='reseller'")['n'];
        $n2=(int)one("SELECT COUNT(*) n FROM accounts WHERE kind='customer'")['n'];
        $n3=(int)one('SELECT COUNT(*) n FROM agents')['n'];
        $n4=(int)one('SELECT COUNT(*) n FROM calls')['n'];
        $title='منصة OnTrack';$sub='إدارة الحسابات والخطط والإيجنتات والتكاملات من نقطة واحدة.';
    }elseif($kind==='reseller'){
        $n1=(int)one("SELECT COUNT(*) n FROM accounts WHERE parent_id=?",[$id])['n'];
        $n2=(int)one("SELECT COALESCE(SUM(p.call_quota),0) n FROM subscriptions s JOIN plans p ON s.plan_id=p.id JOIN accounts a ON a.id=s.account_id WHERE a.parent_id=? AND s.status='active'",[$id])['n'];
        $n3=site_limit($id,'call_quota');$n4=(int)one('SELECT COUNT(*) n FROM integrations WHERE account_id=?',[$id])['n'];
        $title='مساحة الريسيلر';$sub='إدارة العملاء والهوية والاشتراكات من مكان واحد دون رفع ملفات.';
    }else{
        $n1=(int)one('SELECT COUNT(*) n FROM sites WHERE account_id=?',[$id])['n'];
        $n2=(int)one('SELECT COUNT(*) n FROM agents WHERE account_id=?',[$id])['n'];
        $n3=site_limit($id,'call_quota');
        $n4=(int)one('SELECT COUNT(*) n FROM calls WHERE account_id=?',[$id])['n'];
        $title='مساحة عملك الذكية';$sub='اختار الموقع المناسب، وابنِ الإيجنت، وتابع حملاتك واستهلاكك.';
    }
    echo '<section class="welcome"><div><span class="eyebrow">SMART VOICE CONTROL</span><h2>'.e($title).'</h2><p>'.e($sub).'</p><div class="welcome-actions"><a class="btn light" href="/saas/console/?tab='.($kind==='reseller'?'accounts':'agents').'">إدارة '.($kind==='reseller'?'العملاء':'الإيجنتات').' ←</a><a class="btn ghost-light" href="/saas/console/?tab=releases">تطبيق Android</a></div></div><div class="orb-art"><span></span><b>OT</b></div></section>';
    echo '<div class="metric-grid">';
    if($kind==='platform'){
        echo counter('الريسيلرز',$n1,'♧').counter('العملاء',$n2,'♙').counter('الإيجنتات',$n3,'✧').counter('سجلات المكالمات',$n4,'☏');
    }elseif($kind==='reseller'){
        echo counter('شركات العملاء',$n1,'♧').counter('حصص العملاء المحجوزة',$n2,'◫').counter('رصيد باقة الريسيلر',$n3,'▣').counter('تكاملات مرتبطة',$n4,'⇄');
    }else{
        echo counter('المواقع',$n1,'◎').counter('الإيجنتات',$n2,'✧').counter('مكالمات الخطة الشهرية',$n3,'☏').counter('سجلات تجريبية',$n4,'▤');
    }
    echo '</div><div class="cols two">';
    $tips='<div class="quick-grid"><a href="/saas/console/?tab=sites"><b>◈</b><span>مواقع مستقلة</span><small>إيجنت ومعرفة لكل موقع</small></a><a href="/saas/console/?tab=agents"><b>✧</b><span>Agent Flow Builder</span><small>السيناريو والشخصية والصوت</small></a><a href="/saas/console/?tab=keys"><b>⌘</b><span>API Keys</span><small>مفاتيح مسمّاة وقابلة للإلغاء</small></a><a href="/saas/console/?tab=staff"><b>♙</b><span>إدارة الموظفين</span><small>أدوار وصلاحيات لكل حساب</small></a></div>';
    echo card('ابدأ من هنا',$tips,'إجراءات سريعة');
    $items=$kind==='platform'?all('SELECT action,created_at FROM audit_events ORDER BY id DESC LIMIT 5'):all('SELECT action,created_at FROM audit_events WHERE account_id=? ORDER BY id DESC LIMIT 5',[$id]);
    echo card('آخر الأحداث',tbl(['الحدث','التاريخ'],array_map(fn($x)=>[e($x['action']),e($x['created_at'])],$items)),'سجل تدقيق حقيقي');
    echo '</div>';
}
function render_accounts(array $a):void{
    if(!in_array($a['kind'],['platform','reseller'],true))deny();
    $id=(int)$a['account_id'];
    $rows=$a['kind']==='platform'?
        all("SELECT a.*, (SELECT COUNT(*) FROM memberships m WHERE m.account_id=a.id) seats FROM accounts a WHERE a.kind!='platform' ORDER BY a.id DESC"):
        all("SELECT a.*, (SELECT COUNT(*) FROM memberships m WHERE m.account_id=a.id) seats FROM accounts a WHERE a.parent_id=? ORDER BY a.id DESC",[$id]);
    echo '<div class="cols two">';
    echo card('إنشاء حساب جديد',form_start('create_account','/?tab=accounts').
        ($a['kind']==='platform'?select_html('نوع الحساب','kind',['reseller'=>'ريسيلر','customer'=>'عميل مباشر']):'').
        field_html('اسم الشركة','name','text','اسم المؤسسة',true).
        field_html('بريد المالك','email','email','owner@company.com',true).
        submit_btn('إنشاء الحساب وإظهار كلمة مرور مؤقتة'));
    $policy=$a['kind']==='platform'?0:$id;
    $options=[0=>'السياسة العامة'];
    if($a['kind']==='platform')foreach(all("SELECT id,name FROM accounts WHERE kind='reseller'") as $reseller)$options[$reseller['id']]=$reseller['name'];
    echo card('سياسة دخول الحسابات',form_start('support_policy','/?tab=accounts').
        ($a['kind']==='platform'?select_html('الحساب','reseller_id',$options):'').
        select_html('صلاحيات الدخول','access_mode',['full'=>'كامل الصلاحيات','read_only'=>'قراءة فقط']).
        '<p class="dim">الحالي: '.e(support_mode($policy)).' · يسجل سجل التدقيق الموظف الفعلي الذي نفّذ التغيير.</p>'.
        submit_btn('حفظ السياسة'));
    echo '</div>';
    $cells=[];
    foreach($rows as $r){
        $support=form_start('support_enter','/?tab=accounts','class="inline-form"').'<input type="hidden" name="account_id" value="'.(int)$r['id'].'"><button class="btn small" type="submit">الدخول للحساب</button></form>';
        $support.=form_start('edit_account','/?tab=accounts','class="inline-form"').'<input type="hidden" name="account_id" value="'.(int)$r['id'].'"><input class="tiny-input" name="name" value="'.e($r['name']).'"><button class="btn small">تعديل</button></form>';
        $support.=form_start('set_account_status','/?tab=accounts','class="inline-form"').'<input type="hidden" name="account_id" value="'.(int)$r['id'].'"><input type="hidden" name="status" value="'.($r['status']==='active'?'suspended':'active').'"><button class="btn small danger">'.($r['status']==='active'?'تعليق':'تفعيل').'</button></form>';
        $cells[]=[e($r['name']).'<small class="block dim">'.e($r['slug']).'</small>',pill((string)$r['kind']),e((string)($r['parent_id']??'مباشر')),e((string)$r['seats']),pill((string)$r['status']),$support];
    }
    echo card('الحسابات التابعة',tbl(['الاسم','النوع','الريسيلر','الأعضاء','الحالة','إدارة'],$cells));
}
function render_plans(array $a):void{
    $type=$a['kind'];$id=(int)$a['account_id'];
    $list=$type==='platform'?all('SELECT * FROM plans ORDER BY kind,id'):all("SELECT * FROM plans WHERE kind='user' AND status='active' ORDER BY id");
    $rows=[];
    foreach($list as $p)$rows[]=[e($p['name']),pill($p['kind']),e((string)$p['call_quota']),e((string)$p['site_limit']),e((string)$p['agent_limit']),e((string)$p['staff_limit']),e((string)$p['price_minor']).' قرش'];
    echo card('كتالوج الخطط',tbl(['الباقة','النوع','مكالمات شهرياً','مواقع','AI','موظفون','سعر تجريبي'],$rows));
    if($type==='platform'){
        foreach($list as $plan){
          if($plan['status']==='archived')continue;
          echo card('تعديل '.e($plan['name']),
              form_start('edit_plan','/?tab=plans','class="form-grid"').'<input type="hidden" name="plan_id" value="'.$plan['id'].'">'.
              '<label class="field">الاسم<input name="name" value="'.e($plan['name']).'" required></label>'.
              '<label class="field">مكالمات<input name="calls" type="number" min="0" value="'.$plan['call_quota'].'"></label>'.
              '<label class="field">المواقع<input name="sites" type="number" min="0" value="'.$plan['site_limit'].'"></label>'.
              '<label class="field">AI<input name="agents" type="number" min="0" value="'.$plan['agent_limit'].'"></label>'.
              '<label class="field">الموظفون<input name="staff" type="number" min="0" value="'.$plan['staff_limit'].'"></label>'.
              '<button class="btn small primary">حفظ الباقة</button></form>'.
              form_start('archive_plan','/?tab=plans','class="inline-form"').'<input type="hidden" name="plan_id" value="'.$plan['id'].'"><button class="btn small danger" onclick="return confirm(\'إيقاف بيع الباقة؟ الاشتراكات الحالية ستظل محفوظة\')">إيقاف بيع الباقة</button></form>');
        }
    }
    if($type==='platform'){
        echo card('إنشاء باقة',form_start('create_plan','/?tab=plans').'<div class="form-grid">'.
            select_html('النوع','kind',['reseller'=>'ريسيلر','user'=>'مستخدم']).field_html('اسم الخطة','name','text','باقة جديدة',true).
            field_html('مكالمات/شهر','calls','number','1000').field_html('المواقع','sites','number','1').
            field_html('AI Agents','agents','number','1').field_html('الموظفون','staff','number','3').
            field_html('أقصى عدد عملاء للريسيلر','customers','number','10').field_html('السعر بالقروش','price','number','0').
            '</div>'.submit_btn('حفظ الخطة'));
    }elseif($type==='reseller'){
        $customers=all("SELECT id,name FROM accounts WHERE parent_id=? AND kind='customer'",[$id]);
        $co=[];$po=[];
        foreach($customers as $c)$co[$c['id']]=$c['name'];
        foreach($list as $p)$po[$p['id']]=$p['name'].' — '.$p['call_quota'].' مكالمة';
        if($co&&$po)echo card('تخصيص باقة لعميل',form_start('assign_plan','/?tab=plans').select_html('العميل','customer_id',$co).select_html('الباقة','plan_id',$po).submit_btn('تعيين مع التحقق من رصيد الريسيلر'));
    }
}
function render_staff(array $a):void{
    $id=(int)$a['account_id'];$rows=all("SELECT u.id,u.name,u.email,m.role FROM memberships m JOIN users u ON u.id=m.user_id WHERE m.account_id=? ORDER BY m.id",[$id]);
    echo '<div class="cols two">';
    $users=[];
    foreach($rows as $r){
       $actions=pill($r['role']);
       if(!in_array($r['role'],['owner','admin'],true)&&in_array($a['role'],['owner','admin'],true)&&!active_support()){
           $actions.=form_start('edit_staff','/?tab=staff','class="inline-form"').'<input type="hidden" name="user_id" value="'.$r['id'].'">'.
                select_html('تغيير الدور','role',['manager'=>'مدير','operator'=>'مشغّل','finance'=>'مالية','support'=>'دعم','viewer'=>'مشاهد']).'<button class="btn small">حفظ</button></form>';
           $actions.=form_start('remove_staff','/?tab=staff','class="inline-form"').'<input type="hidden" name="user_id" value="'.$r['id'].'"><button class="btn small danger" onclick="return confirm(\'إزالة الموظف من هذا الحساب؟\')">إزالة</button></form>';
       }
       $users[]=[e($r['name']),e($r['email']),$actions];
    }
    echo card('فريق العمل',tbl(['الاسم','البريد','الصلاحية والإدارة'],$users));
    if((($a['role']??'')==='owner'||($a['role']??'')==='admin')&&!active_support())
        echo card('دعوة موظف (تحتاج قبول صاحب البريد)',form_start('add_employee','/?tab=staff').field_html('اسم الموظف','name','text','الاسم',true).field_html('البريد الإلكتروني','email','email','staff@company.com',true).
            select_html('الدور','role',['manager'=>'مدير','operator'=>'موظف عمليات','finance'=>'مالية','support'=>'دعم','viewer'=>'مشاهد']).submit_btn('إنشاء دعوة بدون منح صلاحية'));
    $pending=all("SELECT id,email,name,role,expires_at FROM staff_invitations WHERE account_id=? AND status='pending' AND expires_at>? ORDER BY id DESC",[$id,now()]);
    $pendingRows=[];
    foreach($pending as $inv){
        $action=pill('pending');
        if(in_array($a['role'],['owner','admin'],true)&&!active_support())
            $action.=form_start('cancel_staff_invitation','/?tab=staff','class="inline-form"').'<input type="hidden" name="invitation_id" value="'.(int)$inv['id'].'"><button class="btn small danger" type="submit">إلغاء الدعوة</button></form>';
        $pendingRows[]=[e($inv['name']),e($inv['email']),e($inv['role']),e($inv['expires_at']),$action];
    }
    echo card('دعوات الفريق المعلقة',tbl(['الاسم','البريد','الدور','تنتهي','الإجراء'],$pendingRows));
    echo '</div>';
}
function render_invitations():void {
    $u=me();if(!$u)return;
    $invites=pending_staff_invitations_for_user((int)$u['id']);
    echo '<section class="card"><div class="card-head"><h3>دعوات الانضمام لحسابات أخرى</h3></div><p>لا تتم إضافة أي عضوية دون موافقتك. الدعوات المرسلة لبريد لا يملك حسابًا بعد تنتظر التسجيل والتحقق من البريد في مرحلة لاحقة.</p>';
    $rows=[];
    foreach($invites as $inv){
        $button='<form method="post" action="/saas/console/invitations" class="inline-form"><input type="hidden" name="csrf" value="'.e(csrf()).'"><input type="hidden" name="action" value="accept_invitation"><input type="hidden" name="invitation_id" value="'.(int)$inv['id'].'"><button class="btn small primary" type="submit">قبول الدعوة</button></form>';
        $rows[]=[e($inv['account_name']),e($inv['role']),e($inv['expires_at']),$button];
    }
    echo tbl(['الحساب','الدور','تنتهي','قرارك'],$rows).'</section>';
    if(mine())echo '<p><a class="btn small" href="/saas/console/">العودة للوحة التحكم</a></p>';
    else echo '<form method="post" action="/saas/console/logout"><input type="hidden" name="csrf" value="'.e(csrf()).'"><button type="submit" class="btn small">تسجيل الخروج</button></form>';
}
function render_sites(array $a):void{
    $id=(int)$a['account_id'];$sites=all("SELECT * FROM sites WHERE account_id=? AND status!='archived' ORDER BY id DESC",[$id]);
    echo '<div class="cols two">';
    $cells=[];
    foreach($sites as $s)$cells[]=[e($s['name']),'<span dir="ltr">'.e($s['domain']).'</span>',pill($s['status']),'<a class="btn small" href="/saas/console/?tab=agents&site='.$s['id'].'">الإيجنتات ←</a>'.
      form_start('update_site','/?tab=sites','class="inline-form"').'<input type="hidden" name="site_id" value="'.$s['id'].'"><input class="tiny-input" name="name" value="'.e($s['name']).'"><input class="tiny-input" name="domain" value="'.e($s['domain']).'"><button class="btn small">تعديل</button></form>'.
      form_start('archive_site','/?tab=sites','class="inline-form"').'<input type="hidden" name="site_id" value="'.$s['id'].'"><button class="btn small danger" onclick="return confirm(\'أرشفة الموقع وإيجنتاته؟\')">أرشفة</button></form>'];
    echo card('مواقعي',tbl(['الموقع','الدومين','الحالة','التشغيل'],$cells));
    if(allowed('sites.write'))echo card('موقع جديد',form_start('add_site','/?tab=sites').field_html('اسم الموقع','name','text','الصيدلية / الملابس / الدعم',true).field_html('الدومين','domain','text','store.example.com').submit_btn('إضافة موقع مستقل'));
    echo '</div>';
}
function render_agents(array $a):void{
    $id=(int)$a['account_id'];$sites=all("SELECT id,name FROM sites WHERE account_id=? AND status='active' ORDER BY id",[$id]);
    $map=[];foreach($sites as $s)$map[$s['id']]=$s['name'];
    $selected=selected_site($id);
    $agents=$selected?all("SELECT * FROM agents WHERE account_id=? AND site_id=? AND status!='archived' ORDER BY id DESC",[$id,$selected['id']]):[];
    echo '<div class="section-line"><p>كل Agent مربوط بموقع واحد، وله Flow ومعرفة وصوت مستقلين.</p><div class="site-picker">';
    foreach($sites as $site)echo '<a class="chip-link '.($selected&&$site['id']==$selected['id']?'chosen':'').'" href="?tab=agents&site='.$site['id'].'">'.e($site['name']).'</a>';
    echo '</div></div>';
    if(!$sites){echo card('ابدأ بموقع','أنشئ موقعاً من قائمة المواقع قبل إضافة الإيجنت.');return;}
    if(allowed('agents.write'))echo card('إيجنت جديد',form_start('add_agent','/?tab=agents').'<div class="form-grid">'.
        field_html('اسم الإيجنت','name','text','مثال: مساعد الحجوزات',true).
        select_html('الموقع','site_id',$map).
        select_html('الصوت','voice',array_column(all("SELECT code,name FROM voices WHERE ready=1 AND status='active' AND id IN (SELECT voice_id FROM voice_grants WHERE account_id=?) ORDER BY name",[$id]),'name','code')?:['google.aoede'=>'Aoede']).
        '</div><label class="field">تعليمات الإيجنت<textarea name="instructions" rows="3" maxlength="5000" placeholder="شخصية الإيجنت ودوره وطريقة الرد"></textarea></label><label class="field">Knowledge Base<textarea name="knowledge" rows="3" maxlength="20000" placeholder="معلومات هذا الموقع فقط"></textarea></label>'.
        submit_btn('حفظ إيجنت كمسودة'));
    foreach($agents as $agent){
        $flow=e((string)$agent['flow_json']);
        $body='<div class="agent-summary"><span class="pill neutral">'.e($agent['voice']).'</span>'.pill($agent['status']).'<span class="dim">موقع: '.e($selected['name']).'</span></div>';
        if(allowed('agents.write')){$body.=form_start('update_agent','/?tab=agents&site='.$selected['id'],'class="stack agent-form"').'<input type="hidden" name="agent_id" value="'.$agent['id'].'">'.
           '<label class="field">اسم الإيجنت<input name="name" maxlength="140" required value="'.e($agent['name']).'"></label>'.
           '<label class="field">الصوت<select name="voice">';
           foreach(all("SELECT code,name FROM voices WHERE status='active' AND ready=1 AND id IN (SELECT voice_id FROM voice_grants WHERE account_id=?)",[$id]) as $opt)
                $body.='<option value="'.e($opt['code']).'" '.($opt['code']===$agent['voice']?'selected':'').'>'.e($opt['name']).'</option>';
           $body.='<option value="'.e($agent['voice']).'" selected>'.e($agent['voice']).' (الحالي)</option></select></label>'.
           '<label class="field">الشخصية والتعليمات<textarea name="instructions" rows="4" maxlength="5000">'.e($agent['instructions']).'</textarea></label><label class="field">معرفة الموقع<textarea name="knowledge" rows="4" maxlength="20000">'.e($agent['knowledge']).'</textarea></label><div class="flow-head"><strong>Agent Flow Builder</strong><small>عقد وأسئلة وشروط متسلسلة</small></div><div class="flow-builder" data-flow-editor><textarea name="flow_json" class="flow-source" rows="7" maxlength="16000">'.$flow.'</textarea></div>'.submit_btn('حفظ مسودة الإيجنت').
          form_start('archive_agent','/?tab=agents&site='.$selected['id'],'class="inline-form"').'<input type="hidden" name="agent_id" value="'.$agent['id'].'"><button class="btn small danger" onclick="return confirm(\'أرشفة الإيجنت؟\')">أرشفة الإيجنت</button></form>';
        } else $body.='<p>'.e($agent['instructions']).'</p>';
        echo card($agent['name'],$body);
    }
}
function render_campaigns(array $a):void{
    $id=(int)$a['account_id'];$s=all('SELECT id,name FROM sites WHERE account_id=?',[$id]);$map=[];
    foreach($s as $v)$map[$v['id']]=$v['name'];
    $rows=all("SELECT c.*,s.name site_name FROM campaigns c LEFT JOIN sites s ON s.id=c.site_id WHERE c.account_id=? AND c.status!='archived' ORDER BY c.id DESC",[$id]);
    $cells=[];
    foreach($rows as $c){
       $form=form_start('update_campaign','/?tab=campaigns','class="inline-form"').'<input type="hidden" name="campaign_id" value="'.$c['id'].'">'.
         '<input class="tiny-input" name="name" value="'.e($c['name']).'">'.select_html('الحالة','status',['draft'=>'مسودة','paused'=>'موقوفة','archived'=>'أرشفة']).'<button class="btn small">حفظ</button></form>';
       $cells[]=[e($c['name']),e($c['site_name']),pill($c['status']),e($c['created_at']),$form];
    }
    echo card('الحملات',tbl(['الحملة','الموقع','الحالة','وقت الإنشاء','الإدارة'],$cells));
    if($map && allowed('campaigns.write'))
        echo card('إنشاء حملة مسودة',form_start('add_campaign','/?tab=campaigns').field_html('اسم الحملة','name','text','متابعة العملاء',true).select_html('الموقع','site_id',$map).'<p class="info">لا يمكن تشغيل حملة من الويب قبل ربط الجهاز والموافقات وجدول الاتصال.</p>'.submit_btn('إنشاء مسودة'));
}
function render_contacts(array $a):void {
    $id=(int)$a['account_id'];
    $sites=all("SELECT id,name FROM sites WHERE account_id=? AND status='active'",[$id]);
    $siteOptions=[];foreach($sites as $s)$siteOptions[$s['id']]=$s['name'];
    $rows=all("SELECT c.*,s.name site_name FROM contacts c LEFT JOIN sites s ON s.id=c.site_id WHERE c.account_id=? ORDER BY c.id DESC LIMIT 300",[$id]);
    $cells=[];
    foreach($rows as $r){
        $action=form_start('edit_contact','/?tab=contacts','class="inline-form"').
          '<input type="hidden" name="contact_id" value="'.$r['id'].'">'.
          '<input name="name" class="tiny-input" value="'.e($r['name']).'" required>'.
          '<input name="phone" class="tiny-input" value="'.e($r['phone']).'" required>'.
          '<label class="field"><input type="checkbox" name="dnc" '.((int)$r['dnc']===1?'checked':'').'>عدم الاتصال</label>'.
          '<button class="btn small">حفظ</button></form>';
        $action.=form_start('delete_contact','/?tab=contacts','class="inline-form"').'<input type="hidden" name="contact_id" value="'.$r['id'].'"><button class="btn small danger" onclick="return confirm(\'حذف جهة الاتصال؟\')">حذف</button></form>';
        $cells[]=[e($r['name']),e($r['phone']),e($r['site_name']),$r['dnc']?pill('DNC'):pill('active'),$action];
    }
    echo card('جهات اتصال كل موقع',tbl(['الاسم','الهاتف','الموقع','الخصوصية','الإجراءات'],$cells));
    echo '<div class="info">أرقام عدم الاتصال (DNC) محمية على مستوى الحساب بالكامل، بما في ذلك مواقع الحساب الأخرى والتكاملات. لا يمكن إلغاء الحظر بتعديل عادي أو حذف السجل؛ يتطلب ذلك مسار موافقة موثق مستقل (قيد التطوير).</div>';
    if($siteOptions && allowed('contacts.write'))
      echo card('إضافة جهة اتصال',form_start('add_contact','/?tab=contacts').
          field_html('الاسم','name','text','اسم العميل',true).
          field_html('الهاتف','phone','text','+201...',true).
          select_html('الموقع','site_id',$siteOptions).
          '<label class="field"><input type="checkbox" name="consent" required> موافقة العميل على التواصل مسجّلة</label>'.
          '<label class="field"><input type="checkbox" name="dnc"> عدم الاتصال بهذا الرقم</label>'.
          submit_btn('إضافة إلى جهات الاتصال'));
}
function render_calls(array $a):void{
    $id=(int)$a['account_id'];$rows=all('SELECT * FROM calls WHERE account_id=? ORDER BY id DESC LIMIT 100',[$id]);
    echo card('سجل المكالمات',tbl(['العميل','الرقم','المدة','الحالة','النوع','التوقيت'],array_map(fn($v)=>[e($v['name']),e($v['phone']),e(fmt_duration((int)$v['duration_seconds'])),pill($v['status']),(int)$v['is_demo']?'DEMO':'فعلي',e($v['created_at'])],$rows)));
    $observations=all("SELECT device_id,call_id,MAX(sim_slot) sim_slot,MAX(agent_id) agent_id,
        MAX(observed_at) last_at,MAX(duration_seconds) duration_seconds,
        MAX(CASE WHEN phase='ended' THEN 1 ELSE 0 END) ended,
        MAX(CASE WHEN phase='active' THEN 1 ELSE 0 END) active
        FROM tenant_call_events WHERE account_id=? GROUP BY device_id,call_id
        ORDER BY MAX(id) DESC LIMIT 100",[$id]);
    $actual=[];
    $outcomes=['unknown'=>'لم يُقيّم','satisfied'=>'راضٍ (تقييم بشري)',
        'unsatisfied'=>'غير راضٍ (تقييم بشري)','follow_up'=>'متابعة مطلوبة (بشرية)'];
    foreach($observations as $row){
        $agent=one('SELECT name FROM agents WHERE id=? AND account_id=?',[(int)$row['agent_id'],$id]);
        $state=(int)$row['ended']===1
            ?((int)$row['active']===1?'انتهت':'لم يتم الرد')
            :((int)$row['active']===1?'متصلة':'رنين');
        $report=one('SELECT outcome,note,source,updated_at FROM tenant_call_reports WHERE account_id=? AND device_id=? AND call_id=?',
            [$id,(int)$row['device_id'],$row['call_id']]);
        $outcome=$report['outcome']??'unknown';
        $note=(string)($report['note']??'');
        $editor='—';
        if(allowed('calls.write') && (int)$row['ended']===1){
            $editor=form_start('save_tenant_call_report','/?tab=calls','class="compact"').
                '<input type="hidden" name="device_id" value="'.(int)$row['device_id'].'">'.
                '<input type="hidden" name="call_id" value="'.e((string)$row['call_id']).'">'.
                '<select name="outcome" aria-label="تقييم بشري">';
            foreach($outcomes as $value=>$label)
                $editor.='<option value="'.e($value).'"'.($outcome===$value?' selected':'').'>'.e($label).'</option>';
            $editor.='</select><textarea name="note" rows="2" maxlength="1000" placeholder="ملاحظة بشرية، ليست ملخص AI">'.e($note).'</textarea>'.
                '<button class="btn small" type="submit">حفظ التقييم</button></form>';
        }
        $actual[]=[e(substr((string)$row['call_id'],0,8)),'#'.(int)$row['device_id'],
            e('SIM '.((int)$row['sim_slot']+1)),e((string)($agent['name']??'إيجنت مؤرشف')),
            e($state),e(fmt_duration((int)$row['duration_seconds'])),
            e((string)$row['last_at']).' UTC',e($outcomes[$outcome]??'غير معروف'),
            e($note),$editor];
    }
    echo card('مكالمات الشرائح المرتبطة بالحساب — أحداث الهاتف وتقييم الموظف',
        tbl(['Call ID','الجهاز','الشريحة','الإيجنت','آخر حالة','المدة المبلغ عنها',
            'وقت الجهاز','نتيجة يدوية','ملاحظات','تعديل'],$actual));
    echo '<p class="info">الأحداث من حالة اتصال Android فقط، بدون رقم أو صوت أو ملخص AI. تقييم الرضا والملاحظات إدخال بشري يدوي لا يُستنتج من المكالمة ولا يفعّل اتصال متابعة أو يرفع حظر DNC. وقت الجهاز والمدة غير موثقين من المشغل؛ ليست إثباتًا لتشغيل AI أو لتسجيل المكالمة. لا تظهر بيانات شركة أخرى.</p>';
    echo '<div class="info">البيانات الموسومة DEMO غير حقيقية ولا توجد تسجيلات صوتية مزيفة. الربط مع Call Ledger للموبايل يتطلب ترحيل tenant-scoped آمن.</div>';
}
function render_devices(array $a):void {
    $id=(int)$a['account_id'];
    require_once __DIR__.'/DevicePairing.php';
    $bindings=all("SELECT d.*,s.name site_name,ag.name agent_name FROM device_bindings d
        JOIN sites s ON s.id=d.site_id JOIN agents ag ON ag.id=d.agent_id
        WHERE d.account_id=? ORDER BY d.id DESC LIMIT 100",[$id]);
    $rows=[];
    $manage=in_array($a['role'],['owner','admin'],true)&&!active_support();
    foreach($bindings as $d){
        $action=$manage && $d['status']==='active'
            ?form_start('revoke_device_binding','/?tab=devices','class="inline-form"').
                '<input type="hidden" name="device_id" value="'.(int)$d['id'].'">'.
                '<button class="btn small" onclick="return confirm(\'فصل هذا الجهاز وإلغاء توكن الشريحة فورًا؟\')">فصل الجهاز</button></form>'
            :'—';
        if($manage && $d['status']==='active'){
            $options=all("SELECT id,name FROM agents WHERE account_id=? AND site_id=? AND status!='archived' ORDER BY name",
                [$id,$d['site_id']]);
            $chooser=form_start('assign_device_agent','/?tab=devices');
            $chooser.='<input type="hidden" name="device_id" value="'.(int)$d['id'].'">';
            $chooser.='<label class="field">إيجنت هذه الشريحة<select name="agent_id">';
            foreach($options as $opt)$chooser.='<option value="'.(int)$opt['id'].'"'.
                ((int)$opt['id']===(int)$d['agent_id']?' selected':'').'>'.e($opt['name']).'</option>';
            $chooser.='</select></label>'.submit_btn('تعيين الإيجنت');
            $action=$chooser.$action;
        }
        $rows[]=[e($d['device_name']),e($d['site_name']),e($d['agent_name']),
            e('SIM '.((int)$d['sim_slot']+1)),pill($d['status']),
            e($d['last_seen_at']?:'لم يتصل بعد'),$action];
    }
    echo card('أجهزة Android المربوطة بالحساب',tbl(['الجهاز','الموقع','الإيجنت','الشريحة','الحالة','آخر اتصال','الإجراء'],$rows));
    $pairs=all("SELECT p.id,p.status,p.created_at,p.expires_at,s.name site_name,ag.name agent_name
        FROM device_pairings p JOIN sites s ON s.id=p.site_id JOIN agents ag ON ag.id=p.agent_id
        WHERE p.account_id=? AND p.status IN ('pending','pending_sim0','pending_sim1') AND p.expires_at>? ORDER BY p.id DESC LIMIT 5",
        [$id,now()]);
    $pending=[];
    foreach($pairs as $p){
        $action=$manage ?form_start('cancel_device_pairing','/?tab=devices','class="inline-form"').
            '<input type="hidden" name="pairing_id" value="'.(int)$p['id'].'">'.
            '<button class="btn small" type="submit">إلغاء</button></form>' : '—';
        $simLabel=$p['status']==='pending_sim0'?'SIM 1':($p['status']==='pending_sim1'?'SIM 2':'رمز قديم — ألغِه وأنشئ جديدًا');
        $pending[]=[e($p['site_name']),e($p['agent_name']),e($simLabel),e($p['expires_at']).' UTC',$action];
    }
    echo card('طلبات الاقتران المعلقة',tbl(['الموقع','الإيجنت','الشريحة','ينتهي في','الإجراء'],$pending));
    if($manage){
        $sites=all("SELECT id,name FROM sites WHERE account_id=? AND status='active' ORDER BY name",[$id]);
        $agents=all("SELECT ag.id,ag.name,s.name site_name FROM agents ag JOIN sites s ON s.id=ag.site_id
            WHERE ag.account_id=? AND ag.status!='archived' AND s.status='active' ORDER BY ag.id",[$id]);
        $siteOptions=[];$agentOptions=[];
        foreach($sites as $s)$siteOptions[$s['id']]=$s['name'];
        foreach($agents as $ag)$agentOptions[$ag['id']]=$ag['site_name'].' — '.$ag['name'];
        if($siteOptions&&$agentOptions){
            echo card('ربط جهاز وشريحة بخط مستقل',
                '<p>اختر SIM 1 أو SIM 2 أولًا. الرمز المؤقت مقيد بهذه الشريحة فقط ولا يمكن استخدامه لربط الأخرى. لكل شريحة حساب وإيجنت محددان، ولا يستخدم هذا الربط مفتاح هاتف التجربة القديم.</p>'.
                form_start('create_device_pairing','/?tab=devices').
                select_html('الموقع','site_id',$siteOptions).
                select_html('الإيجنت','agent_id',$agentOptions).
                select_html('الشريحة المقصودة','sim_slot',[0=>'SIM 1',1=>'SIM 2']).
                submit_btn('إنشاء رمز اقتران لمدة 10 دقائق').
                '<p class="dim">رمز الاقتران يُعرض مرة واحدة، ولا يتم حفظه كنص. استخدم QR داخل تطبيق Android أو أدخل الرمز يدويًا. يجب تأكيد الشريحة والحساب على الهاتف؛ الربط لا يثبت توافق SIM الصوتي بعد.</p>');
        }else{
            echo card('ربط Android','<p>أضف موقعًا وإيجنت أولًا ثم أنشئ رمز ربط للشريحة.</p>');
        }
    }
    if($a['kind']!=='platform'){
        echo '<p class="info">تجهيز الربط على السيرفر متاح، لكن التطبيق المثبت حاليًا ما زال على هوية التجربة القديمة؛ لا تتوقع مزامنة الجهاز الجديد قبل تحديث Android واختبار الشريحة.</p>';
        return;
    }
    require_once __DIR__.'/MobilePilot.php';
    $settings=pilot_settings();
    $state=pilot_json('call-status.json');
    $seen=!empty($state['seen_at'])?strtotime((string)$state['seen_at']):0;
    $fresh=$seen&&time()-$seen<45;
    $phase=$fresh?(string)($state['phase']??'unknown'):'offline';
    echo '<div class="metric-grid">'.
        counter('هاتف التجربة',$fresh?'متصل':'غير متصل','◉').
        counter('مرحلة المكالمة',$phase,'☏').
        counter('الإيجنت',!empty($settings['enabled'])?'مفعّل':'متوقف','✧').
        counter('Revision',(string)$settings['revision'],'↻').
        '</div>';
    echo card('حالة الجهاز الحقيقية','<p>آخر حالة مستلمة: '.e((string)($state['seen_at']??'لا توجد حالة')).'</p><p class="dim">حفظ سياسة التشغيل لا يعني أن الهاتف نفّذها قبل وصول إقرار المزامنة.</p>');
    if(in_array($a['role'],['owner','admin'],true)&&!active_support()){
        echo card('التحكم الفعلي في سياسة هاتف التجربة',
            form_start('pilot_settings','/?tab=devices').
            '<label class="field">اسم الإيجنت<input name="agent_name" value="'.e((string)$settings['agent_name']).'" required maxlength="120"></label>'.
            '<label class="field">الصوت<input name="voice" value="'.e((string)$settings['voice']).'" required maxlength="40"></label>'.
            '<label class="field">تأخير الرد بالثواني<input type="number" name="answer_delay_seconds" min="0" max="30" value="'.(int)$settings['answer_delay_seconds'].'"></label>'.
            '<label class="field">تعليمات الإيجنت<textarea name="instruction" rows="5" maxlength="5000">'.e((string)$settings['instruction']).'</textarea></label>'.
            '<label class="field"><input type="checkbox" name="auto_answer" '.(!empty($settings['auto_answer'])?'checked':'').'> الرد التلقائي</label>'.
            '<label class="field"><input type="checkbox" name="enabled" '.(!empty($settings['enabled'])?'checked':'').'> تشغيل الإيجنت</label>'.
            submit_btn('حفظ الإعدادات ومزامنتها'));
    }
    echo '<p class="info">هذا الربط خاص بمنصة OnTrack فقط. لن يظهر هاتف الأدمن لدى عملاء الريسيلر بدون Pairing مستقل.</p>';
}
function render_voices(array $a):void{
    $id=(int)$a['account_id'];$list=all('SELECT * FROM voices ORDER BY tier, id');
    $grants=all('SELECT voice_id FROM voice_grants WHERE account_id=?',[$id]);$g=array_column($grants,'voice_id');
    $rows=[];
    foreach($list as $v){
        $last='<span class="dim">'.(in_array($v['id'],$g)?'متاح':'غير مخصص').'</span>';
        if($a['kind']==='platform'){
            $last=form_start('toggle_voice','/?tab=voices','class="inline-form"').'<input type="hidden" name="voice_id" value="'.$v['id'].'"><button class="btn small">إظهار/إخفاء</button></form>';
            $last.=form_start('set_voice_ready','/?tab=voices','class="inline-form"').'<input type="hidden" name="voice_id" value="'.$v['id'].'"><input type="hidden" name="ready" value="'.((int)$v['ready']===1?'0':'1').'"><input type="hidden" name="confirmed" value="1"><button class="btn small" onclick="return confirm(\'هل اختبرت الصوت بنجاح على مزود الاتصال؟\')">'.((int)$v['ready']===1?'إلغاء الجاهزية':'اعتماد صوت').'</button></form>';
        }
        $rows[]=[e($v['name']),e($v['provider']),pill($v['tier']),(int)$v['ready']?pill('ready'):pill('not ready'),pill($v['status']),$last];
    }
    echo card('Voice Catalog',tbl(['الصوت','المزود','الفئة','جاهزية فعلية','العرض','التحكم'],$rows));
    if($a['kind']==='platform')
        echo card('Google Voice Discovery',form_start('sync_google_voices','/?tab=voices').
            '<p>استيراد كتالوج Google Gemini Live المنشور (30 صوتاً). كل صوت جديد يبقى غير مفعّل حتى التحقق من توافقه مع مزوّد الاتصال الفعلي.</p>'.
            submit_btn('مزامنة 30 صوت Google')).
        card('جلب الأصوات مباشرة من حساب Google',
          form_start('fetch_google_voices','/?tab=voices').
          '<label class="field">Google API Key (لا يُحفظ بعد الفحص)<input type="password" name="google_api_key" required autocomplete="off" minlength="12"></label>'.
          '<p class="dim">يستخدم Google Gemini Voices API لجلب الأصوات المتاحة لحسابك؛ كل صوت مستورد يبقى غير مفعل إلى أن تختبره وتسمح به.</p>'.
          submit_btn('جلب الأصوات الحقيقية من Google'));
    echo '<div class="info">أصوات AYO Premium لم يتم إثبات تشغيلها بعد؛ لن تُباع أو تُفعل قبل اختبار المحرك.</div>';
    if($a['kind']==='platform'||$a['kind']==='reseller'){
        $targets=$a['kind']==='platform'?all("SELECT id,name FROM accounts WHERE kind!='platform'"):all('SELECT id,name FROM accounts WHERE parent_id=?',[$id]);
        $opts=[];foreach($targets as $v)$opts[$v['id']]=$v['name'];
        $active=[];foreach($list as $v)if((int)$v['ready']===1)$active[$v['id']]=$v['name'];
        if($opts&&$active)echo card('تخصيص صوت لحساب',form_start('add_voice_grant','/?tab=voices').select_html('الحساب','account_id',$opts).select_html('الصوت الجاهز','voice_id',$active).submit_btn('منح الصوت ضمن صلاحيات الباقة'));
    }
}
function render_secret_controls(string $type,array $row,string $tab):string{
    $a=mine();if(!in_array($a['role'],['owner','admin'],true)||active_support())return pill((string)$row['status']);
    $return='/?tab='.$tab;
    $controls=pill((string)$row['status']);
    if($row['status']!=='active')return $controls;
    $controls.=form_start('reveal_secret',$return,'class="inline-form"').'<input type="hidden" name="secret_type" value="'.e($type).'"><input type="hidden" name="secret_id" value="'.$row['id'].'"><input type="password" name="password" class="tiny-input" placeholder="كلمة مرور حسابك" required autocomplete="current-password"><button class="btn small">عرض ونسخ</button></form>';
    $controls.=form_start('rotate_secret',$return,'class="inline-form"').'<input type="hidden" name="secret_type" value="'.e($type).'"><input type="hidden" name="secret_id" value="'.$row['id'].'"><button class="btn small" onclick="return confirm(\'تغيير السر يلغي المفتاح القديم فوراً، هل تريد الاستمرار؟\')">تدوير</button></form>';
    $action=$type==='api'?'revoke_key':'revoke_integration';
    $field=$type==='api'?'key_id':'integration_id';
    $controls.=form_start($action,$return,'class="inline-form"').'<input type="hidden" name="'.$field.'" value="'.$row['id'].'"><button class="btn small danger" onclick="return confirm(\'إلغاء الاتصال نهائياً؟\')">إلغاء</button></form>';
    return $controls;
}
function render_keys(array $a):void{
    $id=(int)$a['account_id'];$include=($_GET['archived']??'')==='1';
    $list=all("SELECT k.*,s.name site_name FROM api_keys k LEFT JOIN sites s ON s.id=k.site_id WHERE k.account_id=? ".
        ($include?"":"AND k.status='active' ")."ORDER BY k.id DESC",[$id]);
    $rows=[];
    foreach($list as $k){
        $name=e($k['name']);
        if(in_array($a['role'],['owner','admin'],true) && !active_support())
           $name.=form_start('rename_secret','/?tab=keys','class="inline-form"').'<input type="hidden" name="secret_type" value="api"><input type="hidden" name="secret_id" value="'.$k['id'].'"><input class="tiny-input" name="name" value="'.e($k['name']).'"><button class="btn small">تعديل الاسم</button></form>';
        $rows[]=[$name,'<code>'.e($k['prefix']).'•••</code>',e($k['site_name']??'الحساب'),e($k['scopes']),render_secret_controls('api',$k,'keys')];
    }
    echo card('API Keys · <span class="dim">مفتاح لكل موقع</span>',
        '<p class="dim">المفاتيح الجديدة مشفرة بمفتاح خاص خارج موقع الويب. عرضها مجدداً يتطلب كلمة مرور صاحب الحساب. المفاتيح القديمة قبل هذه الإضافة تحتاج تدويرًا لأنها محفوظة Hash فقط.</p>'.
        '<p><a class="btn small" href="?tab=keys'.($include?'':'&archived=1').'">'.($include?'عرض المفاتيح النشطة':'عرض سجل المفاتيح الملغاة').'</a></p>'.
        tbl(['الاسم','المعرّف','الموقع','الصلاحيات','الإجراءات'],$rows));
    $sites=all("SELECT id,name FROM sites WHERE account_id=? AND status='active'",[$id]);
    $map=[0=>'كامل الحساب'];foreach($sites as $v)$map[$v['id']]=$v['name'];
    if(in_array($a['role'],['owner','admin'],true)&&!active_support()){
        $scopes=$a['kind']==='reseller'?'customers:create,plans:read':'agents:invoke,knowledge:read';
        echo card('إنشاء مفتاح باسم واضح',form_start('create_key','/?tab=keys').field_html('الاسم','name','text','الصيدلية - Production',true).
            select_html('الموقع','site_id',$map).field_html('الصلاحيات','scopes','text',$scopes).
            submit_btn('إنشاء المفتاح المشفر'));
    }
}
function render_integrations(array $a):void{
    $id=(int)$a['account_id'];$include=($_GET['archived']??'')==='1';
    $entries=all("SELECT * FROM integrations WHERE account_id=? ".($include?"":"AND status='active' ")."ORDER BY id DESC",[$id]);
    $rows=[];
    foreach($entries as $row){
      $name=e($row['name']);
      if(in_array($a['role'],['owner','admin'],true)&&!active_support())
        $name.=form_start('rename_secret','/?tab=integrations','class="inline-form"').'<input type="hidden" name="secret_type" value="integration"><input type="hidden" name="secret_id" value="'.$row['id'].'"><input class="tiny-input" name="name" value="'.e($row['name']).'"><button class="btn small">تعديل الاسم</button></form>';
      $rows[]=[$name,e($row['provider']),'<code>'.e($row['token_prefix']).'•••</code>',render_secret_controls('integration',$row,'integrations')];
    }
    echo card('التكاملات',
        '<p class="dim">كل تكامل له سر مستقل، ويمكن عرضه بعد تأكيد كلمة المرور، أو تدويره، أو إلغاؤه وإخفاؤه من القائمة النشطة.</p>'.
        '<p><a class="btn small" href="?tab=integrations'.($include?'':'&archived=1').'">'.($include?'عرض النشطة':'سجل التكاملات المعطلة').'</a></p>'.
        tbl(['الاسم','النوع','مفتاح الاتصال','الإجراءات'],$rows));
    if(in_array($a['role'],['owner','admin'],true)&&!active_support())
      echo '<div class="cols two">'.card('إنشاء Integration',
        form_start('new_integration','/?tab=integrations').field_html('اسم الاتصال','name','text','WHMCS Store / Laravel CRM',true).
        select_html('المزود','provider',['whmcs'=>'WHMCS','laravel'=>'Laravel','kashier'=>'Kashier','webhook'=>'Generic Webhook']).
        submit_btn('إنشاء الاتصال')).
        card('Webhook / API','<p>المصدر Laravel أو WHMCS يرسل أحداثاً موقعة. تفعيل الخدمات يتطلب إثبات دفع على السيرفر أو موافقة مالية. حالة الربط الحي لا تعتبر مكتملة دون اختبار.</p><code>POST /saas/console/hooks/index.php</code>').'</div>';
}
function render_domains(array $a):void{
    if($a['kind']!=='reseller')deny();
    $id=(int)$a['account_id'];
    $d=all('SELECT * FROM domains WHERE account_id=? ORDER BY id DESC',[$id]);
    echo card('النطاقات المرتبطة',tbl(['الدومين','TXT verification','الحالة'],array_map(fn($v)=>[e($v['hostname']),'<code>'.e($v['verification_token']).'</code>',pill($v['status'])],$d)));
    echo card('طلب ربط دومين',form_start('add_domain','/?tab=domains').field_html('الدومين أو Subdomain','hostname','text','voice.reseller.com',true).'<p class="info">يتطلب إثبات DNS وربط VirtualHost وشهادة TLS على سيرفر OnTrack. إضافة الدومين لا تفعّله تلقائيًا ولا يحتاج رفع ملفات عند الريسيلر.</p>'.submit_btn('إنشاء طلب الربط'));
}
function render_billing(array $a):void{
    $id=(int)$a['account_id'];
    $sub=one("SELECT p.*,s.status sub_status,s.ends_at FROM subscriptions s JOIN plans p ON p.id=s.plan_id WHERE s.account_id=? ORDER BY s.id DESC LIMIT 1",[$id]);
    $text=$sub?'<div class="plan-panel"><span class="eyebrow">YOUR CURRENT PLAN</span><h2>'.e($sub['name']).'</h2><p>'.e((string)$sub['call_quota']).' مكالمة متصلة شهرياً · '.e((string)$sub['agent_limit']).' AI · '.e((string)$sub['site_limit']).' مواقع</p><small>تنتهي الفترة: '.e($sub['ends_at']).'</small></div>':'<p>لا توجد باقة نشطة.</p>';
    echo card('اشتراك الحساب',$text);
    if(in_array($a['kind'],['platform','reseller'],true)){
        $orders=$a['kind']==='platform'?all('SELECT o.*,a.name account_name FROM orders o JOIN accounts a ON a.id=o.account_id ORDER BY o.id DESC LIMIT 60'):all('SELECT o.*,a.name account_name FROM orders o JOIN accounts a ON a.id=o.account_id WHERE a.parent_id=? ORDER BY o.id DESC LIMIT 60',[$id]);
        $rows=[];
        foreach($orders as $o){
            $act=pill($o['status']);
            if($o['status']==='pending_manual_review')$act.=form_start('manual_approve','/?tab=billing','class="inline-form"').'<input type="hidden" name="order_id" value="'.$o['id'].'"><input name="payment_reference" class="tiny-input" required minlength="8" placeholder="مرجع إثبات الدفع"><button class="btn small primary" type="submit" onclick="return confirm(\'هل تحققت فعلياً من وصول الدفع؟\')">اعتماد الدفع</button></form>';
            $rows[]=[e($o['account_name']),e($o['external_order_id']),$act,e($o['created_at'])];
        }
        echo card('طلبات الدفع والاعتماد',tbl(['العميل','رقم الطلب','الحالة/الإجراء','التاريخ'],$rows));
    }
    echo '<div class="info">مدفوعات Kashier الحقيقية لا تُفعّل من صفحة Success؛ التفعيل يتطلب Webhook موثّقًا أو موافقة دفع يدوي مسجلة.</div>';
}
function render_releases(array $a):void{
    $file='/home/appontrack/public_html/saas/mobile/updates/native/latest.json';
    $apk='';
    if(is_file($file)){
        $d=json_decode((string)file_get_contents($file),true);
        $payload=json_decode((string)($d['payload']??''),true);
        if(is_array($payload)&&is_string($payload['file']??null) && basename($payload['file'])===$payload['file'])
            $apk='/saas/mobile/updates/native/'.rawurlencode($payload['file']);
    }
    $brand=brand($a);
    echo card('تطبيق Android — '.e($brand['name']),'<p>تطبيق الاتصال بشريحة الهاتف، مع تفعيل الجهاز داخل الحساب. ملفات التطبيق والتحديثات مركزية على استضافة OnTrack.</p>'.($apk?'<a class="btn primary" href="'.e($apk).'">↓ تنزيل APK الموقّع</a>':'<div class="info">لم يتم العثور على رابط إصدار موثّق في السيرفر الحالي.</div>').'<p class="small dim">تحديثات الواجهة تتم عبر OTA بدون APK. تحديث أجزاء Android الأصلية يحتاج موافقة النظام على التثبيت.</p>');
}
function render_settings(array $a):void{
    $brand=brand($a);
    echo '<div class="cols two">';
    echo card('هوية حسابك','<div class="brand-preview"><div class="brand-icon">'.e(mb_substr($brand['name'],0,1)).'</div><div><h3>'.e($brand['name']).'</h3><p>ألوان الهوية الأساسية: أحمر / أسود / أبيض</p></div></div><p>المستخدم المباشر يرث OnTrack؛ المستخدم التابع يرث هوية الريسيلر بشكل آمن.</p>');
    if(in_array($a['kind'],['reseller','platform'],true)){
        echo card('تعديل الاسم والشعار',form_start('brand','/?tab=settings','enctype="multipart/form-data"').field_html('الاسم التجاري الظاهر','brand_name','text',$brand['name'],true).'<label class="field">شعار PNG/JPEG/WEBP (حتى 1MB)<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label>'.submit_btn('حفظ الهوية'));
    }else{
        echo card('إعدادات المستخدم','<p>اسم وشعار البرنامج متاحان من علامة الريسيلر الذي تتبع له، أو علامة OnTrack إن كنت عميلاً مباشراً.</p>');
    }
    echo '</div>';
}
