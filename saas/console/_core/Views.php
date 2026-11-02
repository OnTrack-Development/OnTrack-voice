<?php
declare(strict_types=1);

function form_start(string $action,string $return='/',string $extra=''):string{
    $attrs=trim($extra);
    $classes='stack';
    if(preg_match('/\\bclass="([^"]*)"/',$attrs,$match)){
        $classes.=' '.$match[1];
        $attrs=(string)preg_replace('/\\s*class="[^"]*"/','',$attrs,1);
    }
    return '<form method="post" class="'.e(trim($classes)).'" '.trim($attrs).'><input type="hidden" name="csrf" value="'.e(csrf()).'"><input type="hidden" name="action" value="'.e($action).'"><input type="hidden" name="return" value="'.e($return).'">';
}
/** Small intentional edit disclosure; never render an editable input over a row title. */
function row_rename(string $action,string $return,string $field,int $id,string $name,string $secretType=''):string{
    $hidden='<input type="hidden" name="'.e($field).'" value="'.$id.'">';
    if($secretType!=='')$hidden.='<input type="hidden" name="secret_type" value="'.e($secretType).'">';
    return '<details class="row-rename"><summary>تعديل الاسم</summary>'.
        form_start($action,$return,'class="row-rename-form"').$hidden.
        '<label class="field">الاسم<input name="name" value="'.e($name).'" maxlength="120" required></label>'.
        '<button class="btn small primary" type="submit">حفظ الاسم</button></form></details>';
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
    if($type==='platform'&&console_view_mode()==='user')$type='customer';
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
        'flows'=>['◇','السيناريوهات'],'answering'=>['☷','إعدادات الرد'],
        'devices'=>['▣','الأجهزة والشرائح'],'calls'=>['☏','سجل المكالمات'],
        'contacts'=>['♧','جهات الاتصال'],'campaigns'=>['▤','الحملات'],
        'billing'=>['▣','الفوترة'],'releases'=>['↓','تطبيق Android'],
        'settings'=>['⚙','إعدادات المنصة']
    ];
    if($type==='reseller')return [
        'overview'=>['⌂','نظرة عامة'],'accounts'=>['♧','عملائي'],
        'plans'=>['◫','باقات العملاء'],'voices'=>['♫','الأصوات المتاحة'],
        'staff'=>['♙','الفريق والأدوار'],'domains'=>['◎','الدومينات والبراند'],
        'sites'=>['◈','المواقع'],'agents'=>['✧','الإيجنتات'],
        'flows'=>['◇','السيناريوهات'],'answering'=>['☷','إعدادات الرد'], 'devices'=>['▣','الأجهزة'],
        'integrations'=>['⇄','Laravel / WHMCS'],
        'keys'=>['⌘','مفاتيح API'],'billing'=>['▣','الاشتراكات'],
        'releases'=>['↓','تطبيق Android'],'settings'=>['⚙','هوية الريسيلر']
    ];
    return [
        'overview'=>['⌂','الرئيسية'],'sites'=>['◈','مواقعي'],
        'agents'=>['✧','AI Agent Builder'],'flows'=>['◇','السيناريوهات'],
        'answering'=>['☷','إعدادات الرد'],'campaigns'=>['▤','الحملات'],
        'calls'=>['☏','سجل المكالمات'],'contacts'=>['♧','جهات الاتصال'],'devices'=>['▣','الأجهزة والخطوط'],
        'voices'=>['♫','الأصوات'],'staff'=>['♙','الموظفون'],
        'keys'=>['⌘','مفاتيح API'],'integrations'=>['⇄','التكاملات'],'billing'=>['▣','الباقة والرصيد'],
        'releases'=>['↓','Android'],'settings'=>['⚙','الإعدادات']
    ];
}
function layout_open(array $a,string $tab):void{
    $u=me();$brand=brand($a);$items=nav_entries((string)$a['kind']);$heading=$items[$tab][1]??'الرئيسية';
    $role=console_view_mode($a)==='user'?'USER WORKSPACE':match($a['kind']){'platform'=>'ADMIN CONTROL','reseller'=>'RESELLER HUB',default=>'USER WORKSPACE'};
    $support=active_support()!==null;
    header('Content-Type: text/html; charset=utf-8');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Cache-Control: private,no-store');
    // A stale CSS/JS cache must not render new server HTML with the old layout.
    $uiVersion=substr(hash_file('sha256',__DIR__.'/../assets/css/app.css'),0,12);
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#121215"><title>'.e($heading).' | '.e($brand['name']).'</title><link rel="stylesheet" href="/saas/console/assets/css/app.css?v='.$uiVersion.'"><script src="/saas/console/assets/js/app.js?v='.$uiVersion.'" defer></script><script src="/saas/console/assets/js/flow-graph.js?v='.$uiVersion.'" defer></script><script src="/saas/console/assets/js/qrcode.js?v='.$uiVersion.'" defer></script><script src="/saas/console/assets/js/pairing-qr.js?v='.$uiVersion.'" defer></script></head><body>';
    echo '<div class="app-shell"><aside class="sidebar" id="mainNavigation" aria-label="القائمة الرئيسية"><button type="button" class="nav-close" id="navClose" aria-label="إغلاق القائمة">×</button><a href="/saas/console/" class="brand"><span class="brand-logo">';
    if(!empty($brand['logo'])) echo '<img src="/saas/console/brand-logo" alt="">';else echo '<b>'.e(mb_substr($brand['name'],0,1)).'</b>';
    echo '</span><span class="brand-lines"><strong>'.e($brand['name']).'</strong><small>'.e($role).'</small></span></a>';
    echo '<nav class="nav-list" aria-label="أقسام المنصة">';
    $workspace=['overview','agents','flows','answering','calls','campaigns','contacts','devices','sites'];
    $platform=['accounts','plans','billing','staff','domains'];
    $settings=['voices','integrations','keys','releases','settings'];
    $groups=['workspace'=>['مساحة العمل',$workspace],'platform'=>['الحسابات والإدارة',$platform],
        'system'=>['الإعدادات والتكاملات',$settings]];
    foreach($groups as $group=>$groupData){
        $visible=array_intersect_key($items,array_flip($groupData[1]));
        if(!$visible)continue;
        $isCurrent=array_key_exists($tab,$visible);
        echo '<details class="nav-group"'.($isCurrent?' open':'').'><summary class="nav-group-title">'.e($groupData[0]).'<span aria-hidden="true">⌄</span></summary><div class="nav-group-links">';
        foreach($groupData[1] as $key){
            if(!isset($visible[$key]))continue;
            $entry=$items[$key];$active=$tab===$key?' selected':'';
            echo '<a href="/saas/console/?tab='.e($key).'" class="nav-link'.$active.'"'.($active?' aria-current="page"':'').'><span class="nav-icon">'.e($entry[0]).'</span><span>'.e($entry[1]).'</span></a>';
        }
        echo '</div></details>';
    }
    echo '</nav><div class="side-footer"><span class="live-dot"></span> OnTrack Voice <small>Business communication platform</small></div></aside><button type="button" class="nav-backdrop" id="navBackdrop" aria-label="إغلاق القائمة" tabindex="-1"></button><div class="main-area">';
    echo '<header class="topbar"><button type="button" class="icon-button nav-toggle" id="navToggle" aria-controls="mainNavigation" aria-expanded="false" aria-label="فتح القائمة">☰</button><div class="headline"><span class="eyebrow">'.e($role).'</span><h1>'.e($heading).'</h1></div><div class="header-actions">';
    $members=memberships((int)$u['id']);
    if(count($members)>1&&!$support){
        echo form_start('switch');$options=[];
        foreach($members as $m)$options[$m['account_id']]=$m['name'].' · '.$m['kind'];
        echo select_html('المساحة','account_id',$options).submit_btn('تبديل');
    }
    if($a['kind']==='platform'&&in_array($a['role'],['owner','admin'],true)&&!$support){
        $targetView=console_view_mode($a)==='user'?'admin':'user';
        echo form_start('set_view','','class="inline-form"').'<input type="hidden" name="view" value="'.e($targetView).'"><button type="submit" class="btn small primary">'.($targetView==='user'?'مساحة المستخدم':'لوحة الأدمن').'</button></form>';
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
    if($v=flash('secret')){
        // Selectable input works without Clipboard API; no network or external scripts.
        $visible=preg_match('/(?:otv_|otint_)[A-Za-z0-9_-]{20,140}/',(string)$v,$match)?$match[0]:(string)$v;
        echo '<div class="alert secret"><b>مفتاح الاتصال:</b><div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">';
        echo '<input type="text" readonly dir="ltr" spellcheck="false" autocomplete="off" data-copy-source value="'.e($visible).'" style="flex:1;min-width:220px;font-family:monospace">';
        echo '<button type="button" class="btn small primary" data-copy-secret>نسخ المفتاح</button></div>';
        echo '<small class="dim" data-copy-result role="status">اضغط على الحقل لتحديد المفتاح يدويًا إذا لزم.</small></div>';
    }
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
    if($notice=flash('success'))echo '<div class="alert success">'.e($notice).'</div>';
    echo '<form method="post" action="/saas/console/login" class="stack">'.'<input type="hidden" name="csrf" value="'.e(csrf()).'"><label class="field">البريد الإلكتروني<input required type="email" name="email" autocomplete="username" placeholder="name@company.com"></label><label class="field">كلمة المرور<div class="password-wrap"><input required type="password" name="password" autocomplete="current-password" id="loginPassword" placeholder="••••••••••"><button type="button" class="password-toggle" data-toggle-password>إظهار</button></div></label><button class="btn primary wide" type="submit">تسجيل الدخول <span>←</span></button></form><p><a href="/saas/console/register">إنشاء حساب شركة جديد</a></p><p class="login-foot">تجربة أولية · يتم تحديد الصلاحيات حسب الحساب</p></div></body></html>';
}
function render_overview(array $a):void{
    $id=(int)$a['account_id'];$kind=$a['kind'];
    if($kind==='platform'&&console_view_mode($a)==='user')$kind='customer';
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
        $support.=row_rename('edit_account','/?tab=accounts','account_id',(int)$r['id'],(string)$r['name']);
        $support.=form_start('set_account_status','/?tab=accounts','class="inline-form"').'<input type="hidden" name="account_id" value="'.(int)$r['id'].'"><input type="hidden" name="status" value="'.($r['status']==='active'?'suspended':'active').'"><button class="btn small danger">'.($r['status']==='active'?'تعليق':'تفعيل').'</button></form>';
        $cells[]=[e($r['name']).'<small class="block dim">'.e($r['slug']).'</small>',pill((string)$r['kind']),e((string)($r['parent_id']??'مباشر')),e((string)$r['seats']),pill((string)$r['status']),$support];
    }
    echo card('الحسابات التابعة',tbl(['الاسم','النوع','الريسيلر','الأعضاء','الحالة','إدارة'],$cells));
}
function render_plans(array $a):void{
    $type=(string)$a['kind'];$id=(int)$a['account_id'];
    $list=$type==='platform'?all('SELECT * FROM plans ORDER BY kind,id'):all("SELECT * FROM plans WHERE kind='user' AND status='active' ORDER BY id");
    $rows=[];
    foreach($list as $p){
        $rows[]=[
            '<strong>'.e((string)$p['name']).'</strong>',
            pill((string)$p['kind']),
            e((string)$p['call_quota']),
            e((string)$p['site_limit']).' / '.e((string)$p['agent_limit']),
            number_format((int)$p['price_minor']/100,2).' ج.م',
            number_format((int)($p['overage_minor']??0)/100,2).' ج.م / مكالمة',
            pill((string)$p['status'])
        ];
    }
    echo card('الباقات والأسعار',
        '<p class="dim">الأسعار بالجنيه المصري. كل باقة تحتوي عدد مكالمات شهريًا، وبعد تجاوز العدد يظهر سعر المكالمة الإضافية. لا يتم تحصيل أي مبلغ تلقائيًا قبل اعتماد نظام قياس وفوترة فعلي.</p>'.
        tbl(['الباقة','النوع','المكالمات/شهر','مواقع / AI','سعر الباقة','سعر المكالمة الزائدة','الحالة'],$rows));
    if($type==='platform'){
        echo '<section class="card"><div class="card-head"><h3>إدارة الباقات</h3></div><p class="dim">افتح الباقة التي تريد تعديلها، بدلًا من عرض نماذج طويلة لكل الباقات.</p><div class="plan-editors">';
        foreach($list as $plan){
            if($plan['status']==='archived')continue;
            echo '<details class="plan-editor"><summary><strong>'.e((string)$plan['name']).'</strong><span>'.number_format((int)$plan['price_minor']/100,2).' ج.م / 30 يوم</span><span class="edit-chevron" aria-hidden="true">⌄</span></summary><div class="plan-editor-body">'.
                form_start('edit_plan','/?tab=plans','class="plan-form"').
                '<input type="hidden" name="plan_id" value="'.(int)$plan['id'].'"><div class="form-grid">'.
                '<label class="field">اسم الباقة<input name="name" maxlength="120" value="'.e((string)$plan['name']).'" required></label>'.
                '<label class="field">المكالمات الشهرية<input name="calls" type="number" min="0" value="'.(int)$plan['call_quota'].'" required></label>'.
                '<label class="field">المواقع<input name="sites" type="number" min="0" value="'.(int)$plan['site_limit'].'" required></label>'.
                '<label class="field">الإيجنتات<input name="agents" type="number" min="0" value="'.(int)$plan['agent_limit'].'" required></label>'.
                '<label class="field">الموظفون<input name="staff" type="number" min="0" value="'.(int)$plan['staff_limit'].'" required></label>'.
                '<label class="field">حد عملاء الريسيلر<input name="customers" type="number" min="0" value="'.(int)$plan['reseller_customers'].'" required></label>'.
                '<label class="field">سعر الباقة (قرش)<input name="price" type="number" min="0" max="1000000000" step="1" value="'.(int)$plan['price_minor'].'" required><small>100 قرش = 1 جنيه</small></label>'.
                '<label class="field">سعر كل مكالمة بعد الحد (قرش)<input name="overage" type="number" min="0" max="10000000" step="1" value="'.(int)($plan['overage_minor']??0).'" required><small>0 = بدون تكلفة إضافية</small></label>'.
                '</div><div class="plan-actions"><button class="btn primary" type="submit">حفظ تغييرات الباقة</button></div></form>'.
                form_start('archive_plan','/?tab=plans','class="plan-archive"').
                '<input type="hidden" name="plan_id" value="'.(int)$plan['id'].'">'.
                '<button class="btn small danger" type="submit" onclick="return confirm(\'إيقاف بيع الباقة؟ الاشتراكات الحالية ستظل محفوظة\')">إيقاف بيع الباقة</button></form>'.
                '</div></details>';
        }
        echo '</div></section>';
        echo card('إضافة باقة جديدة',
            form_start('create_plan','/?tab=plans').
            '<div class="form-grid">'.
            select_html('نوع الباقة','kind',['reseller'=>'ريسيلر','user'=>'مستخدم']).
            field_html('اسم الباقة','name','text','اسم الباقة',true).
            field_html('المكالمات الشهرية','calls','number','1000',true).
            field_html('المواقع','sites','number','1',true).
            field_html('الإيجنتات','agents','number','1',true).
            field_html('الموظفون','staff','number','3',true).
            field_html('حد عملاء الريسيلر','customers','number','10',true).
            '<label class="field">سعر الباقة (قرش)<input name="price" type="number" min="0" max="1000000000" step="1" value="0" required></label>'.
            '<label class="field">سعر المكالمة الزائدة (قرش)<input name="overage" type="number" min="0" max="10000000" step="1" value="0" required></label>'.
            '</div>'.submit_btn('إنشاء الباقة'));
    }elseif($type==='reseller'){
        $customers=all("SELECT id,name FROM accounts WHERE parent_id=? AND kind='customer'",[$id]);
        $co=[];$po=[];
        foreach($customers as $c)$co[$c['id']]=$c['name'];
        foreach($list as $p)$po[$p['id']]=$p['name'].' — '.$p['call_quota'].' مكالمة';
        if($co&&$po)echo card('تخصيص باقة',form_start('assign_plan','/?tab=plans').select_html('الحساب','customer_id',$co).select_html('الباقة','plan_id',$po).submit_btn('تعيين الباقة'));
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
                select_html('تغيير الدور','role',['manager'=>'مدير','operator'=>'مشغّل','finance'=>'مالية','support'=>'دعم','viewer'=>'مشاهد']).'<button class="btn small primary">حفظ</button></form></details>';
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
      '<details class="row-rename"><summary>تعديل بيانات الموقع</summary>'.form_start('update_site','/?tab=sites','class="row-rename-form"').'<input type="hidden" name="site_id" value="'.$s['id'].'"><label class="field">الاسم<input name="name" value="'.e($s['name']).'"></label><label class="field">الدومين<input name="domain" value="'.e($s['domain']).'"></label><button class="btn small primary">حفظ الموقع</button></form></details>'.
      form_start('archive_site','/?tab=sites','class="inline-form"').'<input type="hidden" name="site_id" value="'.$s['id'].'"><button class="btn small danger" onclick="return confirm(\'أرشفة الموقع وإيجنتاته؟\')">أرشفة</button></form>'];
    echo card('مواقعي',tbl(['الموقع','الدومين','الحالة','التشغيل'],$cells));
    if(allowed('sites.write'))echo card('موقع جديد',form_start('add_site','/?tab=sites').field_html('اسم الموقع','name','text','الصيدلية / الملابس / الدعم',true).field_html('الدومين','domain','text','store.example.com').submit_btn('إضافة موقع مستقل'));
    echo '</div>';
}
function render_agents(array $a):void{
    require_once __DIR__.'/FlowTemplates.php';
    require_once __DIR__.'/AgentAnswerPolicy.php';
    $id=(int)$a['account_id'];$sites=all("SELECT id,name FROM sites WHERE account_id=? AND status='active' ORDER BY id",[$id]);
    $map=[];foreach($sites as $s)$map[$s['id']]=$s['name'];
    $selected=selected_site($id);
    $agents=$selected?all("SELECT * FROM agents WHERE account_id=? AND site_id=? AND status!='archived' ORDER BY id DESC",[$id,$selected['id']]):[];
    echo '<div class="section-line agents-toolbar"><div class="agents-heading"><span class="eyebrow">AGENT WORKSPACE</span><h2>الإيجنتات والسيناريوهات</h2><p>اختر موقعًا، افتح الإيجنت، ثم احفظ أو انشر إعداداته من محرر واحد.</p></div><div class="site-picker" aria-label="اختيار الموقع">';
    foreach($sites as $site)echo '<a class="chip-link '.($selected&&$site['id']==$selected['id']?'chosen':'').'" href="?tab=agents&site='.$site['id'].'">'.e($site['name']).'</a>';
    echo '</div></div>';
    if(!$sites){echo card('ابدأ بموقع','أنشئ موقعاً من قائمة المواقع قبل إضافة الإيجنت.');return;}
    $templateOptions=[];
    foreach(flow_template_catalog() as $key=>$tpl)$templateOptions[$key]=$tpl['category'].' — '.$tpl['title'];
    if(allowed('agents.write'))echo '<details class="agent-create"><summary class="btn primary">إنشاء إيجنت جديد</summary>'.card('إيجنت جديد',form_start('add_agent','/?tab=agents').'<div class="form-grid">'.
        field_html('اسم الإيجنت','name','text','مثال: مساعد الحجوزات',true).
        select_html('الموقع','site_id',$map).
        select_html('الصوت','voice',array_column(all("SELECT code,name FROM voices WHERE ready=1 AND status='active' AND id IN (SELECT voice_id FROM voice_grants WHERE account_id=?) ORDER BY name",[$id]),'name','code')?:['google.aoede'=>'Aoede']).
        '</div>'.select_html('مكتبة السيناريوهات الجاهزة','flow_template',$templateOptions).
        '<label class="field">تعليمات الإيجنت<textarea name="instructions" rows="3" maxlength="5000" placeholder="شخصية الإيجنت ودوره وطريقة الرد"></textarea></label><label class="field">Knowledge Base<textarea name="knowledge" rows="3" maxlength="20000" placeholder="معلومات هذا الموقع فقط"></textarea></label>'.
        '<div class="agent-publish-actions"><button class="btn primary" type="submit" name="publish_now" value="1">إنشاء ونشر السيناريو مباشرة</button><button class="btn" type="submit">إنشاء مسودة للتعديل</button></div></form>').'</details>';
    $templateGraphs=[];
    foreach(flow_template_catalog() as $key=>$tpl)$templateGraphs[]=['id'=>$key,'title'=>$tpl['category'].' — '.$tpl['title'],
        'description'=>$tpl['description'],'graph'=>flow_template_graph($key)];
    echo '<script type="application/json" id="ontrack-flow-templates">'.
        json_encode($templateGraphs,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR).'</script>';
    foreach($agents as $agent){
        $flow=e((string)$agent['flow_json']);
        $answer=answer_policy_for_agent($id,(int)$agent['id']);
        $version=published_agent_flow($id,(int)$agent['site_id'],(int)$agent['id']);
        $body='<div class="agent-summary"><span class="pill neutral">'.e($agent['voice']).'</span>'.pill($agent['status']).'<span class="dim">موقع: '.e($selected['name']).'</span>'.($version?'<span class="pill neutral">المنشور v'.(int)$version['revision'].'</span>':'<span class="pill neutral">غير منشور</span>').'</div>';
        if(allowed('agents.write')){$body.=form_start('update_agent','/?tab=agents&site='.$selected['id'],'class="stack agent-form"').'<input type="hidden" name="agent_id" value="'.$agent['id'].'">'.
           '<label class="field">اسم الإيجنت<input name="name" maxlength="140" required value="'.e($agent['name']).'"></label>'.
           '<label class="field">الصوت<select name="voice">';
           foreach(all("SELECT code,name FROM voices WHERE status='active' AND ready=1 AND id IN (SELECT voice_id FROM voice_grants WHERE account_id=?)",[$id]) as $opt)
                $body.='<option value="'.e($opt['code']).'" '.($opt['code']===$agent['voice']?'selected':'').'>'.e($opt['name']).'</option>';
           $body.='<option value="'.e($agent['voice']).'" selected>'.e($agent['voice']).' (الحالي)</option></select></label>'.
           '<label class="field">الشخصية والتعليمات<textarea name="instructions" rows="4" maxlength="5000">'.e($agent['instructions']).'</textarea></label><label class="field">معرفة الموقع<textarea name="knowledge" rows="4" maxlength="20000">'.e($agent['knowledge']).'</textarea></label>'.
           '<div class="flow-head" id="answer-settings"><strong>إعدادات الرد على المكالمات</strong><small>تنتقل مع النسخة المنشورة إلى الشريحة عند جاهزية المحرك الجديد</small></div>'.
           '<div class="form-grid"><label class="field">التأخير قبل الرد (ثانية)<input name="answer_delay_seconds" type="number" min="0" max="30" value="'.(int)$answer['answer_delay_seconds'].'" required></label>'.
           '<label class="field">الحد الأقصى للمكالمة (دقيقة)<input name="max_call_minutes" type="number" min="1" max="30" value="'.(int)$answer['max_call_minutes'].'" required></label>'.
           '<label class="field">لغة وأسلوب الرد<select name="response_style"><option value="egyptian_short"'.($answer['response_style']==='egyptian_short'?' selected':'').'>مصري مختصر</option><option value="formal_ar"'.($answer['response_style']==='formal_ar'?' selected':'').'>عربي رسمي</option><option value="english"'.($answer['response_style']==='english'?' selected':'').'>إنجليزي</option><option value="bilingual"'.($answer['response_style']==='bilingual'?' selected':'').'>عربي وإنجليزي</option></select></label>'.
           '<label class="field">عند تعذر الاستكمال<select name="fallback"><option value="human"'.($answer['fallback']==='human'?' selected':'').'>تحويل لموظف</option><option value="end"'.($answer['fallback']==='end'?' selected':'').'>إنهاء بأدب</option></select></label></div>'.
           '<label class="field"><input type="checkbox" name="auto_answer"'.($answer['auto_answer']?' checked':'').'> الرد الآلي عند تفعيل محرك الاتصال الجديد</label>'.
           '<div class="flow-head" id="scenario-editor"><strong>Agent Flow Builder</strong><small>عقد وأسئلة وشروط متسلسلة</small></div><div class="flow-builder" data-flow-editor><textarea name="flow_json" class="flow-source" rows="7" maxlength="16000">'.$flow.'</textarea></div>'.
           '<div class="agent-publish-actions"><button class="btn primary" type="submit" name="publish_now" value="1">حفظ ونشر Flow مباشرة</button><button class="btn" type="submit">حفظ كمسودة</button></div></form>'.
                    form_start('archive_agent','/?tab=agents&site='.$selected['id'],'class="inline-form"').'<input type="hidden" name="agent_id" value="'.$agent['id'].'"><button class="btn small danger" onclick="return confirm(\'أرشفة الإيجنت؟\')">أرشفة الإيجنت</button></form>';
        } else $body.='<p>'.e($agent['instructions']).'</p>';
        echo '<details class="agent-editor" id="agent-'.(int)$agent['id'].'"'.((string)($_GET['agent']??'')===(string)$agent['id']?' open':'').'><summary><strong>'.e($agent['name']).'</strong><span>'.pill((string)$agent['status']).'</span><span class="dim">فتح إعدادات الإيجنت والسيناريو</span></summary>'.card('إعدادات الإيجنت',$body).'</details>';
    }
}

