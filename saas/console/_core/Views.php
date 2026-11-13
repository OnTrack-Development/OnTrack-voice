<?php
declare(strict_types=1);
require_once __DIR__.'/VoiceAccess.php';
// Preload for SOG/white-label agent workspace; visiting Voices first is not required.
require_once __DIR__.'/IoPremiumProvider.php';

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
        'invoices'=>['▤','الفواتير'],
        'settings'=>['⚙','الإعدادات']
    ];
    // Administrative spaces manage tenants, plans and platform integrations.
    // Operational tools belong ONLY to the separate User Workspace preview.
    if($type==='platform'){
        $nav=[
        'overview'=>['⌂','نظرة عامة'],
        'accounts'=>['♧','الحسابات والريسيلرز'],
        'plans'=>['◫','الخطط والحدود'],
        'billing'=>['▣','الفوترة'],
        'invoices'=>['▤','الفواتير'],
        'staff'=>['♙','فريق المنصة'],
        'voices'=>['♫','إدارة الأصوات'],
        'integrations'=>['⇄','تكاملات المنصة'],
        'whatsapp_admin'=>['✉','إعدادات ربط واتساب'],
        'releases'=>['↓','تطبيق Android'],
        'settings'=>['⚙','إعدادات المنصة']
        ];
        if(!platform_resellers_visible())$nav['accounts']=['♧','الحسابات'];
        return $nav;
    }
    if($type==='reseller'){
        $nav=[
        'overview'=>['⌂','نظرة عامة'],
        'accounts'=>['♧','عملائي'],
        'plans'=>['◫','باقات العملاء'],
        'billing'=>['▣','الاشتراكات'],
        'invoices'=>['▤','فواتير العملاء'],
        'staff'=>['♙','الفريق والأدوار'],
        'voices'=>['♫','الأصوات المتاحة'],
        'domains'=>['◎','دومين شركتي'],
        'integrations'=>['⇄','تكاملات الشركة'],
        'releases'=>['↓','تطبيق Android'],
        'settings'=>['⚙','إعدادات الشركة']
        ];
        return $nav;
    }
    $nav=[
        'overview'=>['⌂','الرئيسية'],'sites'=>['◈','مواقعي'],
        'agents'=>['✧','استوديو المساعدين'],
        'answering'=>['☷','إعدادات الرد'],'whatsapp'=>['✉','WhatsApp Inbox'],'campaigns'=>['▤','الحملات'],
        'calls'=>['☏','سجل المكالمات'],'contacts'=>['♧','جهات الاتصال'],'devices'=>['▣','الأجهزة والخطوط'],
        'voices'=>['♫','الأصوات'],'staff'=>['♙','الموظفون'],
        'integrations'=>['⇄','التكاملات'],'billing'=>['▣','الباقة والرصيد'],'invoices'=>['▤','فواتيري'],
        'releases'=>['↓','Android'],'settings'=>['⚙','الإعدادات']
    ];
    return $nav;
}
function layout_open(array $a,string $tab):void{
    require_once __DIR__.'/ConsoleTypography.php';
    $accountFont=console_font_effective($a);
    require_once __DIR__.'/ConsoleTheme.php';
    $themeVars=console_theme_attribute((int)$a['account_id']);
    require_once __DIR__.'/BrandIdentity.php';
    $brand=brand_identity_brand($a);
    $display=brand_identity_mode($a);
    if($display==='image' && empty($brand['logo']))$display='text';
    $u=me();$items=nav_entries((string)$a['kind']);$heading=$items[$tab][1]??'الرئيسية';
    $role=white_label_context()
        ?(console_view_mode($a)==='user'?'مساحة العمل':'لوحة الإدارة')
        :(console_view_mode($a)==='user'?'USER WORKSPACE':match($a['kind']){'platform'=>'ADMIN CONTROL','reseller'=>'إدارة الشركة',default=>'USER WORKSPACE'});
    $support=active_support()!==null;
    header('Content-Type: text/html; charset=utf-8');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Cache-Control: private,no-store');
    // A stale CSS/JS cache must not render new server HTML with the old layout.
    $uiVersion=substr(hash('sha256',hash_file('sha256',__DIR__.'/../assets/css/app.css').
        hash_file('sha256',__DIR__.'/../assets/js/app.js').
        hash_file('sha256',__DIR__.'/../assets/js/flow-graph.js')),0,12);
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#121215"><title>'.e($heading).' | '.e($brand['name']).'</title><link rel="stylesheet" href="/saas/console/assets/css/app.css?v='.$uiVersion.'"><script src="/saas/console/assets/js/app.js?v='.$uiVersion.'" defer></script><script src="/saas/console/assets/js/graph-routing.js?v='.$uiVersion.'"></script><script src="/saas/console/assets/js/flow-graph.js?v='.$uiVersion.'" defer></script><script src="/saas/console/assets/js/qrcode.js?v='.$uiVersion.'" defer></script><script src="/saas/console/assets/js/pairing-qr.js?v='.$uiVersion.'" defer></script></head><body data-ot-font="'.e($accountFont).'" data-ot-account="'.(int)$a['account_id'].'"'.$themeVars.'>';
    echo '<div class="app-shell"><aside class="sidebar" id="mainNavigation" aria-label="القائمة الرئيسية"><button type="button" class="nav-close" id="navClose" aria-label="إغلاق القائمة">×</button><a href="/saas/console/" class="brand" data-brand-display="'.e($display).'">';
    if($display!=='text' && !empty($brand['logo']))
        echo '<span class="brand-logo"><img src="/brand-logo?v='.rawurlencode((string)$brand['logo']).'" alt="'.e($brand['name']).'" decoding="async"></span>';
    if($display!=='image')
        echo '<span class="brand-lines"><strong>'.e((string)$brand['name']).'</strong><small>'.e($role).'</small></span>';
    echo '</a>';
    echo '<nav class="nav-list" aria-label="أقسام المنصة">';
    $administrative=in_array($a['kind'],['platform','reseller'],true)&&
        console_view_mode($a)==='admin'&&!$support;
    $groups=[
        'overview'=>[
            'الرئيسية وحالة التشغيل',
            ['overview', 'sites']
        ],
        'agents'=>[
            'الإيجنتات والأصوات وFlow Builder',
            ['agents', 'flows', 'answering', 'voices']
        ],
        'telephony'=>[
            'الشرائح والمكالمات والحملات',
            ['devices', 'calls', 'campaigns', 'contacts']
        ],
        'clients_billing'=>[
            $administrative ? 'العملاء والريسيلرز والباقات والفوترة' : 'الباقات والفوترة',
            ['accounts', 'plans', 'billing', 'invoices', 'domains']
        ],
        'settings_admin'=>[
            'الإعدادات والتكاملات والإدارة',
            ['staff', 'whatsapp_admin', 'whatsapp', 'integrations', 'keys', 'releases', 'settings']
        ]
    ];
    foreach($groups as $group=>$groupData){
        $visible=array_intersect_key($items,array_flip($groupData[1]));
        if(!$visible)continue;
        $isCurrent=array_key_exists($tab,$visible);
        echo '<details class="nav-group" data-nav-group="'.e((string)$group).'"'.($isCurrent?' open':'').'><summary class="nav-group-title">'.e($groupData[0]).'<span aria-hidden="true">⌄</span></summary><div class="nav-group-links">';
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
        $backLabel=($origin['kind']??'')==='reseller'?'العودة إلى إدارة الشركة':'العودة للوحة مدير المنصة';
        echo '<div class="workspace-switcher" aria-label="تبديل مساحة العمل">'.
            '<span class="workspace-switcher-title">أنت داخل حساب '.e((string)$a['name']).' للدعم</span>';
        if(console_can_switch_support_view($a)){
            $to=console_view_mode($a)==='user'?'admin':'user';
            $toLabel=$to==='user'?'عرض واجهة المستخدم للريسيلر':'عرض واجهة إدارة الريسيلر';
            echo form_start('set_support_view','','class="workspace-switch-form" data-no-ajax="1"').
                '<input type="hidden" name="view" value="'.e($to).'">'.
                '<button type="submit" class="workspace-switch-button">⇄ '.e($toLabel).'</button></form>';
        }
        echo form_start('support_leave','','class="workspace-switch-form" data-no-ajax="1"').
            '<button type="submit" class="workspace-switch-button">↩ '.e($backLabel).'</button></form></div>';
    }elseif(console_can_switch_view($a)){
        $to=console_view_mode($a)==='user'?'admin':'user';
        $toLabel=$to==='user'?'الانتقال لمساحة المستخدم':
            (($a['kind']==='reseller')?'العودة إلى إدارة الشركة':'العودة للوحة مدير المنصة');
        echo '<div class="workspace-switcher" aria-label="تبديل مساحة العمل">'.
            '<span class="workspace-switcher-title">المساحة الحالية · '.($to==='user'?'إدارة الحساب':'مساحة المستخدم').'</span>'.
            form_start('set_view','','class="workspace-switch-form" data-no-ajax="1"').
            '<input type="hidden" name="view" value="'.e($to).'">'.
            '<button type="submit" class="workspace-switch-button">⇄ '.e($toLabel).'</button></form></div>';
    }
    echo '<div class="side-footer"><span class="live-dot"></span> '.e((string)$brand['name']).' <small>منصة إدارة أعمالك</small></div></aside><button type="button" class="nav-backdrop" id="navBackdrop" aria-label="إغلاق القائمة" tabindex="-1"></button><div class="main-area">';
    echo '<header class="topbar"><button type="button" class="icon-button nav-toggle" id="navToggle" aria-controls="mainNavigation" aria-expanded="false" aria-label="فتح القائمة">☰</button><div class="headline"><span class="eyebrow">'.e($role).'</span><h1>'.e($heading).'</h1></div><div class="header-actions">';
    $members=memberships((int)$u['id']);
    if(count($members)>1&&!$support){
        echo form_start('switch','','data-no-ajax="1"');$options=[];
        foreach($members as $m)$options[$m['account_id']]=$m['name'].' · '.$m['kind'];
        echo select_html('المساحة','account_id',$options).submit_btn('تبديل');
    }
    if($support){
        $origin=account((int)($a['support_origin']??0));
        if(console_can_switch_support_view($a)){
            $next=console_view_mode($a)==='user'?'admin':'user';
            echo form_start('set_support_view','','class="inline-form workspace-return-top" data-no-ajax="1"').
                '<input type="hidden" name="view" value="'.e($next).'">'.
                '<button type="submit" class="btn small primary">⇄ '.($next==='user'?'واجهة مستخدم الريسيلر':'إدارة الريسيلر').'</button></form>';
        }
        echo form_start('support_leave','','class="inline-form workspace-return-top" data-no-ajax="1"').
            '<button type="submit" class="btn small primary">'.
            (($origin['kind']??'')==='reseller'?'↩ إدارة الشركة':'↩ لوحة المدير').
            '</button></form>';
    }elseif(console_can_switch_view($a)){
        $targetView=console_view_mode($a)==='user'?'admin':'user';
        $switchText=$targetView==='user'?'مساحة المستخدم':
            (($a['kind']==='reseller')?'إدارة الشركة':'لوحة المدير');
        echo form_start('set_view','','class="inline-form workspace-return-top" data-no-ajax="1"').
            '<input type="hidden" name="view" value="'.e($targetView).'">'.
            '<button type="submit" class="btn small primary">⇄ '.e($switchText).'</button></form>';
    }
    echo '<button type="button" class="icon-button" id="themeToggle" aria-label="تغيير المظهر" title="تغيير المظهر">☀</button>';
    echo '<div class="profile"><span class="avatar">'.e(mb_substr($u['name'],0,1)).'</span><span><strong>'.e($u['name']).'</strong><small>'.e($a['role']??'').'</small></span></div>';
    echo form_start('logout','','class="compact"').'<button type="submit" class="logout-btn" title="تسجيل الخروج">↪</button></form>';
    echo '</div></header><main class="content">';
    require_once __DIR__.'/StaffInvitations.php';
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
    if($pairCode=flash('device_pair_code')){
        // One-use six digits bound to a specific SIM. Copying works on mobile.
        $slot=(int)(flash('device_pair_slot')??0);
        $slot=in_array($slot,[0,1],true)?$slot:0;
        $pairCode=(string)$pairCode;
        if(preg_match('/^[0-9]{6}$/D',$pairCode)){
            echo '<section class="alert secret ot-pair-six" aria-label="رمز ربط الشريحة">'.
                '<strong>كود ربط SIM '.($slot+1).'</strong>'.
                '<div id="ot-sim-six-copy" class="ot-pair-six-digits" dir="ltr" style="font-size:38px;font-weight:900;letter-spacing:.16em;text-align:center;padding:14px;user-select:all">'.
                e($pairCode).'</div>'.
                '<button type="button" class="btn primary small" data-wa-copy="ot-sim-six-copy">نسخ الكود</button>'.
                '<p role="status">افتح التطبيق ← ربط SIM '.($slot+1).' ← الصق الستة أرقام واضغط تأكيد.</p>'.
                '<small>صالح 5 دقائق ولمرة واحدة فقط. لا تشارك الكود مع أحد.</small></section>';
        }
    }
    // Synthetic rows retain explicit DEMO marks; no persistent warning bars.
}
function layout_close():void{
    $context=mine();
    $label=$context?brand($context)['name']:'';
    echo '</main><footer class="footer">© '.date('Y').' · '.e((string)$label).' · جميع الحقوق محفوظة</footer></div></div></body></html>';
}
function login_page(?string $error=null):void{
    header('Content-Type: text/html; charset=utf-8');
    header('X-Frame-Options: DENY');header('Cache-Control: no-store');
    $portal=white_label_context();
    $loginBrand=white_label_brand_account();
    require_once __DIR__.'/ConsoleTypography.php';
    $portalFont=$loginBrand?console_font_tenant((int)$loginBrand['id']):'plex';
    require_once __DIR__.'/ConsoleTheme.php';
    $portalTheme=console_theme_attribute($loginBrand?(int)$loginBrand['id']:0);
    $brand=white_label_identity();
    require_once __DIR__.'/BrandIdentity.php';
    $portalMode=$loginBrand?brand_identity_mode($loginBrand):'text';
    if($portalMode==='image' && empty($loginBrand['logo_path']))$portalMode='text';
    $name=(string)$brand['name'];
    $signup=white_label_url('/register');
    $login=white_label_url('/login');
    $forgot=white_label_url('/forgot-password');
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>تسجيل الدخول | '.e($name).'</title><link rel="stylesheet" href="/saas/console/assets/css/app.css"><script src="/saas/console/assets/js/app.js" defer></script></head><body data-ot-font="'.e($portalFont).'" data-ot-account="'.($loginBrand?(int)$loginBrand['id']:0).'"'.$portalTheme.' class="login-body'.($portal?' white-label-auth':'').'"><div class="login-backdrop"></div><main class="login-panel wl-auth-panel"><div class="login-brand" data-brand-display="'.e($portalMode).'">'.white_label_logo_html().'<div class="wl-brand-copy"><strong>'.e($name).'</strong><small>'.($portal?'منصتك لإدارة أعمالك':'منصة الأعمال الذكية').'</small></div></div><div class="login-intro"><span class="eyebrow">'.($portal?'مرحباً بك في '.e($name):'SMART VOICE PLATFORM').'</span><h1>أهلاً برجوعك</h1><p>أدر حسابك وخدماتك وفريقك بسهولة من مكان واحد.</p></div>';
    if($error)echo '<div class="alert danger">'.e($error).'</div>';
    if($notice=flash('success'))echo '<div class="alert success">'.e($notice).'</div>';
    echo '<form method="post" action="'.e($login).'" class="stack"><input type="hidden" name="csrf" value="'.e(csrf()).'"><label class="field">البريد الإلكتروني<input required type="email" name="email" autocomplete="username" placeholder="name@company.com"></label><label class="field">كلمة المرور<div class="password-wrap"><input required type="password" name="password" autocomplete="current-password" id="loginPassword" placeholder="••••••••••"><button type="button" class="password-toggle" data-toggle-password>إظهار</button></div></label><button class="btn primary wide" type="submit">تسجيل الدخول</button></form><div class="wl-auth-links"><a href="'.e($forgot).'">نسيت كلمة المرور؟</a><a href="'.e($signup).'">إنشاء حساب جديد</a></div>';
    if(!$portal&&platform_resellers_visible())echo '<p class="login-foot"><a href="/register?type=reseller">إنشاء حساب مزوّد خدمات</a></p>';
    echo '<p class="wl-copyright">© '.date('Y').' '.e($name).' · جميع الحقوق محفوظة</p></main></body></html>';
}

/**
 * Read ONLY the official on-domain native OTA release manifest. No arbitrary
 * URL, path from the user, CDN redirect, or fake download link.
 */
function ontrack_native_public_release():?array {
    // Deployed console is /public_html/index.php + /public_html/_core/.
    // The signed OTA manifest lives in /public_html/mobile/updates/native/.
    // Three parent traversals escape public_html and hide an existing APK.
    $file=__DIR__.'/../mobile/updates/native/latest.json';
    if(!is_file($file)||is_link($file)||filesize($file)>16384)return null;
    $envelope=json_decode((string)file_get_contents($file),true);
    if(!is_array($envelope)||!is_string($envelope['payload']??null)||
       !is_string($envelope['signature']??null)||
       base64_decode($envelope['signature'],true)===false)return null;
    $data=json_decode($envelope['payload'],true);
    if(!is_array($data)||($data['schema']??null)!==1||
       ($data['channel']??null)!=='stable'||
       ($data['package']??null)!=='com.ontrack.voiceproof')return null;
    $name=$data['file']??null;
    if(!is_string($name)||!preg_match('/^ontrack-voice-v([0-9]{1,9})-([a-f0-9]{64})\\.apk$/D',$name,$m)||
       (int)$m[1]!==(int)($data['version_code']??0)||
       $m[2]!==($data['sha256']??null)||empty($data['version_name'])||
       (int)($data['size']??0)<1000000)return null;
    $apk=dirname($file).'/'.$name;
    if(is_link($apk)||!is_file($apk)||filesize($apk)!==(int)$data['size'])return null;
    // The canonical OTA host also serves reseller white-label domains.
    // Use an absolute approved URL instead of assuming /mobile exists there.
    return ['name'=>(string)$data['version_name'],'code'=>(int)$m[1],
      'url'=>'https://app.ontrackegy.com/mobile/updates/native/'.rawurlencode($name)];
}
function ontrack_update_banner():string {
    $release=ontrack_native_public_release();
    $status=$release
        ?'الإصدار '.e($release['name']).' متاح للتنزيل من سيرفر OnTrack'
        :'افحص آخر إصدار من مركز التحديثات قبل التنزيل';
    $action=$release
        ?'<a class="btn primary" href="'.e($release['url']).'" download>↓ تنزيل APK</a>'
        :'<a class="btn primary" href="/saas/console/?tab=releases">فحص الإصدار</a>';
    return '<section class="ot-master-update" aria-labelledby="otReleaseHeading">'.
        '<div class="ot-master-update-icon" aria-hidden="true">↧</div>'.
        '<div class="ot-master-update-text"><strong id="otReleaseHeading">تحديثات تطبيق OnTrack Voice</strong>'.
        '<span>'.$status.'</span></div><div class="ot-master-update-actions">'.
        $action.'<a class="btn" href="/saas/console/?tab=releases">تفاصيل التحديث</a>'.
        '</div></section>';
}

/** One obvious entry point on customer and reseller home pages.
 * Uses the existing one-time slot-bound token + authenticated account.
 * Does not auto-answer or grant a SIM account without confirmation.
 */
