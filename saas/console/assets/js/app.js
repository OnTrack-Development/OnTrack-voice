(()=>{
  'use strict';
  const body=document.body;
  const themeBtn=document.getElementById('themeToggle');
  const theme=()=>{
    try{return localStorage.getItem('ontrack-console-theme')==='light'?'light':'dark'}
    catch{return 'dark'}
  };
  const setTheme=(mode)=>{
    body.classList.toggle('light',mode==='light');
    if(themeBtn){themeBtn.textContent=mode==='light'?'☾':'☀';themeBtn.setAttribute('aria-label',mode==='light'?'تفعيل المظهر الداكن':'تفعيل المظهر الفاتح')}
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content',mode==='light'?'#f6f6f8':'#0c0d11');
  };
  setTheme(theme());
  themeBtn?.addEventListener('click',()=>{
    const next=body.classList.contains('light')?'dark':'light';
    setTheme(next);try{localStorage.setItem('ontrack-console-theme',next)}catch{}
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
  nav?.querySelectorAll('.nav-link').forEach(link=>link.addEventListener('click',()=>closeNav()));
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
  // Special auth/payment/account-switch endpoints deliberately keep native flows.
  const excludes=new Set(['logout','login','register','switch','support_enter',
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
    const selected=parsed.querySelector('.nav-link.selected')?.getAttribute('href');
    document.querySelectorAll('.nav-link').forEach(link=>{
      const active=link.getAttribute('href')===selected;
      link.classList.toggle('selected',active);
      if(active)link.setAttribute('aria-current','page');
      else link.removeAttribute('aria-current');
    });
    const nextUrl=new URL(requestedUrl,location.href);
    if(replace)history.replaceState({otAjax:true},'',nextUrl.pathname+nextUrl.search+nextUrl.hash);
    else history.pushState({otAjax:true},'',nextUrl.pathname+nextUrl.search+nextUrl.hash);
    window.ontrackMountFlowEditors?.(main);
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
    const endpoint=new URL(form.action||location.href,location.href);
    if(!allowedUrl(endpoint))return;
    event.preventDefault();
    event.stopPropagation();
    if(activeRequest)return;
    if(form.method.toLowerCase()==='get'){
      const url=new URL(form.action||location.href,location.href);
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
      const response=await fetch(form.action||location.pathname,{
        method:'POST',body:data,credentials:'same-origin',cache:'no-store',redirect:'error',
        headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
      });
      const result=await response.json();
      if(!response.ok||result.ok!==true)throw Error(result.error||'تعذر حفظ إعدادات الإيجنت');
      if(status)status.textContent=result.message||'تم الحفظ';
      window.ontrackToast(result.message||'تم حفظ إعدادات الإيجنت');
    }catch(error){
      if(status)status.textContent=error.message||'فشل حفظ الإعدادات';
      window.ontrackToast(error.message||'فشل حفظ إعدادات الإيجنت',{error:true});
    }finally{
      delete form.dataset.agentSaving;
      if(submit)submit.disabled=false;
    }
  });

})();
