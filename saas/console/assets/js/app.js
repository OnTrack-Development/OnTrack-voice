(()=>{
  'use strict';
  const body=document.body;
  const themeBtn=document.getElementById('themeToggle');
  const themeStorageKey='ontrack-console-theme-'+(body.dataset.otAccount||'public');
  const theme=()=>{
    try{
      const stored=localStorage.getItem(themeStorageKey);
      return (stored===null?localStorage.getItem('ontrack-console-theme'):stored)==='light'?'light':'dark';
    }catch{return 'dark'}
  };
  const setTheme=(mode)=>{
    body.classList.toggle('light',mode==='light');
    if(themeBtn){themeBtn.textContent=mode==='light'?'☾':'☀';themeBtn.setAttribute('aria-label',mode==='light'?'تفعيل المظهر الداكن':'تفعيل المظهر الفاتح')}
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content',mode==='light'?'#f6f6f8':'#0c0d11');
  };
  setTheme(theme());
  themeBtn?.addEventListener('click',()=>{
    const next=body.classList.contains('light')?'dark':'light';
    setTheme(next);try{localStorage.setItem(themeStorageKey,next)}catch{}
  });
  // v63: draft preview is local to the settings card until explicitly saved.
  // The actual account theme remains server-owned; no CSS or URL injection.
  const colorRatio=(hex)=>{
    if(!/^#[0-9a-fA-F]{6}$/.test(hex))return 0;
    const channels=[1,3,5].map(i=>{
      const v=parseInt(hex.slice(i,i+2),16)/255;
      return v<=0.04045?v/12.92:Math.pow((v+.055)/1.055,2.4);
    });
    return channels[0]*.2126+channels[1]*.7152+channels[2]*.0722;
  };
  const onColor=hex=>{
    const light=colorRatio(hex)+.05;
    return light/.05>=1.05/light?'#10131a':'#ffffff';
  };
  const setUpPaletteEditor=()=>{
    document.querySelectorAll('[data-ot-palette-editor]').forEach(form=>{
      if(form.dataset.otPaletteBound==='1')return;
      form.dataset.otPaletteBound='1';
      const preview=form.querySelector('[data-ot-palette-preview]');
      const layout=form.querySelector('[data-ot-layout]');
      if(!preview||!layout)return;
      const inputs=Object.fromEntries(
        [...form.querySelectorAll('[data-ot-palette-input]')].map(x=>[x.dataset.otPaletteInput,x])
      );
      const update=()=>{
        const single=layout.value==='single';
        for(const mode of ['dark','light']){
          const sidebar=inputs[mode+'_sidebar'];
          const content=inputs[mode+'_content'];
          const accent=inputs[mode+'_accent'];
          if(!sidebar||!content||!accent)return;
          if(single)sidebar.value=content.value;
          sidebar.setAttribute('aria-disabled',String(single));
          sidebar.classList.toggle('ot-color-locked',single);
          for(const field of [sidebar,content,accent]){
            const out=form.querySelector('[data-ot-value-for="'+field.name+'"]');
            if(out)out.textContent=field.value.toUpperCase();
          }
        }
        const mode=body.classList.contains('light')?'light':'dark';
        const content=inputs[mode+'_content'].value;
        const sidebar=single?content:inputs[mode+'_sidebar'].value;
        const accent=inputs[mode+'_accent'].value;
        const vars={
          '--ot-sidebar-active':sidebar,
          '--ot-content-active':content,
          '--ot-sidebar-ink':onColor(sidebar),
          '--ot-content-ink':onColor(content),
          '--accent':accent,
          '--ot-on-accent':onColor(accent),
        };
        Object.entries(vars).forEach(([key,value])=>preview.style.setProperty(key,value));
        preview.dataset.otPreviewMode=mode;
      };
      form.addEventListener('input',event=>{
        if(event.target.closest('[data-ot-palette-input]'))update();
      });
      form.addEventListener('change',update);
      themeBtn?.addEventListener('click',()=>requestAnimationFrame(update));
      update();
    });
  };
  setUpPaletteEditor();
  // Raw binary upload keeps the image outside multipart/form-data: on some
  // shared hosts their WAF rejected the old multipart brand request with 403.
  document.addEventListener('change',async event=>{
    const input=event.target.closest?.('[data-brand-file]');
    if(!input||!input.files?.length)return;
    const form=input.closest('[data-brand-form]');
    const status=form?.querySelector('[data-brand-upload-status]');
    const submit=form?.querySelector('[type="submit"]');
    const csrf=form?.querySelector('input[name="csrf"]')?.value||'';
    const original=input.files[0];
    const valid=['image/png','image/jpeg','image/webp'];
    if(!form||!csrf||!valid.includes(original.type)){
      if(status)status.textContent='مسموح PNG أو JPEG أو WEBP فقط.';
      input.value='';return;
    }
    const setStatus=text=>{if(status)status.textContent=text};
    const isOversized=original.size>2.4*1024*1024;
    submit?.setAttribute('disabled','disabled');
    setStatus('جارٍ تجهيز الشعار وحفظه…');
    try{
      let blob=original;
      if(isOversized){
        // Large raster files are converted to WebP retaining transparency.
        // No forced square crop. 4096px longest edge, never upscaled.
        let bitmap;
        try{
          bitmap=await createImageBitmap(original);
        }catch{
          throw Error('المتصفح لم يقدر يقرأ الصورة. جرّب PNG أو WebP صالح.');
        }
        const width=bitmap.width,height=bitmap.height;
        if(!width||!height||width>12000||height>12000){
          bitmap.close?.();throw Error('أبعاد الصورة غير مقبولة.');
        }
        const scale=Math.min(1,4096/Math.max(width,height),Math.sqrt(24000000/(width*height)));
        const canvas=document.createElement('canvas');
        canvas.width=Math.max(1,Math.round(width*scale));
        canvas.height=Math.max(1,Math.round(height*scale));
        const ctx=canvas.getContext('2d');
        if(!ctx){bitmap.close?.();throw Error('تعذر تجهيز الصورة على الجهاز.');}
        ctx.clearRect(0,0,canvas.width,canvas.height);
        ctx.drawImage(bitmap,0,0,canvas.width,canvas.height);
        bitmap.close?.();
        blob=await new Promise((resolve,reject)=>
          canvas.toBlob(result=>result?resolve(result):reject(Error('فشل ضغط صورة الشعار.')),
            'image/webp',.9)
        );
      }
      if(blob.size>3*1024*1024)throw Error('الشعار ما زال أكبر من 3MB بعد التحسين؛ جرّب WebP أقل حجمًا.');
      const response=await fetch('/?ot_brand_upload=1',{
        method:'POST',credentials:'same-origin',cache:'no-store',redirect:'error',
        headers:{'Content-Type':blob.type||original.type,'X-CSRF-Token':csrf,
          'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
        body:blob
      });
      const type=(response.headers.get('Content-Type')||'').toLowerCase();
      const payload=type.includes('application/json')
        ?await response.json().catch(()=>null):null;
      if(response.status===403)
        throw Error('Forbidden 403 من طبقة الحماية/الاستضافة. مسار الرفع المباشر لم يصل لتطبيق PHP. راجع WAF لهذا المسار.');
      if(response.status===401||response.status===419)
        throw Error('انتهت صلاحية جلسة الدخول. افتح الإعدادات من جديد.');
      if(!response.ok||payload?.ok!==true)
        throw Error(payload?.error||'فشل رفع الصورة (HTTP '+response.status+').');
      setStatus('اترفع شعارك بنجاح. بيتم تحديث المعاينة…');
      input.value='';window.ontrackToast?.('تم حفظ صورة الشعار بنجاح');
      // Reload guarantees all three placements update (sidebar/login/settings)
      // without leaving an old cached logo on another tab.
      window.location.reload();
    }catch(error){
      setStatus(error.message||'تعذر رفع الشعار.');
      input.value='';
      window.ontrackToast?.(error.message||'تعذر رفع الشعار.',{error:true});
    }finally{
      submit?.removeAttribute('disabled');
    }
  });
  document.querySelectorAll('[data-toggle-password]').forEach(button=>button.addEventListener('click',()=>{
    const input=document.getElementById('loginPassword');if(!input)return;
    input.type=input.type==='password'?'text':'password';
    button.textContent=input.type==='password'?'إظهار':'إخفاء';
  }));
  document.querySelectorAll('[data-copy-secret]').forEach(button=>button.addEventListener('click',async()=>{
    const area=button.closest('.secret');
    const field=area?.querySelector('[data-copy-source]');
    const status=area?.querySelector('[data-copy-result]');
    if(!field)return;
    let copied=false;
    try{if(navigator.clipboard?.writeText){await navigator.clipboard.writeText(field.value);copied=true}}catch(_){}
    if(!copied){
      field.focus();field.select();field.setSelectionRange(0,field.value.length);
      try{copied=document.execCommand('copy')===true}catch(_){}
    }
    if(copied){button.textContent='تم النسخ';if(status)status.textContent='تم نسخ المفتاح بنجاح.'}
    else if(status)status.textContent='تم تحديد المفتاح؛ اختر نسخ من قائمة النص.';
  }));
  document.querySelectorAll('[data-copy-source]').forEach(field=>field.addEventListener('click',()=>field.select()));
  // Dynamic onboarding elements are re-rendered on soft navigation.
  document.addEventListener('click',async event=>{
    const button=event.target.closest?.('[data-wa-copy]');
    if(!button)return;
    const source=document.getElementById(button.getAttribute('data-wa-copy')||'');
    const value=(source?.textContent||'').trim();
    if(!value)return;
    let copied=false;
    try{if(navigator.clipboard?.writeText){await navigator.clipboard.writeText(value);copied=true;}}catch(_){}
    if(!copied){
      const field=document.createElement('textarea');
      field.value=value;field.setAttribute('readonly','');
      field.style.position='fixed';field.style.top='-9999px';
      document.body.append(field);field.select();
      try{copied=document.execCommand('copy')===true;}catch(_){}
      field.remove();
    }
    if(copied){
      const original=button.textContent;button.textContent='تم النسخ ✓';
      window.setTimeout(()=>{if(button.isConnected)button.textContent=original;},1600);
    }else window.ontrackToast?.('تعذر النسخ؛ حدّد النص وانسخه يدويًا.',{error:true});
  });

  // Two basic methods are always visible; Meta Embedded Signup and Coexistence
  // are rendered only when the PLATFORM admin enabled a complete configuration.
  document.addEventListener('click',event=>{
    const choice=event.target.closest?.('[data-wa-method]');
    if(!choice)return;
    const root=choice.closest('.wa-manual-card');if(!root)return;
    const mode=choice.getAttribute('data-wa-method');
    root.querySelectorAll('[data-wa-method]').forEach(item=>{
      const selected=item.getAttribute('data-wa-method')===mode;
      item.classList.toggle('active',selected);
      item.setAttribute('aria-pressed',String(selected));
    });
    root.querySelectorAll('[data-wa-method-panel]').forEach(panel=>{
      panel.hidden=panel.getAttribute('data-wa-method-panel')!==mode;
    });
  });
  document.addEventListener('input',event=>{
    const input=event.target;
    if(input?.name!=='meta_app_id')return;
    const id=String(input.value||'').trim();
    const direct=/^[0-9]{5,100}$/.test(id)
      ?'https://developers.facebook.com/apps/'+id+'/settings/basic/'
      :'https://developers.facebook.com/apps/';
    input.closest('form')?.querySelectorAll('[data-wa-meta-app-link]').forEach(link=>{
      link.href=direct;
    });
  });

  let metaSdkRequested=false;
  const prefetchMetaSdk=(scope=document)=>{
    if(!scope.querySelector?.('[data-wa-embedded-form]')||metaSdkRequested)return;
    metaSdkRequested=true;
    const sdk=document.createElement('script');
    sdk.src='https://connect.facebook.net/en_US/sdk.js';
    sdk.async=true;sdk.defer=true;sdk.crossOrigin='anonymous';
    sdk.onerror=()=>{
      metaSdkRequested=false;
      window.ontrackToast?.('تعذر تحميل Meta SDK؛ تحقق من الاتصال وحاجب الإعلانات.',{error:true});
    };
    document.head.append(sdk);
  };
  window.ontrackMountWhatsApp=prefetchMetaSdk;
  prefetchMetaSdk(document);

  let currentMetaLogin=null;
  const reportMeta=status=>{
    const node=currentMetaLogin?.form?.querySelector('[data-wa-embedded-status]');
    if(node)node.textContent=status;
  };
  const submitMetaResult=()=>{
    const pending=currentMetaLogin;
    if(!pending||pending.submitted||!pending.code||!pending.wabaId)return;
    const form=pending.form;
    if(!form?.isConnected)return;
    if(!form.elements.site_id?.value){reportMeta('حدد الموقع أولاً');return;}
    form.elements.code.value=pending.code;
    form.elements.waba_id.value=pending.wabaId;
    form.elements.phone_number_id.value=pending.phoneId||'';
    pending.submitted=true;
    reportMeta('Meta وافقت؛ بنثبت ملكية الرقم من السيرفر قبل حفظ الاتصال...');
    form.requestSubmit();
  };
  window.addEventListener('message',event=>{
    let parsed;
    try{
      const origin=new URL(event.origin);
      if(origin.protocol!=='https:'||
        !(origin.hostname==='facebook.com'||origin.hostname.endsWith('.facebook.com')))return;
      parsed=typeof event.data==='string'?JSON.parse(event.data):event.data;
    }catch{return;}
    if(!currentMetaLogin||!parsed||parsed.type!=='WA_EMBEDDED_SIGNUP')return;
    if(parsed.event==='CANCEL'||parsed.event==='ERROR'){
      reportMeta('تم إلغاء الربط أو رفضه من Meta. لم يتم حفظ رقم.');
      currentMetaLogin=null;return;
    }
    if(!['FINISH','FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING'].includes(parsed.event))return;
    const data=parsed.data||{};
    const id=String(data.waba_id||'').trim();
    const phone=String(data.phone_number_id||'').trim();
    if(!/^[0-9]{5,100}$/.test(id)||phone&&!/^[0-9]{5,100}$/.test(phone))return;
    currentMetaLogin.wabaId=id;currentMetaLogin.phoneId=phone;
    submitMetaResult();
  });
  document.addEventListener('click',event=>{
    const button=event.target.closest?.('[data-wa-embedded-start]');
    if(!button)return;
    const form=button.closest('form[data-wa-embedded-form]');
    if(!form)return;
    const appId=button.dataset.waAppId||'';
    const configId=button.dataset.waConfigId||'';
    const version=button.dataset.waGraphVersion||'v26.0';
    const mode=button.dataset.waEmbeddedMode||'';
    if(!/^[0-9]{5,100}$/.test(appId)||!/^[0-9]{5,100}$/.test(configId)||
       !['signup','coexist'].includes(mode))return;
    if(!window.FB?.login||!window.FB?.init){
      prefetchMetaSdk(document);
      window.ontrackToast?.('Meta SDK بيتم تحميله؛ اضغط مرة تانية بعد التحميل.',{error:true});
      return;
    }
    currentMetaLogin={form,mode,code:'',wabaId:'',phoneId:'',submitted:false};
    reportMeta('استكمل الموافقة داخل نافذة Meta الرسمية.');
    try{
      window.FB.init({appId,version,xfbml:false,cookie:false});
      const extras={setup:{}};
      if(mode==='coexist')extras.featureType='whatsapp_business_app_onboarding';
      window.FB.login(response=>{
        if(currentMetaLogin?.form!==form)return;
        const code=response?.authResponse?.code;
        if(typeof code!=='string'||!/^[A-Za-z0-9._~-]{10,1600}$/.test(code)){
          reportMeta('Meta لم تُرجع رمز الموافقة؛ لم يتم حفظ اتصال.');
          currentMetaLogin=null;return;
        }
        currentMetaLogin.code=code;
        submitMetaResult();
      },{config_id:configId,response_type:'code',
         override_default_response_type:true,extras});
    }catch(_){
      reportMeta('فشل فتح نافذة Meta؛ راجع إعدادات التطبيق لدى الإدارة.');
      currentMetaLogin=null;
    }
  });
  // Responsive WEB navigation: an accessible drawer only below the tablet breakpoint.
  const navToggle=document.getElementById('navToggle');
  const navClose=document.getElementById('navClose');
  const navBackdrop=document.getElementById('navBackdrop');
  const nav=document.getElementById('mainNavigation');
  const isCompact=window.matchMedia('(max-width: 810px)');
  const closeNav=(restoreFocus=false)=>{
    if(!body.classList.contains('nav-open'))return;
    body.classList.remove('nav-open');
    navToggle?.setAttribute('aria-expanded','false');
    navToggle?.setAttribute('aria-label','فتح القائمة');
    if(restoreFocus)navToggle?.focus();
  };
  navToggle?.addEventListener('click',()=>{
    if(!isCompact.matches)return;
    const open=!body.classList.contains('nav-open');
    body.classList.toggle('nav-open',open);
    navToggle.setAttribute('aria-expanded',String(open));
    navToggle.setAttribute('aria-label',open?'إغلاق القائمة':'فتح القائمة');
    if(open)navClose?.focus();
  });
  navClose?.addEventListener('click',()=>closeNav(true));
  navBackdrop?.addEventListener('click',()=>closeNav(true));
  // Delegate to the sidebar itself: AJAX can replace the nav links when an
  // admin toggles a feature, otherwise newly-added links lose mobile handlers.
  nav?.addEventListener('click',event=>{
    if(event.target.closest?.('.nav-link'))closeNav();
  });
  document.addEventListener('keydown',event=>{
    if(event.key==='Escape')closeNav(true);
    if(event.key==='Tab'&&body.classList.contains('nav-open')&&isCompact.matches&&nav){
      const elements=[...nav.querySelectorAll('a[href],button:not([disabled])')].filter(el=>el.getClientRects().length);
      if(!elements.length)return;
      const first=elements[0],last=elements[elements.length-1];
      if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus()}
      else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus()}
    }
  });
  isCompact.addEventListener?.('change',()=>closeNav());
  // Keep direct links anchored to their actual editor.
  const openAgentFromHash=()=>{
    if(!/^#agent-[1-9][0-9]*$/.test(window.location.hash))return;
    const editor=document.getElementById(window.location.hash.slice(1));
    if(editor?.matches('details.agent-editor'))editor.open=true;
  };
  openAgentFromHash();
  window.addEventListener('hashchange',openAgentFromHash);
  // Consistent in-app confirmation/feedback; browser alert/confirm/prompt are
  // intentionally never used. Put the overlay inside the Fullscreen element so
  // deleting a Flow node cannot force native fullscreen to close.
  let activeDialog=false;
  window.ontrackConfirm=(message,{title='تأكيد العملية',confirmText='تأكيد',danger=false}={})=>{
    if(activeDialog)return Promise.resolve(false);
    activeDialog=true;
    return new Promise(resolve=>{
      const host=document.fullscreenElement||document.body;
      const previous=document.activeElement;
      const overlay=document.createElement('div');overlay.className='ot-confirm-overlay';
      const dialog=document.createElement('section');dialog.className='ot-confirm-dialog';
      dialog.setAttribute('role','alertdialog');
      dialog.setAttribute('aria-modal','true');
      const titleElement=document.createElement('h3');
      titleElement.id='ot-confirm-title-'+Date.now();
      titleElement.textContent=title;
      dialog.setAttribute('aria-labelledby',titleElement.id);
      const textElement=document.createElement('p');textElement.textContent=String(message);
      const actions=document.createElement('div');actions.className='ot-confirm-actions';
      const cancel=document.createElement('button');cancel.type='button';cancel.className='btn';
      cancel.textContent='إلغاء';
      const approve=document.createElement('button');approve.type='button';
      approve.className='btn'+(danger?' danger':' primary');approve.textContent=confirmText;
      actions.append(cancel,approve);dialog.append(titleElement,textElement,actions);
      overlay.append(dialog);host.append(overlay);
      const finish=ok=>{
        overlay.removeEventListener('keydown',onKey);
        overlay.remove();activeDialog=false;
        if(previous?.isConnected&&typeof previous.focus==='function')previous.focus();
        resolve(ok);
      };
      const onKey=event=>{
        if(event.key==='Escape'){event.preventDefault();event.stopPropagation();finish(false);return}
        if(event.key!=='Tab')return;
        const first=cancel,last=approve;
        if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus()}
        else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus()}
      };
      overlay.addEventListener('keydown',onKey);
      cancel.addEventListener('click',()=>finish(false),{once:true});
      approve.addEventListener('click',()=>finish(true),{once:true});
      overlay.addEventListener('click',event=>{if(event.target===overlay)finish(false)});
      cancel.focus();
    });
  };
  window.ontrackToast=(message,{error=false}={})=>{
    const host=document.fullscreenElement||document.body;
    const toast=document.createElement('div');
    toast.className='ot-ui-toast'+(error?' error':'');
    toast.setAttribute('role',error?'alert':'status');
    toast.textContent=String(message);
    host.append(toast);
    setTimeout(()=>toast.remove(),4300);
  };
  // Legacy console form actions use the same OnTrack modal. The actual submit
  // happens only after the user presses our confirm button.
  document.addEventListener('submit',async event=>{
    const button=event.submitter;
    if(!button?.hasAttribute('data-confirm')||button.dataset.otApproved==='1')return;
    // Block BOTH native submit and later AJAX capture listeners until approved.
    event.preventDefault();event.stopImmediatePropagation();
    const form=button.form;
    if(!form)return;
    const accepted=await window.ontrackConfirm(button.getAttribute('data-confirm'),{
      title:'تأكيد التغيير',
      confirmText:button.getAttribute('data-confirm-label')||'تأكيد',
      danger:button.classList.contains('danger')
    });
    if(!accepted)return;
    button.dataset.otApproved='1';
    try{form.requestSubmit(button)}
    finally{delete button.dataset.otApproved}
  },true);

  // Same-origin, progressive AJAX navigation. Existing PHP authorization,
  // server-side validation, CSRF and redirect destinations remain authoritative.
  // Auth, view changes, support and account transitions ALWAYS use native
  // POST + PHP 303 redirect so the entire sidebar, permissions and state reload.
  const excludes=new Set(['logout','login','register','switch','set_view','support_enter',
    'support_leave','checkout_plan','manual_approve','manual_payment_approve']);
  let activeRequest=false;
  const allowedUrl=url=>url.origin===location.origin &&
    url.pathname.startsWith('/saas/console/') &&
    !url.pathname.startsWith('/saas/console/api/') &&
    !url.pathname.startsWith('/saas/console/device/') &&
    !url.pathname.startsWith('/saas/console/hooks/');
  const refreshSections=(html,requestedUrl,replace=false)=>{
    const parsed=new DOMParser().parseFromString(html,'text/html');
    const main=parsed.querySelector('main.content');
    const existing=document.querySelector('main.content');
    if(!main||!existing)throw Error('استجابة الصفحة ليست لوحة تحكم صالحة');
    existing.replaceWith(main);
    document.title=parsed.title||document.title;
    const heading=parsed.querySelector('.topbar .headline');
    const currentHeading=document.querySelector('.topbar .headline');
    if(heading&&currentHeading)currentHeading.replaceWith(heading);
    // Server-side roles, feature toggles and subscription changes may alter
    // navigation itself. Only replacing <main> leaves removed actions visible
    // until a hard reload and hides newly allowed sections from the sidebar.
    const nextNavigation=parsed.querySelector('.sidebar .nav-list');
    const oldNavigation=document.querySelector('.sidebar .nav-list');
    if(nextNavigation && oldNavigation){
      const previousOpen=[...oldNavigation.querySelectorAll('.nav-group[open]')]
        .map(group=>group.getAttribute('data-nav-group')).filter(Boolean);
      nextNavigation.querySelectorAll('.nav-group').forEach(group=>{
        if(previousOpen.includes(group.getAttribute('data-nav-group')))group.open=true;
      });
      oldNavigation.replaceWith(nextNavigation);
    }else{
      const selected=parsed.querySelector('.nav-link.selected')?.getAttribute('href');
      document.querySelectorAll('.nav-link').forEach(link=>{
        const active=link.getAttribute('href')===selected;
        link.classList.toggle('selected',active);
        if(active)link.setAttribute('aria-current','page');
        else link.removeAttribute('aria-current');
      });
    }
    const nextUrl=new URL(requestedUrl,location.href);
    if(replace)history.replaceState({otAjax:true},'',nextUrl.pathname+nextUrl.search+nextUrl.hash);
    else history.pushState({otAjax:true},'',nextUrl.pathname+nextUrl.search+nextUrl.hash);
    // AJAX replaces the editor DOM after the original hashchange event.
    // Re-open a deep-linked editor against the freshly rendered page.
    openAgentFromHash();
    window.ontrackMountFlowEditors?.(main);
    window.ontrackMountWhatsApp?.(main);
    window.ontrackMountPairingQR?.(main);
    window.ontrackMountMfaQR?.(main);
    if(main.querySelector('[data-copy-secret]'))main.querySelectorAll('[data-copy-source]').forEach(field=>{
      field.addEventListener('click',()=>field.select());
    });
    closeNav();
  };
  window.ontrackSoftNavigate=async(target,{replace=false}={})=>{
    const url=new URL(target,location.href);
    if(!allowedUrl(url))return false;
    if(activeRequest)return false;
    activeRequest=true;body.classList.add('ot-ajax-loading');
    try{
      const response=await fetch(url.href,{
        method:'GET',credentials:'same-origin',cache:'no-store',
        headers:{'Accept':'text/html','X-Requested-With':'XMLHttpRequest'}});
      if(!response.ok||!response.headers.get('content-type')?.includes('text/html'))
        throw Error('تعذر تحميل الصفحة ('+response.status+')');
      const finalUrl=new URL(response.url,location.href);
      if(!allowedUrl(finalUrl))throw Error('الجلسة انتهت أو الصفحة تحتاج تسجيل الدخول');
      refreshSections(await response.text(),finalUrl.href,replace);
      return true;
    }catch(error){window.ontrackToast(error.message||'تعذر تحديث الصفحة',{error:true});return false}
    finally{activeRequest=false;body.classList.remove('ot-ajax-loading')}
  };
  document.addEventListener('click',event=>{
    if(event.defaultPrevented||event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
    const anchor=event.target.closest?.('a[href]');
    if(!anchor||anchor.target||anchor.hasAttribute('download')||anchor.hasAttribute('data-no-ajax'))return;
    const url=new URL(anchor.href,location.href);
    if(!allowedUrl(url))return;
    if(url.hash && url.pathname===location.pathname && url.search===location.search)return;
    event.preventDefault();
    void window.ontrackSoftNavigate(url.href);
  });
  document.addEventListener('popstate',()=>{
    void window.ontrackSoftNavigate(location.href,{replace:true});
  });
  document.addEventListener('submit',async event=>{
    if(event.defaultPrevented||!event.target.matches('form'))return;
    const form=event.target;
    if(!form.closest('.app-shell')||form.classList.contains('agent-form')||
       form.hasAttribute('data-no-ajax'))return;
    const data=new FormData(form,event.submitter||undefined);
    const action=data.get('action');
    if(typeof action==='string'&&excludes.has(action))return;
    const endpoint=new URL(form.getAttribute('action')||location.href,location.href);
    if(!allowedUrl(endpoint))return;
    event.preventDefault();
    event.stopPropagation();
    if(activeRequest)return;
    if(form.method.toLowerCase()==='get'){
      const url=new URL(form.getAttribute('action')||location.href,location.href);
      for(const [name,value] of data)if(typeof value==='string')url.searchParams.set(name,value);
      void window.ontrackSoftNavigate(url.href);
      return;
    }
    activeRequest=true;body.classList.add('ot-ajax-loading');
    const submitter=event.submitter;
    if(submitter)submitter.disabled=true;
    try{
      const response=await fetch(endpoint.href,{
        method:'POST',body:data,credentials:'same-origin',cache:'no-store',
        headers:{'Accept':'text/html','X-Requested-With':'XMLHttpRequest'}
      });
      if(!response.ok||!response.headers.get('content-type')?.includes('text/html'))
        throw Error('تعذر تنفيذ العملية ('+response.status+')');
      const finalUrl=new URL(response.url,location.href);
      if(!allowedUrl(finalUrl))throw Error('الصفحة تحتاج تسجيل الدخول أو إجراء منفصل');
      const html=await response.text();
      refreshSections(html,finalUrl.href,true);
      const msg=document.querySelector('main.content .alert.danger,main.content .alert.success');
      if(msg)window.ontrackToast(msg.textContent?.trim().slice(0,210)||'تم تحديث البيانات',{
        error:msg.classList.contains('danger')});
      else window.ontrackToast('تم تحديث البيانات بدون إعادة تحميل');
    }catch(error){window.ontrackToast(error.message||'تعذر تنفيذ العملية',{error:true})}
    finally{activeRequest=false;body.classList.remove('ot-ajax-loading');if(submitter?.isConnected)submitter.disabled=false}
  },true);

  // Keep the actual visual Builder mounted while switching intelligence,
  // knowledge and voice panels. No page navigation or loss of AI chat memory.
  document.addEventListener('click',event=>{
    const button=event.target.closest?.('[data-builder-tab]');
    if(!button)return;
    const form=button.closest('form.agent-form');
    if(!form)return;
    const current=button.getAttribute('data-builder-tab');
    form.querySelectorAll('[data-builder-tab]').forEach(tab=>{
      const chosen=tab===button;
      tab.classList.toggle('active',chosen);
      tab.setAttribute('aria-selected',String(chosen));
    });
    form.querySelectorAll('[data-builder-pane]').forEach(pane=>{
      pane.hidden=pane.getAttribute('data-builder-pane')!==current;
    });
  });
  // Switching the active runtime mode never removes the other editor's
  // text/graph. Both are saved as a draft; only publishing changes SIM calls.
  document.addEventListener('change',event=>{
    const mode=event.target.closest?.('select[name="scenario_mode"]');
    if(!mode)return;
    const form=mode.closest('form');
    form?.querySelector(mode.value==='scenario'
      ?'[data-builder-tab="scenario"]':'[data-builder-tab="visual"]')?.click();
  });
  // A fresh, editable example of the earlier dog-sales demo idea; do not
  // claim the exact old text was recovered or overwrite a user's draft.
  const dogSaleExample=[
    'ديمو تمثيلي كوميدي باللهجة المصرية، مش عملية بيع أو تبنّي حقيقية.',
    'الشخصية: نور، بنت مصرية فرفوشة، شقية، لعوب وخفيفة الدم. كلامك عفوي وغير رسمي؛ نبرة هادية في السماع، ومرحة في الرد، بسرعة طبيعية من غير تمطيط أو بطء.',
    'اسمعي المتصل للآخر حتى لو وقف لحظة يفكر. ما تقاطعوش. اسألي سؤال واحد، واستني الإجابة الكاملة. لو قاطعك اسكتي، واسمعي الجديد، وما تعيديش كلامك من الأول.',
    'المشهد: بتحاولي تعرضي كلب عنده تلات رجول، بسعر افتتاحي 350 جنيه. في القصة عنده إسهال وغازات وريحته عاملة أزمة في الشقة. اذكري العيوب دي بوضوح؛ ما تدّعيش إنه سليم.',
    'ابدئي بهزار خفيف: عندنا كلب تلات رجول بس عامل نفسه بطل! بنطلب 350 جنيه. إيه رأيك؟ ثم اسمعي رد العميل.',
    'فاصلي على مهلك: 350، ولو رفض اسأليه هو شايف كام، وبعد الاعتراضات انزلي بالتدريج 300، 250، 200، 150، 100، 50. بلاش تعرضي المجاني من أول مرة.',
    'لو مفيش أي اتفاق بعد محاولات تفاوض متعددة، تقدري تطرحي التبنّي المجاني كآخر حل تمثيلي؛ ما تضغطيش على حد مش مستعد يرعى كلب محتاج طبيب بيطري.',
    'ردودك قصيرة وطبيعية مع إفيهات، من غير إطالة ولا تكرار أو انتقال للمرحلة التالية قبل ما العميل يخلص.',
    'لما العميل يوافق صراحة على الشراء أو التبنّي، اقفلي المشهد بالمفاجأة الكوميدية: إنت فاكر إن إحنا هنديلك الكلب؟ إحنا ما بنبيعش كلاب أصلاً! يلا يا عم اتكل على الله من هنا!',
    'بعد قفلة المشهد توقفي عن الكلام. ما تدّعيش إن المكالمة اتقفلت إلا لو الجهاز نفذ إنهاء الاتصال. ممنوع تحصيل فلوس أو بيانات دفع؛ الديمو كله تمثيل.'
  ].join('\n');
  document.addEventListener('click',async event=>{
    const button=event.target.closest?.('[data-scenario-example="dog-sale"]');
    if(!button)return;
    const form=button.closest('form');
    const editor=form?.querySelector('textarea[name="scenario_text"]');
    if(!editor)return;
    if(editor.value.trim()){
      const confirmed=await window.ontrackConfirm(
        'تحميل مثال البيع هيستبدل نص السيناريو الحالي في المسودة، لكنه مش هيغير البيلدر ولا هينشر أي شيء.',
        {title:'تحميل مثال السيناريو',confirmText:'استبدال النص'});
      if(!confirmed)return;
    }
    editor.value=dogSaleExample;
    editor.dispatchEvent(new Event('input',{bubbles:true}));
    const mode=form.querySelector('select[name="scenario_mode"]');
    if(mode){mode.value='scenario';mode.dispatchEvent(new Event('change',{bubbles:true}));}
    editor.focus();
    window.ontrackToast('مثال سيناريو البيع اتضاف للمسودة. اضغط نشر عشان يتفعّل في المكالمات.');
  });
  // Agent Studio stores the complete published policy as one atomic backend
  // request; never reloads the screen, loses the current tab or exposes keys.
  document.addEventListener('submit',async event=>{
    const form=event.target;
    if(!form?.matches('form.agent-studio-form'))return;
    event.preventDefault();event.stopPropagation();
    if(form.dataset.agentSaving==='1')return;
    const submit=event.submitter;
    const publish=submit?.name==='publish_now';
    if(publish){
      const accepted=await window.ontrackConfirm(
        'نشر إعدادات AI Agent الجديدة يجعلها متاحة للمكالمات عند المزامنة. لا يتم إجراء أي مكالمة من هنا.',
        {title:'نشر الإيجنت الذكي',confirmText:'نشر الإعدادات'});
      if(!accepted)return;
    }
    const data=new FormData(form);
    if(publish)data.set('publish_now','1');else data.delete('publish_now');
    const status=form.querySelector('.agent-studio-status');
    form.dataset.agentSaving='1';
    if(submit)submit.disabled=true;
    if(status)status.textContent='جارٍ '+(publish?'النشر':'الحفظ')+'…';
    try{
      // Group multiline owner-authored text in a single Unicode-safe field.
      // Some shared-host WAF rules reject legitimate JSON nodes/instructions
      // with a fake HTML 404 before the request reaches PHP. All decoded
      // values are still checked by the existing CSRF, RBAC and Flow compiler.
      const textKeys=['flow_json','scenario_text','instructions','knowledge',
        'agent_goal','agent_guardrails'];
      const texts={};
      for(const key of textKeys){
        if(data.has(key)){
          const value=data.get(key);
          if(typeof value!=='string')throw Error('صيغة حقل إعدادات المساعد غير مدعومة');
          texts[key]=value;
          data.delete(key);
        }
      }
      const textBytes=new TextEncoder().encode(JSON.stringify(texts));
      let binary='';
      for(let offset=0;offset<textBytes.length;offset+=8192)
        binary+=String.fromCharCode(...textBytes.subarray(offset,offset+8192));
      data.set('studio_fields_b64',btoa(binary).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,''));
      const encoded=new URLSearchParams(data);
      const response=await fetch(form.getAttribute('action')||location.pathname,{
        method:'POST',body:encoded,credentials:'same-origin',cache:'no-store',redirect:'error',
        headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest',
          'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}
      });
      // Never parse an empty/HTML response as JSON: a stale session, failed
      // CSRF check or server-side fatal may otherwise surface only the opaque
      // "Unexpected end of JSON input" error on the owner's phone.
      if(response.redirected||response.status===401||response.status===419){
        throw Error('انتهت جلسة الدخول أو صلاحية الطلب. افتح الصفحة من جديد، وسجّل الدخول إذا لزم، ثم أعد النشر.');
      }
      const contentType=(response.headers.get('Content-Type')||'').toLowerCase();
      const raw=await response.text();
      if(!raw.trim())throw Error('السيرفر رجّع ردًا فارغًا (HTTP '+response.status+'). لم يتم تأكيد الحفظ أو النشر؛ راجع سجل الإصدارات قبل تكرار الطلب.');
      if(!contentType.includes('application/json'))throw Error(
        response.status===404
          ?'مسار حفظ إعدادات المساعد رجّع صفحة 404 بدل JSON. لم يتأكد الحفظ أو النشر؛ راجع إعدادات الحماية والمسار على السيرفر.'
          :'السيرفر رجّع استجابة غير JSON (HTTP '+response.status+'). لم يتم تأكيد النشر؛ راجع حالة السيرفر.');
      let result;
      try{result=JSON.parse(raw)}catch{
        throw Error('السيرفر رجّع JSON غير مكتمل (HTTP '+response.status+'). لم يتم تأكيد حفظ الإعدادات.');
      }
      if(!response.ok||result?.ok!==true)throw Error(result?.error||'تعذر حفظ إعدادات الإيجنت (HTTP '+response.status+')');
      if(status)status.textContent=result.message||'تم الحفظ';
      form.dispatchEvent(new CustomEvent('ontrack:studio-saved',{
        detail:{message:result.message||'تم الحفظ',published:result.published===true}}));
      window.ontrackToast(result.message||'تم حفظ إعدادات الإيجنت');
    }catch(error){
      form.dispatchEvent(new CustomEvent('ontrack:studio-failed',{
        detail:{message:error.message||'فشل حفظ الإعدادات'}}));
      if(status)status.textContent=error.message||'فشل حفظ الإعدادات';
      window.ontrackToast(error.message||'فشل حفظ إعدادات الإيجنت',{error:true});
    }finally{
      delete form.dataset.agentSaving;
      if(submit)submit.disabled=false;
    }
  });

})();