function render_sim_pair_quick_start(array $a,string $returnTab='overview'):void {
    $returnTab=in_array($returnTab,['overview','devices'],true)?$returnTab:'overview';
    // Keep SIM pairing discoverable in Admin/Reseller overview without enabling
    // privileged SIM operations there. User Workspace owns the actual pairing.
    if(in_array((string)($a['kind']??''),['platform','reseller'],true)&&console_view_mode($a)!=='user') {
        $body='<p class="dim">ربط SIM 1 أو SIM 2 بكود 6 أرقام متاح من مساحة المستخدم، مع فصل صلاحيات إدارة المنصة.</p>';
        if(console_can_switch_view($a) && !active_support()) {
            $body.=form_start('set_view','/?tab=overview','data-no-ajax="1"').
                '<input type="hidden" name="view" value="user">'.
                '<button type="submit" class="btn primary">فتح مساحة المستخدم وربط الشريحة</button></form>';
        } elseif(console_can_switch_support_view($a)) {
            $body.=form_start('set_support_view','/?tab=overview','data-no-ajax="1"').
                '<input type="hidden" name="view" value="user">'.
                '<button type="submit" class="btn primary">فتح واجهة مستخدم الريسيلر للربط</button></form>';
        } else {
            $body.='<p><a class="btn" href="/saas/console/?tab=devices">فحص الشرائح والأجهزة</a></p>';
        }
        echo card('ربط الهاتف والشريحة بسهولة',$body);
        return;
    }
    if(!in_array((string)($a['kind']??''),['customer','reseller','platform'],true))return;
    $accountId=(int)$a['account_id'];
    $canPair=in_array((string)($a['role']??''),['owner','admin'],true)&&!active_support();
    $items=all("SELECT ag.id,ag.name,ag.site_id,s.name site_name,
      (SELECT COUNT(*) FROM agent_flow_versions v WHERE v.agent_id=ag.id
       AND v.account_id=ag.account_id AND v.site_id=ag.site_id) revisions
      FROM agents ag JOIN sites s ON s.id=ag.site_id AND s.account_id=ag.account_id
      WHERE ag.account_id=? AND ag.status!='archived' AND s.status='active'
      ORDER BY revisions DESC,ag.id ASC LIMIT 40",[$accountId]);
    $rows=[];
    foreach($items as $item){
        $label=(string)$item['site_name'].' · '.(string)$item['name'];
        if((int)$item['revisions']<1)$label.=' (انشر السيناريو قبل تشغيل المكالمات)';
        $rows[(string)$item['id']]=$label;
    }
    $body='<p class="dim">افتح التطبيق واضغط «ربط الشريحة»، ثم اختار SIM 1 أو SIM 2 واكتب الكود المكوّن من 6 أرقام. مفيش QR ولا API Key.</p>';
    if(!$canPair){
        $body.='<p class="dim">ربط شريحة جديدة متاح لمالك الحساب أو مديره فقط، وليس في وضع الدعم.</p>';
    }elseif(!$rows){
        $body.='<p>علشان تربط الشريحة لازم يكون عندك مساعد على موقع نشط. قاعدة المعرفة مش مطلوبة.</p>'.
          '<a class="btn primary" href="/saas/console/?tab=agents">إنشاء مساعد للربط</a>';
    }else{
        $options=count($rows)>1?
            '<p class="dim">المساعد الافتراضي: '.e(reset($rows)).'</p>'.
            '<details><summary>تغيير المساعد (اختياري)</summary>'.
            select_html('المساعد اللي هيرد على الشريحة','agent_id',$rows).'</details>':
            '<input type="hidden" name="agent_id" value="'.e((string)array_key_first($rows)).'">'.
            '<p class="dim">المساعد المختار: '.e(reset($rows)).'</p>';
        $body.=form_start('create_device_pairing','/?tab='.$returnTab,
          'class="ot-sim-pair-fast" data-no-ajax="1"').$options.
          '<div class="ot-master-update-actions ot-sim-pair-buttons">'.
          '<button type="submit" name="sim_slot" value="0" class="btn primary">ربط SIM 1 الآن</button>'.
          '<button type="submit" name="sim_slot" value="1" class="btn primary">ربط SIM 2 الآن</button></div></form>'.
          '<p class="dim">بعد الضغط هيظهر كود 6 أرقام صالح لمدة 5 دقائق؛ اكتبه في التطبيق واضغط ربط.</p>';
    }
    $body.='<p><a href="/saas/console/?tab=devices">عرض الشرائح المربوطة وحالتها</a></p>';
    echo card('ربط الهاتف والشريحة بسهولة',$body);
}
function render_overview(array $a):void{
    $id=(int)$a['account_id'];$kind=$a['kind'];
    if(in_array($kind,['platform','reseller'],true)&&console_view_mode($a)==='user')$kind='customer';
    if($kind==='platform'){
        $n1=(int)one("SELECT COUNT(*) n FROM accounts WHERE kind='reseller'")['n'];
        $n2=(int)one("SELECT COUNT(*) n FROM accounts WHERE kind='customer'")['n'];
        $n3=(int)one('SELECT COUNT(*) n FROM agents')['n'];
        $n4=(int)one('SELECT COUNT(*) n FROM calls')['n'];
        $title='منصة OnTrack';$sub='إدارة الحسابات والخطط والمساعدون والتكاملات من نقطة واحدة.';
    }elseif($kind==='reseller'){
        $n1=(int)one("SELECT COUNT(*) n FROM accounts WHERE parent_id=?",[$id])['n'];
        $n2=(int)one("SELECT COALESCE(SUM(p.call_quota),0) n FROM subscriptions s JOIN plans p ON s.plan_id=p.id JOIN accounts a ON a.id=s.account_id WHERE a.parent_id=? AND s.status='active' AND s.starts_at<=? AND s.ends_at>?",[$id,now(),now()])['n'];
        $n3=site_limit($id,'call_quota');$n4=(int)one('SELECT COUNT(*) n FROM integrations WHERE account_id=?',[$id])['n'];
        $title='لوحة إدارة شركتك';$sub='تابع عملاءك وخدماتك وباقاتك وهويتك من مكان واحد.';
    }else{
        $n1=(int)one('SELECT COUNT(*) n FROM sites WHERE account_id=?',[$id])['n'];
        $n2=(int)one('SELECT COUNT(*) n FROM agents WHERE account_id=?',[$id])['n'];
        $n3=site_limit($id,'call_quota');
        $n4=(int)one('SELECT COUNT(*) n FROM calls WHERE account_id=?',[$id])['n'];
        $title='مساحة عملك الذكية';$sub='اختار الموقع المناسب، وابنِ المساعد، وتابع حملاتك واستهلاكك.';
    }
    $primaryAction=$kind==='platform'?'accounts':($kind==='reseller'?'accounts':'agents');
    $primaryLabel=$kind==='platform'?(platform_resellers_visible()?'إدارة الحسابات والريسيلرز':'إدارة العملاء'):($kind==='reseller'?'إدارة العملاء':'إدارة المساعدون');
    $secondaryAction=$kind==='platform'?'settings':($kind==='reseller'?'plans':'releases');
    $secondaryLabel=$kind==='platform'?'إعدادات المنصة':($kind==='reseller'?'باقات العملاء':'تطبيق Android');
    echo '<section class="welcome"><div><span class="eyebrow">SMART VOICE CONTROL</span><h2>'.e($title).'</h2><p>'.e($sub).'</p><div class="welcome-actions">'.
        '<a class="btn light" href="/saas/console/?tab='.e($primaryAction).'">'.e($primaryLabel).' ←</a>'.
        '<a class="btn ghost-light" href="/saas/console/?tab='.e($secondaryAction).'">'.e($secondaryLabel).'</a>'.
        '</div></div><div class="orb-art"><span></span><b>OT</b></div></section>';
    echo ontrack_update_banner();
    $release=ontrack_native_public_release();
    if($release){
        echo '<div class="ot-master-update ot-home-actions"><div class="ot-master-update-text"><strong>تحميل تطبيق Android مباشرة</strong><span>آخر إصدار موقّع: '.e($release['name']).'</span></div>'.
            '<div class="ot-master-update-actions"><a class="btn primary ot-direct-apk" href="'.e($release['url']).'">↓ تحميل APK الآن</a></div></div>';
    }
    if($a['kind']==='reseller' && (console_can_switch_view($a)||console_can_switch_support_view($a))){
        $to=console_view_mode($a)==='user'?'admin':'user';
        $action=active_support()?'set_support_view':'set_view';
        echo '<section class="ot-master-update ot-reseller-user-switch"><div class="ot-master-update-text"><strong>واجهات حساب الريسيلر</strong>'.
            '<span>بدّل بين إدارة الشركة ومساحة المستخدم بنفس الحساب والصلاحيات.</span></div><div class="ot-master-update-actions">'.
            form_start($action,'/?tab=overview','class="inline-form" data-no-ajax="1"').
            '<input type="hidden" name="view" value="'.e($to).'">'.
            '<button type="submit" class="btn primary">⇄ '.
            ($to==='user'?'فتح واجهة مستخدم الريسيلر':'العودة لإدارة الريسيلر').
            '</button></form></div></section>';
    }
    render_sim_pair_quick_start($a);
    echo '<div class="metric-grid">';
    if($kind==='platform'){
        echo (platform_resellers_visible()?counter('الريسيلرز',$n1,'♧'):'').counter('العملاء',$n2,'♙').counter('المساعدون',$n3,'✧').counter('سجلات المكالمات',$n4,'☏');
    }elseif($kind==='reseller'){
        echo counter('شركات العملاء',$n1,'♧').counter('حصص عملائك المحجوزة',$n2,'◫').counter('رصيد باقة شركتك',$n3,'▣').counter('تكاملات مرتبطة',$n4,'⇄');
    }else{
        echo counter('المواقع',$n1,'◎').counter('المساعدون',$n2,'✧').counter('مكالمات الخطة الشهرية',$n3,'☏').counter('سجلات تجريبية',$n4,'▤');
    }
    echo '</div><div class="cols two">';
    if($kind==='platform'){
        $tips='<div class="quick-grid">'.
            '<a href="/saas/console/?tab=accounts"><b>♧</b><span>'.(platform_resellers_visible()?'الحسابات والريسيلرز':'حسابات العملاء').'</span><small>إدارة العملاء والشركات</small></a>'.
            '<a href="/saas/console/?tab=plans"><b>◫</b><span>الخطط والحدود</span><small>إدارة باقات المنصة</small></a>'.
            '<a href="/saas/console/?tab=whatsapp_admin"><b>✉</b><span>إعدادات واتساب</span><small>ربط منصة Meta المركزية</small></a>'.
            '<a href="/saas/console/?tab=settings"><b>⚙</b><span>إعدادات المنصة</span><small>الهوية والتكاملات المركزية</small></a></div>';
    }elseif($kind==='reseller'){
        $tips='<div class="quick-grid">'.
            '<a href="/saas/console/?tab=accounts"><b>♧</b><span>عملائي</span><small>إدارة حسابات العملاء</small></a>'.
            '<a href="/saas/console/?tab=plans"><b>◫</b><span>باقات العملاء</span><small>الحدود والاشتراكات</small></a>'.
            '<a href="/saas/console/?tab=domains"><b>◎</b><span>الدومينات والهوية</span><small>البراند الخاص بالريسيلر</small></a>'.
            '<a href="/saas/console/?tab=settings"><b>⚙</b><span>إعدادات الريسيلر</span><small>إدارة العلامة التجارية</small></a></div>';
    }else{
        $tips='<div class="quick-grid"><a href="/saas/console/?tab=sites"><b>◈</b><span>مواقع مستقلة</span><small>إيجنت ومعرفة لكل موقع</small></a><a href="/saas/console/?tab=agents"><b>✧</b><span>تصميم المحادثة</span><small>السيناريو والشخصية والصوت</small></a><a href="/saas/console/?tab=devices"><b>▣</b><span>ربط الشرائح</span><small>SIM 1 / SIM 2 بكود 6 أرقام</small></a><a href="/saas/console/?tab=staff"><b>♙</b><span>إدارة الموظفين</span><small>أدوار وصلاحيات لكل حساب</small></a></div>';
    }
    echo card('ابدأ من هنا',$tips,'إجراءات سريعة');
    $items=$kind==='platform'?all('SELECT action,created_at FROM audit_events ORDER BY id DESC LIMIT 5'):all('SELECT action,created_at FROM audit_events WHERE account_id=? ORDER BY id DESC LIMIT 5',[$id]);
    echo card('آخر الأحداث',tbl(['الحدث','التاريخ'],array_map(fn($x)=>[e($x['action']),e($x['created_at'])],$items)),'سجل تدقيق حقيقي');
    echo '</div>';
}
function render_accounts(array $a):void{
    if(!in_array($a['kind'],['platform','reseller'],true))deny();
    require_once __DIR__.'/BillingUsage.php';
    require_once __DIR__.'/CustomerAccounts.php';
    $selected=max(0,(int)($_GET['customer_id']??0));
    if($selected>0){render_customer_detail($a,$selected);return;}
    $id=(int)$a['account_id'];
    $rows=$a['kind']==='platform'?
        all("SELECT a.*, (SELECT COUNT(*) FROM memberships m WHERE m.account_id=a.id) seats FROM accounts a WHERE ".(platform_resellers_visible()?"a.kind!='platform'":"a.kind='customer' AND (a.parent_id IS NULL OR a.parent_id IN (SELECT id FROM accounts WHERE kind='platform'))")." ORDER BY a.id DESC"):
        all("SELECT a.*, (SELECT COUNT(*) FROM memberships m WHERE m.account_id=a.id) seats FROM accounts a WHERE a.parent_id=? ORDER BY a.id DESC",[$id]);
    echo '<div class="cols two">';
    if(in_array($a['role'],['owner','admin'],true)&&!active_support()){
        $base='/register';
        if($a['kind']==='platform'){
            echo card('روابط التسجيل العامة',
                '<p>روابط تسجيل الحسابات الجديدة.</p>'.
                '<p><a class="btn" href="'.$base.'">تسجيل عميل مباشر</a> '.(platform_resellers_visible()?'<a class="btn" href="'.$base.'?type=reseller">تسجيل ريسيلر</a>':'').'</p>'.
                '<p class="dim" dir="ltr">https://app.ontrackegy.com'.$base.'</p>');
        }else{
            $verified=one("SELECT hostname FROM domains WHERE account_id=? AND status='active' ORDER BY id DESC LIMIT 1",[$id]);
            $portalBase=$verified?'https://'.$verified['hostname']:'https://app.ontrackegy.com/portal/'.rawurlencode((string)$a['slug']);
            echo card('رابط بوابة عملائك',
                '<p>ده رابط شركتك باسمها وشعارها. العملاء هيشوفوا علامتك التجارية فقط، من غير اسم النظام الأساسي.</p>'.
                '<p><a class="btn primary" href="'.e($portalBase.'/register').'">فتح صفحة إنشاء الحساب</a> '.
                '<a class="btn" href="'.e($portalBase.'/login').'">فتح صفحة الدخول</a></p>'.
                '<label class="field">رابط تسجيل عملائك<input dir="ltr" readonly onclick="this.select()" value="'.e($portalBase.'/register').'"></label>'.
                '<label class="field">رابط دخول عملائك<input dir="ltr" readonly onclick="this.select()" value="'.e($portalBase.'/login').'"></label>');
        }
    }
    if(in_array($a['role'],['owner','admin'],true)&&!active_support()&&
       console_view_mode($a)==='admin')echo card('إنشاء حساب جديد',form_start('create_account','/?tab=accounts').
        ($a['kind']==='platform'?select_html('نوع الحساب','kind',platform_resellers_visible()?['reseller'=>'ريسيلر','customer'=>'عميل مباشر']:['customer'=>'عميل مباشر']):'').
        field_html('اسم الشركة','name','text','اسم المؤسسة',true).
        field_html('بريد المالك','email','email','owner@company.com',true).
        submit_btn('إنشاء الحساب وإظهار كلمة مرور مؤقتة'));
    $policy=$a['kind']==='platform'?0:$id;
    $options=[0=>'السياسة العامة'];
    if($a['kind']==='platform'&&platform_resellers_visible())foreach(all("SELECT id,name FROM accounts WHERE kind='reseller'") as $reseller)$options[$reseller['id']]=$reseller['name'];
    echo card('سياسة دخول الحسابات',form_start('support_policy','/?tab=accounts').
        ($a['kind']==='platform'?select_html('الحساب','reseller_id',$options):'').
        select_html('صلاحيات الدخول','access_mode',['full'=>'كامل الصلاحيات','read_only'=>'قراءة فقط']).
        '<p class="dim">الحالي: '.e(support_mode($policy)).' · يسجل سجل التدقيق الموظف الفعلي الذي نفّذ التغيير.</p>'.
        submit_btn('حفظ السياسة'));
    echo '</div>';
    $cells=[];
    // Available plans are loaded once and filtered per tenant kind, so the
    // inline form cannot accidentally preselect a customer plan for a reseller.
    $adminCanGrant=$a['kind']==='platform'&&in_array($a['role'],['owner','admin'],true)
        &&!active_support()&&console_view_mode($a)==='admin';
    $planChoices=['user'=>[],'reseller'=>[]];
    if($adminCanGrant)foreach(all("SELECT p.id,p.name,p.kind,p.price_minor,p.call_quota
        FROM plans p LEFT JOIN reseller_plan_owners o ON o.plan_id=p.id
        WHERE p.status='active' AND o.plan_id IS NULL ORDER BY p.kind,p.price_minor,p.id") as $p){
        if(!isset($planChoices[$p['kind']]))continue;
        $planChoices[$p['kind']][(string)$p['id']]=(string)$p['name'].' — '.
            (int)$p['call_quota'].' مكالمة · '.number_format((int)$p['price_minor']/100,2).' ج.م';
    }
    foreach($rows as $r){
        $cid=(int)$r['id'];
        $support='<a class="btn small primary" href="/?tab=accounts&amp;customer_id='.$cid.'">ملف العميل</a>';
        if(($r['status']??'')!=='archived' && in_array($a['role'],['owner','admin'],true)
           && !active_support() && console_view_mode($a)==='admin'){
            $support.=form_start('support_enter','/?tab=accounts','class="inline-form"').
                '<input type="hidden" name="account_id" value="'.$cid.'">'.
                '<button class="btn small" type="submit">الدخول للحساب</button></form>';
            $support.=row_rename('edit_account','/?tab=accounts','account_id',$cid,(string)$r['name']);
            $support.=form_start('set_account_status','/?tab=accounts','class="inline-form"').
                '<input type="hidden" name="account_id" value="'.$cid.'">'.
                '<input type="hidden" name="status" value="'.($r['status']==='active'?'suspended':'active').'">'.
                '<button class="btn small danger">'.($r['status']==='active'?'إيقاف':'إعادة تفعيل').'</button></form>';
        }
        if($r['status']==='archived' && in_array($a['role'],['owner','admin'],true)
            && !active_support() && console_view_mode($a)==='admin'){
            $support.=form_start('customer_restore','/?tab=accounts','class="inline-form"').
                '<input type="hidden" name="customer_id" value="'.$cid.'">'.
                '<button class="btn small" type="submit">استعادة الحساب</button></form>';
        }
        $support.='<a class="btn small" href="/?tab=invoices&amp;customer_id='.$cid.'">فواتير العميل</a>';
        $current=one("SELECT p.id plan_id,p.name,p.price_minor,p.call_quota,p.site_limit,p.agent_limit,s.starts_at,s.ends_at FROM subscriptions s JOIN plans p ON p.id=s.plan_id
            WHERE s.account_id=? AND s.status='active' AND s.starts_at<=? AND s.ends_at>?
            ORDER BY s.id DESC LIMIT 1",[(int)$r['id'],now(),now()]);
        $usage=$current?voice_usage_summary((int)$r['id']):null;
        $planInfo=$current?'<strong>'.e((string)$current['name']).'</strong>'.
            '<small class="block dim">من '.e((string)$current['starts_at']).' حتى '.e((string)$current['ends_at']).'</small>'.
            '<small class="block dim">مكالمات: '.(int)($usage['used']??0).' / '.(int)$current['call_quota'].
            ' · مواقع: '.(int)$current['site_limit'].' · AI: '.(int)$current['agent_limit'].'</small>'
            :'<span class="dim">لا توجد باقة نشطة</span>';
        $planKind=$r['kind']==='reseller'?'reseller':'user';
        if($adminCanGrant&&$r['status']==='active'&&!empty($planChoices[$planKind])){
            $planInfo.='<details class="row-rename"><summary>تعيين / تغيير الباقة الآن</summary>'.
                form_start('admin_grant_plan','/?tab=accounts','class="row-rename-form" data-no-ajax="1"').
                '<input type="hidden" name="customer_id" value="'.(int)$r['id'].'">'.
                select_html('الباقة الجديدة','plan_id',$planChoices[$planKind]).
                '<label class="field">سبب المنحة الإدارية<input name="grant_reason" required minlength="5" maxlength="200" placeholder="تجربة داخلية أو منحة من الإدارة"></label>'.
                '<label class="field"><input type="checkbox" name="confirm_admin_grant" value="1" required> أتأكد إن ده تفعيل إداري، مش إثبات دفع</label>'.
                '<button type="submit" class="btn small primary" data-confirm="تفعيل الباقة المحددة فورًا لهذا الحساب كمنحة إدارية بدون تسجيل أي دفعة؟">حفظ وتفعيل الباقة</button></form></details>';
        }
        if(in_array($a['role'],['owner','admin'],true)&&!active_support()&&
           console_view_mode($a)==='admin'&&
           in_array($r['kind'],['customer','reseller'],true)&&
           ($a['kind']==='platform'||($a['kind']==='reseller'&&$r['kind']==='customer'))){
            $cid=(int)$r['id'];
            if($current){
                $planInfo.=form_start('manager_cancel_customer_plan','/?tab=accounts','class="inline-form"').
                    '<input type="hidden" name="customer_id" value="'.$cid.'">'.
                    '<button type="submit" class="btn small danger" data-confirm="إلغاء اشتراك العميل فورًا وإيقاف استحقاقاته؟">إلغاء اشتراك العميل</button></form>';
            }
            $freeChoices=[];
            foreach(plan_catalog_available($r) as $p){
                if((int)$p['price_minor']===0)$freeChoices[(string)$p['id']]=$p['name'];
            }
            if($freeChoices){
                $planInfo.='<details class="row-rename"><summary>تجديد باقة مجانية</summary>'.
                    form_start('manager_renew_customer_free','/?tab=accounts').
                    '<input type="hidden" name="customer_id" value="'.$cid.'">'.
                    select_html('الباقة','plan_id',$freeChoices).
                    '<small class="dim">الاشتراك النشط لا يُمدد تلقائيًا.</small>'.
                    submit_btn('تجديد مجاني').'</details>';
            }
        }
        $cells[]=[e($r['name']).'<small class="block dim">'.e($r['slug']).'</small>',pill((string)$r['kind']),e((string)($r['parent_id']??'مباشر')),e((string)$r['seats']),pill((string)$r['status']),$planInfo,$support];
    }
    echo card('الحسابات التابعة',tbl(['الاسم','النوع',platform_resellers_visible()?'الريسيلر':'الحساب','الأعضاء','الحالة','الباقة الحالية وتعديلها','إدارة'],$cells));
}
function render_customer_detail(array $actor,int $customerId):void {
    require_once __DIR__.'/CustomerAccounts.php';
    require_once __DIR__.'/BillingInvoices.php';
    try{$customer=customer_accounts_scope($actor,$customerId);}
    catch(InvalidArgumentException){deny(404,'العميل غير متاح');}
    $id=(int)$customer['id'];
    $canEdit=in_array($actor['role'],['owner','admin'],true)&&
        !active_support()&&console_view_mode($actor)==='admin';
    $profile=customer_accounts_profile($id);
    $metrics=customer_accounts_metrics($id);
    $counts=$metrics['counts'];$sub=$metrics['subscription'];$usage=$metrics['usage'];
    $owner=one("SELECT u.email,u.name FROM users u JOIN memberships m ON m.user_id=u.id
        WHERE m.account_id=? AND m.role='owner' ORDER BY m.id LIMIT 1",[$id]);
    echo '<div class="section-line"><a class="btn small" href="/?tab=accounts">العودة للعملاء</a>'.
        '<strong>ملف العميل: '.e((string)$customer['name']).'</strong> '.
        pill((string)$customer['status']).'</div>';
    echo card('بيانات الحساب',
        '<p>رقم الحساب: #'.$id.' · النوع: '.e((string)$customer['kind']).
        ' · تاريخ الإنشاء: '.e((string)$customer['created_at']).'</p>'.
        '<p>بريد تسجيل دخول المالك: <strong dir="ltr">'.e($owner['email']??'غير متاح').'</strong></p>'.
        '<p>جهة الاتصال: '.e((string)$profile['contact_name']).' · '.
        '<span dir="ltr">'.e((string)$profile['contact_email']).'</span> · '.
        '<span dir="ltr">'.e((string)$profile['phone']).'</span></p>'.
        '<p>العنوان: '.nl2br(e((string)$profile['address'])).'</p>'.
        '<p>ملاحظات الإدارة: '.nl2br(e((string)$profile['notes'])).'</p>');
    echo '<div class="metric-grid">'.
        counter('فواتير',$counts['invoices'],'▤').
        counter('طلبات',$counts['orders'],'▣').
        counter('مكالمات',$counts['calls'],'☏').
        counter('مواقع',$counts['sites'],'◈').
        counter('مساعدون',$counts['agents'],'✧').
        counter('أجهزة',$counts['devices'],'◉').
        '</div>';
    $planInfo=$sub?
        '<p><strong>'.e((string)$sub['name']).'</strong> — من '.e($sub['starts_at']).
        ' إلى '.e($sub['ends_at']).' · '.
        (int)($usage['used']??0).' / '.(int)($usage['quota']??0).' مكالمة</p>':
        '<p class="dim">لا توجد باقة نشطة.</p>';
    echo card('الاشتراك والاستهلاك',$planInfo.
        '<p>إجمالي المدفوعات المسجلة بالفواتير: <strong>'.
        billing_invoice_money((int)$counts['paid_minor']).'</strong></p>'.
        '<p><a class="btn small" href="/?tab=invoices&amp;customer_id='.$id.'">عرض فواتير العميل</a></p>');
    $orders=all('SELECT id,external_order_id,amount_minor,status,created_at,payment_reference
        FROM orders WHERE account_id=? ORDER BY id DESC LIMIT 20',[$id]);
    $entries=[];
    foreach($orders as $o)$entries[]=[
        '#'.(int)$o['id'],e((string)$o['status']),
        billing_invoice_money((int)$o['amount_minor']),
        e((string)$o['created_at']),
        e((string)($o['payment_reference']?:'—'))];
    echo card('آخر المعاملات',tbl(['الطلب','الحالة','المبلغ','التاريخ','مرجع الدفع'],$entries));
    if(!$canEdit)return;
    if($customer['status']==='archived'){
        echo card('استعادة العميل',form_start('customer_restore','/?tab=accounts').
            '<input type="hidden" name="customer_id" value="'.$id.'">'.
            '<p class="dim">الاستعادة تعيد العميل موقوفًا، ولن تُفعّل الخدمات حتى إعادة التفعيل الصريحة.</p>'.
            submit_btn('استعادة الحساب'));
        return;
    }
    $form=form_start('customer_profile_save','/?tab=accounts&customer_id='.$id).
        '<input type="hidden" name="customer_id" value="'.$id.'"><div class="form-grid">'.
        '<label class="field">اسم الشركة<input name="name" required maxlength="120" value="'.e($customer['name']).'"></label>'.
        '<label class="field">اسم جهة الاتصال<input name="contact_name" maxlength="190" value="'.e($profile['contact_name']).'"></label>'.
        '<label class="field">البريد الخاص بالفواتير والتواصل<input name="contact_email" type="email" maxlength="190" value="'.e($profile['contact_email']).'"></label>'.
        '<label class="field">رقم الهاتف<input name="phone" type="tel" maxlength="45" value="'.e($profile['phone']).'"></label>'.
        '<label class="field">العنوان<textarea name="address" maxlength="800">'.e($profile['address']).'</textarea></label>'.
        '<label class="field">ملاحظات الإدارة<textarea name="notes" maxlength="2000">'.e($profile['notes']).'</textarea></label>'.
        '</div>'.submit_btn('حفظ بيانات العميل');
    echo card('تعديل بيانات العميل',$form);
    $loginForm=form_start('customer_owner_email','/?tab=accounts&customer_id='.$id).
        '<input type="hidden" name="customer_id" value="'.$id.'">'.
        '<label class="field">تغيير بريد دخول مالك الحساب<input type="email" name="owner_email" maxlength="190" required value="'.e($owner['email']??'').'"></label>'.
        '<p class="dim">تغيير بريد الدخول يؤثر على تسجيل الدخول فقط، ولا يغيّر الفواتير الصادرة بالفعل.</p>'.
        submit_btn('تحديث بريد الدخول');
    echo card('بيانات تسجيل الدخول',$loginForm);
    $toggle=$customer['status']==='active'?'suspended':'active';
    $controls=form_start('set_account_status','/?tab=accounts&customer_id='.$id).
        '<input type="hidden" name="account_id" value="'.$id.'">'.
        '<input type="hidden" name="status" value="'.$toggle.'">'.
        '<button class="btn primary" type="submit">'.($toggle==='active'?'إعادة تفعيل العميل':'إيقاف العميل').'</button></form>';
    $controls.=form_start('customer_delete','/?tab=accounts').
        '<input type="hidden" name="customer_id" value="'.$id.'">'.
        '<input type="hidden" name="confirm" value="DELETE">'.
        '<button class="btn danger" type="submit" data-confirm="تأكيد حذف العميل؟ الحساب الفارغ فقط يُحذف نهائيًا؛ أما المستخدم فيُؤرشف وتظل الفواتير والتسجيلات محفوظة.">حذف العميل</button></form>';
    echo card('إدارة حالة العميل',$controls.
        '<p class="dim">الإيقاف يمنع الدخول والاستخدام فورًا دون مسح السجلات. الحذف الآمن يُؤرشف الحسابات ذات الفواتير أو المكالمات، ويحذف الحساب الفارغ فقط.</p>');
}
function render_plans(array $a):void{
    require_once __DIR__.'/GoogleTenantKeys.php';google_voice_key_schema();
    require_once __DIR__.'/AiRelay.php';ai_relay_assistant_limits_schema();
    $type=(string)$a['kind'];$id=(int)$a['account_id'];
    plan_catalog_init();
    $list=$type==='platform'
        ?all("SELECT p.* FROM plans p LEFT JOIN reseller_plan_owners o ON o.plan_id=p.id
            WHERE o.plan_id IS NULL".(platform_resellers_visible()?"":" AND p.kind='user'")." ORDER BY p.kind,p.id")
        :all("SELECT p.* FROM plans p LEFT JOIN reseller_plan_owners o ON o.plan_id=p.id
            WHERE p.kind='user' AND p.status='active'
                AND (o.plan_id IS NULL OR o.reseller_account_id=?)
            ORDER BY p.price_minor,p.id",[$id]);
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
                '<label class="field">المساعدون<input name="agents" type="number" min="0" value="'.(int)$plan['agent_limit'].'" required></label>'.
                '<label class="field">الموظفون<input name="staff" type="number" min="0" value="'.(int)$plan['staff_limit'].'" required></label>'.
                (platform_resellers_visible()?'<label class="field">حد عملاء الريسيلر<input name="customers" type="number" min="0" value="'.(int)$plan['reseller_customers'].'" required></label>':'<input type="hidden" name="customers" value="'.(int)$plan['reseller_customers'].'">').
                '<label class="field">سعر الباقة (قرش)<input name="price" type="number" min="0" max="1000000000" step="1" value="'.(int)$plan['price_minor'].'" required><small>100 قرش = 1 جنيه</small></label>'.
                '<label class="field">سعر كل مكالمة بعد الحد (قرش)<input name="overage" type="number" min="0" max="10000000" step="1" value="'.(int)($plan['overage_minor']??0).'" required><small>0 = بدون تكلفة إضافية</small></label>'.
                '<label class="field">عدد مفاتيح Google للعميل<input name="google_key_limit" type="number" min="0" max="1000" required value="'.(int)($plan['google_key_limit']??1).'"></label>'.
                '<label class="field">مفاتيح Assistant Text للعميل<input name="assistant_key_limit" type="number" min="0" max="1000" required value="'.(int)($plan['assistant_key_limit']??0).'"></label>'.
                '</div><div class="plan-actions"><button class="btn primary" type="submit">حفظ تغييرات الباقة</button></div></form>'.
                form_start('platform_plan_dispose','/?tab=plans','class="plan-archive"').
                '<input type="hidden" name="operation" value="delete"><input type="hidden" name="plan_id" value="'.(int)$plan['id'].'">'.
                '<button class="btn small danger" type="submit" data-confirm="حذف الباقة إذا لم تستخدم؛ وإلا أرشفتها مع حفظ السجلات؟">حذف / أرشفة</button></form>'.
                form_start('archive_plan','/?tab=plans','class="plan-archive"').
                '<input type="hidden" name="plan_id" value="'.(int)$plan['id'].'">'.
                '<button class="btn small danger" type="submit" data-confirm="إيقاف بيع الباقة؟ الاشتراكات الحالية ستظل محفوظة">إيقاف بيع الباقة</button></form>'.
                '</div></details>';
        }
        echo '</div></section>';
        $archived=array_values(array_filter($list,static fn($p)=>(string)$p['status']==='archived'));
        if($archived){
            $forms='';
            foreach($archived as $plan){
                $forms.='<div class="section-line"><span>'.e((string)$plan['name']).'</span>'.
                    form_start('platform_plan_dispose','/?tab=plans','class="inline-form"').
                    '<input type="hidden" name="operation" value="restore"><input type="hidden" name="plan_id" value="'.(int)$plan['id'].'">'.
                    '<button class="btn small" type="submit">إعادة إتاحة الباقة</button></form></div>';
            }
            echo card('باقات مؤرشفة قابلة للاستعادة',$forms);
        }
        echo card('إضافة باقة جديدة',
            form_start('create_plan','/?tab=plans').
            '<div class="form-grid">'.
            select_html('نوع الباقة','kind',platform_resellers_visible()?['reseller'=>'ريسيلر','user'=>'مستخدم']:['user'=>'مستخدم']).
            field_html('اسم الباقة','name','text','اسم الباقة',true).
            field_html('المكالمات الشهرية','calls','number','1000',true).
            field_html('المواقع','sites','number','1',true).
            field_html('المساعدون','agents','number','1',true).
            field_html('الموظفون','staff','number','3',true).
            (platform_resellers_visible()?field_html('حد عملاء الريسيلر','customers','number','10',true):'<input type="hidden" name="customers" value="0">').
            '<label class="field">سعر الباقة (قرش)<input name="price" type="number" min="0" max="1000000000" step="1" value="0" required></label>'.
            '<label class="field">سعر المكالمة الزائدة (قرش)<input name="overage" type="number" min="0" max="10000000" step="1" value="0" required></label>'.
            '<label class="field">عدد مفاتيح Google للعميل<input name="google_key_limit" type="number" min="0" max="1000" required value="1"></label>'.
            '<label class="field">مفاتيح Assistant Text للعميل<input name="assistant_key_limit" type="number" min="0" max="1000" required value="0"></label>'.
            '</div>'.submit_btn('إنشاء الباقة'));
    }elseif($type==='reseller'){
        $custom=plan_catalog_reseller_owned($id);
        if(in_array($a['role'],['owner','admin'],true)&&!active_support()&&
           console_view_mode($a)==='admin'){
            echo card('إنشاء باقة خاصة لعملائي',
                form_start('reseller_plan_save','/?tab=plans').
                '<div class="form-grid">'.
                field_html('اسم الباقة','name','text','باقة شركتي',true).
                field_html('مكالمات شهرية','calls','number','200',true).
                field_html('مواقع','sites','number','1',true).
                field_html('مساعدون','agents','number','1',true).
                field_html('موظفون','staff','number','1',true).
                '<label class="field">سعر الباقة (قرش)<input type="number" name="price" min="0" max="1000000000" value="0" required></label>'.
                '<label class="field">سعر المكالمة الإضافية (قرش)<input type="number" name="overage" min="0" value="0" required></label>'.
                '<label class="field">عدد مفاتيح Google<input type="number" name="google_key_limit" min="0" value="1" required></label>'.
                '<label class="field">مفاتيح مساعد النص<input type="number" name="assistant_key_limit" min="0" value="0" required></label>'.
                '</div><p class="dim">الباقة خاصة بشركتك. التفعيل المدفوع يحتاج دفعًا موثّقًا؛ وإنفاق العملاء لا يتجاوز حصتك.</p>'.
                submit_btn('إنشاء باقة العملاء'));
            $global=all("SELECT p.id,p.name FROM plans p LEFT JOIN reseller_plan_owners o ON o.plan_id=p.id
                WHERE p.kind='user' AND p.status='active' AND o.plan_id IS NULL ORDER BY p.id");
            $opts=[];
            foreach($global as $g)$opts[(string)$g['id']]=$g['name'];
            if($opts)echo card('نسخ باقة عامة وتخصيصها',
                form_start('reseller_plan_copy','/?tab=plans').
                select_html('اختر القالب العام','plan_id',$opts).
                '<p class="dim">ينشئ نسخة مستقلة لشركتك، ولا يغيّر باقات أدمن المنصة.</p>'.
                submit_btn('نسخ الباقة إلى حسابي'));
            $editors='';
            foreach($custom as $p){
                $pid=(int)$p['id'];
                $editors.='<details class="plan-editor"><summary><strong>'.e((string)$p['name']).'</strong> — '.
                    number_format((int)$p['price_minor']/100,2).' ج.م · '.e($p['status']).'</summary><div class="plan-editor-body">';
                if($p['status']==='active'){
                    $editors.=form_start('reseller_plan_save','/?tab=plans').
                        '<input type="hidden" name="plan_id" value="'.$pid.'"><div class="form-grid">'.
                        '<label class="field">اسم الباقة<input name="name" maxlength="120" required value="'.e($p['name']).'"></label>';
                    foreach(['calls'=>['مكالمات شهرية','call_quota'],'sites'=>['المواقع','site_limit'],
                      'agents'=>['المساعدون','agent_limit'],'staff'=>['الموظفون','staff_limit'],
                      'price'=>['سعر الباقة (قرش)','price_minor'],
                      'overage'=>['سعر المكالمة الإضافية','overage_minor'],
                      'google_key_limit'=>['مفاتيح Google','google_key_limit'],
                      'assistant_key_limit'=>['مفاتيح مساعد النص','assistant_key_limit']] as $field=>$detail){
                        $editors.='<label class="field">'.e($detail[0]).'<input type="number" min="0" name="'.$field.
                            '" value="'.(int)($p[$detail[1]]??0).'" required></label>';
                    }
                    $editors.='</div>'.submit_btn('حفظ تغييرات الباقة');
                }
                $op=$p['status']==='archived'?'restore':'delete';
                $editors.=form_start('reseller_plan_dispose','/?tab=plans','class="inline-form"').
                    '<input type="hidden" name="plan_id" value="'.$pid.'">'.
                    '<input type="hidden" name="operation" value="'.$op.'">'.
                    '<button type="submit" class="btn small" data-confirm="تأكيد إجراء الباقة؟">'.
                    ($op==='restore'?'استعادة الباقة':'حذف إن لم تستخدم / أرشفة').'</button></form>'.
                    '</div></details>';
            }
            echo card('إدارة باقات شركتي',$editors?:'<p class="dim">لم تنشئ أي باقات خاصة بعد.</p>');
        }
        $customers=all("SELECT id,name FROM accounts WHERE parent_id=? AND kind='customer'",[$id]);
        $co=[];$po=[];
        foreach($customers as $c)$co[$c['id']]=$c['name'];
        foreach($list as $p){
            // Resellers cannot activate priced subscriptions without verified payment.
            if((int)$p['price_minor']>0 && !is_demo())continue;
            $po[$p['id']]=$p['name'].' — '.$p['call_quota'].' مكالمة';
        }
        if($co&&$po&&in_array($a['role'],['owner','admin'],true)&&!active_support()&&console_view_mode($a)==='admin')
            echo card('تخصيص باقة مجانية لعميل',form_start('assign_plan','/?tab=plans').
                select_html('الحساب','customer_id',$co).select_html('الباقة','plan_id',$po).
                '<p class="dim">الباقات المدفوعة تتطلب تأكيد دفع أو منحة من أدمن المنصة.</p>'.
                submit_btn('تعيين الباقة'));
        $limits=[];
        foreach($customers as $customer){
            $limit=google_voice_key_limit((int)$customer['id']);
            $limits[]=[e((string)$customer['name']),e((string)$limit)];
        }
        echo card('حد مفاتيح Google لكل عميل',
            '<p class="dim">تقدر تقلل عدد المفاتيح المسموح للعميل، ولكن لا تقدر تتجاوز الحد اللي في باقته.</p>'.
            tbl(['العميل','الحد الحالي'],$limits).
            ($co?form_start('google_customer_key_limit','/?tab=plans').
                select_html('العميل','customer_id',$co).
                '<label class="field">حد المفاتيح<input type="number" name="google_key_limit" min="0" max="1000" required value="1"></label>'.
                submit_btn('حفظ حد المفاتيح'):''));

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
    render_human_escalation_settings($a);
}
/** Independent phone directory: a manager does NOT need to create a SaaS
 * login or set up a voice-PIN just to receive post-call escalation alerts.
 */
function render_human_escalation_settings(array $a):void {
    require_once __DIR__.'/HumanEscalations.php';
    he_init();
    $id=(int)$a['account_id'];
    $manage=in_array($a['role'],['owner','admin'],true)&&!active_support();
    $contacts=all('SELECT id,name,phone_e164,recipient_role,status
        FROM human_escalation_contacts WHERE account_id=? ORDER BY id DESC LIMIT 100',[$id]);
    $options=[0=>'بدون تحويل تلقائي'];
    $contactRows=[];
    foreach($contacts as $c){
        if($c['status']==='active')$options[(int)$c['id']]=
            $c['name'].' — '.$c['phone_e164'].' ('.
            ($c['recipient_role']==='manager'?'مدير':'موظف').')';
        $control=$manage && $c['status']==='active'
            ?form_start('human_contact_disable','/?tab=staff','class="inline-form"').
                '<input type="hidden" name="contact_id" value="'.(int)$c['id'].'">'.
                '<button class="btn small danger" data-confirm="تعطيل رقم الموظف وإيقاف الإشعارات المعلقة له؟">تعطيل</button></form>'
            :'—';
        $contactRows[]=[e($c['name']),
            '<span dir="ltr">'.e($c['phone_e164']).'</span>',
            $c['recipient_role']==='manager'?'مدير':'موظف',
            $c['status']==='active'?'مفعّل':'متوقف',$control];
    }
    echo '<section id="human-escalation-settings" class="card">'.
        '<div class="card-head"><h3>التحويل البشري وإبلاغ المدير بعد المكالمة</h3></div>'.
        '<p>سجّل أرقام المديرين والموظفين، وحدّد لكل مساعد الشخص المسؤول عن المكالمات التي يطلب فيها العميل موظفًا بشريًا. النظام يسجّل السبب والملخص، وبعد انتهاء المكالمة بالمهلة التي تحددها لكل مساعد يصدر إشعار اتصال إلى الشريحة المرتبطة. لا يبدأ اتصال أثناء مكالمة العميل.</p>'.
        '<p class="dim">الاتصال الآلي متوقف افتراضيًا حتى تفعّله وتختار رقمًا. بدء الاتصال لا يعني أن المدير استلم الرسالة؛ تتم متابعة حالة المحاولة بشكل مستقل.</p>'.
        tbl(['الاسم','رقم الهاتف','الصفة','الحالة','الإجراء'],$contactRows).'</section>';
    if(!$manage)return;
    echo card('إضافة رقم مدير أو موظف',
        form_start('human_contact_add','/?tab=staff').
        '<div class="form-grid">'.
        '<label class="field">اسم المسؤول<input name="contact_name" maxlength="140" required placeholder="اسم المدير أو الموظف"></label>'.
        '<label class="field">رقم الاتصال الدولي<input type="tel" name="phone_e164" dir="ltr" maxlength="20" required placeholder="+201012345678"></label>'.
        select_html('الصفة','recipient_role',['manager'=>'مدير','employee'=>'موظف']).
        '</div>'.submit_btn('حفظ رقم الاتصال'));
    $agents=all("SELECT ag.id,ag.name,ag.site_id,s.name site_name,
        r.contact_id,r.enabled,r.delay_seconds
        FROM agents ag JOIN sites s ON s.id=ag.site_id AND s.account_id=ag.account_id
        LEFT JOIN human_escalation_routes r ON r.agent_id=ag.id AND r.account_id=ag.account_id
        WHERE ag.account_id=? AND ag.status!='archived' ORDER BY ag.id DESC LIMIT 100",[$id]);
    $routes='';
    $delayChoices=[10=>'10 ثوانٍ',20=>'20 ثانية',30=>'30 ثانية',60=>'دقيقة',120=>'دقيقتان'];
    foreach($agents as $ag){
        $routeOptions=$options;
        $selected=(int)($ag['contact_id']??0);
        if(!array_key_exists($selected,$routeOptions))$selected=0;
        $routes.='<div class="call-handoff-route"><strong>'.e($ag['name']).'</strong>'.
            '<small class="dim">'.e($ag['site_name']).'</small>'.
            form_start('human_route_save','/?tab=staff').
            '<input type="hidden" name="agent_id" value="'.(int)$ag['id'].'">'.
            select_html('موظف التحويل','contact_id',$routeOptions,$selected).
            '<label class="field"><input type="checkbox" name="enabled" value="1"'.
                (!empty($ag['enabled'])?' checked':'').'> تفعيل الاتصال التلقائي للمسؤول عند طلب العميل</label>'.
            select_html('مهلة اتصال المدير بعد نهاية المكالمة','handoff_delay_seconds',$delayChoices,(int)($ag['delay_seconds']??10)).
            '<p class="dim">لا يتصل بالمسؤول أثناء مكالمة العميل؛ المهلة تبدأ من وقت انتهائها.</p>'.
            submit_btn('حفظ مسار التحويل').'</form></div>';
    }
    echo card('تحديد المسؤول لكل AI Agent',
        $routes?:'<p>أنشئ مساعدًا أولًا حتى تختار مسؤول التحويل الخاص به.</p>');
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
    require_once __DIR__.'/RecycleBin.php';
    $id=(int)$a['account_id'];$sites=all("SELECT * FROM sites WHERE account_id=? AND status!='archived' ORDER BY id DESC",[$id]);
    echo '<div class="cols two">';
    $cells=[];
    foreach($sites as $s)$cells[]=[e($s['name']),'<span dir="ltr">'.e($s['domain']).'</span>',pill($s['status']),'<a class="btn small" href="/saas/console/?tab=agents&site='.$s['id'].'">المساعدون ←</a>'.
      '<details class="row-rename"><summary>تعديل بيانات الموقع</summary>'.form_start('update_site','/?tab=sites','class="row-rename-form"').'<input type="hidden" name="site_id" value="'.$s['id'].'"><label class="field">الاسم<input name="name" value="'.e($s['name']).'"></label><label class="field">الدومين<input name="domain" value="'.e($s['domain']).'"></label><button class="btn small primary">حفظ الموقع</button></form></details>'.
      form_start('archive_site','/?tab=sites','class="inline-form"').'<input type="hidden" name="site_id" value="'.$s['id'].'"><button class="btn small danger" data-confirm="أرشفة الموقع وإيجنتاته؟">أرشفة</button></form>'];
    echo card('مواقعي',tbl(['الموقع','الدومين','الحالة','التشغيل'],$cells));
    if(allowed('sites.write'))echo card('موقع جديد',form_start('add_site','/?tab=sites').field_html('اسم الموقع','name','text','الصيدلية / الملابس / الدعم',true).field_html('الدومين','domain','text','store.example.com').submit_btn('إضافة موقع مستقل'));
    echo '</div>';
    if(in_array($a['role'],['owner','admin'],true) && !active_support()){
        $recycleRows=[];
        foreach(recycle_entries($id) as $entry){
            $kind=$entry['kind'];
            $restore=form_start('restore_archived','/?tab=sites','class="inline-form"').
                '<input type="hidden" name="recycle_kind" value="'.e($kind).'">'.
                '<input type="hidden" name="recycle_id" value="'.(int)$entry['id'].'">'.
                '<button type="submit" class="btn small">استعادة</button></form>';
            $recycleRows[]=[e($entry['name']),$kind==='site'?'موقع':'إيجنت',
                e((string)($entry['archived_at']??'أرشيف قديم')),$restore];
        }
        echo card('سلة المحذوفات — المواقع والمساعدون',
            '<p class="dim">الاستعادة لا تعيد تشغيل المساعد تلقائيًا ولا تلمس السجلات المالية أو سجل المكالمات.</p>'.
            tbl(['العنصر','النوع','تاريخ الأرشفة','إجراء'],$recycleRows));
        echo card('سياسة الاحتفاظ بالأرشيف',
            form_start('recycle_policy','/?tab=sites').
            '<label class="field">مدة الاحتفاظ بالأيام (7–3650)'.
            '<input name="retention_days" type="number" min="7" max="3650" value="'.(int)recycle_retention($id).'" required>'.
            '</label><p class="dim">مدة الاحتفاظ لا تنفذ حذفًا نهائيًا تلقائيًا. الحذف النهائي يحتاج تدقيق التبعيات والموافقات.</p>'.
            submit_btn('حفظ سياسة الاحتفاظ'));
    }
}
/** Only operational controls: stored text and tested read-only sources.
 * Caller-based privileges are registered here but NOT yet trusted by voice runtime.
 */
function render_voice_data_sources(array $a,array $site):void {
    require_once __DIR__.'/VoiceKnowledge.php';
    $id=(int)$a['account_id'];$siteId=(int)$site['id'];
    if(!allowed('agents.write'))return;
    $url='/?tab=agents&site='.$siteId;
    echo '<details class="agent-create voice-data-workspace"><summary class="btn">مصادر المعرفة والتكاملات وأرقام الموظفين</summary>';
    $folders=all('SELECT id,name,parent_id FROM voice_knowledge_folders WHERE account_id=? AND site_id=? ORDER BY parent_id,id LIMIT 100',[$id,$siteId]);
    $folderOptions=['0'=>'بدون مجلد'];
    $folderLabels=[0=>'بدون مجلد'];
    foreach($folders as $folder){
        $fid=(int)$folder['id'];
        $prefix=(int)$folder['parent_id']>0?'↳ ':'';
        $folderLabels[$fid]=$prefix.(string)$folder['name'];
        $folderOptions[(string)$fid]=$folderLabels[$fid];
    }
    $rows=all("SELECT id,name,kind,visibility,status,folder_id,version,content
       FROM voice_knowledge_sources WHERE account_id=? AND site_id=? AND status IN ('active','review') ORDER BY id DESC LIMIT 40",
       [$id,$siteId]);
    $html='<p class="info">قاعدة المعرفة اختيارية: لو فيه ملف أو نص أو رابط عام معتمد، المساعد يستفيد منه، ولو مفيش يشتغل بالسيناريو والتعليمات مباشرة من غير ما يتوقف. لا تحتاج ترفع ملفًا أو تضيف مصدرًا لحفظ أو نشر المساعد.</p>';
    $pdfReady=voice_knowledge_pdf_extractor()!==null;
    $html.='<p class="info">'.($pdfReady
       ?'استخراج PDF النصي متاح على الاستضافة. ملفات PDF المصورة تحتاج OCR منفصل.'
       :'استخراج PDF غير متاح على الاستضافة حاليًا؛ يلزم تثبيت Poppler pdftotext قبل قبول ملفات PDF. لا يتم الادعاء بفهرستها.').'</p>';
    $indexState=voice_kb_search_status($id,$siteId);
    $html.='<p class="info">فهرس الكلمات والمرادفات للمصادر العامة المعتمدة: '.
        (int)$indexState['ready'].' جاهز من '.(int)$indexState['total']. '؛ '.
        (int)$indexState['pending'].' تحتاج إعادة فهرسة، و'.
        (int)$indexState['review'].' بانتظار موافقة المالك.</p>';
    $html.=form_start('voice_knowledge_reindex',$url,'class="inline-form"').
        '<input type="hidden" name="site_id" value="'.$siteId.'">'.
        '<button class="btn small" type="submit">إعادة فهرسة المعرفة المعتمدة</button></form>';
    $html.=form_start('voice_knowledge_folder_add',$url).
        '<input type="hidden" name="site_id" value="'.$siteId.'">'.
        field_html('اسم المجلد الجديد','folder_name','text','مثلاً سياسات الحجز',true).
        select_html('المجلد الأعلى','parent_id',$folderOptions,'0').
        '<button class="btn" type="submit">إنشاء مجلد</button></form>';
    $html.=form_start('voice_knowledge_upload',$url,'enctype="multipart/form-data"').
        '<input type="hidden" name="site_id" value="'.$siteId.'">'.
        field_html('اسم مصدر المعرفة','name','text','مثلاً سياسة المواعيد',true).
        select_html('مجلد المعرفة','knowledge_folder_id',$folderOptions,'0').
        select_html('إتاحة المعرفة','knowledge_visibility',
            ['public'=>'عام للمساعد والعملاء','staff'=>'داخلي — لا يظهر للمتصلين']).
        '<label class="field">TXT / MD / CSV / JSON / DOCX / PDF (حتى 2MB؛ يتحول إلى نص فقط)<input type="file" name="knowledge_file" accept=".txt,.md,.csv,.json,.docx,.pdf"></label>'.
        '<label class="field">أو محتوى نصي (اختياري)<textarea name="knowledge_text" maxlength="180000" rows="4" placeholder="اختياري — لو فاضي، السيناريو يشتغل عادي"></textarea></label>'.
        '<button class="btn primary" type="submit">حفظ مصدر المعرفة (اختياري)</button></form>';
    foreach($rows as $row){
        $sourceId=(int)$row['id'];$fid=(int)$row['folder_id'];
        $html.='<div class="voice-data-row"><strong>'.e($row['name']).'</strong>'.
            '<span>'.e($folderLabels[$fid]??'مجلد غير متاح').' · '.e($row['kind']).
            ' · v'.(int)$row['version'].' · '.($row['visibility']==='public'?'عام':'داخلي').'</span>'.
            ($row['status']==='review'
                ?'<span>بانتظار مراجعة التغييرات — لن يظهر للإيجنت قبل الاعتماد</span>'.
                    form_start('voice_knowledge_approve',$url,'class="inline-form"').
                    '<input type="hidden" name="site_id" value="'.$siteId.'">'.
                    '<input type="hidden" name="source_id" value="'.$sourceId.'">'.
                    '<button class="btn small primary" type="submit">اعتماد النسخة الجديدة</button></form>'
                :'<details><summary>مراجعة وتعديل المصدر</summary>'.
            form_start('voice_knowledge_update',$url).
            '<input type="hidden" name="site_id" value="'.$siteId.'">'.
            '<input type="hidden" name="source_id" value="'.$sourceId.'">'.
            '<label class="field">المحتوى النصي<textarea name="knowledge_text" maxlength="180000" rows="5">'.e($row['content']).'</textarea></label>'.
            '<button class="btn small" type="submit">حفظ إصدار جديد</button></form></details>').
            form_start('voice_knowledge_delete',$url,'class="inline-form"').
            '<input type="hidden" name="source_id" value="'.$sourceId.'"><button class="btn small danger" type="submit">أرشفة المصدر</button></form></div>';
    }
    foreach($folders as $folder){
        $fid=(int)$folder['id'];
        $html.='<div class="voice-data-row"><span>مجلد: '.e($folderLabels[$fid]).'</span>'.
            form_start('voice_knowledge_folder_delete',$url,'class="inline-form"').
            '<input type="hidden" name="site_id" value="'.$siteId.'">'.
            '<input type="hidden" name="folder_id" value="'.$fid.'">'.
            '<button class="btn small danger" type="submit">حذف لو فارغ</button></form></div>';
    }
    echo card('قاعدة المعرفة والمجلدات',$html);
    // Public HTTPS sources are imported as review-only; model sees only approved facts.
    require_once __DIR__.'/VoiceKnowledgeWeb.php';
    $links=all("SELECT w.id,w.name,w.url,w.status,w.last_error,w.last_fetched_at,w.source_id,
           k.status source_status
        FROM voice_kb_web_links w LEFT JOIN voice_knowledge_sources k
          ON k.id=w.source_id AND k.account_id=w.account_id AND k.site_id=w.site_id
        WHERE w.account_id=? AND w.site_id=? ORDER BY w.id DESC LIMIT 40",[$id,$siteId]);
    $linkHtml='<p class="info">روابط HTTPS عامة فقط. منع عناوين الشبكات الخاصة وإعادة التوجيه. كل نسخة جديدة تدخل المراجعة قبل استخدامها. التحديث الآن يدوي؛ الموعد التالي يُسجَّل للمتابعة ولا ندّعي وجود Cron فهرسة آلي.</p>'.
        form_start('voice_knowledge_url_add',$url).
        '<input type="hidden" name="site_id" value="'.$siteId.'">'.
        field_html('اسم صفحة المعرفة','name','text','سياسة المواعيد',true).
        field_html('رابط صفحة HTTPS عامة','knowledge_url','url','https://example.com/faq',true).
        select_html('مجلد الرابط','knowledge_folder_id',$folderOptions,'0').
        select_html('الإتاحة','knowledge_visibility',['public'=>'عام','staff'=>'داخلي'],'public').
        '<label class="field">فاصل مراجعة التحديث بالساعات<input type="number" min="1" max="720" name="interval_hours" value="24"></label>'.
        submit_btn('حفظ الرابط للمراجعة').
        form_start('voice_knowledge_due_sync',$url).
        '<input type="hidden" name="site_id" value="'.$siteId.'">'.
        '<button class="btn" type="submit">فحص 3 روابط مستحقة للتحديث</button></form>';
    foreach($links as $link){
        $status=($link['source_status']??'')==='active'?'معتمد':(string)$link['status'];
        $linkHtml.='<div class="voice-data-row"><strong>'.e($link['name']).'</strong>'.
            '<span dir="ltr">'.e($link['url']).'</span><span>'.e($status).
            (!empty($link['last_error'])?' · '.e($link['last_error']):'').'</span>'.
            form_start('voice_knowledge_url_sync',$url,'class="inline-form"').
            '<input type="hidden" name="site_id" value="'.$siteId.'">'.
            '<input type="hidden" name="link_id" value="'.(int)$link['id'].'">'.
            '<button class="btn small" type="submit">جلب وتحديث الآن</button></form></div>';
    }
    echo card('روابط المعرفة والتحديث اليدوي',$linkHtml);
    $agentRows=all("SELECT id,name FROM agents WHERE account_id=? AND site_id=? AND status!='archived'
        ORDER BY id DESC LIMIT 80",[$id,$siteId]);
    if($agentRows&&$rows){
        $sourceOpts=[];$agentOpts=['0'=>'كل إيجنتات الموقع'];
        foreach($rows as $source)$sourceOpts[(string)$source['id']]=$source['name'].' #'.$source['id'];
        foreach($agentRows as $agent)$agentOpts[(string)$agent['id']]=$agent['name'].' #'.$agent['id'];
        $scopeHtml='<p class="dim">المصدر العام يمكن إتاحته لكل المساعدين أو لإيجنت واحد. انتهاء TTL يوقف ظهوره للمكالمات، ولا يجدد معلومات الفواتير أو الحجوزات الحيّة.</p>'.
            form_start('voice_knowledge_scope_set',$url).
            '<input type="hidden" name="site_id" value="'.$siteId.'">'.
            select_html('المصدر','source_id',$sourceOpts).
            select_html('نطاق المعرفة','scope_mode',['all'=>'كل الإيجنتات','agent'=>'إيجنت محدد'],'all').
            select_html('الإيجنت المختار','agent_id',$agentOpts,'0').
            '<label class="field">الأولوية 1–1000<input type="number" name="priority" min="1" max="1000" value="100"></label>'.
            '<label class="field">صلاحية المصدر بالساعات (0 = بدون انتهاء)<input type="number" name="ttl_hours" min="0" max="8760" value="0"></label>'.
            submit_btn('حفظ حدود المعرفة');
        echo card('صلاحيات المعرفة لكل Agent وTTL',$scopeHtml);
    }

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
        require_once __DIR__.'/VoiceConnectorCache.php';
        $cacheStatus=[];
        $sourcePriority=voice_connector_cache_priorities($id,$siteId);
        foreach(voice_connector_cache_rows($id,$siteId) as $snapshot)
            $cacheStatus[(int)$snapshot['connector_id']]=$snapshot;
        $cacheHtml='<p class="info">يجري تخزين المعلومات العامة فقط من موصل متحقق منه. تنتهي تلقائيًا حسب TTL، ويقرأها الإيجنت من السيرفر دون انتظار الشبكة. الفواتير والمواعيد الشخصية والحجوزات لا تُحفظ هنا ولا تعتبر مؤكدة دون API حيّة.</p>';
        foreach($connectors as $connector){
            if($connector['status']!=='verified')continue;
            $snapshot=$cacheStatus[(int)$connector['id']]??null;
            $state=$snapshot!==null?($snapshot['status']==='current'
                ?'سارية حتى '.$snapshot['expires_at'].' UTC'
                :'انتهت صلاحيتها '.$snapshot['expires_at'].' UTC')
                :'لا توجد لقطة بيانات عامة محفوظة';
            $cacheHtml.='<div class="voice-data-row"><strong>'.e($connector['name']).'</strong>'.
                '<span>'.e($state).' · أولوية '.(int)($sourcePriority[(int)$connector['id']]??100).'</span>'.
                form_start('voice_connector_cache_priority_set',$url,'class="inline-form"').
                '<input type="hidden" name="site_id" value="'.$siteId.'">'.
                '<input type="hidden" name="connector_id" value="'.(int)$connector['id'].'">'.
                '<label class="field">أولوية المصدر العام<input type="number" name="priority" min="1" max="1000" value="'.(int)($sourcePriority[(int)$connector['id']]??100).'" required></label>'.
                '<button class="btn small" type="submit">حفظ الأولوية</button></form>'.
                form_start('voice_connector_cache_refresh',$url,'class="inline-form"').
                '<input type="hidden" name="site_id" value="'.$siteId.'">'.
                '<input type="hidden" name="connector_id" value="'.(int)$connector['id'].'">'.
                '<label class="field">صلاحية بالدقائق<input type="number" name="ttl_minutes" min="5" max="1440" value="60" required></label>'.
                '<button class="btn small" type="submit">جلب معلومات عامة وتجديد صلاحيتها</button></form></div>';
        }
        echo card('Cache للبيانات العامة من API',$cacheHtml);
        // Approved connector discovery proposes endpoint drafts ONLY.
        require_once __DIR__.'/VoiceOpenApi.php';
        $restConnectors=array_values(array_filter($connectors,
            static fn($c)=>in_array($c['type'],['laravel','custom'],true)&&$c['status']==='verified'));
        $openApiHtml='<p class="info">اكتشاف OpenAPI 3 من نفس السيرفر الموثّق فقط: لا قراءة لخوادم يحددها الملف ولا تشغيل تلقائي لأدوات الكتابة. اكتشاف المسار لا يعني نجاح حجز أو ربط حي.</p>';
        if($restConnectors){
            $restChoices=[];
            foreach($restConnectors as $c)$restChoices[(string)$c['id']]=$c['name'].' · #'.$c['id'];
            $openApiHtml.=form_start('voice_openapi_discover',$url).
                '<input type="hidden" name="site_id" value="'.$siteId.'">'.
                select_html('موصل Laravel / REST متحقق','connector_id',$restChoices).
                select_html('مسار Schema الآمن','schema_path',[
                    '/openapi.json'=>'/openapi.json',
                    '/api/openapi.json'=>'/api/openapi.json',
                    '/docs/openapi.json'=>'/docs/openapi.json'
                ],'/openapi.json').
                submit_btn('فحص OpenAPI من المصدر');
            $candidates=voice_openapi_candidates($id,$siteId);
            foreach($candidates as $candidate){
                $openApiHtml.='<div class="voice-data-row"><strong>'.e($candidate['connector_name']).
                    '</strong><span>'.e($candidate['method']).' '.e($candidate['path']).' · '.
                    e($candidate['operation']).' · '.e($candidate['summary']).' · '.e($candidate['contract']??'schema_unverified').'</span>'.
                    form_start('voice_openapi_draft',$url,'class="inline-form"').
                    '<input type="hidden" name="site_id" value="'.$siteId.'">'.
                    '<input type="hidden" name="connector_id" value="'.(int)$candidate['connector_id'].'">'.
                    '<input type="hidden" name="schema_sha256" value="'.e($candidate['sha256']).'">'.
                    '<input type="hidden" name="tool_operation" value="'.e($candidate['operation']).'">'.
                    '<input type="hidden" name="tool_path" value="'.e($candidate['path']).'">'.
                    '<button class="btn small" type="submit">حفظ كمسودة متوقفة</button></form></div>';
            }
        }else $openApiHtml.='<p class="dim">أضف موصل REST واختبره بنجاح أولًا.</p>';
        echo card('OpenAPI · اكتشاف مسارات Laravel / REST',$openApiHtml);
        // External tools require an owner-pinned policy and a separate approval.
        // These tests are operator-initiated; published call flows NEVER infer success.
        require_once __DIR__.'/VoiceToolGateway.php';
        voice_tools_init();
        $verified=all("SELECT id,name,type FROM voice_data_connectors WHERE account_id=? AND site_id=? AND status='verified'
            ORDER BY id DESC LIMIT 40",[$id,$siteId]);
        $toolChoices=[];
        foreach($verified as $toolConnector)$toolChoices[(string)$toolConnector['id']]=
            $toolConnector['name'].' · '.$toolConnector['type'];
        $operationChoices=[
            'whmcs.products'=>'WHMCS: المنتجات','whmcs.services'=>'WHMCS: خدمات عميل متحقق',
            'whmcs.invoices'=>'WHMCS: فواتير عميل متحقق','whmcs.tickets'=>'WHMCS: تذاكر عميل متحقق',
            'whmcs.ticket.create'=>'WHMCS: فتح تذكرة بموافقة صريحة',
            'whmcs.ticket.reply'=>'WHMCS: رد على تذكرة بموافقة',
            'rest.slots'=>'Laravel/REST: قراءة المواعيد',
            'rest.booking.create'=>'Laravel/REST: إنشاء حجز بموافقة'];
        $toolHtml='<p class="info">التنفيذ الخارجي مغلق افتراضيًا؛ لا يمكن للمساعد اختيار عنوان أو صلاحية أو إعلان حجز دون مرجع حقيقي. اعتماد الأدمن لسياسة تشغيل لا يُنفذ الطلب: كل طلب يحتاج موافقة ثانية.</p>'.
            '<p class="dim">الحجز REST يحتاج 3 خطوات موثقة: استعلم عن rest.slots لنفس Call ID وService ID، '.
            'انتظر رد المواعيد المتاحة من المزود، ثم حضّر rest.booking.create لنفس الموصل والعميل والمكالمة. '.
            'راجع اسم ورقم العميل والموعد قبل التأكيد. بدون Booking ID حقيقي لا تعلن نجاح الحجز.</p>';
        if($toolChoices){
            $toolHtml.=form_start('voice_tool_policy_save',$url).
                '<input type="hidden" name="site_id" value="'.$siteId.'">'.
                select_html('موصل سبق التحقق منه','connector_id',$toolChoices).
                select_html('العملية المسموح بها','tool_operation',$operationChoices).
                '<label class="field">مسار HTTPS المحدد لـLaravel/REST فقط<input name="tool_path" maxlength="350" placeholder="/api/appointments"></label>'.
                '<label class="field"><input type="checkbox" name="tool_enabled" value="1"> تفعيل هذه العملية في سياسة الموقع</label>'.
                submit_btn('حفظ سياسة العملية');
        }else $toolHtml.='<p class="dim">اختبر اتصالًا أولًا من قسم تكاملات القراءة؛ ليس هناك موصل متحقق.</p>';
        $policyRows=all("SELECT p.id,p.operation,p.enabled,p.path,c.name FROM voice_tool_policies p
          JOIN voice_data_connectors c ON c.id=p.connector_id AND c.account_id=p.account_id
          WHERE p.account_id=? AND p.site_id=? ORDER BY p.id DESC LIMIT 30",[$id,$siteId]);
        $toolsForTest=[];
        foreach($policyRows as $policy){
            $toolHtml.='<div class="voice-data-row"><strong>'.e($policy['name']).'</strong>'.
                '<span>'.e($policy['operation']).' · '.($policy['enabled']?'مفعل بشرط الموافقة':'متوقف').
                ' · '.e($policy['path']).'</span></div>';
            if($policy['enabled'])$toolsForTest[(string)$policy['id']]=$policy['operation'].' · '.$policy['name'];
        }
        if($toolsForTest){
            $toolHtml.='<details><summary>تجربة عملية موثقة (تتطلب موافقة منفصلة)</summary>'.
                form_start('voice_tool_prepare',$url).
                '<input type="hidden" name="site_id" value="'.$siteId.'">'.
                select_html('عملية من سياسة محددة','policy_id',$toolsForTest).
                '<label class="field">Call ID — مطلوب للحجز، ويجب مطابقته لاستعلام المواعيد السابق<input name="call_id" maxlength="64" placeholder="call-123456789"></label>'.
                '<label class="field">معاملات العملية JSON حسب نوعها<textarea name="tool_payload_json" rows="4" maxlength="5000" required placeholder="{&quot;client_id&quot;:123}">{}</textarea></label>'.
                submit_btn('تحضير الطلب للمراجعة').'</details>';
        }
        $pending=$_SESSION['voice_tool_pending']??null;
        if(is_array($pending)&&(int)($pending['account_id']??0)===$id&&
            (int)($pending['site_id']??0)===$siteId&&is_string($pending['approval_token']??null)){
            $preview=$pending['preview']??null;
            $review=is_array($preview)&&is_array($preview['fields']??null)
                ?'<pre dir="auto" class="voice-tool-review">'.e(json_encode([
                    'operation'=>$preview['operation']??'',
                    'fields'=>$preview['fields'],'executed'=>false],
                    JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)).'</pre>'
                :'<p class="dim">المعاينة القديمة غير متاحة؛ أعد تحضير الطلب قبل الموافقة.</p>';
            $toolHtml.='<section class="card"><strong>تأكيد مستقل قبل التنفيذ</strong>'.
                '<p class="info">لن يُرسل طلب للخدمة الخارجية إلا بعد قراءة المعاملات والموافقة المنفصلة. عملية كتابة ناجحة لا يُعلن عنها إلا بإيصال المرجع.</p>'.
                $review.
                form_start('voice_tool_confirm',$url).
                '<input type="hidden" name="site_id" value="'.$siteId.'">'.
                '<input type="hidden" name="request_id" value="'.(int)$pending['request_id'].'">'.
                '<label class="field"><input name="explicit_consent" type="checkbox" value="yes" required> أؤكد تنفيذ الطلب المخزن لدى الخادم بالخدمة الخارجية</label>'.
                submit_btn('تأكيد التنفيذ الآن').'</section>';
        }
        $history=voice_tool_history($id,$siteId,20);
        if($history){
            $historyRows=[];
            foreach($history as $entry)$historyRows[]=[
                (int)$entry['id'],e($entry['operation']),e($entry['status']),
                e((string)($entry['reference_id']??'')),e((string)($entry['error_code']??''))];
            $toolHtml.=tbl(['رقم الطلب','العملية','النتيجة','معرّف النظام','الكود'],$historyRows);
        }
        // Outstanding real SIM Flow nodes only: owner must prepare and
        // independently approve a matching Tool Gateway request first.
        // Never present a demo simulation as evidence of a live call.
        require_once __DIR__.'/VoiceFlowRuntime.php';
        voice_flow_runtime_init();
        $pendingDeviceFlows=all("SELECT id,call_id,agent_id,state_json FROM voice_flow_sessions
            WHERE account_id=? AND site_id=? AND source='device'
              AND status='awaiting_tool' ORDER BY updated_at DESC LIMIT 20",[$id,$siteId]);
        if($pendingDeviceFlows){
            $toolHtml.='<section class="card"><h4>طلبات Flow معلقة من شريحة حقيقية</h4>'.
                '<p class="dim">العملية هنا لا تتنفذ تلقائيًا من كلام Gemini؛ لازم طلب API ناجح بنفس Call ID والأداة وموافقة بشرية منفصلة.</p>';
            foreach($pendingDeviceFlows as $pendingFlow){
                $state=json_decode((string)$pendingFlow['state_json'],true);
                $policyId=(int)($state['pending_tool']['policy_id']??0);
                if($policyId<1)continue;
                $toolHtml.='<div class="voice-data-row"><strong>Flow #'.(int)$pendingFlow['id'].
                    ' · Call '.e((string)$pendingFlow['call_id']).'</strong><small>Agent #'.
                    (int)$pendingFlow['agent_id'].' · Policy #'.$policyId.'</small>';
                $actorMember=me();
                $candidate=$actorMember?one("SELECT id,status FROM voice_tool_requests
                    WHERE account_id=? AND site_id=? AND actor_id=? AND policy_id=?
                      AND call_id=? AND status IN ('succeeded','failed','uncertain')
                    ORDER BY id DESC LIMIT 1",[
                    $id,$siteId,(int)$actorMember['id'],$policyId,
                    (string)$pendingFlow['call_id']]):null;
                if($candidate){
                    $toolHtml.=form_start('voice_flow_device_tool_complete',$url,'class="inline-form"').
                        '<input type="hidden" name="site_id" value="'.$siteId.'">'.
                        '<input type="hidden" name="session_id" value="'.(int)$pendingFlow['id'].'">'.
                        '<input type="hidden" name="tool_request_id" value="'.(int)$candidate['id'].'">'.
                        '<button class="btn small" type="submit">تثبيت نتيجة API المؤكدة ('.e($candidate['status']).')</button></form>';
                }else{
                    $toolHtml.='<small>محتاج تحضير وتأكيد عملية #'.$policyId.
                        ' لنفس Call ID من النموذج بالأعلى، وبعدها يظهر زر تثبيت النتيجة.</small>';
                }
                $toolHtml.='</div>';
            }
            $toolHtml.='</section>';
        }
        echo card('Integration Manager · العمليات والموافقات',$toolHtml);

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

/** Both editors are retained. Only the selected one guides live speech after publish. */
function agent_scenario_mode_picker(array $policy):string {
    return '<div class="agent-studio-section agent-scenario-choice">'.
        '<strong>طريقة تشغيل السيناريو في المكالمات</strong>'.
        '<p class="info">اختار النص الحر أو مخطط Flow لكل مساعد. تغيير الاختيار لا يمسح أي سيناريو أو عقدة؛ النشر وحده يفعّل الاختيار في المكالمات.</p>'.
        select_html('مسار المحادثة الفعّال','scenario_mode',[
            'builder'=>'Flow Builder — المخطط المرئي',
            'scenario'=>'سيناريو نصي — كلام مكتوب خطوة بخطوة'],$policy['scenario_mode']).
        '</div>';
}
function agent_scenario_text_editor(string $value):string {
    return '<div class="agent-builder-intro"><strong>السيناريو النصي</strong><p>اكتب طريقة بدء المكالمة، الأسئلة، الردود، الاعتراضات، والإنهاء. المساعد يفهم النص ويمشي عليه بشكل طبيعي، بدون تحويله إلى بلوكات.</p></div>'.
        '<label class="field">نص السيناريو (حد أقصى 8000 حرف)'.
        '<textarea name="scenario_text" data-scenario-text rows="14" maxlength="8000" dir="auto" placeholder="ابدأ بالترحيب... اسأل عن احتياج العميل... وضّح الخدمة... تعامل مع الاعتراضات... أنهِ المكالمة...">'.e($value).'</textarea></label>'.
        '<div class="row" style="gap:8px;flex-wrap:wrap">'.
        '<button class="btn" type="button" data-scenario-example="dog-sale">تحميل مثال ديمو بيع كلب (قابل للتعديل)</button>'.
        '<button class="btn" type="button" data-scenario-example="lebanese-feedback">تقييم خدمة Voice Agent · لبناني ودلع (قابل للتعديل)</button>'.
        '</div>'.
        '<small class="dim">ده مثال جديد بنفس فكرة ديمو البيع، مش استرجاع حرفي لنص الديمو القديم. السيناريو بيتحفظ حتى وإنت مشغّل البيلدر.</small>';
}
function agent_api_source_picker(array $policy,array $sources):string {
    return '<div class="agent-studio-section"><div class="agent-studio-section-title"><strong>تجهيز API متعدد اللغات</strong><small>مصدر واحد موثّق لكل مساعد؛ القراءة فقط في المرحلة الحالية</small></div>'.
        select_html('مصدر API المختبر','api_connector_id',$sources,(string)$policy['api_connector_id']).
        select_html('لغة التعامل مع بيانات API','api_language',[
            'auto'=>'تلقائي حسب لغة المتصل',
            'ar'=>'العربية','en'=>'English',
            'multilingual'=>'متعدد اللغات'],(string)$policy['api_language']).
        '<p class="info">أضف المصدر من «مصادر المعرفة والتكاملات»، واختبر اتصاله الأول. إعداد اللغة لا يمنح صلاحيات كتابة، ولا يشغّل استدعاءات API أثناء المكالمة تلقائيًا.</p></div>';
}
/** استوديو المساعدين replaces the obsolete branching-template UI. */
function agent_studio_policy_fields(array $policy):string{
    $html='<div class="agent-studio-section"><div class="agent-studio-section-title"><strong>الهدف والسلوك</strong><small>المساعد يختار مسار المحادثة بنفسه</small></div>'.
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
function voice_gender_label(string $g):string {
    return match($g){'female'=>'أنثى','male'=>'ذكر',default=>'محايد'};
}
function voice_provider_label(string $p):string {
    return match($p){
        'fish_audio'=>'Fish Audio','gemini_tts'=>'Google TTS',
        'gemini_live'=>'Gemini Live',default=>$p
    };
}
function voice_sample_preview_control(array $voice):string {
    $vid=(int)($voice['id']??0);
    $rel=trim((string)($voice['sample_path']??''));
    $cached=false;$sampleHref='';
    if($rel!==''&&preg_match('/^assets\/voice-samples\/[A-Za-z0-9._-]{4,100}\.(mp3|wav|ogg)$/D',$rel)===1){
        $local=__DIR__.'/../'.$rel;
        if(is_file($local)&&filesize($local)>64){
            $cached=true;
            $sampleHref='/saas/console/'.$rel;
        }
    }
    return '<div class="voice-sample-box" data-voice-sample-box data-voice-id="'.$vid.'">'.
        '<button type="button" class="btn small voice-preview-btn" data-voice-preview data-voice-id="'.$vid.'"'.
        ($sampleHref!==''?' data-sample-url="'.e($sampleHref).'"':'').'>▶ معاينة الصوت</button>'.
        '<span class="'.($cached?'pill ok ':'').'voice-cache-tag"'.($cached?'':' hidden').'>كاش محلي · 0 توكن</span>'.
        '<audio class="voice-preview-audio" controls preload="none"'.
        ($sampleHref!==''?' src="'.e($sampleHref).'"':' hidden').'></audio>'.
        '</div>';
}
function agent_voice_picker_html(string $selectHtml,array $voiceRows,string $selectedCode):string {
    $selectHtml=str_replace('<select name="voice">','<select name="voice" data-voice-select>',$selectHtml);
    $cards='';
    foreach($voiceRows as $vr){
        $code=(string)($vr['code']??'');
        if($code==='')continue;
        $label=(string)($vr['display_name']??$vr['name']??$code);
        $gender=(string)($vr['gender']??'neutral');
        if(!in_array($gender,['female','male','neutral'],true))$gender='neutral';
        $dialect=trim((string)($vr['dialect']??''));
        $provider=(string)($vr['provider']??'');
        $providerGroup=str_starts_with($provider,'fish')?'fish_audio':'google';
        $active=$code===$selectedCode;
        $haystack=mb_strtolower($label.' '.$code.' '.$dialect.' '.$provider.' '.voice_gender_label($gender),'UTF-8');
        $cards.='<div class="voice-picker-card'.($active?' selected':'').'" role="button" tabindex="0"'.
            ' aria-pressed="'.($active?'true':'false').'"'.
            ' data-voice-pick-card data-voice-code="'.e($code).'"'.
            ' data-voice-gender="'.e($gender).'"'.
            ' data-voice-provider="'.e($providerGroup).'"'.
            ' data-voice-haystack="'.e($haystack).'">'.
            '<div class="voice-picker-card-head"><strong>'.e($label).'</strong>'.
            ($active?'<span class="pill ok voice-selected-badge">مختار</span>':'<span class="voice-selected-badge" hidden>مختار</span>').
            '</div>'.
            '<div class="voice-picker-badges">'.
            '<span class="voice-badge gender-'.e($gender).'">'.e(voice_gender_label($gender)).'</span>'.
            ($dialect!==''?'<span class="voice-badge dialect">'.e($dialect).'</span>':'').
            '<span class="voice-badge provider">'.e(voice_provider_label($provider)).'</span>'.
            '</div>'.
            voice_sample_preview_control($vr).
            '</div>';
    }
    $picker='<div class="voice-picker" data-voice-picker data-preview-url="/saas/console/" data-preview-csrf="'.e(csrf()).'">'.
        $selectHtml.
        '<div class="voice-picker-toolbar">'.
        '<input type="search" class="voice-picker-search" data-voice-search placeholder="ابحث باسم الصوت أو اللهجة أو المزوّد...">'.
        '<div class="voice-picker-filters" role="group" aria-label="تصفية الأصوات">'.
        '<button type="button" class="btn small active" data-voice-filter="all">الكل</button>'.
        '<button type="button" class="btn small" data-voice-filter="female">أنثى</button>'.
        '<button type="button" class="btn small" data-voice-filter="male">ذكر</button>'.
        '<button type="button" class="btn small" data-voice-filter="fish_audio">Fish Audio</button>'.
        '<button type="button" class="btn small" data-voice-filter="google">Google</button>'.
        '</div></div>'.
        '<div class="voice-picker-grid">'.($cards?:'<p class="dim">استخدم القائمة أعلاه لاختيار الصوت المتاح.</p>').'</div>'.
        '</div>';
    return '<div class="voice-picker-wrap">'.$picker.'</div>';
}
function render_agents(array $a):void{
    require_once __DIR__.'/AgentAnswerPolicy.php';
    $id=(int)$a['account_id'];
    $sites=all("SELECT id,name FROM sites WHERE account_id=? AND status='active' ORDER BY id",[$id]);
    $selected=selected_site($id);
    $map=[];foreach($sites as $site)$map[$site['id']]=$site['name'];
    echo '<div class="section-line agents-toolbar"><div class="agents-heading"><span class="eyebrow">مساحة المساعدين</span>'.
        '<h2>المساعدون</h2><p>صمّم مساعدك وعدّل مهمته وصوته ومخطط المحادثة.</p>'.
        '</div><div class="site-picker" aria-label="اختيار الموقع">';
    foreach($sites as $site)
        echo '<a class="chip-link '.($selected&&(int)$site['id']===(int)$selected['id']?'chosen':'').
            '" href="?tab=agents&site='.(int)$site['id'].'">'.e($site['name']).'</a>';
    echo '</div></div>';
    if(!$sites){echo card('إنشاء مساحة عمل','أضف موقعًا أولًا من قسم المواقع.');return;}
    if($selected)render_voice_data_sources($a,$selected);
    echo '<div class="agent-studio-banner"><strong>مساعدك، بطريقتك</strong>'.
        '<p>حدّد هدف المساعد وطريقة كلامه، ثم صمّم المحادثة التي تناسب شغلك.</p></div>';
    require_once __DIR__.'/IoPremiumProvider.php';
    $voiceQuery=$a['kind']==='platform'
        ?"SELECT * FROM voices WHERE ready=1 AND status='active' ORDER BY tier DESC, provider DESC, name ASC"
        :"SELECT * FROM voices WHERE ready=1 AND status='active' AND (code='io.premium' OR id IN (SELECT voice_id FROM voice_grants WHERE account_id=?)) ORDER BY tier DESC, provider DESC, name ASC";
    $rawVoices=$a['kind']==='platform'?all($voiceQuery):all($voiceQuery,[$id]);
    $voiceRows=[];$voices=[];
    $ioAllowed=io_premium_access_allowed($id);
    foreach($rawVoices as $vr){
        $code=(string)$vr['code'];
        if($code==='io.premium'){
            if(!$ioAllowed)continue;
            $vr['display_name']=(string)$vr['name'].' · مفتاح المنصة';
        }else{
            $vr['display_name']=(string)$vr['name'];
        }
        $voices[$code]=$vr['display_name'];
        $voiceRows[$code]=$vr;
    }
    if(!$voices)$voices=['google.aoede'=>'Aoede'];
    $apiSources=['0'=>'بدون مصدر API'];
    if($selected)foreach(all("SELECT id,name,type FROM voice_data_connectors
        WHERE account_id=? AND site_id=? AND status='verified' ORDER BY name ASC LIMIT 100",[
        $id,(int)$selected['id']]) as $source)
        $apiSources[(string)$source['id']]=$source['name'].' ('.$source['type'].')';
    if(allowed('agents.write')){
        $defaults=answer_policy_defaults();
        $defaultVoiceCode=(string)(array_key_first($voices)??'google.aoede');
        $create=form_start('add_agent','/?tab=agents&site='.(int)$selected['id'],'class="agent-studio-form-create"').
            '<div class="form-grid">'.field_html('اسم المساعد','name','text','مساعد خدمة العملاء',true).
            select_html('الموقع','site_id',$map).'</div>'.
            agent_voice_picker_html(select_html('الصوت','voice',$voices,$defaultVoiceCode),array_values($voiceRows),$defaultVoiceCode).
            '<label class="field">التعليمات وأسلوب الحديث<textarea name="instructions" rows="4" maxlength="5000" placeholder="اتكلم بالمصري وبأسلوب مهني..."></textarea></label>'.
            '<label class="field">معرفة المساعد<textarea name="knowledge" rows="4" maxlength="20000" placeholder="المنتجات، الخدمات والسياسات المعتمدة"></textarea></label>'.
            agent_scenario_mode_picker($defaults).
            agent_scenario_text_editor('').
            agent_api_source_picker($defaults,$apiSources).
            agent_studio_policy_fields($defaults).
            '<div class="agent-publish-actions"><button class="btn primary" type="submit" name="publish_now" value="1">إنشاء ونشر المساعد</button>'.
            '<button class="btn" type="submit">إنشاء مسودة</button></div></form>';
        echo '<details class="agent-create"><summary class="btn primary">+ إنشاء مساعد</summary>'.
            card('إنشاء مساعد جديد',$create).'</details>';
    }
    $agents=$selected?all("SELECT * FROM agents WHERE account_id=? AND site_id=? AND status!='archived' ORDER BY id DESC",
        [$id,$selected['id']]):[];
    foreach($agents as $agent){
        $policy=answer_policy_for_agent($id,(int)$agent['id']);
        $version=published_agent_flow($id,(int)$selected['id'],(int)$agent['id']);
        $summary='<div class="agent-summary"><span class="pill neutral">مساعد ذكي</span>'.
            '<span class="pill neutral">'.($policy['scenario_mode']==='scenario'?'السيناريو النصي':'Flow Builder').'</span>'.
            pill((string)$agent['status']).'<span class="dim">صوت: '.e($voices[$agent['voice']]??'الصوت المختار').'</span>'.
            ($version?'<span class="pill neutral">المنشور v'.(int)$version['revision'].'</span>':'<span class="pill neutral">غير منشور</span>').'</div>';
        if(allowed('agents.write')){
            $options=$voices;
            if(!isset($options[$agent['voice']]))$options[$agent['voice']]=$agent['voice'].' (الحالي)';
            $restoreForm='';
            $past=all('SELECT revision,published_at FROM agent_flow_versions
                WHERE account_id=? AND site_id=? AND agent_id=? ORDER BY revision DESC LIMIT 20',
                [$id,(int)$selected['id'],(int)$agent['id']]);
            if($past){
                $versionChoices=[];
                foreach($past as $versionRow)$versionChoices[(string)$versionRow['revision']]=
                    'v'.(int)$versionRow['revision'].' · '.(string)$versionRow['published_at'];
                $restoreForm='<div class="agent-studio-section"><strong>استرجاع نسخة منشورة إلى المسودة</strong>'.
                    '<p class="dim">يسترجع المخطط والسيناريو وإعدادات النسخة المختارة إلى المحرر فقط؛ المنشور يظل كما هو حتى توافق على نشر مستقل.</p>'.
                    form_start('restore_agent_flow_draft','/?tab=agents&site='.(int)$selected['id'],
                        'class="inline-form"').
                    '<input type="hidden" name="agent_id" value="'.(int)$agent['id'].'">'.
                    select_html('النسخة المحفوظة','revision',$versionChoices).
                    '<button class="btn small" type="submit" data-confirm="استرجاع النسخة كمسودة بدون نشر؟">استرجاع كمسودة</button></form></div>';
            }
            $editing=form_start('update_agent','/?tab=agents&site='.(int)$selected['id'],
                'class="stack agent-form agent-studio-form" action="'.e(white_label_url('/')).'"').
                '<input type="hidden" name="agent_id" value="'.(int)$agent['id'].'">'.
                agent_scenario_mode_picker($policy).
                '<div class="agent-builder-tabs" role="tablist" aria-label="أقسام إعدادات الإيجنت">'.
                '<button type="button" class="agent-builder-tab'.($policy['scenario_mode']==='builder'?' active':'').'" data-builder-tab="visual" aria-selected="'.($policy['scenario_mode']==='builder'?'true':'false').'">✦ Flow Builder</button>'.
                '<button type="button" class="agent-builder-tab'.($policy['scenario_mode']==='scenario'?' active':'').'" data-builder-tab="scenario" aria-selected="'.($policy['scenario_mode']==='scenario'?'true':'false').'">✎ السيناريو النصي</button>'.
                '<button type="button" class="agent-builder-tab" data-builder-tab="intelligence" aria-selected="false">🧠 الشخصية والمهام</button>'.
                '<button type="button" class="agent-builder-tab" data-builder-tab="knowledge" aria-selected="false">📚 المعرفة والتكاملات</button>'.
                '<button type="button" class="agent-builder-tab" data-builder-tab="voice" aria-selected="false">⚙️ الصوت والمكالمة</button></div>'.
                '<div class="agent-builder-pane" data-builder-pane="visual"'.($policy['scenario_mode']==='scenario'?' hidden':'').'>'.
                '<div class="agent-builder-intro"><strong>تصميم المحادثة</strong><p>رتّب الخطوات والوصلات واسحبها بحرية. مساعد التصميم متاح للاقتراح والتعديل بعد موافقتك.</p></div>'.
                '<div class="flow-builder" data-flow-editor><textarea name="flow_json" class="flow-source" rows="7" maxlength="16000">'.e((string)$agent['flow_json']).'</textarea></div></div>'.
                '<div class="agent-builder-pane" data-builder-pane="scenario"'.($policy['scenario_mode']!=='scenario'?' hidden':'').'>'.
                agent_scenario_text_editor($policy['scenario_text']).'</div>'.
                '<div class="agent-builder-pane" data-builder-pane="intelligence" hidden>'.
                '<div class="form-grid"><label class="field">اسم المساعد<input name="name" maxlength="140" required value="'.e($agent['name']).'"></label>'.
                '</div>'.
                '<label class="field">التعليمات وأسلوب الحديث<textarea name="instructions" rows="4" maxlength="5000">'.e($agent['instructions']).'</textarea></label>'.
                agent_studio_policy_fields($policy).
                '</div><div class="agent-builder-pane" data-builder-pane="knowledge" hidden>'.
                '<div class="agent-studio-section"><strong>المعرفة والتكاملات</strong><p class="info">اربط الملفات ومصادر WHMCS وLaravel وWordPress من مصادر بيانات الموقع. المساعد يقترح ما يلزم منها دون إنشاء صلاحيات أو مفاتيح تلقائيًا.</p>'.
                '<label class="field">معلومات المساعد<textarea name="knowledge" rows="6" maxlength="20000">'.e($agent['knowledge']).'</textarea></label></div>'.
                agent_api_source_picker($policy,$apiSources).'</div>'.
                '<div class="agent-builder-pane" data-builder-pane="voice" hidden>'.
                '<div class="agent-studio-section"><div class="agent-studio-section-title"><strong>الصوت والرد والمكالمة</strong></div>'.
                agent_voice_picker_html(select_html('الصوت المستخدم لهذا المساعد','voice',$options,(string)$agent['voice']),array_values($voiceRows),(string)$agent['voice']).
                '<p class="dim">الصوت المختار بيتفعّل في المكالمات بعد الضغط على «نشر إعدادات المساعد»؛ أصوات Fish Audio وIO Premium تحتاج اعتماد الإدارة وإتاحة الصلاحية للحساب.</p>'.
                '<div class="form-grid"><label class="field">مهلة الرد بالثواني<input name="answer_delay_seconds" type="number" min="0" max="30" value="'.(int)$policy['answer_delay_seconds'].'" required></label>'.
                '<label class="field">المدة القصوى بالدقائق<input name="max_call_minutes" type="number" min="1" max="30" value="'.(int)$policy['max_call_minutes'].'" required></label>'.
                select_html('الأسلوب','response_style',[
                    'egyptian_short'=>'مصري مختصر','formal_ar'=>'عربي رسمي','english'=>'إنجليزي','bilingual'=>'عربي وإنجليزي','multilingual'=>'أي لغة يتحدث بها العميل'],$policy['response_style']).
                select_html('عند تعذر الاستكمال','fallback',['human'=>'عرض مساعدة موظف','end'=>'إنهاء بأدب'],$policy['fallback']).'</div>'.
                '<label class="field"><input type="checkbox" name="auto_answer"'.($policy['auto_answer']?' checked':'').'> الرد الآلي عند تفعيل ربط الشريحة</label></div></div>'.
                '<div class="agent-publish-actions"><button class="btn primary" type="submit" name="publish_now" value="1">نشر إعدادات المساعد</button>'.
                '<button class="btn" type="submit">حفظ مسودة بدون تحديث</button>'.
                '<span class="agent-studio-status" role="status"></span></div></form>'.
                $restoreForm.
                form_start('archive_agent','/?tab=agents&site='.(int)$selected['id'],'class="inline-form"').
                '<input type="hidden" name="agent_id" value="'.(int)$agent['id'].'">'.
                '<button class="btn small danger" data-confirm="أرشفة المساعد؟">أرشفة المساعد</button></form>';
            $summary.=$editing;
        }else $summary.='<p>'.e((string)$agent['instructions']).'</p>';
        echo '<details class="agent-editor" id="agent-'.(int)$agent['id'].'"'.
            ((string)($_GET['agent']??'')===(string)$agent['id']?' open':'').
            '><summary><strong>'.e($agent['name']).'</strong><span>'.pill((string)$agent['status']).
            '</span><span class="dim">تعديل المهمة والمخطط والصوت</span></summary>'.
            card('إعدادات المساعد · '.e($agent['name']),$summary).'</details>';
    }
    if(!$agents)echo '<p class="info">لا يوجد مساعدون للموقع الحالي. أنشئ مساعدك الأول من الأعلى.</p>';
}
function render_flows(array $a):void{
    $id=(int)$a['account_id'];
    $agents=all("SELECT ag.id,ag.name,ag.site_id,s.name site_name FROM agents ag JOIN sites s ON s.id=ag.site_id
        WHERE ag.account_id=? AND ag.status!='archived' AND s.status='active' ORDER BY ag.id DESC",[$id]);
    echo '<p class="info">اختر المساعد لعرض مخطط المحادثة وتعديله بصريًا، ثم احفظ مسودة أو انشر التغييرات.</p>';
    $rows=[];
    foreach($agents as $agent){
        $version=published_agent_flow($id,(int)$agent['site_id'],(int)$agent['id']);
        $url='/?tab=agents&site='.(int)$agent['site_id'].'&agent='.(int)$agent['id'];
        $rows[]=[e($agent['name']),e($agent['site_name']),
            $version?'v'.(int)$version['revision']:'مسودة',
            '<a class="btn small primary" href="'.e($url).'">فتح المخطط</a>'];
    }
    echo card('مخططات المحادثة',tbl(['المساعد','الموقع','النسخة','الإجراء'],$rows));
    if(in_array($a['role'],['owner','admin'],true)&&allowed('agents.write')&&!active_support()){
        require_once __DIR__.'/VoiceFlowRuntime.php';
        voice_flow_runtime_init();
        $available=[];
        foreach($agents as $agent)
            if(published_agent_flow($id,(int)$agent['site_id'],(int)$agent['id']))
                $available[(string)$agent['id']]=$agent['name'].' · '.$agent['site_name'];
        if($available){
            $test='<p class="info">محاكاة خادم لحالة العقد والانتقالات. لا تُعادل مكالمة SIM، ولا تنفذ عملية خارجية دون موافقة ونتيجة API.</p>';
            foreach($agents as $agent){
                if(!isset($available[(string)$agent['id']]))continue;
                $test.=form_start('voice_flow_test_start','/?tab=flows','class="inline-form"').
                    '<input type="hidden" name="agent_id" value="'.(int)$agent['id'].'">'.
                    '<input type="hidden" name="site_id" value="'.(int)$agent['site_id'].'">'.
                    '<button class="btn small" type="submit">اختبار '.e($agent['name']).'</button></form>';
            }
            $active=$_SESSION['voice_flow_active']??null;
            if(is_array($active)&&(int)($active['account_id']??0)===$id&&
               (int)($active['actor_id']??0)===(int)(me()['id']??0)){
                try{
                    $s=voice_flow_session($id,(int)$active['session_id'],(int)me()['id']);
                    $state=json_decode((string)$s['state_json'],true);
                    $test.='<p><strong>جلسة #'.(int)$s['id'].'</strong> · '.e($s['status']).
                        ' · Node: '.e((string)($state['cursor']??'')).' · revision '.(int)$s['published_revision'].'</p>'.
                        form_start('voice_flow_test_step','/?tab=flows').
                        '<input type="hidden" name="session_id" value="'.(int)$s['id'].'">'.
                        '<label class="field">رد العميل عند انتظار سؤال (اتركه فارغًا لبداية المسار)<input name="flow_reply" maxlength="500"></label>'.
                        submit_btn('تنفيذ خطوة على السيرفر');
                    $pending=$state['pending_tool']??null;
                    if(is_array($pending)){
                        $test.='<p>العقدة '.e($pending['node_id']).' تنتظر نتيجة API موثقة؛ استخدم Integration Manager بنفس Call ID: '.e($s['call_id']).'</p>'.
                            form_start('voice_flow_test_tool_complete','/?tab=flows').
                            '<input type="hidden" name="session_id" value="'.(int)$s['id'].'">'.
                            '<label class="field">معرّف طلب API المؤكد<input type="number" name="tool_request_id" min="1" required></label>'.
                            submit_btn('ربط نتيجة API بالخطة');
                    }
                    $last=$_SESSION['voice_flow_last']??null;
                    if(is_array($last))foreach((array)($last['events']??[]) as $event)
                        $test.='<p>'.e((string)($event['kind']??'event')).': '.e((string)($event['text']??$event['reference_id']??'')).'</p>';
                    $traceRows=[];
                    foreach(voice_flow_session_traces($id,(int)$s['id'],(int)me()['id']) as $trace)
                        $traceRows[]=[e($trace['created_at']),e($trace['node_id']),
                            e($trace['event_type']),e($trace['event_detail'])];
                    $test.=tbl(['الوقت','العقدة','الحدث','التفاصيل'],$traceRows);
                }catch(Throwable $error){
                    $test.='<p class="dim">جلسة المحاكاة غير متاحة؛ افتح جلسة جديدة.</p>';
                }
            }
            echo card('اختبار تنفيذ Flow وتتبع كل عقدة',$test);
        }
        $policyOptions=[];
        foreach(all("SELECT p.id,p.operation,c.name FROM voice_tool_policies p
          JOIN voice_data_connectors c ON c.id=p.connector_id AND c.account_id=p.account_id
          WHERE p.account_id=? AND p.enabled=1 AND c.status='verified' ORDER BY p.id DESC LIMIT 60",[$id]) as $p)
            $policyOptions[(string)$p['id']]=$p['operation'].' · '.$p['name'];
        if($policyOptions&&$available){
            $bind='<p class="dim">حدد عقدة من مخطط مساعد منشور ثم سياسة أداة صرح بها صاحب الحساب. لا تنفذ أي عملية بمجرد الربط.</p>';
            foreach($agents as $agent){
                if(!isset($available[(string)$agent['id']]))continue;
                $bind.='<details><summary>'.e($agent['name']).' · '.e($agent['site_name']).'</summary>'.
                    form_start('voice_flow_tool_bind','/?tab=flows').
                    '<input type="hidden" name="agent_id" value="'.(int)$agent['id'].'">'.
                    '<input type="hidden" name="site_id" value="'.(int)$agent['site_id'].'">'.
                    '<label class="field">Node ID في المخطط<input name="node_id" maxlength="60" required></label>'.
                    select_html('سياسة أداة معتمدة','policy_id',$policyOptions).
                    submit_btn('ربط العقدة بالعملية المصرح بها').'</details>';
            }
            echo card('ربط عقد Tool / CRM / Booking بسياسة', $bind);
        }
    }

}

function render_answering(array $a):void {
    $id=(int)$a['account_id'];
    $agents=all("SELECT ag.id,ag.name,ag.site_id,s.name site_name FROM agents ag JOIN sites s ON s.id=ag.site_id
        WHERE ag.account_id=? AND ag.status!='archived' AND s.status='active' ORDER BY ag.id DESC",[$id]);
    echo '<p class="info">إعدادات الرد محفوظة لكل إيجنت بشكل مستقل؛ لا تصل للشريحة إلا في نسخة إعدادات AI Agent المنشورة. ربط المحرك الصوتي الحقيقي على SIM ما زال يحتاج اختبارًا.</p>';
    $rows=[];
    foreach($agents as $agent){
        $policy=answer_policy_for_agent($id,(int)$agent['id']);
        $url='/?tab=agents&site='.(int)$agent['site_id'].'&agent='.(int)$agent['id'].'#agent-'.(int)$agent['id'];
        $rows[]=[e($agent['name']),$policy['auto_answer']?'مُعدّ للرد الآلي':'يدوي',
            (int)$policy['answer_delay_seconds'].' ثوانٍ',
            '<a class="btn small primary" href="'.e($url).'">تعديل إعدادات الرد ←</a>'];
    }
    echo card('إعدادات الرد بحسب المساعد',tbl(['المساعد','السياسة','التأخير','التعديل'],$rows));
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
        echo card('إنشاء حملة مسودة',form_start('add_campaign','/?tab=campaigns').field_html('اسم الحملة','name','text','متابعة العملاء',true).select_html('الموقع','site_id',$map).'<p class="info">المسودة العامة لا تنفذ أي اتصال. الحملات المرتبطة فعلًا بشريحة Android تظهر في القسم التالي.</p>'.submit_btn('إنشاء مسودة'));
    require_once __DIR__.'/TenantCampaignQueue.php';
    tcq_init();
    $simCampaigns=all("SELECT c.*,s.name AS site_name,d.device_name
        FROM tenant_campaigns c JOIN device_bindings d ON d.id=c.device_binding_id
        AND d.account_id=c.account_id AND d.site_id=c.site_id AND d.sim_slot=c.sim_slot
        JOIN sites s ON s.id=c.site_id AND s.account_id=c.account_id
        WHERE c.account_id=? ORDER BY c.created_at DESC LIMIT 80",[$id]);
    $simRows=[];
    foreach($simCampaigns as $campaign){
        $action='—';
        if(allowed('campaigns.write')){
            $action='';
            $choices=$campaign['status']==='cancelled'?[]:
                ($campaign['status']==='paused'?['preview','resume','cancel']:
                ($campaign['status']==='draft'?['preview','arm','pause','cancel']:
                    ['preview','pause','cancel']));
            foreach($choices as $op){
                $action.=form_start('sim_campaign_manage','/?tab=campaigns','class="inline-form"').
                    '<input type="hidden" name="device_binding_id" value="'.(int)$campaign['device_binding_id'].'">'.
                    '<input type="hidden" name="sim_campaign_id" value="'.e($campaign['id']).'">'.
                    '<input type="hidden" name="sim_campaign_operation" value="'.e($op).'">'.
                    '<button class="btn small" type="submit">'.e(match($op){
                       'preview'=>'معاينة الموافقة','arm'=>'اعتماد للجدولة',
                       'resume'=>'استئناف','pause'=>'إيقاف',default=>'إلغاء'
                    }).'</button></form>';
            }
        }
        $simRows[]=[e($campaign['name']),e($campaign['site_name']),
            'SIM '.((int)$campaign['sim_slot']+1).' · '.e($campaign['device_name']),
            pill($campaign['status']),e($campaign['starts_at']).' → '.e($campaign['ends_at']),$action];
    }
    echo card('الحملات المجدولة على شرائح Android',
        '<p class="dim">يجب توثيق الموافقة لكل رقم من جهات الاتصال؛ DNC يمنع الاتصال. الجدولة محفوظة لكن التنفيذ الآلي على SIM يظل معطلًا حتى اختبار الجهاز الحقيقي.</p>'.
        tbl(['الحملة','الموقع','الجهاز والخط','الحالة','النافذة UTC','الإجراء'],$simRows));
}
function render_contacts(array $a):void {
    $id=(int)$a['account_id'];
    require_once __DIR__.'/TenantCampaignQueue.php';
    tcq_init();
    $allConsents=all('SELECT id,contact_id,method,created_at FROM tenant_campaign_consents WHERE account_id=?',[$id]);
    $consents=[];foreach($allConsents as $stored)$consents[(int)$stored['contact_id']]=$stored;
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
        if((int)$r['dnc']!==1 && allowed('contacts.write')) {
            $action.='<details class="row-rename"><summary>تسجيل موافقة الاتصال التسويقي</summary>'.
                form_start('record_campaign_consent','/?tab=contacts','class="row-rename-form"').
                '<input type="hidden" name="contact_id" value="'.(int)$r['id'].'">'.
                select_html('نوع إثبات الموافقة','consent_method',[
                    'verbal'=>'موافقة صوتية موثقة','form'=>'نموذج موافقة',
                    'written'=>'موافقة كتابية','owner_test'=>'اختبار المالك الداخلي']).
                '<label class="field">مرجع الإثبات (8–240 حرفًا؛ لن يُحفظ نصه)<input name="consent_evidence" minlength="8" maxlength="240" required placeholder="consent-ref / date"></label>'.
                '<button class="btn small" type="submit">تسجيل الإثبات</button></form></details>';
        }
        $ack=$consents[(int)$r['id']]??null;
        $consentLabel=(int)$r['dnc']===1?'محظور':($ack?'إقرار: '.e($ack['method']):'غير مثبت');
        $cells[]=[e($r['name']),e($r['phone']),e($r['site_name']),
            $r['dnc']?pill('DNC'):pill('active'),$consentLabel,$action];
    }
    echo card('جهات اتصال كل موقع',tbl(['الاسم','الهاتف','الموقع','الخصوصية','موافقة التسويق','الإجراءات'],$cells));
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
    require_once __DIR__.'/CallHistory.php';
    $history=call_history_filters($id,$_GET);
    $keyword=$history['q'];$filter=$history['status'];
    $page=min(10000,max(1,(int)($_GET['page']??1)));
    $total=call_history_count($history);
    $list=call_history_rows($history,100,($page-1)*100);
    $requestedId=max(0,(int)($_GET['call']??0));
    $selected=null;
    foreach($list as $item)if((int)$item['id']===$requestedId)$selected=$item;
    if($selected===null)$selected=$list[0]??null;
    $stats=['n'=>$total];
    $siteOptions=['0'=>'كل المواقع'];
    foreach(all('SELECT id,name FROM sites WHERE account_id=? ORDER BY id',[$id]) as $site)
        $siteOptions[(string)$site['id']]=$site['name'];
    $filterQuery=http_build_query(array_filter([
        'tab'=>'calls','q'=>$keyword,'status'=>$filter,
        'sim_slot'=>$history['sim'],'site_id'=>$history['site'],
        'from'=>$history['from'],'to'=>$history['to']
    ],static fn($value)=>$value!==''));
    echo '<section class="calls-workspace" aria-label="صندوق المكالمات">';
    echo '<header class="calls-top"><div><span class="eyebrow">ONTRACK VOICE</span><h2>صندوق المكالمات</h2>'.
        '<p>المكالمات الواردة والصادرة، ملخصاتها، ملاحظات العملاء وطلبات تدخل الموظف. الاسم والرقم يظهران عند وصول تعريف موثوق من جهاز Android؛ بدون اختلاق بيانات.</p></div>'.
        '<span class="calls-total">'.(int)($stats['n']??0).' مكالمة مسجّلة</span></header>';
    echo '<form class="calls-filters" action="/" method="get" role="search"><input type="hidden" name="tab" value="calls">'.
        '<label class="field">بحث بالاسم أو الرقم أو Call ID<input name="q" value="'.e($keyword).'" maxlength="80"></label>'.
        '<label class="field">الحالة<select name="status">';
    $labels=['all'=>'كل الحالات','active'=>'مكالمة جارية','ended'=>'انتهت',
        'ringing'=>'رنين','answered'=>'تم الرد','missed'=>'فائتة',
        'failed'=>'فشلت','completed'=>'مكتملة','busy'=>'مشغول','no_answer'=>'لم يرد'];
    foreach($labels as $key=>$name)echo '<option value="'.e($key).'"'.($filter===$key?' selected':'').'>'.e($name).'</option>';
    echo '</select></label>'.
        select_html('الشريحة','sim_slot',['all'=>'كل الشرائح','0'=>'SIM 1','1'=>'SIM 2'],$history['sim']).
        select_html('الموقع','site_id',$siteOptions,$history['site']).
        '<label class="field">من تاريخ<input type="date" name="from" value="'.e($history['from']).'"></label>'.
        '<label class="field">إلى تاريخ<input type="date" name="to" value="'.e($history['to']).'"></label>'.
        '<button class="btn primary" type="submit">تصفية</button></form>';
    if(allowed('calls.read')) {
        echo form_start('export_call_history','/?tab=calls','class="inline-form"');
        foreach(['q','status','sim_slot','site_id','from','to'] as $filterName) {
            $filterValue=match($filterName) {
                'q'=>$keyword,'status'=>$filter,'sim_slot'=>$history['sim'],
                'site_id'=>$history['site'],'from'=>$history['from'],
                default=>$history['to']
            };
            echo '<input type="hidden" name="'.e($filterName).'" value="'.e($filterValue).'">';
        }
        echo '<button class="btn small" type="submit">تصدير CSV (حتى 5000)</button></form>';
    }
    if($total>100){
        echo '<nav class="calls-pagination" aria-label="صفحات سجل المكالمات">';
        if($page>1)echo '<a href="/?'.e($filterQuery).'&page='.($page-1).'">السابق</a>';
        echo ' صفحة '.(int)$page.' من '.(int)ceil($total/100).' ';
        if($page*100<$total)echo '<a href="/?'.e($filterQuery).'&page='.($page+1).'">التالي</a>';
        echo '</nav>';
    }
    echo '<div class="calls-inbox"><div class="calls-list" aria-label="سجل المكالمات">';
    if(!$list)echo '<div class="calls-empty">لا توجد مكالمات مطابقة للبحث. البيانات هتظهر هنا بعد وصول سجل مكالمات حقيقي من الجهاز.</div>';
    foreach($list as $c){
        $chosen=$selected&&(int)$selected['id']===(int)$c['id'];
        $url='/?'. $filterQuery .'&page='.(int)$page.'&call='.(int)$c['id'];
        $letter=mb_substr((string)($c['name']?:$c['phone']),0,1);
        echo '<a class="call-contact'.($chosen?' active':'').'" href="'.e($url).'"'.($chosen?' aria-current="true"':'').'><span class="call-avatar">'.e($letter).'</span>'.
            '<span class="call-contact-main"><strong>'.e((string)($c['name']?:($c['phone']!==''?'رقم غير مسجل':'هوية المتصل لم تصل بعد'))).'</strong>'.
            '<small dir="ltr">'.e((string)($c['phone']?:'رقم غير متاح · راجع إذن سجل المكالمات')).'</small>'.
                (($c['handoff_status']??'')==='requested'?'<small class="call-handoff-tag">⚑ طلب موظف بشري</small>':'').
                (!empty($c['summary'])?'<small class="call-summary-snippet">'.e(mb_substr((string)$c['summary'],0,90)).'</small>':'').
                '</span>'.
            '<span class="call-contact-meta"><time>'.e((string)$c['created_at']).'</time>'.pill((string)$c['status']).'</span></a>';
    }
    echo '</div><section class="call-detail" aria-label="تفاصيل المكالمة">';
    if($selected){
        require_once __DIR__.'/TenantVoiceTranscript.php';
        $transcriptRecord=null;
        if(!empty($selected['device_binding_id'])&&!empty($selected['external_call_id'])){
            $callScope=['account_id'=>$id,'site_id'=>(int)$selected['site_id'],
                'id'=>(int)$selected['device_binding_id'],'sim_slot'=>(int)$selected['sim_slot']];
            $transcriptRecord=tenant_voice_transcript_find($callScope,(string)$selected['external_call_id']);
        }
        $realEvidence='';
        if(!empty($selected['device_binding_id'])&&!empty($selected['external_call_id'])){
            require_once __DIR__.'/VoiceToolGateway.php';
            require_once __DIR__.'/VoiceCallEvidence.php';
            $evidence=voice_call_evidence($id,(int)$selected['site_id'],
                (int)$selected['device_binding_id'],(int)$selected['sim_slot'],
                (string)$selected['external_call_id']);
            if($evidence['grouped_transcript']){
                $realEvidence.='<details class="call-real-transcript"><summary>محادثة المتصل والإيجنت · مرتبة حسب الوقت</summary>'.
                    '<p class="dim">المحتوى من أحداث جهاز الهاتف فقط. السطور المتتالية لنفس المتحدث مجمعة للعرض؛ '.
                    'يمكن تدقيق المعرفات والتوقيتات الأصلية. التفريغ ليس تسجيل GSM مؤكدًا.</p>';
                foreach($evidence['grouped_transcript'] as $utterance)
                    $realEvidence.='<p><small>'.e($utterance['first_at']).
                        ($utterance['last_at']!==$utterance['first_at']?' → '.e($utterance['last_at']):'').
                        ' · '.e($utterance['speaker']).' · '.e($utterance['event_type']).
                        ' · '.(int)$utterance['event_count'].' حدث</small>'.
                        '<span>'.nl2br(e($utterance['content'])).'</span>'.
                        '<small dir="ltr">Event IDs: '.e(implode(', ',$utterance['event_ids'])).'</small></p>';
                $realEvidence.='</details>';
            }
            if($evidence['verified_references']){
                $realEvidence.='<details class="call-real-transcript"><summary>معرّفات API المؤكدة لهذه المكالمة</summary>'.
                    '<p class="dim">مراجع رجعت من مزود خارجي بحالة succeeded مرتبطة بنفس Call ID؛ '.
                    'ليست استنتاجات من النص المتحدث.</p>';
                foreach($evidence['verified_references'] as $ref)
                    $realEvidence.='<p><small>'.e($ref['completed_at']).' · '.
                        e($ref['operation']).'</small> · مرجع '.e($ref['reference_id']).'</p>';
                $realEvidence.='</details>';
            }
            if($evidence['tool_traces']){
                $realEvidence.='<details class="call-real-transcript"><summary>Tool Trace · نتائج عمليات API</summary>';
                foreach($evidence['tool_traces'] as $trace)
                    $realEvidence.='<p><small>'.e($trace['created_at']).' · '.e($trace['operation']).
                        '</small> '.e($trace['status']).' · '.e($trace['detail']).'</p>';
                $realEvidence.='<p class="dim">حالة succeeded فقط تعني أن نظام العمليات تحقّق من استجابة المزود؛ حالات uncertain لا تعني نجاحًا.</p></details>';
            }
        }
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
                '<strong>إبلاغ المدير أو الموظف بعد المكالمة</strong>'.
                '<p>'.e(match((string)($selected['manager_notification_status']??'')){
                    'pending'=>'مكالمة الإبلاغ مجدولة بعد مرور 10 ثوانٍ من نهاية المكالمة',
                    'claimed'=>'التطبيق استلم طلب الاتصال؛ في انتظار البدء الفعلي',
                    'dial_started'=>'بدأ الهاتف محاولة الاتصال بالموظف؛ لم يتأكد سماع الملخص',
                    'call_ended_unverified'=>'انتهت محاولة الاتصال؛ لم يتأكد سماع الموظف للملخص',
                    'failed'=>'فشل بدء اتصال الموظف من الجهاز — راجع الإعدادات',
                    'unknown'=>'النتيجة غير مؤكدة؛ تتطلب مراجعة بشرية قبل أي إعادة',
                    'cancelled'=>'أُلغي الإبلاغ لأن رقم الموظف لم يعد نشطًا',
                    default=>($selected['handoff_status']??'not_requested')==='requested'
                       ?'لم تُجهّز مكالمة متابعة؛ راجع رقم المدير وتفعيل مسار التحويل للمساعد'
                       :'لم يُطلب إبلاغ موظف'
                }).'</p>'.
                (!empty($selected['manager_notification_name'])
                  ?'<p class="dim">المسؤول: '.e((string)$selected['manager_notification_name']).'</p>':'').
                ($transcriptRecord
                    ?'<details class="call-real-transcript"><summary>تفريغ صوت المكالمة الحقيقي (Gemini Live)</summary>'.
                        '<p class="dim">مصدر التفريغ: '.e((string)$transcriptRecord['source']).' · '.e((string)$transcriptRecord['created_at']).'</p>'.
                        '<pre style="white-space:pre-wrap;overflow-wrap:anywhere">'.e((string)$transcriptRecord['transcript']).'</pre></details>'
                    :'<p class="dim">لم يصل تفريغ صوتي موثّق لهذه المكالمة بعد.</p>').
                '<p class="dim">ملف التسجيل الصوتي لا يظهر قبل رفع تسجيل حقيقي مصرح به.</p>'.$realEvidence.'</div>';
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
                    '<label class="field"><input type="checkbox" name="staff_acknowledged" value="yes"> '.
                    'أؤكد أن موظفًا استلم طلب العميل بالفعل خارج المكالمة؛ لا تحدد «مكتمل» بغير هذا الإقرار.</label>'.
                    '<p class="dim">المرحلة الأولى تسجل الطلب والسبب والملخص وحالة التدخل كما وقعت. '.
                    'اتصال المدير تلقائيًا بعد 10 ثوانٍ ليس إثباتًا للتسليم ولا جزءًا من قبول هذه المرحلة.</p>'.
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
    // Keep the six-digit SIM 1/SIM 2 actions visible on the devices screen itself.
    render_sim_pair_quick_start($a,'devices');
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
                select_html('تغيير المساعد على نفس الشريحة','agent_id',$choices,(string)$d['agent_id']).
                '<button class="btn small primary" type="submit">تغيير المساعد بدون إعادة ربط</button></form>'.$action;
        }
        $rows[]=[e($d['device_name']),e($d['site_name']),e($d['agent_name']),
            e('SIM '.((int)$d['sim_slot']+1)),pill($d['status']),
            e($d['last_seen_at']?:'لم يتصل بعد'),$action];
    }
    echo card('أجهزة Android المربوطة بالحساب',tbl(['الجهاز','الموقع','المساعد','الشريحة','الحالة','آخر اتصال','الإجراء'],$rows));
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
    echo card('طلبات الاقتران المعلقة',tbl(['الموقع','المساعد','الشريحة','ينتهي في','الإجراء'],$pending));
    echo '<div class="info">الربط لكل شريحة: اضغط ربط، اكتب الكود المكوّن من 6 أرقام في التطبيق، ثم راقب حالة الشريحة. جلسة Google Live تحتاج مزوّدًا معتمدًا واختبار صوت فعلي.</div>';
}
function render_voices(array $a):void{
    require_once __DIR__.'/IoPremiumProvider.php';
    $id=(int)$a['account_id'];$list=all('SELECT * FROM voices ORDER BY tier DESC, provider DESC, id ASC');
    $grants=all('SELECT voice_id FROM voice_grants WHERE account_id=?',[$id]);$g=array_column($grants,'voice_id');
    $rows=[];
    $showProvider=$a['kind']==='platform';
    foreach($list as $v){
        // Customers see only genuinely ready and granted voices, not raw
        // upstream provider IDs, development placeholders or technical diagnostics.
        if(!$showProvider && (
           (int)$v['ready']!==1 || (string)$v['status']!=='active' ||
           ($v['code']==='io.premium'
             ?!io_premium_access_allowed($id)
             :!in_array($v['id'],$g))))continue;
        $gender=(string)($v['gender']??'neutral');
        if(!in_array($gender,['female','male','neutral'],true))$gender='neutral';
        $dialect=trim((string)($v['dialect']??''));
        $previewHtml=voice_sample_preview_control($v);
        if($showProvider){
            $ref=trim((string)($v['source_ref_id']??''));
            if($ref===''&&str_starts_with((string)$v['code'],'fish.'))$ref=substr((string)$v['code'],5);
            $last=form_start('toggle_voice','/?tab=voices','class="inline-form"').
                '<input type="hidden" name="voice_id" value="'.$v['id'].'"><button class="btn small">إظهار/إخفاء</button></form>';
            $last.=form_start('set_voice_ready','/?tab=voices','class="inline-form"').
                '<input type="hidden" name="voice_id" value="'.$v['id'].'">'.
                '<input type="hidden" name="ready" value="'.((int)$v['ready']===1?'0':'1').'">'.
                '<input type="hidden" name="confirmed" value="1">'.
                '<button class="btn small" data-confirm="هل اختبرت الصوت بنجاح على مزود الاتصال؟">'.
                ((int)$v['ready']===1?'إلغاء الجاهزية':'اعتماد صوت').'</button></form>';
            $nameCell='<strong>'.e((string)$v['name']).'</strong>'.
                ($ref!==''?'<br><code class="dim" dir="ltr" title="'.e($ref).'">'.e(substr($ref,0,12)).'…</code>':'');
            $provCell=e(voice_provider_label((string)$v['provider'])).' <span class="voice-badge gender-'.e($gender).'">'.e(voice_gender_label($gender)).'</span>';
            $rows[]=[$nameCell,$provCell,$dialect!==''?e($dialect):'<span class="dim">—</span>',$previewHtml,pill((string)$v['tier']),
                (int)$v['ready']?pill('جاهز'):pill('قيد الإعداد'),pill((string)$v['status']),$last];
        }else{
            $typeCell='<span class="voice-badge gender-'.e($gender).'">'.e(voice_gender_label($gender)).'</span>'.
                ($dialect!==''?' <span class="voice-badge dialect">'.e($dialect).'</span>':'');
            $rows[]=[e((string)$v['name']),$typeCell,$previewHtml,e((string)$v['tier']),'متاح'];
        }
    }
    if($showProvider && in_array((string)$a['role'],['owner','admin'],true) && !active_support())
        echo render_io_premium_admin_provider();
    echo '<div data-voice-picker data-preview-url="/saas/console/" data-preview-csrf="'.e(csrf()).'">';
    if($showProvider) echo card('إدارة كتالوج الأصوات',tbl(
       ['الصوت','المزوّد / النوع','اللهجة','المعاينة','الفئة','الجاهزية','العرض','التحكم'],$rows));
    else echo card('الأصوات المتاحة لك',tbl(['الصوت','النوع واللهجة','المعاينة','الفئة','الحالة'],$rows));
    echo '</div>';
    if($a['kind']==='platform'){
        if(in_array((string)$a['role'],['owner','admin'],true) && !active_support()){
            echo card('جلب واستيراد مكتبة أصوات Fish Audio',
                form_start('sync_fish_voices','/?tab=voices').
                '<div class="form-grid">'.
                '<label class="field">Fish Audio API Key (اختياري لو محفوظ في IO Premium)<input type="password" name="fish_sync_api_key" autocomplete="off" maxlength="2048" placeholder="سيُستخدم المفتاح المحفوظ تلقائيًا"></label>'.
                '<label class="field">بحث بالكلمة أو اللهجة (اختياري)<input type="text" name="fish_query" maxlength="80" placeholder="مثال: Arabic أو Lebanese أو Khalid"></label>'.
                '</div>'.
                '<label class="field"><input type="checkbox" name="mark_ready" value="1"> اعتماد الأصوات المستوردة كجاهزة لاختيارها فورًا</label>'.
                '<p class="dim">يتم حفظ الأصوات وعيناتها الصوتية في السيرفر (كاش دائم) لتشغيل المعاينة لاحقًا بدون استهلاك أي توكن من Fish Audio.</p>'.
                submit_btn('استيراد وتحديث أصوات Fish Audio'));
            echo card('إضافة صوت Fish Audio برقم المرجع (Reference ID)',
                form_start('add_fish_voice','/?tab=voices').
                '<div class="form-grid">'.
                '<label class="field">اسم الصوت للمستخدم<input type="text" name="name" maxlength="120" required placeholder="مثال: لبناني أنثوي دافئ"></label>'.
                '<label class="field">Reference ID (32 حرف hex)<input type="text" name="source_ref_id" dir="ltr" pattern="[a-fA-F0-9]{32}" maxlength="32" required placeholder="00000000000000000000000000000000"></label>'.
                select_html('النوع','gender',['female'=>'أنثى','male'=>'ذكر','neutral'=>'محايد'],'female').
                '<label class="field">اللهجة / اللغة<input type="text" name="dialect" maxlength="80" placeholder="مثال: لبناني / مصري / خليجي"></label>'.
                '</div>'.
                '<label class="field"><input type="checkbox" name="mark_ready" value="1" checked> اعتماد الصوت كجاهز للمنصة فورًا</label>'.
                submit_btn('حفظ وإضافة الصوت'));
        }
        echo card('Google Voice Discovery',form_start('sync_google_voices','/?tab=voices').
            '<p>استيراد كتالوج Google Gemini Live المنشور (30 صوتاً). كل صوت جديد يبقى غير مفعّل حتى التحقق من توافقه مع مزوّد الاتصال الفعلي.</p>'.
            submit_btn('مزامنة 30 صوت Google')).
        card('جلب الأصوات مباشرة من حساب Google',
          form_start('fetch_google_voices','/?tab=voices').
          '<label class="field">Google API Key (اختياري لو متسجّل في إعدادات الأدمن)<input type="password" name="google_api_key" autocomplete="off" minlength="16" placeholder="سيُستخدم مفتاح Google المحفوظ إن وجد"></label>'.
          '<p class="dim">استيراد الأصوات الحقيقية هيستخدم المفتاح المحفوظ. لو حساب Google لا يسمح بالـVoices API، هيظهر الكتالوج الأساسي مع توضيح المشكلة؛ مش هنقول المفتاح خاطئ.</p>'.
          submit_btn('مزامنة أصوات Google المتاحة'));
    }
    if($a['kind']==='platform')echo '<div class="info">الأصوات الجديدة لا تُفعّل قبل اختبارها واعتمادها.</div>';
    if($a['kind']==='platform'||$a['kind']==='reseller'){
        $targets=$a['kind']==='platform'?all("SELECT id,name FROM accounts WHERE kind!='platform'"):all('SELECT id,name FROM accounts WHERE parent_id=?',[$id]);
        $opts=[];foreach($targets as $v)$opts[$v['id']]=$v['name'];
        $active=[];foreach($list as $v)
            if((int)$v['ready']===1 && $v['code']!=='io.premium')$active[$v['id']]=$v['name'];
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
function render_keys(array $a):void {
    // Legacy URL is intentionally informational. No customer/reseller
    // API credentials are created, revealed, or rotated in the console.
    echo card('الربط بدون API Keys',
        '<p>ربط شرائح SIM 1 وSIM 2 يتم من صفحة «الأجهزة والخطوط» بكود مكون من 6 أرقام فقط، وبدون تسجيل دخول داخل تطبيق Android.</p>'.
        '<p>مفاتيح مزودي الصوت والذكاء الاصطناعي تخص إعدادات الخدمة، وليست مفاتيح يطلبها العميل لربط الهاتف.</p>'.
        '<a class="btn primary" href="/?tab=devices">فتح ربط الشرائح</a>'.
        ($a['kind']==='platform'
          ?' <a class="btn" href="/?tab=voices">مفاتيح مزودي الصوت</a>':''));
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
    if(in_array($a['role'],['owner','admin'],true)&&!active_support()){
        require_once __DIR__.'/OperationalOutbox.php';
        $ops=operational_outbox_status($id,$a['kind']==='platform');
        $states=[];foreach($ops['states'] as $status=>$count)
            $states[]=[e($status),(string)(int)$count];
        echo card('تشغيل Events / Outbox',
            '<p class="dim">الأحداث تُسلّم إلى السجل المحلي الموثوق فقط؛ لا نعتبرها إشعارًا وصل إلى WHMCS أو بريدًا خارجيًا.</p>'.
            tbl(['الحالة','العدد'],$states));
        if($a['kind']==='platform'){
            require_once __DIR__.'/EmailDelivery.php';
            $mailStates=email_queue_status();
            $mailRows=[];foreach($mailStates as $row)
                $mailRows[]=[e((string)$row['purpose']),e((string)$row['status']), (int)$row['amount']];
            $health=(array)($ops['health']??[]);
            $healthStates=[
                'healthy'=>'سليم','stale'=>'متأخر / متوقف',
                'failed'=>'آخر تشغيل غير ناجح','missing'=>'لم يُرصد',
                'invalid'=>'وقت غير صالح'
            ];
            $heart=[];
            foreach(($health['workers']??[]) as $worker){
                $state=(string)$worker['status'];
                $age=$worker['age_seconds']===null?'—':(string)intdiv((int)$worker['age_seconds'],60).' دقيقة';
                $heart[]=[e((string)$worker['worker']),e($healthStates[$state]??'غير معروف'),
                    e($age),e((string)($worker['last_success_at']??'لم ينجح بعد')),
                    (int)$worker['delivered'],(int)$worker['failed']];
            }
            $overall=($health['all_healthy']??false)
                ?'<p><strong>تم رصد جميع Workers خلال الفترة المتوقعة.</strong></p>'
                :'<p><strong>تنبيه تشغيلي: Worker متوقف أو متأخر أو لم ينجح بعد.</strong> راجع حالات العمال أدناه وCron وسجلات التشغيل قبل الاعتماد.</p>';
            echo card('صحة Workers والبريد',
                $overall.
                '<p class="dim">الحالة مشتقة من Heartbeat لكل Worker، وليست إثباتًا لوصول رسالة Meta أو البريد. الأوقات UTC.</p>'.
                tbl(['نوع الرسالة','الحالة','العدد'],$mailRows).
                tbl(['الـWorker','حالة التشغيل','آخر محاولة منذ','آخر نجاح UTC','تمت المعالجة','فشل'],$heart));
        }
    }
    if(in_array($a['role'],['owner','admin'],true)&&!active_support())
      echo '<div class="cols two">'.card('إنشاء Integration',
        form_start('new_integration','/?tab=integrations').field_html('اسم الاتصال','name','text','WHMCS / Laravel / WordPress / نظام خارجي',true).
        select_html('المزود','provider',['whmcs'=>'WHMCS','laravel'=>'Laravel','wordpress'=>'WordPress / WooCommerce','generic_rest'=>'نظام خارجي (REST / Webhook)','kashier'=>'Kashier','webhook'=>'Generic Webhook']).
        submit_btn('إنشاء الاتصال')).
        card('Webhook / API','<p>مصادر WHMCS وLaravel وWordPress وأي نظام خارجي ترسل ملخصات بيانات موقّعة HMAC. موصلات WordPress والعام قراءة فقط حاليًا. تنفيذ الأوامر يتطلب أذونات صريحة وتأكيدا مستقلا؛ إضافة الاتصال لا تعني نجاح الربط الفعلي.</p><code>POST /saas/console/hooks/index.php</code>').'</div>';
}
function render_domains(array $a):void{
    if($a['kind']!=='reseller')deny();
    require_once __DIR__.'/DomainVerification.php';
    $id=(int)$a['account_id'];
    $brand=brand($a);
    $d=all('SELECT * FROM domains WHERE account_id=? ORDER BY id DESC',[$id]);
    $rows=[];
    foreach($d as $v){
        $status=(string)$v['status'];
        $action='—';
        if(in_array($a['role'],['owner','admin'],true)&&!active_support()&&$status!=='active'){
            $action=form_start('verify_domain_dns','/?tab=domains','class="inline-form"').
                '<input type="hidden" name="domain_id" value="'.(int)$v['id'].'">'.
                '<button class="btn small" type="submit">تأكيد ملكية DNS</button></form>';
            if($status==='dns_verified')
                $action.=form_start('activate_domain_https','/?tab=domains','class="inline-form"').
                    '<input type="hidden" name="domain_id" value="'.(int)$v['id'].'">'.
                    '<button class="btn small primary" type="submit">فحص HTTPS وتفعيل البوابة</button></form>';
        }
        if($status==='active')
            $action='<a class="btn small" href="https://'.e((string)$v['hostname']).'/login">فتح لوحة شركتك</a>'.
                ' <a class="btn small" href="https://'.e((string)$v['hostname']).'/register">صفحة التسجيل</a>';
        $description=match($status){
            'active'=>'الدومين جاهز للعملاء',
            'dns_verified'=>'الملكية مؤكدة · استكمل إعداد الاستضافة وSSL',
            default=>'بانتظار إثبات ملكية الدومين'
        };
        $rows[]=['<strong dir="ltr">'.e((string)$v['hostname']).'</strong><small class="block dim">'.e($description).'</small>',
            '<code dir="ltr">'.e(domain_dns_name((string)$v['hostname'])).'</code>',
            '<code dir="ltr">'.e('ontrack-verify='.$v['verification_token']).'</code>',
            pill($status),$action];
    }
    $slug=(string)$a['slug'];
    $fallback='https://app.ontrackegy.com/portal/'.rawurlencode($slug);
    echo card('بوابة شركتك',
        '<div class="brand-preview"><div class="wl-fallback">'.e(mb_substr((string)$brand['name'],0,1)).'</div><div><h3>'.e((string)$brand['name']).'</h3>'.
        '<p>العملاء يشوفوا اسم شركتك وشعارك فقط في التسجيل والدخول ولوحة الحساب، بدون علامة مزوّد النظام.</p></div></div>'.
        '<p><strong>رابط معاينة بوابتك قبل ربط دومينك:</strong></p>'.
        '<p><a class="btn primary" href="'.e($fallback.'/login').'">معاينة تسجيل الدخول</a> '.
        '<a class="btn" href="'.e($fallback.'/register').'">معاينة التسجيل</a></p>'.
        '<label class="field">الرابط الدائم المؤقت لعملائك<input readonly dir="ltr" onclick="this.select()" value="'.e($fallback.'/login').'"></label>');
    echo card('ربط دومين شركتك',
        '<p>مثال: <code dir="ltr">app.sog.com</code>. بعد ربطه، تسجيل الدخول والتسجيل ولوحة الحساب هيشتغلوا تحت الدومين بتاعك بنفس الهوية، من غير ظهور رابط المنصة.</p>'.
        '<div class="stack"><p><strong>1.</strong> أنشئ سجل CNAME للاسم <code dir="ltr">app</code> يشير إلى <code dir="ltr">'.e(WHITE_LABEL_PLATFORM_HOST).'</code> (حسب إعداد DNS الخاص بدومينك).</p>'.
        '<p><strong>2.</strong> أضف سجل TXT الموضح لكل دومين واضغط تأكيد ملكية DNS.</p>'.
        '<p><strong>3.</strong> اربط الاسم بالاستضافة كـ Domain Alias / VirtualHost وأصدر شهادة SSL صالحة للدومين.</p>'.
        '<p><strong>4.</strong> اضغط فحص HTTPS وتفعيل البوابة. التفعيل لن يتم قبل نجاح جميع الاختبارات.</p></div>'.
        '<p class="dim">تأكيد DNS لا يضيف الدومين إلى استضافة cPanel ولا يصدر شهادة SSL تلقائياً. لا تشارك رابط الدومين الجديد قبل ظهور حالته نشط.</p>'.
        tbl(['الدومين','سجل TXT','قيمة TXT','الحالة','الخطوة التالية'],$rows));
    if(in_array($a['role'],['owner','admin'],true)&&!active_support())
        echo card('إضافة دومين جديد',
            form_start('add_domain','/?tab=domains').
            '<label class="field">الدومين أو النطاق الفرعي<input name="hostname" maxlength="190" dir="ltr" placeholder="app.example.com" required></label>'.
            submit_btn('إنشاء طلب الربط'));
}

function render_billing(array $a):void{
    $id=(int)$a['account_id'];
    require_once __DIR__.'/BillingUsage.php';
    if($a['kind']==='platform'){
        // Platform ownership is not a metered customer subscription; a missing
        // subscriptions row must never be displayed as an account expiration.
        echo card('اشتراك الحساب','<div class="plan-panel"><span class="eyebrow">PLATFORM OWNER</span>'.
            '<h2>حساب مالك المنصة</h2><p>الاستحقاقات غير محدودة ما دام الحساب نشطًا؛ لا يحتاج باقة مستخدم أو تجديدًا يوميًا.</p></div>');
        echo card('معدل استهلاك المكالمات المتصلة',
            '<p>حساب مالك المنصة لا يخضع لحصة مكالمات العملاء أو رسوم تجاوز الباقة.</p>');
    }else{
        $sub=one("SELECT p.*,s.status sub_status,s.ends_at FROM subscriptions s JOIN plans p ON p.id=s.plan_id WHERE s.account_id=? AND s.status='active' AND s.starts_at<=? AND s.ends_at>? ORDER BY s.id DESC LIMIT 1",[$id,now(),now()]);
        $isActive=$sub!==null;
        $text=$isActive?'<div class="plan-panel"><span class="eyebrow">YOUR CURRENT PLAN</span><h2>'.e($sub['name']).'</h2><p>'.e((string)$sub['call_quota']).' مكالمة متصلة شهرياً · '.e((string)$sub['agent_limit']).' AI · '.e((string)$sub['site_limit']).' مواقع</p><small>تنتهي الفترة: '.e($sub['ends_at']).'</small></div>':'<p>لا توجد باقة نشطة. إنشاء الحساب لا يمنح اشتراكًا مدفوعًا تلقائيًا.</p>';
        echo card('اشتراك الحساب',$text);
        $usage=voice_usage_summary($id);
        echo card('معدل استهلاك المكالمات المتصلة',
            '<p><strong>'.(int)$usage['used'].'</strong> مستخدم / <strong>'.(int)$usage['quota'].
            '</strong> متاح · متبقي <strong>'.(int)$usage['remaining'].
            '</strong> · مكالمات التجاوز <strong>'.(int)$usage['overage'].'</strong>.</p>'.
            '<p>تقدير التجاوز '.e(number_format($usage['estimate_minor']/100,2)).
            ' ج.م — لم يتم تحصيل رسوم التجاوز تلقائيًا.</p>'.
            (!$usage['active']?'<p class="dim">لا يوجد اشتراك فعّال في الفترة الحالية.</p>':''));
    }

    // Only the authenticated owner's verified external SSO subject can
    // claim a paid WHMCS/Laravel order. Email is never used as identity.
    if($a['kind']==='customer'&&in_array($a['role'],['owner','admin'],true)&&!active_support()){
        require_once __DIR__.'/ExternalOrderClaim.php';
        $person=me();
        $unclaimed=$person?external_order_claim_candidates((int)$person['id'],$id):[];
        if($unclaimed){
            $cards='<p class="dim">طلبات مدفوعة من WHMCS/Laravel تخص هويتك الخارجية المرتبطة والموثقة. اضغط لتفعيل اشتراك هذا الحساب فقط.</p>';
            foreach($unclaimed as $entry){
                $cards.=form_start('external_order_claim_paid','/?tab=billing','class="inline-form"').
                  '<input type="hidden" name="order_id" value="'.(int)$entry['id'].'">'.
                  '<strong>'.e((string)$entry['integration_name']).' · '.e((string)$entry['external_order_id']).
                  ' · '.number_format((int)$entry['amount_minor']/100,2).' ج.م</strong>'.
                  '<button type="submit" class="btn primary small" data-confirm="ربط الطلب المدفوع بهوية هذا الحساب وتفعيل الاشتراك بعد التحقق؟">تفعيل الطلب المدفوع</button></form>';
            }
            echo card('مطابقة وتفعيل طلبات مدفوعة من نظام خارجي',$cards);
        }
    }
    if(in_array($a['kind'],['reseller','customer'],true)
       &&in_array($a['role'],['owner','admin'],true)&&!active_support()){
        $plans=plan_catalog_available(account($id));
        $free=[];$paid=[];
        foreach($plans as $plan){
            if((int)$plan['price_minor']===0)
                $free[(string)$plan['id']]=$plan['name'].' — '.$plan['call_quota'].' مكالمة / 30 يوم';
            else
                $paid[(string)$plan['id']]=$plan['name'].' — '.number_format((int)$plan['price_minor']/100,2).' ج.م / 30 يوم';
        }
        if($free)echo card('اختيار باقة مجانية وتفعيلها الآن',
            form_start('self_select_free_plan','/?tab=billing').
            select_html('الباقة المجانية','plan_id',$free).
            '<p class="dim">التفعيل مباشر لمدة 30 يومًا إذا لم يكن لديك اشتراك فعال. تغيير الباقة الحالية لا يمدد تاريخ انتهائها.</p>'.
            submit_btn('اختيار وتفعيل الباقة'));
        if($paid)echo card('شراء أو تجديد باقة',
            form_start('checkout_plan','/?tab=billing').
            select_html('اختر باقة','plan_id',$paid).
            '<p class="dim">الباقات المدفوعة تُفعّل بعد تأكيد الدفع الموثّق من Kashier؛ أو يمنحها أدمن المنصة مباشرة كمنحة إدارية منفصلة.</p>'.
            submit_btn('متابعة الدفع الآمن'));
        if(!$free&&!$paid)echo card('اختيار الباقة',
            '<p class="dim">لا توجد باقات متاحة من نوع هذا الحساب حاليًا. تواصل مع أدمن المنصة لإنشاء أو تعيين باقة.</p>');
        $current=one("SELECT p.*,s.ends_at,s.id subscription_id FROM subscriptions s
            JOIN plans p ON p.id=s.plan_id WHERE s.account_id=? AND s.status='active'
            AND s.starts_at<=? AND s.ends_at>? ORDER BY s.id DESC LIMIT 1",[$id,now(),now()]);
        $switchRows=[];$options=[];
        foreach($plans as $plan){
            $options[(string)$plan['id']]=$plan['name'].' — '.
                number_format((int)$plan['price_minor']/100,2).' ج.م / 30 يوم';
            $q=subscription_change_quote($current,$plan);
            $action=$current && (int)$current['id']===(int)$plan['id']
                ?'الباقة الحالية':($q['direction']==='upgrade'?'ترقية':
                    ($q['direction']==='downgrade'?'تخفيض':'تغيير'));
            $switchRows[]=[e((string)$plan['name']),$action,
                number_format($q['full_period_difference_minor']/100,2).' ج.م',
                number_format($q['estimated_remaining_difference_minor']/100,2).' ج.م',
                number_format((int)$plan['price_minor']/100,2).' ج.م'];
        }
        if($options)echo card('Upgrade / Downgrade — تغيير باقتك',
            '<p class="dim">نعرض فرق سعر 30 يوم وتقدير الفرق النسبي للمعاينة فقط، وليس خصمًا نقديًا. الدفع عبر Kashier بسعر فترة 30 يوم كاملة، ويضاف 30 يومًا بعد تاريخ انتهاء اشتراكك الجاري دون ضياع الأيام المتبقية.</p>'.
            ($current?'<p>باقتك الحالية: <strong>'.e($current['name']).'</strong> · متبقي '.
                (int)subscription_change_quote($current,$current)['remaining_days'].' يوم</p>':
                '<p>ليس لديك اشتراك نشط حاليًا.</p>').
            tbl(['الباقة','نوع التغيير','فرق سعر 30 يوم','فرق المدة المتبقية (تقديري)','السعر الجديد'],$switchRows).
            form_start('self_change_plan','/?tab=billing').
            select_html('اختر الباقة الجديدة','plan_id',$options).
            '<p class="dim">باقة مدفوعة: التفعيل بعد إشعار دفع موثّق فقط. الفترة الجديدة تبدأ بعد نهاية الاشتراك الحالي المتبقي، وتُضاف 30 يومًا عند التجديد بدون خصم أو ضياع للأيام الحالية.</p>'.
            submit_btn('متابعة تغيير الباقة'));
        if($current){
            $schedule=subscription_scheduled_end($id,(int)$current['subscription_id']);
            if($schedule)echo card('إلغاء عند نهاية الفترة',
                '<p>تم تسجيل طلب عدم التجديد عند نهاية الفترة: '.e($schedule['effective_at']).'.</p>'.
                form_start('self_resume_renewal','/?tab=billing').
                submit_btn('سحب طلب الإلغاء'));
            else echo card('إيقاف التجديد عند نهاية الفترة',
                form_start('self_cancel_at_end','/?tab=billing').
                '<p class="dim">اشتراكك الحالي يستمر لنهايته. التجديد المدفوع ليس تلقائيًا في النظام الحالي.</p>'.
                submit_btn('إيقاف التجديد عند انتهاء الاشتراك'));
        }
        if($current)echo card('إلغاء الاشتراك الحالي',
            form_start('self_cancel_subscription','/?tab=billing').
            '<p class="dim">الإلغاء فوري ويوقف الاستحقاقات، لكنه لا يحذف سجلات الدفع والاشتراك.</p>'.
            '<button type="submit" class="btn danger" data-confirm="إلغاء اشتراكك فورًا وإيقاف المكالمات واستحقاقات الباقة؟">إلغاء الاشتراك</button></form>');
        if($options)echo card('تجديد الاشتراك',
            form_start('self_renew_subscription','/?tab=billing').
            select_html('باقة التجديد','plan_id',$options).
            '<p class="dim">التجديد المجاني لا يمدد الاشتراك النشط مرارًا. الباقات المدفوعة تمر للدفع الموّثق.</p>'.
            submit_btn('تجديد الباقة'));
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
        // Refund/reversal reviews are financial exceptions, not successful
        // subscription cancellations. Never let a partial or historical
        // refund silently change a paid entitlement without reconciliation.
        require_once __DIR__.'/KashierWebhook.php';
        kashier_refund_init();
        $refunds=$a['kind']==='platform'
            ?all("SELECT k.refund_reference,k.event_kind,k.amount_minor,k.status,
                o.external_order_id,ac.name account_name FROM kashier_refund_events k
                JOIN orders o ON o.id=k.order_id
                JOIN accounts ac ON ac.id=o.account_id
                WHERE k.status='review_required' ORDER BY k.created_at DESC LIMIT 50")
            :all("SELECT k.refund_reference,k.event_kind,k.amount_minor,k.status,
                o.external_order_id,ac.name account_name FROM kashier_refund_events k
                JOIN orders o ON o.id=k.order_id
                JOIN accounts ac ON ac.id=o.account_id AND ac.parent_id=?
                WHERE k.status='review_required' ORDER BY k.created_at DESC LIMIT 50",[$id]);
        if($refunds){
            $exceptions=[];
            foreach($refunds as $refund)
                $exceptions[]=[
                    e($refund['account_name']),
                    e((string)$refund['external_order_id']),
                    e((string)$refund['event_kind']),
                    e(number_format(((int)$refund['amount_minor'])/100,2)).' ج.م',
                    e((string)$refund['refund_reference']),
                    'مراجعة مالية مطلوبة؛ لم يلغِ النظام اشتراكًا غير مؤكد'];
            echo card('مراجعة استردادات Kashier غير المحسومة',
                '<p class="info">الاسترداد الجزئي أو غير المحدد أو المتعلق باشتراك قديم لا يوقف الاشتراك تلقائيًا. افحص مرجع العملية من Kashier قبل أي تسوية يدوية.</p>'.
                tbl(['العميل','الطلب','الحدث','القيمة','مرجع','الإجراء المطلوب'],$exceptions));
        }
    }
    echo '<div class="info">مدفوعات Kashier الحقيقية لا تُفعّل من صفحة Success؛ التفعيل يتطلب Webhook موثّقًا أو موافقة دفع يدوي مسجلة.</div>';
}
function render_invoices(array $a):void {
    require_permission('billing.read');
    require_once __DIR__.'/BillingInvoices.php';
    $accountId=(int)$a['account_id'];
    $isManager=in_array($a['kind'],['platform','reseller'],true)&&
        in_array($a['role'],['owner','admin','finance'],true)&&
        !active_support()&&console_view_mode($a)==='admin';
    $customerFilter=max(0,(int)($_GET['customer_id']??0));
    $rows=billing_invoice_rows($a,150,$customerFilter?:null);
    $labels=['paid'=>'مدفوعة','unpaid'=>'غير مدفوعة','overdue'=>'متأخرة',
             'cancelled'=>'ملغاة','demo'=>'تجريبية'];
    $counts=['paid'=>0,'unpaid'=>0,'overdue'=>0,'cancelled'=>0,'demo'=>0];
    $totals=['paid'=>0,'open'=>0];
    foreach($rows as $invoice){
        $status=billing_invoice_status($invoice);
        if(isset($counts[$status]))$counts[$status]++;
        if($status==='paid')$totals['paid']+=(int)$invoice['amount_minor'];
        if(in_array($status,['unpaid','overdue'],true))$totals['open']+=(int)$invoice['amount_minor'];
    }
    echo card('ملخص الفواتير',
        '<div class="metric-grid">'.
        counter('مدفوعة',$counts['paid'],'✓').
        counter('غير مدفوعة',$counts['unpaid'],'◌').
        counter('متأخرة',$counts['overdue'],'!').
        counter('ملغاة',$counts['cancelled'],'×').
        '</div><p class="dim">المبالغ المسددة الظاهرة: '.billing_invoice_money($totals['paid']).
        ' · مبالغ مستحقة: '.billing_invoice_money($totals['open']).
        ' · النتائج لآخر '.count($rows).' فاتورة ضمن صلاحياتك.</p>');
    if($isManager){
        $clients=$a['kind']==='platform'
            ?all("SELECT id,name,kind,parent_id FROM accounts WHERE kind IN ('customer','reseller') AND status='active' ORDER BY id DESC LIMIT 300")
            :all("SELECT id,name,kind,parent_id FROM accounts WHERE parent_id=? AND kind='customer' AND status='active' ORDER BY id DESC LIMIT 300",[$accountId]);
        $clientOptions=[];$planOptions=[];
        foreach($clients as $client){
            $clientOptions[(string)$client['id']]=$client['name'];
            foreach(plan_catalog_available($client) as $plan){
                if((int)$plan['price_minor']>0)
                    $planOptions[(string)$plan['id']]=$plan['name'].' · '.billing_invoice_money((int)$plan['price_minor']);
            }
        }
        if($clientOptions&&$planOptions)echo card('إصدار فاتورة لعميل — مراجعة دفع يدوي',
            form_start('invoice_create','/?tab=invoices').
            select_html('العميل','customer_id',$clientOptions).
            select_html('باقة مدفوعة','plan_id',$planOptions).
            '<p class="dim">يصدر طلبًا وفاتورة غير مسددة فقط. لا يفعّل الاشتراك حتى توثيق وصول الدفع. الباقة يجب أن تكون متاحة لهذا العميل.</p>'.
            submit_btn('إصدار الفاتورة'));
        if($a['kind']==='platform')echo card('استيراد فواتير الطلبات السابقة',
            form_start('invoice_backfill','/?tab=invoices').
            '<p class="dim">استيراد دفعة من 100 طلب قديم فقط، بدون تغيير حالات الطلبات أو إنشاء مدفوعات وهمية.</p>'.
            submit_btn('استيراد فواتير قديمة'));
    }
    $invoiceRows=[];
    foreach($rows as $invoice){
        $status=billing_invoice_status($invoice);
        $ref=(int)$invoice['id'];$orderId=(int)$invoice['order_id'];
        $controls='<a class="btn small" target="_blank" rel="noopener noreferrer" href="/?tab=invoices&amp;print='.$ref.'">طباعة / PDF</a>';
        if($isManager && $status==='cancelled'){
            $controls.=form_start('invoice_reissue','/?tab=invoices','class="inline-form"').
                '<input type="hidden" name="invoice_id" value="'.$ref.'">'.
                '<button type="submit" class="btn small" data-confirm="إصدار فاتورة جديدة من الأصل الملغى؟">إعادة إصدار</button></form>';
        }
        if($isManager&&in_array($status,['unpaid','overdue'],true)){
            $order=one('SELECT status FROM orders WHERE id=?',[$orderId]);
            if($order && $order['status']==='pending_manual_review'){
                $controls.=form_start('manual_approve','/?tab=invoices','class="inline-form"').
                    '<input type="hidden" name="order_id" value="'.$orderId.'">'.
                    '<input name="payment_reference" class="tiny-input" required minlength="8" maxlength="160" placeholder="مرجع تحويل موثّق" aria-label="مرجع سداد موثّق">'.
                    '<button type="submit" class="btn small primary" data-confirm="هل تحققت من استلام المبلغ بالفعل في حسابك؟">تم السداد</button></form>';
                $controls.=form_start('invoice_cancel','/?tab=invoices','class="inline-form"').
                    '<input type="hidden" name="invoice_id" value="'.$ref.'">'.
                    '<button type="submit" class="btn small danger" data-confirm="إلغاء هذه الفاتورة غير المسددة وطلب الدفع اليدوي؟">إلغاء</button></form>';
            }
        }
        $invoiceRows[]=[
            '<strong>'.e((string)$invoice['invoice_no']).'</strong>',
            e((string)$invoice['bill_to_name']),
            e((string)$invoice['plan_name']),
            billing_invoice_money((int)$invoice['amount_minor']),
            e($labels[$status]??$status),
            e((string)$invoice['issued_at']),
            e((string)$invoice['due_at']),$controls
        ];
    }
    echo card('سجل الفواتير والمستحقات',
        '<p class="dim">حالة «مدفوعة» لا تظهر إلا بعد دفع موثّق أو استيراد طلب قديم مرجعه مسجل. لا يُعتبر زر اعتماد الدفع إثباتًا مستقلًا.</p>'.
        tbl(['رقم الفاتورة','العميل','الباقة','المبلغ','الحالة','الإصدار','الاستحقاق','الإجراءات'],$invoiceRows));
}
function render_releases(array $a):void{
    $release=ontrack_native_public_release();
    $brand=brand($a);
    $body='<p>تحديثات تطبيق '.e($brand['name']).' بتظهر في مركز التحديثات داخل الهاتف. '.
        'تنزيل APK من سيرفر OnTrack متاح أيضًا من هنا، والتثبيت يحتاج موافقة Android.</p>';
    if($release){
        $body.='<div class="ot-release-version"><strong>الإصدار '.e($release['name']).'</strong>'.
            '<span>versionCode '.(int)$release['code'].'</span></div>'.
            '<a class="btn primary" href="'.e($release['url']).'" download>↓ تحميل إصدار Android الموقّع</a>';
    }else{
        $body.='<div class="alert secret">ملف إصدار Android غير متاح أو بياناته غير مطابقة. '.
            'لم يتم عرض رابط تنزيل غير موثوق.</div>';
    }
    $body.='<p class="dim">الواجهة تُحدّث بشكل مستقل عبر OTA. الملف الأصلي يتحقق من التوقيع '.
        'قبل تثبيته داخل التطبيق، ولا يتم حذف الربط أو بيانات المستخدم تلقائيًا.</p>'.
        '<a class="btn" href="/saas/console/?tab=overview">← العودة للرئيسية</a>';
    echo card('مركز تحديثات Android — '.e($brand['name']),$body);
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

function render_ai_relay_settings(array $a):void {
    require_once __DIR__.'/AiRelay.php';
    $isPlatform=$a['kind']==='platform';
    $accountId=(int)$a['account_id'];
    $scopes=$isPlatform
        ?['voice'=>'Voice Relay — مفاتيح Google للمكالمات','assistant'=>'AI Assistant Relay — المزوّدون والموديلات']
        :['assistant'=>'AI Assistant Text — مفاتيح الإيجنت الخاصة بحسابك'];
    foreach($scopes as $scope=>$heading){
        $entries=ai_relay_rows($scope,false,$accountId);
        $limit=$isPlatform||$a['kind']==='reseller'?0:ai_relay_assistant_key_limit($accountId);
        $canAdd=$isPlatform||$a['kind']==='reseller'||count($entries)<$limit;
        $options=$scope==='voice'
            ?'<option value="google_live">Google Gemini Live</option>'
            :'<option value="google_live">Google Live</option><option value="google_text">Google Text REST</option><option value="openai_compatible">Custom OpenAI-compatible</option><option value="omnira_a">Omnira A · OpenAI-compatible Text</option><option value="omnira_b">Omnira B · OpenAI-compatible Text</option><option value="nvidia">NVIDIA · OpenAI-compatible Text</option>';
        $modelChoices=$scope==='voice'
            ?'<option value="gemini-3.8-live"><option value="gemini-3.8-live-extended-thinking"><option value="gemini-3.1-flash-live-preview">'
            :'<option value="gemini-3.8-live-extended-thinking"><option value="gemini-3.8-flash"><option value="gemini-3.8-live">';
        $modelList='<datalist id="relay-models-'.e($scope).'">'.$modelChoices.'</datalist>';
        $content='<p>مجموعتان منفصلتان: Google Live للمكالمات وAssistant Text لتحرير Flow. '.
            'Omnira A/B وNVIDIA وCustom للنص فقط عبر HTTPS Endpoint متوافق؛ لا تعمل تلقائيًا كصوت Live. '.
            'المفاتيح مشفرة ولا تصل إلى المتصفح أو APK، والاختبار الفعلي يثبت التوافق.</p>'.
            '<p class="dim">مفاتيح المجموعة: '.count($entries).
            ($isPlatform||$a['kind']==='reseller'?' · غير محدود':' · حد الباقة: '.(int)$limit).'</p>';
        if($canAdd)$content.=form_start('relay_add','/?tab=settings').
            '<input type="hidden" name="scope" value="'.e($scope).'">'.
            '<div class="cols two"><label class="field">اسم المفتاح<input name="relay_label" maxlength="100" required placeholder="Primary / Backup"></label>'.
            '<label class="field">Provider<select name="relay_driver">'.$options.'</select></label>'.
            '<label class="field">Model ID — اختَر من المقترحات أو اكتب أي موديل<input name="relay_model" list="relay-models-'.e($scope).'" dir="ltr" maxlength="120" required placeholder="gemini-3.8-live"></label>'.
            '<label class="field">API Key<input type="password" name="relay_api_key" maxlength="512" autocomplete="off" required></label></div>'.
            '<label class="field">HTTPS Chat Completions Endpoint لمزودي OpenAI-compatible<input name="relay_endpoint" dir="ltr" maxlength="600" placeholder="https://api.provider.example/v1/chat/completions"></label>'.
            submit_btn('إضافة مفتاح / مزوّد');
        else $content.='<p class="dim">وصلت لحد الباقة أو إضافة مفاتيح Assistant غير متاحة لهذه الباقة.</p>';
        if(!$entries)$content.='<p class="dim">لا يوجد Relay محفوظ. المزوّد الحالي يظل يعمل إلى أن تضيف أول مفتاح.</p>';
        foreach($entries as $item){
            $id=(int)$item['id'];
            $cool=!empty($item['cooldown_until'])&&$item['cooldown_until']>now();
            $state=$cool?'Cooldown حتى '.e($item['cooldown_until']).' UTC':
                ((int)$item['enabled']===1?'مفعّل':'متوقف');
            $content.='<div class="card" style="margin:10px 0;padding:12px">'.
                '<strong>'.e($item['label']).'</strong> <small dir="ltr">'.e($item['driver']).' · '.e($item['model']).'</small>'.
                '<p class="dim">#'.$id.' · '.e($state).' · آخر حالة: '.e($item['last_status']).'</p>'.
                '<div style="display:flex;gap:6px;flex-wrap:wrap">';
            foreach(['up'=>'↑','down'=>'↓','toggle'=>((int)$item['enabled']===1?'إيقاف':'تفعيل'),
                'reset'=>'إعادة تفعيل بعد التهدئة','delete'=>'حذف'] as $operation=>$label){
                $content.=form_start('relay_manage','/?tab=settings','class="inline-form"').
                    '<input type="hidden" name="relay_id" value="'.$id.'">'.
                    '<input type="hidden" name="relay_operation" value="'.e($operation).'">'.
                    '<button type="submit" class="btn small">'.e($label).'</button></form>';
            }
            $content.='<details style="width:100%;margin:8px 0"><summary>تعديل الاسم والموديل والمفتاح</summary>'.
                form_start('relay_edit','/?tab=settings').
                '<input type="hidden" name="relay_id" value="'.$id.'">'.
                '<label class="field">اسم المزوّد<input name="relay_label" maxlength="100" required value="'.e($item['label']).'"></label>'.
                '<label class="field">Model ID<input name="relay_model" list="relay-models-'.e($scope).'" dir="ltr" maxlength="120" required value="'.e($item['model']).'"></label>'.
                '<label class="field">Endpoint<input name="relay_endpoint" dir="ltr" maxlength="600" value="'.e($item['endpoint']).'"></label>'.
                '<label class="field">API Key جديد (اتركه فاضيًا للاحتفاظ بالموجود)<input type="password" name="relay_api_key" maxlength="512" autocomplete="off"></label>'.
                submit_btn('حفظ تعديل المزوّد').'</details>';
            $content.=form_start('relay_test','/?tab=settings','class="inline-form"').
                '<input type="hidden" name="relay_id" value="'.$id.'">'.
                '<button type="submit" class="btn small primary">اختبار اتصال</button></form></div></div>';
        }
        echo card($heading,$content.$modelList);
    }
}
function render_console_theme_settings(array $a):void {
    require_once __DIR__.'/ConsoleTheme.php';
    $id=(int)$a['account_id'];
    $own=console_theme_stored($id);
    $current=console_theme_effective($id);
    $canEdit=in_array((string)$a['role'],['owner','admin'],true)&&!active_support();
    $label=$a['kind']==='platform'?'ألوان لوحة الأدمن':
        ($a['kind']==='reseller'?'ألوان شركتي والبوابة الخاصة بي':'ألوان مساحة عملي');
    $preview='<div class="ot-theme-preview" data-ot-palette-preview aria-label="معاينة ألوان الواجهة">'.
        '<div class="ot-theme-preview-side"><strong>الشعار</strong><span>الرئيسية</span><span>العملاء</span><span>الإعدادات</span></div>'.
        '<div class="ot-theme-preview-main"><strong>معاينة لوحة التحكم</strong><span>المحتوى والبطاقات</span>'.
        '<button type="button" class="ot-theme-preview-button">زر أساسي</button></div></div>';
    $msg='<p class="dim">نفس الوضعين المعروفين: فاتح وداكن. اختار لون القائمة الجانبية، والمحتوى، والأزرار لكل وضع، أو خليه لونًا واحدًا للقائمة والمحتوى. بدون عشرات الثيمات.</p>'.
       '<p class="dim">كل التغييرات تظهر في المعاينة قبل الحفظ، ولن تغيّر ألوان الريسيلرز الآخرين أو موقع الأدمن.</p>';
    if($a['kind']==='customer' && (int)($a['parent_id']??0)>0){
        $msg.='<p class="dim">حسابك يرث ألوان الريسيلر افتراضيًا، وتقدر تختار ألوانك الخاصة.</p>';
    }
    if(!$canEdit){
        echo card($label,$msg.$preview.'<p class="dim">يمكن للمالك أو مدير الحساب تعديل الهوية.</p>');
        return;
    }
    $form=form_start('save_console_theme','/?tab=settings',
        'class="ot-palette-form" data-ot-palette-editor data-no-ajax="1"').
        $msg.
        '<label class="field">تنسيق ألوان القائمة والمحتوى'.
        '<select name="layout" data-ot-layout>'.
        '<option value="split"'.($current['layout']==='split'?' selected':'').'>ألوان منفصلة: Sidebar والمحتوى</option>'.
        '<option value="single"'.($current['layout']==='single'?' selected':'').'>لون موحد للقائمة والمحتوى</option>'.
        '</select></label>';
    foreach(['dark'=>'داكن','light'=>'فاتح'] as $mode=>$title){
        $form.='<fieldset class="ot-palette-fieldset" data-ot-mode="'.e($mode).'">'.
            '<legend>الوضع '.e($title).'</legend><div class="ot-palette-grid">';
        foreach(['sidebar'=>'لون الـSidebar','content'=>'لون خلفية المحتوى','accent'=>'لون الأزرار والعناصر المهمة'] as $part=>$labelPart){
            $field=$mode.'_'.$part;
            $form.='<label class="ot-palette-input"><span>'.e($labelPart).'</span>'.
                '<input type="color" name="'.e($field).'" data-ot-palette-input="'.$field.
                '" value="'.e($current[$field]).'" required>'.
                '<output data-ot-value-for="'.$field.'">'.e($current[$field]).'</output></label>';
        }
        $form.='</div></fieldset>';
    }
    $form.=$preview.
        '<div class="ot-palette-actions">'.submit_btn('حفظ ألوان حسابي').'</div>';
    if($own){
        $form.='<p class="dim">ألوان خاصة محفوظة على هذا الحساب.</p>';
    }
    echo card($label,$form);
    echo card('استعادة الألوان الافتراضية',
        form_start('reset_console_theme','/?tab=settings','data-no-ajax="1"').
        '<p class="dim">يرجع حسابك للأحمر والأسود والأبيض، أو لألوان الريسيلر إذا كنت عميلًا تابعًا له، دون المساس بباقي الإعدادات.</p>'.
        '<button class="btn" type="submit" data-confirm="إعادة تعيين ألوان الحساب إلى الإعداد الافتراضي؟">إعادة تعيين الألوان</button></form>');
}
function render_settings(array $a):void{
    render_console_theme_settings($a);
    require_once __DIR__.'/ConsoleTypography.php';
    console_font_init();
    $brand=brand($a);
    $fontOptions=console_font_options();
    $accountId=(int)$a['account_id'];
    $userId=(int)(me()['id']??0);
    $currentTenant=one('SELECT font_code FROM console_font_preferences WHERE account_id=? AND user_id=0',[$accountId]);
    $tenantSelection=($currentTenant && console_font_valid((string)$currentTenant['font_code']))
        ?(string)$currentTenant['font_code']:'inherit';
    $personalChoice=console_font_personal($accountId,$userId)??'inherit';
    $tenantFont=console_font_tenant($accountId);
    $effectiveFont=console_font_effective($a);
    $previews='<div class="ot-font-options" aria-label="معاينة الخطوط العربية">';
    foreach($fontOptions as $fontKey=>$fontLabel){
        $previews.='<div class="ot-font-option" data-font-sample="'.e($fontKey).'">'.
            '<strong>'.e($fontLabel).'</strong>'.
            '<span lang="ar">إدارة أعمالك بوضوح — أهلاً بيك في أونتراك</span>'.
            '<small>المكالمات · العملاء · الفواتير · 12345</small></div>';
    }
    $previews.='</div>';
    $fontControls='<p class="dim">3 خطوط عربية واضحة من غير Tahoma أو Arial. تقدر تختار الخط لواجهة حسابك، وإعداد الشركة يظهر كافتراضي لأعضاء الفريق وعملاء الريسيلر ما لم يختاروا خطًا آخر.</p>'.
        '<p class="dim">المستخدم الحالي: <strong>'.e($fontOptions[$effectiveFont]??'IBM Plex Sans Arabic').'</strong></p>'.
        $previews;
    if(!active_support()){
        $personalOptions=['inherit'=>'استخدم خط الشركة الافتراضي']+$fontOptions;
        $fontControls.=form_start('save_console_font','/?tab=settings','data-no-ajax="1"').
            '<input type="hidden" name="scope" value="personal">'.
            select_html('الخط الخاص بواجهتي','font',$personalOptions,$personalChoice).
            '<small class="dim">خطك الشخصي لا يغيّر خطوط باقي مستخدمي الشركة.</small>'.
            submit_btn('حفظ الخط الشخصي');
        if(in_array($a['role'],['owner','admin'],true)){
            $tenantOptions=['inherit'=>'خط العلامة الافتراضي / الموروث']+$fontOptions;
            $fontControls.=form_start('save_console_font','/?tab=settings','data-no-ajax="1"').
                '<input type="hidden" name="scope" value="tenant">'.
                select_html('خط الشركة والأعضاء','font',$tenantOptions,$tenantSelection).
                '<small class="dim">الخط الحالي للشركة: '.e($fontOptions[$tenantFont]??'IBM Plex Sans Arabic').'. هذا هو الافتراضي لزملائك وعملائك قبل أي اختيار شخصي.</small>'.
                submit_btn('اعتماد خط الشركة');
        }
    }else{
        $fontControls.='<p class="dim">المعاينة متاحة، لكن لا يمكن تغيير الخط أثناء جلسة الدعم.</p>';
    }
    echo card('الخطوط العربية | Typography',$fontControls);
    // Owner decision 2026-11-04: no administrator 2FA/QR setup tile.
    // Auth uses password, session hardening and existing login rate limits.
    if($a['kind']==='platform'&&console_view_mode($a)==='admin'&&in_array($a['role'],['owner','admin'],true)&&!active_support()){
        echo card('إعدادات منصة واتساب المركزية',
            '<p class="dim">تكوين تطبيق Meta وEmbedded Signup وإعدادات الربط المركزي للمنصة في قسم مخصص.</p>'.
            '<p><a class="btn primary" href="/saas/console/?tab=whatsapp_admin">إعدادات ربط واتساب ←</a></p>');
        echo card('إظهار الريسيلرز',form_start('platform_resellers_visibility','/?tab=settings').
            '<label class="field"><input type="checkbox" name="resellers_enabled" value="1" '.(platform_resellers_visible()?'checked':'').'> تفعيل وإظهار الريسيلرز وتسجيلهم وباقاتهم</label>'.
            '<p class="dim">عند الإيقاف تختفي خيارات الريسيلرز من لوحة الإدارة وروابط التسجيل والباقات، مع بقاء حساباتهم واشتراكاتهم دون حذف.</p>'.
            submit_btn('حفظ الإعداد'));
        // Legacy key remains encrypted and auto-imports once into Voice Relay.
        // Avoid showing a second Google editor that would not update Relay priority.
        render_ai_relay_settings($a);
    }
    if($a['kind']!=='platform'){
        require_once __DIR__.'/GoogleVoiceKeysView.php';
        render_google_voice_key_settings($a);
        if(in_array($a['role'],['owner','admin'],true)&&!active_support())
            render_ai_relay_settings($a);
    }
    echo card('كلمة المرور والحساب',
        '<p>تقدر تغيّر كلمة المرور باستخدام الحالية، أو تسترجعها من البريد لو نسيتها. من غير مصادقة ثنائية أثناء التجارب.</p>'.
        '<p><a class="btn primary" href="/change-password">تغيير كلمة المرور</a> '.
        '<a class="btn" href="/forgot-password">استرجاع كلمة المرور</a></p>');
    echo '<div class="cols two">';
    $adminIdentity=in_array($a['kind'],['platform','reseller'],true)&&console_view_mode($a)==='admin';
    $identityTitle=$adminIdentity?($a['kind']==='platform'?'هوية المنصة':'هوية شركتك'):'هوية حسابك';
    $identityDescription=$adminIdentity
        ?'هنا بتتحكم في هوية الإدارة والعلامة التجارية. إعدادات وبيانات المستخدم التشغيلية موجودة في مساحة المستخدم.'
        :'بيانات الهوية والشعار بتظهر بشكل مناسب للبوابة الخاصة بشركتك.';
    require_once __DIR__.'/BrandIdentity.php';
    $brand=brand_identity_brand($a);
    $mode=brand_identity_mode($a);
    $hasLogo=!empty($brand['logo']);
    if($mode==='image'&&!$hasLogo)$mode='text';
    $logoPreview=$hasLogo
        ?'<img class="ot-brand-preview-image" src="/brand-logo?v='.rawurlencode((string)$brand['logo']).'" alt="'.e((string)$brand['name']).'" width="280" height="92">'
        :'<span class="ot-brand-preview-wordmark">'.e((string)$brand['name']).'</span>';
    $previewMode=$hasLogo?$mode:'text';
    echo card($identityTitle,
        '<div class="brand-preview ot-brand-preview" data-brand-display="'.e($previewMode).'">'.
        $logoPreview.'<div class="ot-brand-preview-copy"><h3>'.e((string)$brand['name']).
        '</h3><p>عرض كامل للشعار بدون قص، والاسم اختياري حسب طريقة العرض.</p></div></div>'.
        '<p>'.e($identityDescription).'</p>');
    if(brand_identity_edit_allowed($a)){
        $modeOptions=[
            'image'=>'صورة الشعار فقط — بدون نص أو أيقونة إضافية',
            'text'=>'اسم الشركة كنص فقط — بدون صورة',
            'both'=>'الشعار والاسم معًا'
        ];
        $modeField=select_html('طريقة عرض العلامة','brand_display',$modeOptions,$mode);
        $brandForm=form_start('brand','/?tab=settings',
            'enctype="multipart/form-data" data-no-ajax="1" data-brand-form').
            '<label class="field">اسم العلامة التجارية<input name="brand_name" type="text" maxlength="100" value="'.e((string)$brand['name']).'" required></label>'.
            $modeField.
            '<div class="ot-brand-upload-zone"><label class="field">الصورة الأصلية (PNG أو JPEG أو WebP — عريضة أو مربعة)'.
            '<input type="file" name="logo" data-brand-file accept="image/png,image/jpeg,image/webp" aria-label="اختيار صورة شعار الشركة">'.
            '</label>'.
            '<p class="dim">أقصى حجم للخادم 3 ميجابايت. بيتم رفع الصورة مباشرة بدون multipart لتجنب خطأ Forbidden 403، مع الحفاظ على أبعاد الشعار.</p>'.
            '<p role="status" aria-live="polite" data-brand-upload-status>اختار ملف لرفع الشعار، وبعدها احفظ طريقة العرض.</p>'.
            '</div>'.
            submit_btn('حفظ الاسم وطريقة العرض');
        echo card('شعار شركتك — أنت تختار طريقة عرضه',$brandForm);
        if($hasLogo)echo card('إزالة صورة الشعار',
            form_start('brand_logo_remove','/?tab=settings','data-no-ajax="1"').
            '<p class="dim">إزالة الصورة فقط. اسم الشركة وألوانها لا تتغير.</p>'.
            '<button type="submit" class="btn danger" data-confirm="هل تريد حذف صورة الشعار الحالية؟">حذف صورة الشعار</button></form>');
    }else{
        echo card('إعدادات المستخدم',
            '<p>العلامة والشعار يظهران حسب إعدادات المنصة أو الريسيلر، ولا يمكن تعديلهُما من حسابك.</p>');
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
            ($existingChannel?'<small>ما تغيّرش إعدادات Meta. استقبال الرسائل لا يساوي صلاحية الإرسال؛ اختبر موصل إرسال الموديول الحالي ثم فعّل AI.</small>':
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
            ($existingChannel?form_start('wa_existing_outbound_test','/?tab=whatsapp','class="inline-form"').
            '<input type="hidden" name="connection_id" value="'.(int)$conn['id'].'">'.
            '<button class="btn small" type="submit">اختبار إرسال الموديول الحالي</button></form>':'').
            (!$existingChannel?form_start('wa_connection_test','/?tab=whatsapp','class="inline-form"').
            '<input type="hidden" name="connection_id" value="'.(int)$conn['id'].'">'.
            '<button class="btn small" type="submit">اختبار مفتاح Meta والرقم</button></form>':'').
            (!empty($conn['ai_agent_id'])&&(!$existingChannel||wa_existing_outbound_ready($conn))?
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
            '<div class="wa-manual-cell third">'.select_html('المساعد الذي يرد على واتساب','ai_agent_id',$agentOptions).'<small class="wa-field-help">من <a href="/saas/console/?tab=agents">استوديو المساعدين</a>، اختار إيجنت منشور.</small></div>'.
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
            '<div class="wa-manual-cell third">'.select_html('المساعد المنشور','ai_agent_id',$agentOptions).'</div>'.
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
                '<div class="wa-manual-cell half">'.select_html('المساعد المنشور','ai_agent_id',$agentOptions).'</div></div>'.
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
