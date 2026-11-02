<?php
declare(strict_types=1);
require_once __DIR__.'/VoiceAccess.php';

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
function select_html(string $label,string $key,array $options,string $selected=''):string{
    $html='<label class="field">'.e($label).'<select name="'.e($key).'">';
    foreach($options as $val=>$desc)$html.='<option value="'.e((string)$val).'"'.((string)$val===$selected?' selected':'').'>'.e((string)$desc).'</option>';
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
    if(in_array($type,['platform','reseller'],true)&&console_view_mode()==='user')$type='customer';
    $common=[
        'overview'=>['⌂','الرئيسية'],
        'staff'=>['♙','الموظفون'],
        'billing'=>['▣','الباقات والمدفوعات'],
        'settings'=>['⚙','الإعدادات']
    ];
    // Administrative spaces manage tenants, plans and platform integrations.
    // Operational tools belong ONLY to the separate User Workspace preview.
    if($type==='platform')return [
        'overview'=>['⌂','نظرة عامة'],
        'accounts'=>['♧','الحسابات والريسيلرز'],
        'plans'=>['◫','الخطط والحدود'],
        'billing'=>['▣','الفوترة'],
        'staff'=>['♙','فريق المنصة'],
        'voices'=>['♫','إدارة الأصوات'],
        'integrations'=>['⇄','تكاملات المنصة'],
        'keys'=>['⌘','مفاتيح API'],
        'whatsapp_admin'=>['✉','إعدادات ربط واتساب'],
        'releases'=>['↓','تطبيق Android'],
        'settings'=>['⚙','إعدادات المنصة']
    ];
    if($type==='reseller')return [
        'overview'=>['⌂','نظرة عامة'],
        'accounts'=>['♧','عملائي'],
        'plans'=>['◫','باقات العملاء'],
        'billing'=>['▣','الاشتراكات'],
        'staff'=>['♙','الفريق والأدوار'],
        'voices'=>['♫','الأصوات المتاحة'],
        'domains'=>['◎','الدومينات والبراند'],
        'integrations'=>['⇄','تكاملات الريسيلر'],
        'keys'=>['⌘','مفاتيح API'],
        'releases'=>['↓','تطبيق Android'],
        'settings'=>['⚙','إعدادات الريسيلر']
    ];
    return [
        'overview'=>['⌂','الرئيسية'],'sites'=>['◈','مواقعي'],
        'agents'=>['✧','Agent Studio'],
        'answering'=>['☷','إعدادات الرد'],'whatsapp'=>['✉','WhatsApp Inbox'],'campaigns'=>['▤','الحملات'],
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
    $uiVersion=substr(hash('sha256',hash_file('sha256',__DIR__.'/../assets/css/app.css').hash_file('sha256',__DIR__.'/../assets/js/app.js')),0,12);
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#121215"><title>'.e($heading).' | '.e($brand['name']).'</title><link rel="stylesheet" href="/saas/console/assets/css/app.css?v='.$uiVersion.'"><script src="/saas/console/assets/js/app.js?v='.$uiVersion.'" defer></script><script src="/saas/console/assets/js/graph-routing.js?v='.$uiVersion.'"></script><script src="/saas/console/assets/js/flow-graph.js?v='.$uiVersion.'" defer></script><script src="/saas/console/assets/js/qrcode.js?v='.$uiVersion.'" defer></script><script src="/saas/console/assets/js/pairing-qr.js?v='.$uiVersion.'" defer></script></head><body>';
    echo '<div class="app-shell"><aside class="sidebar" id="mainNavigation" aria-label="القائمة الرئيسية"><button type="button" class="nav-close" id="navClose" aria-label="إغلاق القائمة">×</button><a href="/saas/console/" class="brand"><span class="brand-logo">';
    if(!empty($brand['logo'])) echo '<img src="/saas/console/brand-logo" alt="">';else echo '<b>'.e(mb_substr($brand['name'],0,1)).'</b>';
    echo '</span><span class="brand-lines"><strong>'.e($brand['name']).'</strong><small>'.e($role).'</small></span></a>';
    echo '<nav class="nav-list" aria-label="أقسام المنصة">';
    $administrative=in_array($a['kind'],['platform','reseller'],true)&&
        console_view_mode($a)==='admin'&&!$support;
    $workspace=$administrative?($a['kind']==='platform'?['overview','whatsapp_admin']:['overview']):
        ['overview','agents','flows','answering','whatsapp','calls','campaigns','contacts','devices','sites'];
    $platform=['accounts','plans','billing','staff','domains'];
    $settings=['voices','integrations','keys','releases','settings'];
    $groups=['workspace'=>[$administrative?'لوحة الإدارة':'مساحة العمل',$workspace],
        'platform'=>['الحسابات والإدارة',$platform],
        'system'=>[$administrative?'إعدادات وإدارة المنصة':'الإعدادات والتكاملات',$settings]];
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
    echo '</nav>';
    if($support){
        $origin=account((int)($a['support_origin']??0));
        $backLabel=($origin['kind']??'')==='reseller'?'العودة لمساحة الريسيلر':'العودة للوحة مدير المنصة';
        echo '<div class="workspace-switcher" aria-label="تبديل مساحة العمل">'.
            '<span class="workspace-switcher-title">أنت داخل حساب عميل للدعم</span>'.
            form_start('support_leave','','class="workspace-switch-form" data-no-ajax="1"').
            '<button type="submit" class="workspace-switch-button">↩ '.e($backLabel).'</button></form></div>';
    }elseif(console_can_switch_view($a)){
        $to=console_view_mode($a)==='user'?'admin':'user';
        $toLabel=$to==='user'?'الانتقال لمساحة المستخدم':
            (($a['kind']==='reseller')?'العودة لمساحة الريسيلر':'العودة للوحة مدير المنصة');
        echo '<div class="workspace-switcher" aria-label="تبديل مساحة العمل">'.
            '<span class="workspace-switcher-title">المساحة الحالية · '.($to==='user'?'إدارة الحساب':'مساحة المستخدم').'</span>'.
            form_start('set_view','','class="workspace-switch-form" data-no-ajax="1"').
            '<input type="hidden" name="view" value="'.e($to).'">'.
            '<button type="submit" class="workspace-switch-button">⇄ '.e($toLabel).'</button></form></div>';
    }
    echo '<div class="side-footer"><span class="live-dot"></span> OnTrack Voice <small>Business communication platform</small></div></aside><button type="button" class="nav-backdrop" id="navBackdrop" aria-label="إغلاق القائمة" tabindex="-1"></button><div class="main-area">';
    echo '<header class="topbar"><button type="button" class="icon-button nav-toggle" id="navToggle" aria-controls="mainNavigation" aria-expanded="false" aria-label="فتح القائمة">☰</button><div class="headline"><span class="eyebrow">'.e($role).'</span><h1>'.e($heading).'</h1></div><div class="header-actions">';
    $members=memberships((int)$u['id']);
    if(count($members)>1&&!$support){
        echo form_start('switch','','data-no-ajax="1"');$options=[];
        foreach($members as $m)$options[$m['account_id']]=$m['name'].' · '.$m['kind'];
        echo select_html('المساحة','account_id',$options).submit_btn('تبديل');
    }
    if($support){
        $origin=account((int)($a['support_origin']??0));
        echo form_start('support_leave','','class="inline-form workspace-return-top" data-no-ajax="1"').
            '<button type="submit" class="btn small primary">'.
            (($origin['kind']??'')==='reseller'?'↩ لوحة الريسيلر':'↩ لوحة المدير').
            '</button></form>';
    }elseif(console_can_switch_view($a)){
        $targetView=console_view_mode($a)==='user'?'admin':'user';
        $switchText=$targetView==='user'?'مساحة المستخدم':
            (($a['kind']==='reseller')?'لوحة الريسيلر':'لوحة المدير');
        echo form_start('set_view','','class="inline-form workspace-return-top" data-no-ajax="1"').
            '<input type="hidden" name="view" value="'.e($targetView).'">'.
            '<button type="submit" class="btn small primary">⇄ '.e($switchText).'</button></form>';
    }
    echo '<button type="button" class="icon-button" id="themeToggle" aria-label="تغيير المظهر" title="تغيير المظهر">☀</button>';
    echo '<div class="profile"><span class="avatar">'.e(mb_substr($u['name'],0,1)).'</span><span><strong>'.e($u['name']).'</strong><small>'.e($a['role']??'').'</small></span></div>';
    echo form_start('logout','','class="compact"').'<button type="submit" class="logout-btn" title="تسجيل الخروج">↪</button></form>';
    echo '</div></header><main class="content">';
    $myInvites=pending_staff_invitations_for_user((int)$u['id']);
    if($myInvites && !$support)echo '<div class="alert success">لديك '.count($myInvites).' دعوة انضمام تنتظر قرارك. <a href="/saas/console/invitations">مراجعة الدعوات ←</a></div>';
    if($support)echo '<div class="support-context"><span>حساب '.e($a['name']).' · '.(support_mode((int)($a['support_origin']??0))==='read_only'?'قراءة فقط':'صلاحيات كاملة').'</span>'.form_start('support_leave','','data-no-ajax="1"').'<button class="btn small" type="submit">العودة للوحة الإدارة</button></form></div>';
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
    if(in_array($kind,['platform','reseller'],true)&&console_view_mode($a)==='user')$kind='customer';
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
    $primaryAction=$kind==='platform'?'accounts':($kind==='reseller'?'accounts':'agents');
    $primaryLabel=$kind==='platform'?'إدارة الحسابات والريسيلرز':($kind==='reseller'?'إدارة العملاء':'إدارة الإيجنتات');
    $secondaryAction=$kind==='platform'?'whatsapp_admin':($kind==='reseller'?'plans':'releases');
    $secondaryLabel=$kind==='platform'?'إعدادات ربط واتساب':($kind==='reseller'?'باقات العملاء':'تطبيق Android');
    echo '<section class="welcome"><div><span class="eyebrow">SMART VOICE CONTROL</span><h2>'.e($title).'</h2><p>'.e($sub).'</p><div class="welcome-actions">'.
        '<a class="btn light" href="/saas/console/?tab='.e($primaryAction).'">'.e($primaryLabel).' ←</a>'.
        '<a class="btn ghost-light" href="/saas/console/?tab='.e($secondaryAction).'">'.e($secondaryLabel).'</a>'.
        '</div></div><div class="orb-art"><span></span><b>OT</b></div></section>';
    echo '<div class="metric-grid">';
    if($kind==='platform'){
        echo counter('الريسيلرز',$n1,'♧').counter('العملاء',$n2,'♙').counter('الإيجنتات',$n3,'✧').counter('سجلات المكالمات',$n4,'☏');
    }elseif($kind==='reseller'){
        echo counter('شركات العملاء',$n1,'♧').counter('حصص العملاء المحجوزة',$n2,'◫').counter('رصيد باقة الريسيلر',$n3,'▣').counter('تكاملات مرتبطة',$n4,'⇄');
    }else{
        echo counter('المواقع',$n1,'◎').counter('الإيجنتات',$n2,'✧').counter('مكالمات الخطة الشهرية',$n3,'☏').counter('سجلات تجريبية',$n4,'▤');
    }
    echo '</div><div class="cols two">';
    if($kind==='platform'){
        $tips='<div class="quick-grid">'.
            '<a href="/saas/console/?tab=accounts"><b>♧</b><span>الحسابات والريسيلرز</span><small>إدارة العملاء والشركات</small></a>'.
            '<a href="/saas/console/?tab=plans"><b>◫</b><span>الخطط والحدود</span><small>إدارة باقات المنصة</small></a>'.
            '<a href="/saas/console/?tab=whatsapp_admin"><b>✉</b><span>إعدادات ربط واتساب</span><small>Embedded Signup وCoexistence</small></a>'.
            '<a href="/saas/console/?tab=settings"><b>⚙</b><span>إعدادات المنصة</span><small>الهوية والتكاملات المركزية</small></a></div>';
    }elseif($kind==='reseller'){
        $tips='<div class="quick-grid">'.
            '<a href="/saas/console/?tab=accounts"><b>♧</b><span>عملائي</span><small>إدارة حسابات العملاء</small></a>'.
            '<a href="/saas/console/?tab=plans"><b>◫</b><span>باقات العملاء</span><small>الحدود والاشتراكات</small></a>'.
            '<a href="/saas/console/?tab=domains"><b>◎</b><span>الدومينات والهوية</span><small>البراند الخاص بالريسيلر</small></a>'.
            '<a href="/saas/console/?tab=settings"><b>⚙</b><span>إعدادات الريسيلر</span><small>إدارة العلامة التجارية</small></a></div>';
    }else{
        $tips='<div class="quick-grid"><a href="/saas/console/?tab=sites"><b>◈</b><span>مواقع مستقلة</span><small>إيجنت ومعرفة لكل موقع</small></a><a href="/saas/console/?tab=agents"><b>✧</b><span>Agent Flow Builder</span><small>السيناريو والشخصية والصوت</small></a><a href="/saas/console/?tab=keys"><b>⌘</b><span>API Keys</span><small>مفاتيح مسمّاة وقابلة للإلغاء</small></a><a href="/saas/console/?tab=staff"><b>♙</b><span>إدارة الموظفين</span><small>أدوار وصلاحيات لكل حساب</small></a></div>';
    }
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
                '<button class="btn small danger" type="submit" data-confirm="إيقاف بيع الباقة؟ الاشتراكات الحالية ستظل محفوظة">إيقاف بيع الباقة</button></form>'.
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
                select_html('تغيير الدور','role',['manager'=>'مدير','operator'=>'مشغّل','reception'=>'استقبال','finance'=>'مالية','support'=>'دعم','viewer'=>'مشاهد']).'<button class="btn small primary">حفظ</button></form></details>';
           $actions.=form_start('remove_staff','/?tab=staff','class="inline-form"').'<input type="hidden" name="user_id" value="'.$r['id'].'"><button class="btn small danger" data-confirm="إزالة الموظف من هذا الحساب؟">إزالة</button></form>';
       }
       $users[]=[e($r['name']),e($r['email']),$actions];
    }
    echo card('فريق العمل',tbl(['الاسم','البريد','الصلاحية والإدارة'],$users));
    if((($a['role']??'')==='owner'||($a['role']??'')==='admin')&&!active_support())
        echo card('دعوة موظف (تحتاج قبول صاحب البريد)',form_start('add_employee','/?tab=staff').field_html('اسم الموظف','name','text','الاسم',true).field_html('البريد الإلكتروني','email','email','staff@company.com',true).
            select_html('الدور','role',['manager'=>'مدير','operator'=>'موظف عمليات','reception'=>'استقبال','finance'=>'مالية','support'=>'دعم','viewer'=>'مشاهد']).submit_btn('إنشاء دعوة بدون منح صلاحية'));
    $pending=all("SELECT id,email,name,role,expires_at FROM staff_invitations WHERE account_id=? AND status='pending' AND expires_at>? ORDER BY id DESC",[$id,now()]);
    $pendingRows=[];
    foreach($pending as $inv){
        $action=pill('pending');
        if(in_array($a['role'],['owner','admin'],true)&&!active_support())
            $action.=form_start('cancel_staff_invitation','/?tab=staff','class="inline-form"').'<input type="hidden" name="invitation_id" value="'.(int)$inv['id'].'"><button class="btn small danger" type="submit">إلغاء الدعوة</button></form>';
        $pendingRows[]=[e($inv['name']),e($inv['email']),e($inv['role']),e($inv['expires_at']),$action];
    }
    echo card('دعوات الفريق المعلقة',tbl(['الاسم','البريد','الدور','تنتهي','الإجراء'],$pendingRows));
    render_voice_staff_section($a,$rows);
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
      form_start('archive_site','/?tab=sites','class="inline-form"').'<input type="hidden" name="site_id" value="'.$s['id'].'"><button class="btn small danger" data-confirm="أرشفة الموقع وإيجنتاته؟">أرشفة</button></form>'];
    echo card('مواقعي',tbl(['الموقع','الدومين','الحالة','التشغيل'],$cells));
    if(allowed('sites.write'))echo card('موقع جديد',form_start('add_site','/?tab=sites').field_html('اسم الموقع','name','text','الصيدلية / الملابس / الدعم',true).field_html('الدومين','domain','text','store.example.com').submit_btn('إضافة موقع مستقل'));
    echo '</div>';
}
/** Only operational controls: stored text and tested read-only sources.
 * Caller-based privileges are registered here but NOT yet trusted by voice runtime.
 */