function render_flows(array $a):void {
    $id=(int)$a['account_id'];
    $agents=all("SELECT ag.id,ag.name,ag.site_id,s.name site_name FROM agents ag JOIN sites s ON s.id=ag.site_id
        WHERE ag.account_id=? AND ag.status!='archived' AND s.status='active' ORDER BY ag.id DESC",[$id]);
    echo '<p class="info">مكتبة قوالب عربية قابلة للتعديل؛ اختر الإيجنت ثم افتح محرره لتطبيق القالب أو الاختبار النصي أو النشر المباشر.</p>';
    $rows=[];
    foreach($agents as $agent){
        $version=published_agent_flow($id,(int)$agent['site_id'],(int)$agent['id']);
        $url='/?tab=agents&site='.(int)$agent['site_id'].'#agent-'.(int)$agent['id'];
        $rows[]=[e($agent['name']),e($agent['site_name']),$version?'v'.(int)$version['revision']:'مسودة',
            '<a class="btn small primary" href="'.e($url).'">فتح السيناريو وتعديله ←</a>'];
    }
    echo card('السيناريوهات المنشورة والمسودات',tbl(['الإيجنت','الموقع','آخر إصدار','التعديل والاختبار'],$rows));
    if(!$agents)echo '<p><a class="btn primary" href="/saas/console/?tab=agents">إنشاء أول إيجنت من قوالب السيناريوهات</a></p>';
}
function render_answering(array $a):void {
    $id=(int)$a['account_id'];
    $agents=all("SELECT ag.id,ag.name,ag.site_id,s.name site_name FROM agents ag JOIN sites s ON s.id=ag.site_id
        WHERE ag.account_id=? AND ag.status!='archived' AND s.status='active' ORDER BY ag.id DESC",[$id]);
    echo '<p class="info">إعدادات الرد محفوظة لكل إيجنت بشكل مستقل؛ لا تصل للشريحة إلا في نسخة Flow المنشورة. ربط المحرك الصوتي الحقيقي على SIM ما زال يحتاج اختبارًا.</p>';
    $rows=[];
    foreach($agents as $agent){
        $policy=answer_policy_for_agent($id,(int)$agent['id']);
        $url='/?tab=agents&site='.(int)$agent['site_id'].'#agent-'.(int)$agent['id'];
        $rows[]=[e($agent['name']),$policy['auto_answer']?'مُعدّ للرد الآلي':'يدوي',
            (int)$policy['answer_delay_seconds'].' ثوانٍ',
            '<a class="btn small primary" href="'.e($url).'">تعديل إعدادات الرد ←</a>'];
    }
    echo card('إعدادات الرد بحسب الإيجنت',tbl(['الإيجنت','السياسة','التأخير','التعديل'],$rows));
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
        $action='<details class="row-rename"><summary>تعديل جهة الاتصال</summary>'.form_start('edit_contact','/?tab=contacts','class="row-rename-form"').
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
    $id=(int)$a['account_id'];
    $keyword=trim((string)($_GET['q']??''));
    if(mb_strlen($keyword)>80)$keyword=mb_substr($keyword,0,80);
    $requestedStatus=(string)($_GET['status']??'all');
    $statuses=['all','answered','missed','failed','completed','busy','no_answer'];
    $filter=in_array($requestedStatus,$statuses,true)?$requestedStatus:'all';
    $params=[$id];
    $where='account_id=?';
    if($keyword!==''){
        $where.=" AND (name LIKE ? OR phone LIKE ?)";
        $term='%'.str_replace(['%','_'],['\\%','\\_'],$keyword).'%';
        $params[]=$term;$params[]=$term;
    }
    if($filter!=='all'){$where.=' AND status=?';$params[]=$filter;}
    $list=all('SELECT id,name,phone,status,duration_seconds,is_demo,created_at FROM calls WHERE '.$where.' ORDER BY id DESC LIMIT 200',$params);
    $requestedId=max(0,(int)($_GET['call']??0));
    $selected=null;
    foreach($list as $item){if((int)$item['id']===$requestedId)$selected=$item;}
    if($selected===null)$selected=$list[0]??null;
    $stats=one('SELECT COUNT(*) n FROM calls WHERE account_id=?',[$id]);
    echo '<section class="calls-workspace" aria-label="صندوق المكالمات">';
    echo '<header class="calls-top"><div><span class="eyebrow">ONTRACK VOICE</span><h2>صندوق المكالمات</h2>'.
        '<p>المكالمات الواردة والصادرة وسجل نتائجها. التسجيلات والتفريغ النصي يظهران فقط بعد وصولهما من الهاتف.</p></div>'.
        '<span class="calls-total">'.(int)($stats['n']??0).' مكالمة مسجّلة</span></header>';
    echo '<form class="calls-filters" action="/saas/console/" method="get" role="search"><input type="hidden" name="tab" value="calls">'.
        '<label class="field">بحث بالاسم أو الرقم<input name="q" value="'.e($keyword).'" placeholder="اسم العميل أو الرقم" maxlength="80"></label>'.
        '<label class="field">الحالة<select name="status">';
    $labels=['all'=>'كل الحالات','answered'=>'تم الرد','missed'=>'فائتة','failed'=>'فشلت','completed'=>'مكتملة','busy'=>'مشغول','no_answer'=>'لم يرد'];
    foreach($labels as $key=>$name)echo '<option value="'.e($key).'"'.($filter===$key?' selected':'').'>'.e($name).'</option>';
    echo '</select></label><button class="btn primary" type="submit">بحث</button></form>';
    echo '<div class="calls-inbox"><div class="calls-list" aria-label="سجل المكالمات">';
    if(!$list)echo '<div class="calls-empty">لا توجد مكالمات مطابقة للبحث. البيانات هتظهر هنا بعد وصول سجل مكالمات حقيقي من الجهاز.</div>';
    foreach($list as $c){
        $chosen=$selected&&(int)$selected['id']===(int)$c['id'];
        $url='/?tab=calls&call='.(int)$c['id'].'&status='.rawurlencode($filter).'&q='.rawurlencode($keyword);
        $letter=mb_substr((string)($c['name']?:$c['phone']),0,1);
        echo '<a class="call-contact'.($chosen?' active':'').'" href="'.e($url).'"'.($chosen?' aria-current="true"':'').'><span class="call-avatar">'.e($letter).'</span>'.
            '<span class="call-contact-main"><strong>'.e((string)($c['name']?:'رقم غير مسجل')).'</strong><small dir="ltr">'.e((string)$c['phone']).'</small></span>'.
            '<span class="call-contact-meta"><time>'.e((string)$c['created_at']).'</time>'.pill((string)$c['status']).'</span></a>';
    }
    echo '</div><section class="call-detail" aria-label="تفاصيل المكالمة">';
    if($selected){
        echo '<div class="call-detail-header"><span class="call-avatar large">'.e(mb_substr((string)($selected['name']?:$selected['phone']),0,1)).'</span>'.
            '<h3>'.e((string)($selected['name']?:'رقم غير مسجل')).'</h3><p dir="ltr">'.e((string)$selected['phone']).'</p></div>'.
            '<div class="call-facts"><div><span>الحالة</span>'.pill((string)$selected['status']).'</div>'.
            '<div><span>المدة</span><strong>'.e(fmt_duration((int)$selected['duration_seconds'])).'</strong></div>'.
            '<div><span>التاريخ</span><strong>'.e((string)$selected['created_at']).'</strong></div>'.
            '<div><span>المصدر</span><strong>'.((int)$selected['is_demo']===1?'بيانات تجربة':'سجل الجهاز').'</strong></div></div>'.
            '<div class="call-transcript-empty"><strong>المحادثة والملخص</strong><p>لا يوجد تفريغ نصي أو تسجيل مرتبط بهذا السجل. مش هنعرض رسائل أو تسجيلات وهمية.</p></div>';
    }else echo '<div class="calls-empty">اختار مكالمة لعرض التفاصيل بعد مزامنتها من الشريحة.</div>';
    echo '</section></div></section>';
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
    echo '<div class="info">الربط الجديد لكل شريحة على حدة: اختر الشركة والإيجنت، أنشئ QR، ثم راقب آخر اتصال للجهاز. جلسة Google Live تحتاج مزوّدًا معتمدًا واختبار صوت فعلي.</div>';
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
          '<label class="field">Google API Key (اختياري لو متسجّل في إعدادات الأدمن)<input type="password" name="google_api_key" autocomplete="off" minlength="16" placeholder="سيُستخدم مفتاح Google المحفوظ إن وجد"></label>'.
          '<p class="dim">استيراد الأصوات الحقيقية هيستخدم المفتاح المحفوظ. لو حساب Google لا يسمح بالـVoices API، هيظهر الكتالوج الأساسي مع توضيح المشكلة؛ مش هنقول المفتاح خاطئ.</p>'.
          submit_btn('مزامنة أصوات Google المتاحة'));
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
    $controls.=form_start('reveal_secret',$return,'class="inline-form"').'<input type="hidden" name="secret_type" value="'.e($type).'"><input type="hidden" name="secret_id" value="'.$row['id'].'"><button class="btn small" type="submit">عرض ونسخ المفتاح</button></form>';
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
           $name.=row_rename('rename_secret','/?tab=keys','secret_id',(int)$k['id'],(string)$k['name'],'api');
        $rows[]=[$name,'<code>'.e($k['prefix']).'•••</code>',e($k['site_name']??'الحساب'),e($k['scopes']),render_secret_controls('api',$k,'keys')];
    }
    echo card('مفاتيح API',
        '<p class="dim">المفاتيح الجديدة مشفرة بمفتاح خاص خارج موقع الويب. عرضها مجددًا متاح للمالك أو الأدمن المسجّل، مع تسجيل كل عرض في سجل التدقيق. المفاتيح القديمة قبل هذه الإضافة تحتاج تدويرًا لأنها محفوظة Hash فقط.</p>'.
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
        $name.=row_rename('rename_secret','/?tab=integrations','secret_id',(int)$row['id'],(string)$row['name'],'integration');
      $rows[]=[$name,e($row['provider']),'<code>'.e($row['token_prefix']).'•••</code>',render_secret_controls('integration',$row,'integrations')];
    }
    echo card('التكاملات',
        '<p class="dim">كل تكامل له سر مستقل. المالك أو الأدمن يقدر يعرضه وينسخه، وكل عرض متسجل في Audit Log.</p>'.
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

    if(in_array($a['kind'],['reseller','customer'],true)
       &&in_array($a['role'],['owner','admin'],true)&&!active_support()){
        $plans=all("SELECT id,name,price_minor,call_quota FROM plans WHERE kind=? AND status='active' ORDER BY price_minor,id",
            [$a['kind']==='reseller'?'reseller':'user']);
        $choices=[];foreach($plans as $plan){
            if((int)$plan['price_minor']<=0)continue;
            $choices[(string)$plan['id']]=$plan['name'].' — '.number_format((int)$plan['price_minor']/100,2).' ج.م / 30 يوم';
        }
        if($choices)echo card('شراء أو تجديد باقة',
            form_start('checkout_plan','/?tab=billing').
            select_html('اختر باقة','plan_id',$choices).
            '<p class="dim">الدفع عبر صفحة Kashier الآمنة. الاشتراك لا يتفعل إلا بعد وصول تأكيد دفع موقّع على السيرفر.</p>'.
            submit_btn('متابعة الدفع الآمن'));
    }
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
    $mfaActive=mfa_gate_required((int)me()['id']);
    echo card('التحقق بخطوتين',
        $mfaActive
        ?'<p>المصادقة مفعّلة لهذا الحساب. عند تسجيل الدخول ستحتاج كود تطبيق المصادقة.</p><span class="pill ok">مفعّل ومؤكد</span>'
        :'<p>غير مفعّل. تسجيل الدخول يعمل بكلمة المرور، ويمكنك اختيار تفعيله بعد مسح QR وتأكيد كود من التطبيق.</p><a class="btn primary" href="/saas/console/mfa/setup">ربط تطبيق المصادقة وعرض QR</a>');
    if($a['kind']==='platform'&&in_array($a['role'],['owner','admin'],true)&&!active_support()){
        $provider=one("SELECT name FROM integrations WHERE account_id=? AND provider='google_live' AND status='active' ORDER BY id DESC LIMIT 1",[$a['account_id']]);
        echo card('محرك الصوت الجديد — Google Gemini Live',
            '<p>الربط الصوتي على الشريحة يعتمد على مفتاح Gemini Live ساري، ويُخزّن مشفرًا على السيرفر ولا يظهر داخل التطبيق. الاختبار الحالي يتأكد من إصدار رمز مؤقت حقيقي من Google.</p>'.
            '<p class="info">حالة المزوّد: '.($provider?'محفوظ — '.e($provider['name']):'غير مربوط').'. ربط المزوّد لا يشغّل المكالمات قبل فحص الصوت ثنائي الاتجاه على الهاتف.</p>'.
            form_start('save_live_provider','/?tab=settings').
            '<label class="field">موديل Gemini Live<input name="model" dir="ltr" list="google-live-models" required maxlength="120" value="'.e((string)($provider['name']??'gemini-3.8-live')).'"><datalist id="google-live-models"><option value="gemini-3.8-live"><option value="gemini-3.8-live-extended-thinking"><option value="gemini-3.1-flash-live-preview"></datalist></label>'.
            '<label class="field">Google API Key<input type="password" name="google_key" autocomplete="new-password" required maxlength="256"></label>'.
            '<p class="dim">هنتحقق من صلاحية المفتاح عبر Google API، وبعدين نعرض نتيجة اختبار Live بشكل منفصل.</p>'.
            submit_btn('تحقق واحفظ مفتاح Google'));
    }
    echo '<div class="cols two">';
    echo card('هوية حسابك','<div class="brand-preview"><div class="brand-icon">'.e(mb_substr($brand['name'],0,1)).'</div><div><h3>'.e($brand['name']).'</h3><p>ألوان الهوية الأساسية: أحمر / أسود / أبيض</p></div></div><p>المستخدم المباشر يرث OnTrack؛ المستخدم التابع يرث هوية الريسيلر بشكل آمن.</p>');
    if(in_array($a['kind'],['reseller','platform'],true)){
        echo card('تعديل الاسم والشعار',form_start('brand','/?tab=settings','enctype="multipart/form-data"').field_html('الاسم التجاري الظاهر','brand_name','text',$brand['name'],true).'<label class="field">شعار PNG/JPEG/WEBP (حتى 1MB)<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label>'.submit_btn('حفظ الهوية'));
    }else{
        echo card('إعدادات المستخدم','<p>اسم وشعار البرنامج متاحان من علامة الريسيلر الذي تتبع له، أو علامة OnTrack إن كنت عميلاً مباشراً.</p>');
    }
    echo '</div>';
}
