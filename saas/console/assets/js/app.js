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
}
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
    event.preventDefault();event.stopPropagation();
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

})();