function render_voice_data_sources(array $a,array $site):void {
    $id=(int)$a['account_id'];$siteId=(int)$site['id'];
    if(!allowed('agents.write'))return;
    $url='/?tab=agents&site='.$siteId;
    echo '<details class="agent-create voice-data-workspace"><summary class="btn">مصادر المعرفة والتكاملات وأرقام الموظفين</summary>';
    $rows=all('SELECT id,name,kind,status,visibility FROM voice_knowledge_sources WHERE account_id=? AND site_id=? ORDER BY id DESC LIMIT 30',[$id,$siteId]);
    $html='<p class="info">مصادر المعرفة التي تخص الموقع الحالي فقط. تُستخدم في اقتراحات Flow وإجابات الإيجنت بعد النشر.</p>';
    $html.=form_start('voice_knowledge_upload',$url,'enctype="multipart/form-data"').
        '<input type="hidden" name="site_id" value="'.$siteId.'">'.
        field_html('اسم مصدر المعرفة','name','text','مثلاً سياسات الحجوزات',true).
        select_html('إتاحة المعرفة','knowledge_visibility',['public'=>'معلومات عامة للإيجنت والمتصلين','staff'=>'داخلية — لا تُستخدم في مكالمات العملاء']).
        '<label class="field">ملف TXT أو MD أو CSV أو JSON بحد 180KB<input type="file" name="knowledge_file" accept=".txt,.md,.csv,.json"></label>'.
        '<label class="field">أو محتوى نصي<textarea name="knowledge_text" maxlength="180000" rows="4" placeholder="يمكنك كتابة المعلومات بدل رفع ملف"></textarea></label>'.
        '<button class="btn primary" type="submit">إضافة إلى قاعدة المعرفة</button></form>';
    foreach($rows as $row){
        $html.='<div class="voice-data-row"><strong>'.e($row['name']).'</strong><span>'.e($row['kind']).' · '.($row['visibility']==='public'?'عام':'داخلي').'</span>'.
            form_start('voice_knowledge_delete',$url,'class="inline-form"').
            '<input type="hidden" name="source_id" value="'.(int)$row['id'].'"><button class="btn small danger" type="submit">إزالة المصدر</button></form></div>';
    }
    echo card('قاعدة المعرفة',$html);
    if(in_array($a['role'],['owner','admin'],true)&&!active_support()){
        $connectors=all('SELECT id,name,type,status,last_tested_at FROM voice_data_connectors WHERE account_id=? AND site_id=? ORDER BY id DESC LIMIT 20',[$id,$siteId]);
        $html='<p class="info">WordPress وWHMCS وLaravel أو REST مخصص. الاتصال للقراءة فقط، ويظل غير مفعّل حتى ينجح اختبار حقيقي. لا تُنفَّذ أوامر من صوت العميل.</p>'.
            form_start('voice_connector_add',$url).
            '<input type="hidden" name="site_id" value="'.$siteId.'">'.
            select_html('نوع المصدر','type',['wordpress'=>'WordPress REST','whmcs'=>'WHMCS API (المنتجات فقط)','laravel'=>'Laravel HTTPS API','custom'=>'REST API عام للقراءة']).
            field_html('اسم التكامل','name','text','مثلاً متجر الفرع',true).
            field_html('العنوان العام HTTPS','base_url','url','https://example.com',true).
            field_html('مسار القراءة المحدد','read_path','text','/wp-json/wp/v2/pages?per_page=5',true).
            select_html('اعتماد الاتصال','auth_mode',['none'=>'عام دون كلمة مرور','bearer'=>'Bearer Token','wp_application'=>'WordPress Application Password','whmcs'=>'WHMCS Identifier:Secret']).
            '<label class="field">بيانات الاتصال (تُشفّر على السيرفر)<input type="password" name="credential" autocomplete="new-password" maxlength="2000"></label>'.
            '<button class="btn primary" type="submit">إضافة مصدر للقراءة</button></form>';
        foreach($connectors as $row){
            $html.='<div class="voice-data-row"><strong>'.e($row['name']).'</strong><span>'.e($row['type']).' · '.e($row['status']).'</span>'.
                form_start('voice_connector_test',$url,'class="inline-form"').'<input type="hidden" name="connector_id" value="'.(int)$row['id'].'"><button class="btn small" type="submit">اختبار اتصال حقيقي</button></form>'.
                form_start('voice_connector_delete',$url,'class="inline-form"').'<input type="hidden" name="connector_id" value="'.(int)$row['id'].'"><button class="btn small danger" type="submit">حذف</button></form></div>';
        }
        echo card('تكاملات القراءة', $html);
        $staff=all("SELECT v.id,v.phone_e164,v.status,u.name,m.role
          FROM voice_staff_numbers v JOIN users u ON u.id=v.user_id
          JOIN memberships m ON m.account_id=v.account_id AND m.user_id=v.user_id
          WHERE v.account_id=? ORDER BY v.id DESC LIMIT 40",[$id]);
        $options=[];
        foreach(all("SELECT u.id,u.name,m.role FROM memberships m
           JOIN users u ON u.id=m.user_id WHERE m.account_id=? AND u.status='active' ORDER BY u.name",[$id]) as $member)
            $options[(int)$member['id']]=$member['name'].' ('.$member['role'].')';
        $html='<p class="info">يمكن ربط أكثر من رقم بكل موظف. صلاحيات كل رقم محددة ولا تتجاوز صلاحيات الموظف في الحساب. <strong>التعرف على الرقم وحده لا يفتح بيانات حساسة؛ يلزم تحقق PIN منفصل. تفعيل DTMF داخل المكالمة لم يكتمل بعد.</strong></p>'.
            form_start('voice_caller_add',$url).
            select_html('الموظف من فريق الحساب','user_id',$options).
            field_html('رقم دولي E.164','phone_e164','tel','+201000000000',true).
            '<label class="field">PIN من 6 إلى 10 أرقام<input name="pin" type="password" pattern="[0-9]{6,10}" minlength="6" maxlength="10" autocomplete="new-password" required></label>'.
            '<fieldset><legend>صلاحيات القراءة المسموحة لهذا الرقم (لا تتجاوز دور الموظف)</legend>';
        foreach(['general'=>'معلومات عامة','tickets'=>'التذاكر','bookings'=>'الحجوزات والمواعيد',
            'finance'=>'المالية','websites'=>'المواقع','operations'=>'التشغيل'] as $scope=>$label)
            $html.='<label class="field"><input type="checkbox" name="scopes[]" value="'.e($scope).'"> '.e($label).'</label>';
        $html.='</fieldset><button class="btn primary" type="submit">حفظ الرقم وصلاحياته</button></form>';
        foreach($staff as $row){
            $html.='<div class="voice-data-row"><strong>'.e($row['name']).'</strong><span dir="ltr">'.e($row['phone_e164']).'</span>'.
              '<span>'.e($row['role']).' · '.e($row['status']).'</span>';
            if($row['status']==='active')$html.=form_start('voice_caller_delete',$url,'class="inline-form"').
               '<input type="hidden" name="caller_id" value="'.(int)$row['id'].'"><button class="btn small danger">سحب الصلاحية</button></form>';
            $html.='</div>';
        }
        echo card('أرقام الموظفين والأدوار',$html);
    }
    echo '</details>';
}

/** Agent Studio replaces the obsolete branching-template UI. */
function agent_studio_policy_fields(array $policy):string{
    $html='<div class="agent-studio-section"><div class="agent-studio-section-title"><strong>الهدف والسلوك</strong><small>الإيجنت يختار مسار المحادثة بنفسه</small></div>'.
        '<label class="field">الهدف الأساسي<textarea name="agent_goal" rows="3" maxlength="1800" required>'.e($policy['goal']).'</textarea></label>'.
        '<label class="field">الضوابط والحدود<textarea name="agent_guardrails" rows="3" maxlength="1800">'.e($policy['guardrails']).'</textarea></label></div>';
    $html.='<div class="agent-studio-section"><div class="agent-studio-section-title"><strong>القدرات المتاحة</strong><small>الصلاحيات لا تتجاوز ما يسمح به السيرفر</small></div><div class="agent-studio-capabilities">';
    $capabilities=[
        'use_knowledge'=>['قاعدة المعرفة','قراءة المعلومات العامة المرفوعة لنفس الموقع فقط'],
        'use_signed_facts'=>['مصادر البيانات','عرض المصادر الموثّقة؛ البيانات الخاصة لا تُقرأ دون تحقق مستقل'],
        'human_handoff'=>['التصعيد البشري','اقتراح تدخل موظف، من غير ادعاء إتمام التحويل']
    ];
    foreach($capabilities as $key=>$description){
        $html.='<label class="agent-studio-capability"><input type="checkbox" name="'.e($key).'"'.(!empty($policy[$key])?' checked':'').'> <span><strong>'.e($description[0]).'</strong><small>'.e($description[1]).'</small></span></label>';
    }
    return $html.'</div></div>';
}
function render_agents(array $a):void{
    require_once __DIR__.'/AgentAnswerPolicy.php';
    $id=(int)$a['account_id'];
    $sites=all("SELECT id,name FROM sites WHERE account_id=? AND status='active' ORDER BY id",[$id]);
    $selected=selected_site($id);
    $map=[];foreach($sites as $site)$map[$site['id']]=$site['name'];
    echo '<div class="section-line agents-toolbar"><div class="agents-heading"><span class="eyebrow">DYNAMIC AI AGENT STUDIO</span>'.
        '<h2>Agent Builder · بلدر الإيجنت الذكي</h2><p>البلدر البصري موجود مع إعدادات الشخصية والقدرات والمعرفة؛ AI يقدر يقترح تغيير كل جزء.</p>'.
        '</div><div class="site-picker" aria-label="اختيار الموقع">';
    foreach($sites as $site)
        echo '<a class="chip-link '.($selected&&(int)$site['id']===(int)$selected['id']?'chosen':'').
            '" href="?tab=agents&site='.(int)$site['id'].'">'.e($site['name']).'</a>';
    echo '</div></div>';
    if(!$sites){echo card('إنشاء مساحة عمل','أضف موقعًا أولًا من قسم المواقع.');return;}
    if($selected)render_voice_data_sources($a,$selected);
    echo '<div class="agent-studio-banner"><strong>المحادثة الذكية هي الأساس</strong>'.
        '<p>يتعامل الإيجنت مع تغيير النية والأسئلة الجانبية ويحتفظ بسياق المكالمة. التكاملات الحالية مصادر معلومات للقراءة فقط؛ لا توجد أوامر دفع أو تعديل خدمات أو تحويل مكالمة تلقائي مؤكّد.</p></div>';
    $voices=[];
    foreach(all("SELECT code,name FROM voices WHERE ready=1 AND status='active' AND id IN (SELECT voice_id FROM voice_grants WHERE account_id=?) ORDER BY name",[$id]) as $voice)
        $voices[$voice['code']]=$voice['name'];
    if(!$voices)$voices=['google.aoede'=>'Aoede'];
    if(allowed('agents.write')){
        $defaults=answer_policy_defaults();
        $create=form_start('add_agent','/?tab=agents&site='.(int)$selected['id'],'class="agent-studio-form-create"').
            '<div class="form-grid">'.field_html('اسم الإيجنت','name','text','مساعد خدمة العملاء',true).
            select_html('موقع الإيجنت','site_id',$map).
            select_html('الصوت','voice',$voices).'</div>'.
            '<label class="field">الشخصية والتعليمات<textarea name="instructions" rows="4" maxlength="5000" placeholder="اتكلم بالمصري وبأسلوب مهني..."></textarea></label>'.
            '<label class="field">المعرفة الخاصة بالإيجنت<textarea name="knowledge" rows="4" maxlength="20000" placeholder="المنتجات، الخدمات والسياسات المعتمدة"></textarea></label>'.
            agent_studio_policy_fields($defaults).
            '<div class="agent-publish-actions"><button class="btn primary" type="submit" name="publish_now" value="1">إنشاء ونشر الإيجنت</button>'.
            '<button class="btn" type="submit">إنشاء مسودة</button></div></form>';
        echo '<details class="agent-create"><summary class="btn primary">+ إنشاء AI Agent</summary>'.
            card('إنشاء إيجنت ديناميكي',$create).'</details>';
    }
    $agents=$selected?all("SELECT * FROM agents WHERE account_id=? AND site_id=? AND status!='archived' ORDER BY id DESC",
        [$id,$selected['id']]):[];
    foreach($agents as $agent){
        $policy=answer_policy_for_agent($id,(int)$agent['id']);
        $version=published_agent_flow($id,(int)$selected['id'],(int)$agent['id']);
        $summary='<div class="agent-summary"><span class="pill neutral">AI ديناميكي</span>'.
            pill((string)$agent['status']).'<span class="dim">صوت: '.e($agent['voice']).'</span>'.
            ($version?'<span class="pill neutral">المنشور v'.(int)$version['revision'].'</span>':'<span class="pill neutral">غير منشور</span>').'</div>';
        if(allowed('agents.write')){
            $options=$voices;
            if(!isset($options[$agent['voice']]))$options[$agent['voice']]=$agent['voice'].' (الحالي)';
            $editing=form_start('update_agent','/?tab=agents&site='.(int)$selected['id'],
                'class="stack agent-form agent-studio-form"').
                '<input type="hidden" name="agent_id" value="'.(int)$agent['id'].'">'.
                '<div class="agent-builder-tabs" role="tablist" aria-label="أقسام البلدر">'.
                '<button type="button" class="agent-builder-tab active" data-builder-tab="visual" aria-selected="true">✦ البلدر البصري</button>'.
                '<button type="button" class="agent-builder-tab" data-builder-tab="intelligence" aria-selected="false">🧠 الشخصية والأهداف</button>'.
                '<button type="button" class="agent-builder-tab" data-builder-tab="knowledge" aria-selected="false">📚 المعرفة والتكاملات</button>'.
                '<button type="button" class="agent-builder-tab" data-builder-tab="voice" aria-selected="false">⚙️ الصوت والمكالمة</button></div>'.
                '<div class="agent-builder-pane" data-builder-pane="visual">'.
                '<div class="agent-builder-intro"><strong>Visual Agent Builder</strong><p>صمّم خريطة القدرات والمسارات بحرية، ومساعد AI يقدر يطور المخطط وكمان خصائص الإيجنت. الخريطة ليست مسار مكالمة إجباري.</p></div>'.
                '<div class="flow-builder" data-flow-editor><textarea name="flow_json" class="flow-source" rows="7" maxlength="16000">'.e((string)$agent['flow_json']).'</textarea></div></div>'.
                '<div class="agent-builder-pane" data-builder-pane="intelligence" hidden>'.
                '<div class="form-grid"><label class="field">اسم الإيجنت<input name="name" maxlength="140" required value="'.e($agent['name']).'"></label>'.
                select_html('الصوت','voice',$options,(string)$agent['voice']).'</div>'.
                '<label class="field">الشخصية والتعليمات<textarea name="instructions" rows="4" maxlength="5000">'.e($agent['instructions']).'</textarea></label>'.
                agent_studio_policy_fields($policy).
                '</div><div class="agent-builder-pane" data-builder-pane="knowledge" hidden>'.
                '<div class="agent-studio-section"><strong>المعرفة والتكاملات</strong><p class="info">اربط الملفات ومصادر WHMCS وLaravel وWordPress من مصادر بيانات الموقع. المساعد يقترح ما يلزم منها دون إنشاء صلاحيات أو مفاتيح تلقائيًا.</p>'.
                '<label class="field">معرفة الإيجنت<textarea name="knowledge" rows="6" maxlength="20000">'.e($agent['knowledge']).'</textarea></label></div></div>'.
                '<div class="agent-builder-pane" data-builder-pane="voice" hidden>'.
                '<div class="agent-studio-section"><div class="agent-studio-section-title"><strong>الرد والمكالمة</strong></div>'.
                '<div class="form-grid"><label class="field">مهلة الرد بالثواني<input name="answer_delay_seconds" type="number" min="0" max="30" value="'.(int)$policy['answer_delay_seconds'].'" required></label>'.
                '<label class="field">المدة القصوى بالدقائق<input name="max_call_minutes" type="number" min="1" max="30" value="'.(int)$policy['max_call_minutes'].'" required></label>'.
                select_html('الأسلوب','response_style',[
                    'egyptian_short'=>'مصري مختصر','formal_ar'=>'عربي رسمي','english'=>'إنجليزي','bilingual'=>'عربي وإنجليزي'],$policy['response_style']).
                select_html('عند تعذر الاستكمال','fallback',['human'=>'عرض مساعدة موظف','end'=>'إنهاء بأدب'],$policy['fallback']).'</div>'.
                '<label class="field"><input type="checkbox" name="auto_answer"'.($policy['auto_answer']?' checked':'').'> الرد الآلي عند تفعيل ربط الشريحة</label></div></div>'.
                '<div class="agent-publish-actions"><button class="btn primary" type="submit" name="publish_now" value="1">نشر إعدادات الإيجنت</button>'.
                '<button class="btn" type="submit">حفظ مسودة بدون تحديث</button>'.
                '<span class="agent-studio-status" role="status"></span></div></form>'.
                form_start('archive_agent','/?tab=agents&site='.(int)$selected['id'],'class="inline-form"').
                '<input type="hidden" name="agent_id" value="'.(int)$agent['id'].'">'.
                '<button class="btn small danger" data-confirm="أرشفة الإيجنت؟">أرشفة الإيجنت</button></form>';
            $summary.=$editing;
        }else $summary.='<p>'.e((string)$agent['instructions']).'</p>';
        echo '<details class="agent-editor" id="agent-'.(int)$agent['id'].'"'.
            ((string)($_GET['agent']??'')===(string)$agent['id']?' open':'').
            '><summary><strong>'.e($agent['name']).'</strong><span>'.pill((string)$agent['status']).
            '</span><span class="dim">إدارة الشخصية والهدف والأدوات</span></summary>'.
            card('Agent Studio · '.e($agent['name']),$summary).'</details>';
    }
    if(!$agents)echo '<p class="info">لسه مفيش إيجنتات في الموقع ده؛ أنشئ أول AI Agent من الزر اللي فوق.</p>';
}
function render_flows(array $a):void{
    $id=(int)$a['account_id'];
    $agents=all("SELECT ag.id,ag.name,ag.site_id,s.name site_name FROM agents ag JOIN sites s ON s.id=ag.site_id
        WHERE ag.account_id=? AND ag.status!='archived' AND s.status='active' ORDER BY ag.id DESC",[$id]);
    echo '<p class="info">تم استبدال السيناريوهات الثابتة بـ Agent Studio. المحادثة بتتبني حسب هدف الإيجنت والمعرفة والسياسات، مش بالانتقال بين عُقد.</p>';
    $rows=[];
    foreach($agents as $agent){
        $version=published_agent_flow($id,(int)$agent['site_id'],(int)$agent['id']);
        $url='/?tab=agents&site='.(int)$agent['site_id'].'&agent='.(int)$agent['id'];
        $rows[]=[e($agent['name']),e($agent['site_name']),
            $version?'v'.(int)$version['revision']:'مسودة',
            '<a class="btn small primary" href="'.e($url).'">فتح Agent Studio</a>'];
    }
    echo card('الإيجنتات الديناميكية',tbl(['الإيجنت','الموقع','الإصدار','التعديل'],$rows));
}

function render_answering(array $a):void {
    $id=(int)$a['account_id'];
    $agents=all("SELECT ag.id,ag.name,ag.site_id,s.name site_name FROM agents ag JOIN sites s ON s.id=ag.site_id
        WHERE ag.account_id=? AND ag.status!='archived' AND s.status='active' ORDER BY ag.id DESC",[$id]);
    echo '<p class="info">إعدادات الرد محفوظة لكل إيجنت بشكل مستقل؛ لا تصل للشريحة إلا في نسخة إعدادات AI Agent المنشورة. ربط المحرك الصوتي الحقيقي على SIM ما زال يحتاج اختبارًا.</p>';
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
        $action.=form_start('delete_contact','/?tab=contacts','class="inline-form"').'<input type="hidden" name="contact_id" value="'.$r['id'].'"><button class="btn small danger" data-confirm="حذف جهة الاتصال؟">حذف</button></form>';
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
    $statuses=['all','active','ended','ringing','answered','missed','failed','completed','busy','no_answer'];
    $filter=in_array($requestedStatus,$statuses,true)?$requestedStatus:'all';
    $params=[$id];
    $where='account_id=?';
    if($keyword!==''){
        $where.=" AND (name LIKE ? OR phone LIKE ?)";
        $term='%'.str_replace(['%','_'],['\\%','\\_'],$keyword).'%';
        $params[]=$term;$params[]=$term;
    }
    if($filter!=='all'){$where.=' AND status=?';$params[]=$filter;}
    $list=all('SELECT c.id,c.name,c.phone,c.status,c.duration_seconds,c.is_demo,c.device_binding_id,
        c.external_call_id,c.sim_slot,c.site_id,c.created_at,
        r.summary,r.customer_notes,r.outcome,r.handoff_status,r.handoff_reason,
        r.source AS report_source,r.updated_at AS report_updated_at
        FROM calls c LEFT JOIN tenant_call_reports r ON
          r.account_id=c.account_id AND r.site_id=c.site_id
          AND r.device_binding_id=c.device_binding_id AND r.call_id=c.external_call_id
        WHERE '.str_replace(['account_id=?','name LIKE ?','phone LIKE ?','status=?'],
            ['c.account_id=?','c.name LIKE ?','c.phone LIKE ?','c.status=?'],$where).
        ' ORDER BY c.id DESC LIMIT 200',$params);
    $requestedId=max(0,(int)($_GET['call']??0));
    $selected=null;
    foreach($list as $item){if((int)$item['id']===$requestedId)$selected=$item;}
    if($selected===null)$selected=$list[0]??null;
    $stats=one('SELECT COUNT(*) n FROM calls WHERE account_id=?',[$id]);
    echo '<section class="calls-workspace" aria-label="صندوق المكالمات">';
    echo '<header class="calls-top"><div><span class="eyebrow">ONTRACK VOICE</span><h2>صندوق المكالمات</h2>'.
        '<p>المكالمات الواردة والصادرة، ملخصاتها، ملاحظات العملاء وطلبات تدخل الموظف. الاسم والرقم يظهران عند وصول تعريف موثوق من جهاز Android؛ بدون اختلاق بيانات.</p></div>'.
        '<span class="calls-total">'.(int)($stats['n']??0).' مكالمة مسجّلة</span></header>';
    echo '<form class="calls-filters" action="/saas/console/" method="get" role="search"><input type="hidden" name="tab" value="calls">'.
        '<label class="field">بحث بالاسم أو الرقم<input name="q" value="'.e($keyword).'" placeholder="اسم العميل أو الرقم" maxlength="80"></label>'.
        '<label class="field">الحالة<select name="status">';
    $labels=['all'=>'كل الحالات','active'=>'مكالمة جارية','ended'=>'انتهت','ringing'=>'جاري الاتصال','answered'=>'تم الرد','missed'=>'فائتة','failed'=>'فشلت','completed'=>'مكتملة','busy'=>'مشغول','no_answer'=>'لم يرد'];
    foreach($labels as $key=>$name)echo '<option value="'.e($key).'"'.($filter===$key?' selected':'').'>'.e($name).'</option>';
    echo '</select></label><button class="btn primary" type="submit">بحث</button></form>';
    echo '<div class="calls-inbox"><div class="calls-list" aria-label="سجل المكالمات">';
    if(!$list)echo '<div class="calls-empty">لا توجد مكالمات مطابقة للبحث. البيانات هتظهر هنا بعد وصول سجل مكالمات حقيقي من الجهاز.</div>';
    foreach($list as $c){
        $chosen=$selected&&(int)$selected['id']===(int)$c['id'];
        $url='/saas/console/?tab=calls&call='.(int)$c['id'].'&status='.rawurlencode($filter).'&q='.rawurlencode($keyword);
        $letter=mb_substr((string)($c['name']?:$c['phone']),0,1);
        echo '<a class="call-contact'.($chosen?' active':'').'" href="'.e($url).'"'.($chosen?' aria-current="true"':'').'><span class="call-avatar">'.e($letter).'</span>'.
            '<span class="call-contact-main"><strong>'.e((string)($c['name']?:($c['phone']!==''?'رقم غير مسجل':'هوية المتصل لم تصل بعد'))).'</strong>'.
            '<small dir="ltr">'.e((string)($c['phone']?:'رقم غير متاح · راجع إذن سجل المكالمات')).'</small></span>'.
            '<span class="call-contact-meta"><time>'.e((string)$c['created_at']).'</time>'.pill((string)$c['status']).'</span></a>';
    }
    echo '</div><section class="call-detail" aria-label="تفاصيل المكالمة">';
    if($selected){
        echo '<div class="call-detail-header"><span class="call-avatar large">'.e(mb_substr((string)($selected['name']?:$selected['phone']),0,1)).'</span>'.
            '<h3>'.e((string)($selected['name']?:($selected['phone']!==''?'اسم غير مسجل':'هوية المتصل غير متاحة'))).'</h3>'.
            '<p dir="ltr">'.e((string)($selected['phone']?:'لم يصل الرقم من Android')).'</p>'.
            ($selected['phone']===''?'<p class="dim">لو الرقم ظاهر في سجل الهاتف ومش هنا، راجع تصريح READ_CALL_LOG ووصول حدث identified من التطبيق. الأرقام المخفية مش ممكن نستنتجها.</p>':'').
            '</div>'.
            '<div class="call-facts"><div><span>الحالة</span>'.pill((string)$selected['status']).'</div>'.
            '<div><span>المدة</span><strong>'.e(fmt_duration((int)$selected['duration_seconds'])).'</strong></div>'.
            '<div><span>التاريخ</span><strong>'.e((string)$selected['created_at']).'</strong></div>'.
            '<div><span>المصدر</span><strong>'.(!empty($selected['external_call_id']) && !empty($selected['device_binding_id'])?'سجل من شريحة الهاتف':((int)$selected['is_demo']===1?'بيانات تجربة':'سجل الجهاز')).'</strong></div></div>'.
            '<div class="call-transcript-empty"><strong>ملخص المكالمة</strong>'.
                '<p>'.(!empty($selected['summary'])?nl2br(e((string)$selected['summary'])):'لم يُحفظ ملخص لهذه المكالمة بعد. مش هنعرض ملخص آلي وهمي.').'</p>'.
                '<strong>ملاحظات العميل</strong><p>'.(!empty($selected['customer_notes'])?nl2br(e((string)$selected['customer_notes'])):'لا توجد ملاحظات بعد.').'</p>'.
                '<strong>نتيجة المكالمة</strong><p>'.e(match($selected['outcome']??'unknown'){
                    'interested'=>'مهتم','satisfied'=>'راضٍ','unsatisfied'=>'غير راضٍ',
                    'follow_up'=>'يحتاج متابعة','do_not_call'=>'لا تتصل مجددًا',
                    'not_interested'=>'غير مهتم',default=>'غير محددة'
                }).'</p>'.
                '<strong>التحويل لموظف</strong><p>'.e(match($selected['handoff_status']??'not_requested'){
                    'requested'=>'تم طلب تدخل موظف — غير مؤكد التنفيذ',
                    'completed'=>'تم تأكيد استلام الموظف يدويًا',
                    default=>'لم يتم طلب تدخل موظف'
                }).'</p>'.
                (!empty($selected['handoff_reason'])?'<p>'.nl2br(e((string)$selected['handoff_reason'])).'</p>':'').
                '<p class="dim">التسجيل والتفريغ الكامل لن يظهرا إلا لو تم رفع بيانات حقيقية من الهاتف.</p></div>';
            if(allowed('calls.write')&&!empty($selected['device_binding_id'])&&!empty($selected['external_call_id'])){
                echo '<section class="call-annotation-panel"><h3>تعديل تقرير المكالمة وملاحظات العميل</h3>'.
                    '<p class="dim">دي بيانات المكالمة الحالية فقط. طلب التحويل لا يعني إن الموظف استلم، إلا لو تم تأكيده فعليًا.</p>'.
                    form_start('save_call_report','/?tab=calls&call='.(int)$selected['id'],
                        'class="call-annotation-form"').
                    '<input type="hidden" name="call_record_id" value="'.(int)$selected['id'].'">'.
                    '<input type="hidden" name="call_id" value="'.e((string)$selected['external_call_id']).'">'.
                    '<label class="field">ملخص المكالمة<textarea name="summary" maxlength="4000" rows="4">'.e((string)($selected['summary']??'')).'</textarea></label>'.
                    '<label class="field">ملاحظات العميل<textarea name="customer_notes" maxlength="4000" rows="3">'.e((string)($selected['customer_notes']??'')).'</textarea></label>'.
                    select_html('نتيجة المكالمة','outcome',[
                        'unknown'=>'غير محددة','interested'=>'مهتم','satisfied'=>'راضٍ',
                        'unsatisfied'=>'غير راضٍ','follow_up'=>'يحتاج متابعة',
                        'not_interested'=>'غير مهتم','do_not_call'=>'لا تتصل مجددًا'
                    ],(string)($selected['outcome']??'unknown')).
                    select_html('حالة التحويل لموظف','handoff_status',[
                        'not_requested'=>'لم يُطلب','requested'=>'طلب تدخل موظف (لم يتم التنفيذ)',
                        'completed'=>'تم تأكيد استلام موظف'
                    ],(string)($selected['handoff_status']??'not_requested')).
                    '<label class="field">سبب طلب الموظف وملاحظات المتابعة<textarea name="handoff_reason" maxlength="2000" rows="2">'.e((string)($selected['handoff_reason']??'')).'</textarea></label>'.
                    '<button class="btn primary" type="submit">حفظ التقرير والملاحظات</button></form></section>';
            }
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
                '<button class="btn small" data-confirm="فصل هذا الجهاز وإلغاء توكن الشريحة فورًا؟">فصل الجهاز</button></form>'
            :'—';
        $published=all("SELECT ag.id,ag.name FROM agents ag
            JOIN agent_flow_versions v ON v.agent_id=ag.id AND v.account_id=ag.account_id AND v.site_id=ag.site_id
            WHERE ag.account_id=? AND ag.site_id=? AND ag.status!='archived'
            GROUP BY ag.id,ag.name ORDER BY ag.name",[$id,$d['site_id']]);
        if($manage&&$d['status']==='active'&&$published){
            $choices=[];foreach($published as $candidate)$choices[(int)$candidate['id']]=$candidate['name'];
            $action=form_start('change_device_agent','/?tab=devices','class="device-change-agent-form"').
                '<input type="hidden" name="device_id" value="'.(int)$d['id'].'">'.
                select_html('تغيير الإيجنت على نفس الشريحة','agent_id',$choices,(string)$d['agent_id']).
                '<button class="btn small primary" type="submit">تغيير الإيجنت بدون إعادة ربط</button></form>'.$action;
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
            $last.=form_start('set_voice_ready','/?tab=voices','class="inline-form"').'<input type="hidden" name="voice_id" value="'.$v['id'].'"><input type="hidden" name="ready" value="'.((int)$v['ready']===1?'0':'1').'"><input type="hidden" name="confirmed" value="1"><button class="btn small" data-confirm="هل اختبرت الصوت بنجاح على مزود الاتصال؟">'.((int)$v['ready']===1?'إلغاء الجاهزية':'اعتماد صوت').'</button></form>';
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
    $controls.=form_start('rotate_secret',$return,'class="inline-form"').'<input type="hidden" name="secret_type" value="'.e($type).'"><input type="hidden" name="secret_id" value="'.$row['id'].'"><button class="btn small" data-confirm="تغيير السر يلغي المفتاح القديم فوراً، هل تريد الاستمرار؟">تدوير</button></form>';
    $action=$type==='api'?'revoke_key':'revoke_integration';
    $field=$type==='api'?'key_id':'integration_id';
    $controls.=form_start($action,$return,'class="inline-form"').'<input type="hidden" name="'.$field.'" value="'.$row['id'].'"><button class="btn small danger" data-confirm="إلغاء الاتصال نهائياً؟">إلغاء</button></form>';
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
        form_start('new_integration','/?tab=integrations').field_html('اسم الاتصال','name','text','WHMCS / Laravel / WordPress / نظام خارجي',true).
        select_html('المزود','provider',['whmcs'=>'WHMCS','laravel'=>'Laravel','wordpress'=>'WordPress / WooCommerce','generic_rest'=>'نظام خارجي (REST / Webhook)','kashier'=>'Kashier','webhook'=>'Generic Webhook']).
        submit_btn('إنشاء الاتصال')).
        card('Webhook / API','<p>مصادر WHMCS وLaravel وWordPress وأي نظام خارجي ترسل ملخصات بيانات موقّعة HMAC. موصلات WordPress والعام قراءة فقط حاليًا. تنفيذ الأوامر يتطلب أذونات صريحة وتأكيدا مستقلا؛ إضافة الاتصال لا تعني نجاح الربط الفعلي.</p><code>POST /saas/console/hooks/index.php</code>').'</div>';
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
    if(in_array($a['kind'],['platform','reseller'],true)&&console_view_mode($a)==='admin'){
        $orders=$a['kind']==='platform'?all('SELECT o.*,a.name account_name FROM orders o JOIN accounts a ON a.id=o.account_id ORDER BY o.id DESC LIMIT 60'):all('SELECT o.*,a.name account_name FROM orders o JOIN accounts a ON a.id=o.account_id WHERE a.parent_id=? ORDER BY o.id DESC LIMIT 60',[$id]);
        $rows=[];
        foreach($orders as $o){
            $act=pill($o['status']);
            if($o['status']==='pending_manual_review')$act.=form_start('manual_approve','/?tab=billing','class="inline-form"').'<input type="hidden" name="order_id" value="'.$o['id'].'"><input name="payment_reference" class="tiny-input" required minlength="8" placeholder="مرجع إثبات الدفع"><button class="btn small primary" type="submit" data-confirm="هل تحققت فعلياً من وصول الدفع؟">اعتماد الدفع</button></form>';
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
/** Centralized platform-only controls for Meta Tech Provider and Coexistence.
 * Never display any tenant-facing inbox or connection form in this page.
 */
function render_whatsapp_platform_settings(array $a):void {
    if($a['kind']!=='platform'||!in_array($a['role'],['owner','admin'],true)||
       console_view_mode($a)!=='admin'||active_support())deny();
    require_once __DIR__.'/WhatsAppOnboarding.php';
    $meta=wa_platform_onboarding();
    echo '<section class="card platform-whatsapp-intro">'.
         '<div class="wa-manual-heading"><div><span class="eyebrow">PLATFORM / META ONBOARDING</span>'.
         '<h2>إعدادات ربط واتساب — إدارة المنصة</h2>'.
         '<p class="dim">إدارة طرق الربط المتاحة للعملاء. ربط رقمك أو صندوق واردك من مساحة المستخدم فقط.</p>'.
         '</div><a class="btn" href="/saas/console/?tab=settings">إعدادات المنصة</a></div>'.
         '<p class="info">الربط اليدوي وربط واتساب الموجود متاحان للمستخدمين دائمًا. الربط التلقائي وCoexistence لا يظهران للعملاء إلا بعد استكمال متطلبات Meta وتفعيلهما من هنا.</p></section>';
    // The platform flags validate stored IDs only. The Facebook JavaScript
    // Login SDK option is external Meta state and cannot be inferred from them.
    echo '<section class="card wa-meta-jssdk-guide" role="region" aria-label="فحص Facebook Login for Business">'.
        '<h3>مطلوب داخل Meta قبل اختبار الربط التلقائي</h3>'.
        '<p><strong>JSSDK Option is Not Toggled</strong> معناها أن Facebook Login for Business لم يُفعّل تسجيل الدخول عبر JavaScript SDK للتطبيق، حتى لو كانت مفاتيح الربط محفوظة هنا.</p>'.
        '<ol><li>افتح <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener noreferrer">Meta for Developers</a> واختار التطبيق'.(!empty($meta['app_id'])?' رقم <code dir="ltr">'.e((string)$meta['app_id']).'</code>':'').'، ثم <strong>Facebook Login for Business ← Settings</strong>.</li>'.
        '<li>من Client OAuth Settings فعّل <strong>Login with the JavaScript SDK = Yes</strong>، وراجع Client OAuth Login وWeb OAuth Login وEnforce HTTPS.</li>'.
        '<li>أضف <code dir="ltr">app.ontrackegy.com</code> إلى <strong>Allowed Domains for the JavaScript SDK</strong>، واضبط Valid OAuth Redirect URIs المناسبة بحسب إعداد Meta.</li>'.
        '<li>اضغط <strong>Save Changes</strong>، وارجع لمساحة المستخدم وأعد تجربة الربط.</li></ol>'.
        '<p class="dim">مهم: ما بنقدرش نفعّل الخيار ده من API OnTrack؛ لازم صاحب التطبيق يغيّره في Meta. ظهور الحالة «متاح للعملاء» أدناه يعني اكتمال الإعدادات المحلية فقط، مش نجاح اختبار Meta الحي.</p>'.
        '<a class="btn primary" target="_blank" rel="noopener noreferrer" href="https://developers.facebook.com/apps/">فتح إعدادات التطبيق في Meta</a>'.
        '</section>';
    $signupReady=wa_onboarding_ready($meta,'signup');
    $coexistReady=wa_onboarding_ready($meta,'coexist');
    echo '<div class="wa-onboarding-status">'.
         '<span class="pill '.($signupReady?'ok':'neutral').'">الربط التلقائي: '.($signupReady?'الإعداد المحلي مكتمل · Meta لم تُختبر':'غير جاهز').'</span>'.
         '<span class="pill '.($coexistReady?'ok':'neutral').'">Coexistence: '.($coexistReady?'الإعداد المحلي مكتمل · Meta لم تُختبر':'غير جاهز').'</span>'.
         '</div>';
        echo card('WhatsApp · طرق الربط المتاحة للعملاء',
            '<p class="dim">الربط اليدوي وربط واتساب الموجود متاحان لجميع الحسابات. الربط التلقائي وCoexistence لا يظهران إلا بعد تفعيلك إعدادات Meta الخاصة بالمنصة.</p>'.
            '<div class="wa-manual-notice">Embedded Signup v4 يتطلب إعداد Tech Provider أو Solution Partner وتصاريح Meta المناسبة. تفعيل الزر يتيح المسار للعميل، لكنه لا يضمن قبول Meta للرقم حتى إتمام اختبار حقيقي.</div>'.
            form_start('wa_platform_onboarding_save','/?tab=whatsapp_admin','class="wa-settings-form wa-manual-form"').
            '<div class="wa-manual-grid">'.
            '<div class="wa-manual-cell half"><label class="field">Meta Platform App ID<input name="meta_app_id" dir="ltr" inputmode="numeric" maxlength="100" value="'.e((string)$meta['app_id']).'" placeholder="من Meta Developers"></label><small class="wa-field-help"><a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener noreferrer">التطبيق ← Settings ← Basic</a></small></div>'.
            '<div class="wa-manual-cell half"><label class="field">Meta Platform App Secret<input type="password" name="meta_app_secret" maxlength="1000" autocomplete="new-password" placeholder="'.(!empty($meta['app_secret_enc'])?'محفوظ مشفرًا · اتركه فارغًا للاحتفاظ به':'أدخل App Secret').'" ></label><small class="wa-field-help">السيرفر فقط؛ لا يظهر للعميل إطلاقًا.</small></div>'.
            '<div class="wa-manual-cell half"><label class="field">Embedded Signup v4 Configuration ID<input name="meta_signup_config_id" dir="ltr" inputmode="numeric" maxlength="100" value="'.e((string)$meta['signup_config_id']).'"></label><small class="wa-field-help"><a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener noreferrer">Facebook Login for Business ← Configurations</a></small></div>'.
            '<div class="wa-manual-cell half"><label class="field">Coexistence Configuration ID<input name="meta_coexist_config_id" dir="ltr" inputmode="numeric" maxlength="100" value="'.e((string)$meta['coexist_config_id']).'"></label><small class="wa-field-help">Configuration مهيأ لـWhatsApp Business App Coexistence.</small></div>'.
            '<div class="wa-manual-cell half"><label class="field">Meta Graph API Version<input name="graph_version" dir="ltr" maxlength="8" value="'.e((string)$meta['graph_version']).'" required></label></div>'.
            '<div class="wa-manual-cell half"><label class="field">Platform Webhook Verify Token<input type="password" name="meta_verify_token" maxlength="200" autocomplete="new-password" placeholder="'.(!empty($meta['verify_token_hash'])?'محفوظ كـHash · سيبها فارغة للاحتفاظ به':'توكن تختاره من الإدارة').'" ></label><small class="wa-field-help">Callback منصة Meta: <code>https://app.ontrackegy.com/saas/console/whatsapp-platform-webhook.php</code>. مختلف عن الروابط اليدوية.</small></div>'.
            '</div>'.
            '<label class="wa-relay-toggle"><input type="checkbox" name="signup_enabled" value="1" '.($signupReady?'checked':'').'>إظهار وتفعيل الربط التلقائي للعملاء</label>'.
            '<label class="wa-relay-toggle"><input type="checkbox" name="coexist_enabled" value="1" '.($coexistReady?'checked':'').'>إظهار وتفعيل Coexistence للعملاء</label>'.
            submit_btn('حفظ إعدادات طرق واتساب'));
    echo '<section class="card"><h3>إرشادات الربط</h3>'.
        '<p>الربط التلقائي وCoexistence يحتاجان Meta App وConfiguration ID وتصاريح Meta الحقيقية. عدم اكتمال الإعدادات يعني أن الخيارات لن تظهر للعملاء حتى لو كان مفتاح التفعيل محفوظًا من تجربة سابقة.</p>'.
        '<p><a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener noreferrer">فتح Meta Developers لإدارة التطبيق</a></p>'.
        '<a class="btn" href="/saas/console/?tab=overview">العودة للوحة الإدارة</a></section>';
}

function render_settings(array $a):void{
    $brand=brand($a);
    $mfaActive=mfa_gate_required((int)me()['id']);
    echo card('التحقق بخطوتين',
        $mfaActive
        ?'<p>المصادقة مفعّلة لهذا الحساب. عند تسجيل الدخول ستحتاج كود تطبيق المصادقة.</p><span class="pill ok">مفعّل ومؤكد</span>'
        :'<p>غير مفعّل. تسجيل الدخول يعمل بكلمة المرور، ويمكنك اختيار تفعيله بعد مسح QR وتأكيد كود من التطبيق.</p><a class="btn primary" href="/saas/console/mfa/setup">ربط تطبيق المصادقة وعرض QR</a>');
    if($a['kind']==='platform'&&console_view_mode($a)==='admin'&&in_array($a['role'],['owner','admin'],true)&&!active_support()){
        require_once __DIR__.'/WhatsAppOnboarding.php';
        $meta=wa_platform_onboarding();
        echo card('إعدادات واتساب للمنصة',
            '<p>إدارة الربط التلقائي (Meta Embedded Signup)، وCoexistence، ومفاتيح وتكوينات Meta الخاصة بالمنصة في صفحة مستقلة عن صندوق وارد العميل.</p>'.
            '<a class="btn primary" href="/saas/console/?tab=whatsapp_admin">فتح إعدادات ربط واتساب</a>');
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
    $adminIdentity=in_array($a['kind'],['platform','reseller'],true)&&console_view_mode($a)==='admin';
    $identityTitle=$adminIdentity?($a['kind']==='platform'?'هوية المنصة':'هوية الريسيلر'):'هوية حسابك';
    $identityDescription=$adminIdentity
        ?'هنا بتتحكم في هوية الإدارة والعلامة التجارية. إعدادات وبيانات المستخدم التشغيلية موجودة في مساحة المستخدم.'
        :'المستخدم المباشر يرث OnTrack؛ المستخدم التابع يرث هوية الريسيلر بشكل آمن.';
    echo card($identityTitle,'<div class="brand-preview"><div class="brand-icon">'.e(mb_substr($brand['name'],0,1)).'</div><div><h3>'.e($brand['name']).'</h3><p>ألوان الهوية الأساسية: أحمر / أسود / أبيض</p></div></div><p>'.e($identityDescription).'</p>');
    if(in_array($a['kind'],['reseller','platform'],true)&&console_view_mode($a)==='admin'){
        echo card('تعديل الاسم والشعار',form_start('brand','/?tab=settings','enctype="multipart/form-data"').field_html('الاسم التجاري الظاهر','brand_name','text',$brand['name'],true).'<label class="field">شعار PNG/JPEG/WEBP (حتى 1MB)<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label>'.submit_btn('حفظ الهوية'));
    }else{
        echo card('إعدادات المستخدم','<p>اسم وشعار البرنامج متاحان من علامة الريسيلر الذي تتبع له، أو علامة OnTrack إن كنت عميلاً مباشراً.</p>');
    }
    echo '</div>';
}


/** Owner-managed phone roster; employee membership is mandatory and PIN is never rendered. */
function render_voice_staff_section(array $a,array $members):void {
    $accountId=(int)$a['account_id'];
    $canManage=in_array($a['role'],['owner','admin'],true)&&!active_support();
    $query="SELECT v.id,v.user_id,v.phone_e164,v.scopes_json,v.status,u.name,m.role
        FROM voice_staff_numbers v JOIN users u ON u.id=v.user_id
        JOIN memberships m ON m.account_id=v.account_id AND m.user_id=v.user_id
        WHERE v.account_id=? AND v.status='active'";
    $args=[$accountId];
    if(!$canManage){$query.=' AND v.user_id=?';$args[]=(int)(me()['id']??0);}
    $query.=' ORDER BY v.id DESC';
    $numbers=all($query,$args);
    $phoneRows=[];
    foreach($numbers as $n){
        $actions='';
        if($canManage)
            $actions=form_start('voice_staff_revoke','/?tab=staff','class="inline-form"').
                '<input type="hidden" name="number_id" value="'.(int)$n['id'].'">'.
                '<button type="submit" class="btn small danger">إلغاء الصلاحية</button></form>';
        $scopes=json_decode((string)$n['scopes_json'],true);
        $phoneRows[]=[e($n['name']),'<span dir="ltr">'.e($n['phone_e164']).'</span>',
            e(implode('، ',is_array($scopes)?$scopes:[])),$actions];
    }
    echo card('الأرقام المصرح لها بالسؤال الصوتي',
        '<p class="dim">التعرّف على الرقم لا يكفي لفتح بيانات الحساب: يجب إدخال PIN خاص عبر لوحة أرقام المكالمة بعد مطابقة الشريحة والحساب. عرض البيانات حسب دور الموظف فقط.</p>'.
        tbl(['الموظف','رقم الهاتف','نطاق البيانات','الإجراء'],$phoneRows));
    if(!$canManage)return;
    $scopeLabels=['general'=>'عام','tickets'=>'التذاكر','bookings'=>'الحجوزات',
        'finance'=>'الفواتير والحسابات','websites'=>'المواقع','operations'=>'العمليات'];
    $body='<p class="dim">أضف أكثر من رقم للموظف حسب الحاجة. PIN من 6–10 أرقام، ويُخزَّن كـhash وليس نصًا. لن تُفتح البيانات في المكالمة قبل التحقق.</p>';
    foreach($members as $m){
        $role=(string)$m['role'];$scopes=voice_role_scopes($role);
        if(!$scopes)continue;
        $checks='';
        foreach($scopes as $scope)$checks.='<label class="field"><input type="checkbox" name="scopes[]" value="'.e($scope).'" checked> '.e($scopeLabels[$scope]).'</label>';
        $body.='<details class="row-rename"><summary>ربط رقم: '.e($m['name']).' — '.e($role).'</summary>'.
            form_start('voice_staff_enroll','/?tab=staff').
            '<input type="hidden" name="user_id" value="'.(int)$m['id'].'">'.
            '<label class="field">رقم دولي<input name="phone" type="tel" dir="ltr" placeholder="+201012345678" maxlength="20" required></label>'.
            '<label class="field">رمز تحقق PIN<input name="pin" type="password" inputmode="numeric" autocomplete="new-password" minlength="6" maxlength="10" pattern="[0-9]{6,10}" required></label>'.
            '<div class="stack"><strong>البيانات المتاحة أثناء المكالمة</strong>'.$checks.'</div>'.
            submit_btn('إضافة الرقم وتحديد الصلاحيات').'</details>';
    }
    echo card('إضافة أرقام الموظفين', $body);
}


/** Tenant-isolated WhatsApp Inbox and owner-controlled standalone OnTrack AI. */
function render_whatsapp(array $a):void {
    require_once __DIR__.'/WhatsAppSupport.php';
    require_once __DIR__.'/WhatsAppOnboarding.php';
    $waPlatform=wa_platform_onboarding();
    $id=(int)$a['account_id'];
    $sites=all("SELECT id,name FROM sites WHERE account_id=? AND status='active' ORDER BY id",[$id]);
    $connections=all('SELECT id,site_id,public_id,label,phone_number_id,state,ai_agent_id,ai_enabled,
        waba_id,meta_app_id,graph_version,webhook_relay_enabled,
        last_tested_at,last_webhook_at FROM wa_connections WHERE account_id=? ORDER BY id DESC',[$id]);
    $agents=all("SELECT ag.id,ag.name,s.name site_name FROM agents ag
        JOIN sites s ON s.id=ag.site_id AND s.account_id=ag.account_id
        WHERE ag.account_id=? AND ag.status='published' AND s.status='active' ORDER BY ag.id DESC",[$id]);
    $selected=(int)($_GET['conversation']??0);
    $conversation=$selected?one('SELECT * FROM wa_conversations WHERE id=? AND account_id=?',[$selected,$id]):null;
    $list=all("SELECT c.*,w.label connection_label,
        (SELECT m.body FROM wa_messages m WHERE m.conversation_id=c.id AND m.account_id=c.account_id ORDER BY m.id DESC LIMIT 1) last_text
        FROM wa_conversations c JOIN wa_connections w ON w.id=c.connection_id AND w.account_id=c.account_id
        WHERE c.account_id=? ORDER BY c.last_message_at DESC,c.id DESC LIMIT 100",[$id]);
    echo '<div class="section-line"><div><span class="eyebrow">OFFICIAL META CLOUD API · ONTRACK NATIVE SUPPORT</span>'.
        '<h2>WhatsApp · صندوق الوارد</h2><p>رسائل العملاء والرد البشري والأزرار الذكية، وإيجنت OnTrack مستقل لكل قناة. WHMCS أو Laravel تكاملات اختيارية فقط.</p></div></div>';
    if($message=flash('success'))echo '<p class="info" role="status">'.e($message).'</p>';
    if($message=flash('error'))echo '<p class="info" role="alert">'.e($message).'</p>';
    $relayOnce=$_SESSION['wa_relay_secret_once']??null;
    unset($_SESSION['wa_relay_secret_once']);
    if(is_array($relayOnce)&&(int)($relayOnce['account_id']??0)===$id&&
       preg_match('/^[a-f0-9]{64}$/D',(string)($relayOnce['secret']??''))){
        echo '<div class="wa-manual-notice" role="status">مفتاح التوقيع الجديد للـWebhook الخارجي (رقم الاتصال #'.(int)$relayOnce['connection_id'].') — انسخه الآن. لن يظهر بعد تحديث الصفحة.</div>'.
            '<div class="wa-copy-strip"><span>Webhook Signing Secret</span><code id="wa-relay-rotated-once" dir="ltr">'.e($relayOnce['secret']).'</code><button class="btn small" type="button" data-wa-copy="wa-relay-rotated-once">نسخ المفتاح</button></div>';
    }
    echo '<div class="wa-system-status"><span><strong>قنوات Meta: </strong>'.count($connections).'</span>'.
        '<span><strong>محادثات: </strong>'.count($list).'</span>'.
        '<span><strong>محرّك الرد: </strong>OnTrack AI المستقل · يتفعّل بإيجنت منشور ومفتاح منصة</span></div>';
    echo '<div class="wa-workspace"><aside class="wa-inbox-list"><div class="wa-inbox-title">المحادثات <small>آخر 100</small></div>';
    if(!$list)echo '<div class="wa-empty">مافيش محادثات واردة حتى الآن. اربط Meta وتأكد من وصول رسالة فعلية موقّعة.</div>';
    foreach($list as $conv){
        $name=trim((string)($conv['contact_name']??''))?:$conv['contact'];
        $active=$conversation&&(int)$conversation['id']===(int)$conv['id'];
        echo '<a class="wa-contact'.($active?' active':'').'" href="/saas/console/?tab=whatsapp&conversation='.(int)$conv['id'].'">'.
           '<span class="wa-avatar">✉</span><span class="wa-contact-info"><strong>'.e(mb_substr($name,0,45)).'</strong>'.
           '<small dir="ltr">+'.e($conv['contact']).' · '.e($conv['connection_label']).'</small>'.
           '<span>'.e(mb_substr((string)($conv['last_text']??''),0,90)).'</span></span></a>';
    }
    echo '</aside><section class="wa-conversation">';
    if(!$conversation){
        echo '<div class="wa-empty wa-empty-main"><strong>اختار محادثة لعرض الرسائل</strong><p>لما يتدخل الموظف، بيتوقف رد AI على نفس المحادثة، مش على كل الحساب.</p></div>';
    }else{
        $cid=(int)$conversation['id'];
        $locked=!empty($conversation['human_locked_at']);
        $paused=!empty($conversation['ai_pause_until'])&&strcmp((string)$conversation['ai_pause_until'],now())>0;
        $name=trim((string)($conversation['contact_name']??''))?:$conversation['contact'];
        $return='/?tab=whatsapp&conversation='.$cid;
        $rows=all('SELECT role,body,delivery_status,created_at FROM wa_messages WHERE conversation_id=? AND account_id=? ORDER BY id ASC LIMIT 250',[$cid,$id]);
        echo '<div class="wa-chat-header"><div><strong>'.e($name).'</strong><small dir="ltr">+'.e($conversation['contact']).'</small></div><div class="wa-chat-status">';
        if($locked)echo '<span class="pill bad">موظف فقط · قفل مستمر</span>';
        elseif($paused)echo '<span class="pill neutral">AI متوقف حتى '.e($conversation['ai_pause_until']).' UTC</span>';
        else echo '<span class="pill ok">AI غير متوقف بشريًا · التشغيل حسب إعدادات القناة</span>';
        echo '</div></div><div class="wa-messages" aria-label="سجل الرسائل">';
        if(!$rows)echo '<p class="wa-empty">لا توجد رسائل</p>';
        foreach($rows as $m){
            $role=in_array($m['role'],['customer','staff','ai'],true)?$m['role']:'customer';
            $label=match($role){'staff'=>'الموظف','ai'=>'AI Agent',default=>'العميل'};
            echo '<div class="wa-bubble wa-'.$role.'"><span class="wa-bubble-role">'.e($label).'</span>'.
                '<div>'.nl2br(e((string)$m['body'])).'</div>'.
                '<small>'.e((string)$m['created_at']).' UTC · '.e((string)$m['delivery_status']).'</small></div>';
        }
        echo '</div><div class="wa-controls"><div class="wa-lock-row">';
        if($locked){
            echo form_start('wa_human_release',$return,'class="inline-form"').
                '<input type="hidden" name="conversation_id" value="'.$cid.'">'.
                '<button class="btn small" type="submit">إرجاع المحادثة لـAI عند انتهاء الوقف</button></form>';
        }else{
            echo form_start('wa_human_lock',$return,'class="inline-form"').
                '<input type="hidden" name="conversation_id" value="'.$cid.'">'.
                '<button class="btn small" type="submit">استلام المحادثة بالكامل</button></form>';
        }
        echo '</div>';
        echo form_start('wa_manual_reply',$return,'class="wa-compose"').
           '<input type="hidden" name="conversation_id" value="'.$cid.'">'.
           '<label class="field">رد الموظف<textarea name="body" maxlength="4096" rows="3" required placeholder="اكتب ردك، هيُرسل من نفس رقم Meta الرسمي..."></textarea></label>'.
           '<label class="field">أزرار رد سريعة (اختياري: زر في كل سطر، حتى 3 أزرار، 20 حرف للزر)<textarea name="quick_replies" maxlength="200" rows="2" placeholder="تفاصيل أكثر&#10;التحدث لموظف"></textarea></label>'.
           '<button class="btn primary" type="submit">إرسال عبر واتساب وإيقاف AI لمدة 15 دقيقة</button></form>'.
           '<p class="dim">الرد الحر متاح داخل نافذة 24 ساعة من رسالة العميل، وما بنعتبرش Meta Accepted = Delivered.</p></div>';
    }
    echo '</section></div>';
    if(!allowed('agents.write'))return;
    echo '<details class="wa-settings"'.(!$connections?' open':'').'><summary class="btn">ربط واتساب وإدارة القنوات</summary>'.
        '<div class="wa-settings-grid"><section class="card"><h3>القنوات الموجودة</h3>';
    if(!$connections)echo '<p class="dim">لا توجد قناة مفعّلة.</p>';
    foreach($connections as $conn){
        $webhook=wa_public_callback((string)$conn['public_id']);
        $existingChannel=($conn['onboarding_method']??'')==='existing_webhook';
        echo '<div class="wa-channel-row"><strong>'.e($conn['label']).'</strong> '.pill($conn['state']).
            ($existingChannel?'<small>طريقة الربط: موديول واتساب موجود · لم نطلب أي مفاتيح Meta</small>'.
                '<small>Callback القديم في Meta: '.e((string)($conn['source_callback_url']??'غير محدد')).'</small>'.
                '<small>معرّف الرقم: '.(str_starts_with((string)$conn['phone_number_id'],'pending_')?'يتحدد بأول رسالة موقّعة من الموديول':e((string)$conn['phone_number_id'])).'</small>':
                '<small>Phone Number ID: '.e($conn['phone_number_id']).' · WABA ID: '.e((string)($conn['waba_id']??'غير محفوظ')).'</small>'.
                '<small>Meta App ID: '.e((string)($conn['meta_app_id']??'غير محدد')).' · Graph API: '.e(wa_graph_version($conn)).'</small>').
            '<div class="wa-copy-strip wa-copy-strip-small"><span>'.($existingChannel?'رابط استقبال OnTrack من الموديول':'Callback URL (Meta)').'</span><code id="wa-callback-'.(int)$conn['id'].'" dir="ltr">'.e($webhook).'</code><button type="button" class="btn small" data-wa-copy="wa-callback-'.(int)$conn['id'].'">نسخ</button></div>'.
            ($existingChannel?'<small>ما تغيّرش إعدادات Meta؛ لازم تفعّل Forwarding داخل الموديول الحالي، وموصل الإرسال مش متاح بعد.</small>':
                '<small>Webhook Verify Token محفوظ كـHash فقط. استخدم النسخة التي نسختها أثناء إنشاء الاتصال.</small>').
            '<small>Webhook: '.e((string)($conn['last_webhook_at']??'لم يتم تأكيد استقبال رسالة')).'</small>'.
            '<small>OnTrack AI: '.(!empty($conn['ai_agent_id'])?('إيجنت #'.(int)$conn['ai_agent_id'].' · '.(!empty($conn['ai_enabled'])?'مفعّل':'معطّل')):'بدون إيجنت · الرد اليدوي متاح').'</small>'.
            (!empty($conn['webhook_relay_enabled'])?
                '<small>Webhook من مزوّد واتساب خارجي: مفعّل · نفس رابط الاستقبال أعلاه · مخصص للشركة الحالية.</small>':'<small>Webhook خارجي: غير مفعّل · يمكن تفعيله لهذا الرقم.</small>').
            form_start('wa_relay_rotate','/?tab=whatsapp','class="inline-form"').
            '<input type="hidden" name="connection_id" value="'.(int)$conn['id'].'">'.
            '<button class="btn small" type="submit">'.(!empty($conn['webhook_relay_enabled'])?'تغيير مفتاح Webhook':'تفعيل ربط Webhook خارجي').'</button></form>'.
            (!empty($conn['webhook_relay_enabled'])?
                form_start('wa_relay_disable','/?tab=whatsapp','class="inline-form"').
                '<input type="hidden" name="connection_id" value="'.(int)$conn['id'].'">'.
                '<button class="btn small" type="submit">إيقاف استقبال Webhook الخارجي</button></form>':'').
            (!$existingChannel?form_start('wa_connection_test','/?tab=whatsapp','class="inline-form"').
            '<input type="hidden" name="connection_id" value="'.(int)$conn['id'].'">'.
            '<button class="btn small" type="submit">اختبار مفتاح Meta والرقم</button></form>':'').
            (!empty($conn['ai_agent_id'])&&!$existingChannel?
                form_start('wa_native_toggle','/?tab=whatsapp','class="inline-form"').
                '<input type="hidden" name="connection_id" value="'.(int)$conn['id'].'">'.
                '<input type="hidden" name="enabled" value="'.(!empty($conn['ai_enabled'])?'0':'1').'">'.
                '<button class="btn small" type="submit">'.(!empty($conn['ai_enabled'])?'إيقاف رد OnTrack AI':'تفعيل رد OnTrack AI').'</button></form>':'').

            '</div>';
    }
    echo '</section><section class="card wa-manual-card"><div class="wa-manual-heading"><div><span class="eyebrow">META CLOUD API · MANUAL SETUP</span><h3>ربط واتساب يدويًا</h3><p class="dim">البيانات تخص شركتك الحالية فقط؛ مش مطلوب أي ربط بموديول WHMCS.</p></div><span class="pill neutral">Manual Connection</span></div>';
    if(!$sites)echo '<p>أضف موقعًا نشطًا أولًا.</p>';
    else {
        $map=[];foreach($sites as $site)$map[(int)$site['id']]=$site['name'];
        $agentOptions=[0=>'بدون AI — الربط فقط'];
        foreach($agents as $agent)$agentOptions[(int)$agent['id']]=$agent['site_name'].' · '.$agent['name'];
        // Generated fresh on the authenticated server. The callback is NOT active
        // until POST succeeds. The verify token is never persisted in plaintext.
        $publicId=bin2hex(random_bytes(18));
        $verifyToken=bin2hex(random_bytes(24));
        $relaySecret=bin2hex(random_bytes(32));
        $webhook=wa_public_callback($publicId);
        $showSignup=wa_onboarding_ready($waPlatform,'signup');
        $showCoexist=wa_onboarding_ready($waPlatform,'coexist');
        echo '<div class="wa-mode-picker" role="group" aria-label="طريقة ربط واتساب">'.
            '<button type="button" class="wa-mode-button active" data-wa-method="meta_direct" aria-pressed="true"><strong>ربط Meta جديد</strong><small>Webhook مباشر إلى OnTrack</small></button>'.
            '<button type="button" class="wa-mode-button" data-wa-method="existing_webhook" aria-pressed="false"><strong>واتساب مربوط بالفعل</strong><small>احتفظ بربطك الحالي وأضف AI</small></button>'.
            ($showSignup?'<button type="button" class="wa-mode-button" data-wa-method="signup" aria-pressed="false"><strong>الربط التلقائي</strong><small>Meta Embedded Signup</small></button>':'').
            ($showCoexist?'<button type="button" class="wa-mode-button" data-wa-method="coexist" aria-pressed="false"><strong>Coexistence</strong><small>WhatsApp Business App + API</small></button>':'').
            '</div><div data-wa-method-panel="meta_direct">';
        echo '<div class="wa-manual-notice">الربط اليدوي يحتاج بيانات Meta الخاصة برقمك. بعد الحفظ اختبر الربط، ثم انسخ Callback URL وVerify Token إلى إعدادات Webhook في تطبيق Meta. <strong>التحقق من الرقم لا يعني تشغيل استقبال الرسائل تلقائيًا.</strong></div>';
        echo form_start('wa_connection_add','/?tab=whatsapp','class="wa-settings-form wa-manual-form"').
            '<input type="hidden" name="public_id" value="'.e($publicId).'">'.
            '<div class="wa-manual-grid">'.
            '<div class="wa-manual-cell third">'.select_html('الموقع / مساحة العمل','site_id',$map).'<small class="wa-field-help">من <a href="/saas/console/?tab=sites">مواقعي داخل OnTrack</a>، ولو مش موجود أضفه هناك.</small></div>'.
            '<div class="wa-manual-cell third">'.select_html('الإيجنت الذي يرد على واتساب','ai_agent_id',$agentOptions).'<small class="wa-field-help">من <a href="/saas/console/?tab=agents">Agent Studio</a>، اختار إيجنت منشور.</small></div>'.
            '<div class="wa-manual-cell third"><label class="field">اسم الربط<input type="text" name="label" maxlength="120" placeholder="مبيعات / خدمة العملاء" required></label><small class="wa-field-help">اسم تختاره أنت للتنظيم، زي مبيعات أو خدمة عملاء. مش هتجيبه من Meta.</small></div>'.
            '<div class="wa-manual-cell half"><label class="field">Business Account ID (WABA ID)<input name="waba_id" inputmode="numeric" pattern="[0-9]+" maxlength="100" autocomplete="off" dir="ltr" placeholder="WABA ID" required></label><small class="wa-field-help">من <a href="https://business.facebook.com/settings/whatsapp-business-accounts" target="_blank" rel="noopener noreferrer">Meta Business Settings ← WhatsApp Accounts</a> ثم اختار حسابك وانسخ WABA ID.</small></div>'.
            '<div class="wa-manual-cell half"><label class="field">Phone Number ID<input name="phone_number_id" inputmode="numeric" pattern="[0-9]+" maxlength="100" autocomplete="off" dir="ltr" placeholder="Phone Number ID" required></label><small class="wa-field-help">من <a href="https://business.facebook.com/wa/manage/phone-numbers/" target="_blank" rel="noopener noreferrer">WhatsApp Manager ← Phone Numbers</a>؛ انسخ Phone Number ID مش رقم الموبايل.</small></div>'.
            '<div class="wa-manual-cell third"><label class="field">Meta App ID<input name="meta_app_id" inputmode="numeric" pattern="[0-9]*" maxlength="100" autocomplete="off" dir="ltr" placeholder="اختياري"></label><small class="wa-field-help">من <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener noreferrer" data-wa-meta-app-link="settings">Meta Developers ← تطبيقك ← Settings ← Basic</a>.</small></div>'.
            '<div class="wa-manual-cell third"><label class="field">App Secret<input name="app_secret" type="password" maxlength="1000" autocomplete="new-password" placeholder="يُحفظ مشفرًا" required></label><small class="wa-field-help">من <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener noreferrer" data-wa-meta-app-link="settings">App Settings ← Basic ← App Secret ← Show</a> (ما تشاركوش مع أي حد).</small></div>'.
            '<div class="wa-manual-cell third"><label class="field">Access Token<input name="access_token" type="password" maxlength="2000" autocomplete="new-password" placeholder="يُحفظ مشفرًا" required></label><small class="wa-field-help">من <a href="https://business.facebook.com/settings/system-users" target="_blank" rel="noopener noreferrer">Business Settings ← Users ← System Users ← Generate Token</a> بصلاحيات whatsapp_business_messaging وwhatsapp_business_management.</small></div>'.
            '<div class="wa-manual-cell half"><label class="field">Graph API Version<input name="graph_version" value="v26.0" pattern="v[0-9]{1,2}\\.0" maxlength="8" dir="ltr" required></label><small class="wa-field-help">الإصدار المتاح لتطبيقك من <a href="https://developers.facebook.com/docs/graph-api/guides/versioning/" target="_blank" rel="noopener noreferrer">Meta Graph API Versioning</a>.</small></div>'.
            '<div class="wa-manual-cell half"><label class="field">Webhook Verify Token<input name="verify_token" type="text" value="'.e($verifyToken).'" minlength="12" maxlength="200" readonly dir="ltr" autocomplete="off" spellcheck="false" required></label><small class="wa-field-help">ده رمز بيولّده OnTrack؛ انسخه في <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener noreferrer">Meta Developers ← تطبيقك ← WhatsApp ← Configuration ← Webhook</a>.</small></div>'.
            '</div>'.
            '<div class="wa-manual-copy-group">'.
            '<p class="wa-field-help">الرابط ده من OnTrack، انسخه زي ما هو إلى <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener noreferrer">Meta Developers ← WhatsApp ← Configuration ← Webhook</a>، ولا تغيّر Callback القديم لو بتستخدم تكامل واتساب موجود بالفعل.</p><div class="wa-copy-strip"><span>Callback URL</span><code id="wa-new-callback" dir="ltr">'.e($webhook).'</code><button type="button" class="btn small" data-wa-copy="wa-new-callback">نسخ الرابط</button></div>'.
            '<div class="wa-copy-strip"><span>Verify Token</span><code id="wa-new-token" dir="ltr">'.e($verifyToken).'</code><button type="button" class="btn small" data-wa-copy="wa-new-token">نسخ التوكن</button></div>'.
            '</div>'.
            '<details class="wa-relay-options"><summary>Webhook من موديول واتساب أو تكامل آخر (اختياري)</summary>'.
            '<p class="dim">لو واتساب مربوط بالفعل بموديول WhatsApp Notifications أو أي تكامل يستقبل الرسائل، خلّي التكامل يرسل نفس أحداث Meta كما هي أو حدثًا منظّمًا إلى رابط OnTrack بالأعلى. <strong>مجرد وضع عنوان Webhook القديم هنا لا يربط الرسائل</strong>. الربط ده للأحداث الواردة فقط، والردود تخرج من بيانات Meta Cloud API المرتبطة بهذا الرقم.</p>'.
            '<label class="wa-relay-toggle"><input type="checkbox" name="use_webhook_relay" value="1">تفعيل استقبال أحداث Webhook الموقّعة من التكامل الخارجي</label>'.
            '<div class="wa-copy-strip"><span>Webhook Signing Secret</span><code id="wa-new-relay-secret" dir="ltr">'.e($relaySecret).'</code><button type="button" class="btn small" data-wa-copy="wa-new-relay-secret">نسخ المفتاح</button></div>'.
            '<input type="hidden" name="relay_secret" value="'.e($relaySecret).'">'.
            '<p class="dim">يوقّع التكامل جسم طلب POST الخام باستخدام HMAC-SHA256 بهذا المفتاح ويرسله في Header باسم <code>X-OnTrack-Webhook-Signature-256</code> وقيمة <code>sha256=&lt;hex&gt;</code>. استخدم نفس Callback URL للرقم. مفتاح توقيع منفصل عن Verify Token الخاص بـMeta.</p>'.
            '</details>'.
            '<div class="wa-manual-foot"><p>انسخ الرابط والتوكن <strong>قبل الحفظ</strong>؛ التوكن يتخزن Hash فقط ولن يظهر مرة تانية. لو حدّثت الصفحة قبل الحفظ هيتولد رابط وتوكن جديدين. الحالة تبدأ مسودة ولا يتم إرسال رسائل تلقائيًا.</p>'.
            '<button class="btn primary" type="submit">حفظ إعدادات واتساب اليدوية</button></div></form>';
        echo '</div>';
        $existingPublic=bin2hex(random_bytes(18));
        $existingRelaySecret=bin2hex(random_bytes(32));
        $existingCallback=wa_public_callback($existingPublic);
        echo '<div data-wa-method-panel="existing_webhook" hidden>'.
            '<div class="wa-manual-notice"><strong>واتساب مربوط بموديول بالفعل؟</strong> مش محتاج WABA ID ولا Phone Number ID ولا Access Token ولا App Secret ولا Graph API. سيب إعدادات Meta الحالية زي ما هي؛ الموديول الحالي هيبعت الأحداث الموقّعة لـOnTrack. ولإرسال رد AI من نفس الرقم، لازم موصل إرسال خاص بالموديول يتجهز ويتختبر.</div>'.
            form_start('wa_existing_connection_add','/?tab=whatsapp','class="wa-settings-form wa-manual-form"').
            '<input type="hidden" name="public_id" value="'.e($existingPublic).'">'.
            '<input type="hidden" name="relay_secret" value="'.e($existingRelaySecret).'">'.
            '<div class="wa-manual-grid">'.
            '<div class="wa-manual-cell third">'.select_html('الموقع','site_id',$map).'</div>'.
            '<div class="wa-manual-cell third">'.select_html('الإيجنت المنشور','ai_agent_id',$agentOptions).'</div>'.
            '<div class="wa-manual-cell third"><label class="field">اسم الاتصال<input name="label" required maxlength="120" placeholder="واتساب الموجود"></label></div>'.
            '<div class="wa-manual-cell half"><label class="field">Callback URL القديم<input name="source_callback_url" dir="ltr" type="url" placeholder="https://example.com/whatsapp/webhook.php" maxlength="400" required></label><small class="wa-field-help">هات الرابط من إعدادات <strong>الموديول الحالي</strong>، مش من OnTrack. <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener noreferrer">تأكد منه في Meta ← WhatsApp ← Configuration</a>.</small></div>'.
            '<div class="wa-manual-cell half"><label class="field">Verify Token القديم<input name="source_verify_token" type="password" maxlength="200" autocomplete="off" required></label><small class="wa-field-help">التوكن الموجود بالفعل في إعدادات الموديول وMeta؛ مش هيتغير أو يُستخدم لإرسال الرسائل.</small></div>'.
            '</div>'.
            '<div class="wa-manual-copy-group"><div class="wa-copy-strip"><span>OnTrack Receiver URL</span><code id="wa-existing-receiver" dir="ltr">'.e($existingCallback).'</code><button type="button" class="btn small" data-wa-copy="wa-existing-receiver">نسخ</button></div>'.
            '<div class="wa-copy-strip"><span>Forwarding Signing Key</span><code id="wa-existing-signing" dir="ltr">'.e($existingRelaySecret).'</code><button type="button" class="btn small" data-wa-copy="wa-existing-signing">نسخ</button></div></div>'.
            '<p class="dim">انسخ مفتاح التوقيع قبل الحفظ؛ مش هيتعرض تاني. <strong>ما تغيّرش Callback URL القديم داخل Meta.</strong> رابط OnTrack ده بتحطه في إعدادات Forwarding عند موديول واتساب الموجود، مش في Meta. استقبال الرسائل اختبار منفصل، والردود مش هتشتغل لحد ما نوصل واجهة الإرسال بتاعة الموديول.</p>'.
            '<button class="btn primary" type="submit">ربط واتساب الموجود</button></form></div>';
        foreach(['signup'=>'الربط التلقائي عبر Meta','coexist'=>'ربط WhatsApp Business App بدون فصله'] as $mode=>$heading){
            if(!wa_onboarding_ready($waPlatform,$mode))continue;
            $cfg=$mode==='signup'?$waPlatform['signup_config_id']:$waPlatform['coexist_config_id'];
            echo '<div data-wa-method-panel="'.e($mode).'" hidden>'.
                '<div class="wa-manual-notice">'.e($heading).' — هتفتح نافذة Meta الرسمية للموافقة. الإعدادات والأسرار تفضل عند إدارة OnTrack، والعميل مايدخلش أي Access Token. <strong>لو ظهرت JSSDK Option is Not Toggled</strong>، لازم أدمن تطبيق Meta يفعّل Login with the JavaScript SDK من Facebook Login for Business → Settings، ويضيف app.ontrackegy.com إلى Allowed Domains. مش محتاج تغيّر Webhook الرسائل.</div>'.
                form_start('wa_embedded_finish','/?tab=whatsapp',
                    'class="wa-embedded-form" data-wa-embedded-form="'.e($mode).'"').
                '<input type="hidden" name="mode" value="'.e($mode).'">'.
                '<input type="hidden" name="code" value="">'.
                '<input type="hidden" name="waba_id" value="">'.
                '<input type="hidden" name="phone_number_id" value="">'.
                '<div class="wa-manual-grid"><div class="wa-manual-cell half">'.select_html('الموقع','site_id',$map).'</div>'.
                '<div class="wa-manual-cell half">'.select_html('الإيجنت المنشور','ai_agent_id',$agentOptions).'</div></div>'.
                '<div class="wa-embedded-button-row"><button type="button" class="btn primary" data-wa-embedded-start
                    data-wa-app-id="'.e((string)$waPlatform['app_id']).'"
                    data-wa-graph-version="'.e((string)$waPlatform['graph_version']).'"
                    data-wa-config-id="'.e((string)$cfg).'"
                    data-wa-embedded-mode="'.e($mode).'">الربط عبر Meta</button>'.
                '<p class="dim" data-wa-embedded-status role="status">لن يتم إنشاء اتصال إلا بعد موافقة Meta والتحقق من ملكية WABA والرقم.</p></div></form></div>';
        }
    }
    echo '</section></div></details>';
}
