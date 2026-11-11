'use strict';
const {spawn}=require('node:child_process');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const wait=ms=>new Promise(r=>setTimeout(r,ms));
const port=18773; // PHP app port, Chromium debugger is OS-assigned per run
const chromePath=process.env.CHROME_BIN;
if(!chromePath||!fs.existsSync(chromePath))throw Error('CHROME_NOT_FOUND');
const tmp=fs.mkdtempSync(path.join(os.tmpdir(),'voice-ui-chrome-'));
const chrome=spawn(chromePath,[
  '--headless=new','--no-first-run','--no-default-browser-check',
  '--no-sandbox','--disable-dev-shm-usage','--disable-extensions','--disable-gpu',
  '--remote-debugging-address=127.0.0.1','--remote-debugging-port=0',
  '--user-data-dir='+tmp,'about:blank'
],{stdio:'ignore',windowsHide:true});
let ws,uid=0;const pending=new Map();
function cdp(method,params={}){
  const id=++uid;
  return new Promise((resolve,reject)=>{
    const t=setTimeout(()=>{pending.delete(id);reject(Error('CDP timeout '+method))},15000);
    pending.set(id,{resolve,reject,t});
    ws.send(JSON.stringify({id,method,params}));
  });
}
async function evaluate(expression){
  const r=await cdp('Runtime.evaluate',{expression,returnByValue:true});
  if(r.result?.exceptionDetails)throw Error('JS: '+r.result.exceptionDetails.text);
  return r.result?.result?.value;
}
async function load(url){
  await cdp('Page.navigate',{url:'http://127.0.0.1:'+port+url});
  let previous='';
  for(let n=0;n<100;n++){
    await wait(75);
    try{
      const state=await evaluate('(function(){return {route:location.pathname+location.search,ready:document.readyState}})()');
      previous=state.route;
      // CDP Page.navigate returns before Chromium swaps the old document;
      // readyState=complete alone was producing false audit results.
      if(state.route===url && state.ready==='complete'){await wait(85);return}
    }catch{}
  }
  throw Error('load timeout requested='+url+' browser='+previous);
}
async function width(w){
  await cdp('Emulation.setDeviceMetricsOverride',{width:w,height:850,deviceScaleFactor:1,mobile:false});
  await wait(50);
}
function readLayout(){
  const ww=window.innerWidth,body=document.body,doc=document.documentElement;
  const check=e=>{if(!e)return false;const r=e.getBoundingClientRect(),c=getComputedStyle(e);
    return r.width>2&&r.height>2&&c.display!=='none'&&c.visibility!=='hidden'};
  const metrics=selector=>{const e=document.querySelector(selector);if(!e)return null;const r=e.getBoundingClientRect();
    return {x:Math.round(r.x),right:Math.round(r.right),w:Math.round(r.width),visible:check(e)}};
  const internal=e=>{let p=e.parentElement;while(p){
    const c=getComputedStyle(p);
    if(['scroll','auto','hidden','clip'].includes(c.overflowX))return true;
    if(p.matches('.graph-world,.graph-viewport,.table-wrap,.site-picker,.graph-tools,.agent-builder-tabs,.nav-list,.sidebar'))return true;
    p=p.parentElement;
  }return false;};
  const escaping=[...body.querySelectorAll('*')].filter(check).filter(e=>{
    const r=e.getBoundingClientRect();
    return (r.right>ww+6||r.left< -6) && !internal(e);
  }).slice(0,7).map(e=>({tag:e.tagName,cls:String(e.className).slice(0,55),x:Math.round(e.getBoundingClientRect().x),r:Math.round(e.getBoundingClientRect().right)}));
  const fields=[...body.querySelectorAll('input,select,textarea,button')].filter(check)
    .filter(e=>{const r=e.getBoundingClientRect();return (r.left < -6||r.right>ww+6)&&!internal(e)})
    .slice(0,5).map(e=>({name:e.name||e.id||e.tagName,w:Math.round(e.getBoundingClientRect().width)}));
  return {url:location.pathname+location.search,inner:ww,scroll:doc.scrollWidth,
    bodyScroll:body.scrollWidth,bodyOverflowX:getComputedStyle(body).overflowX,esc:escaping,fields,
    main:metrics('.content'),sidebar:metrics('.sidebar'),toggle:metrics('#navToggle'),
    card:metrics('.card'),login:metrics('.login-panel'),graph:metrics('.graph-shell'),table:metrics('.table-wrap')};
}
const expr='('+readLayout.toString()+')()';
const widths=[320,360,390,430,600,768,810,1024,1440];
const compact=[320,390,768,1440];
const publicPages=['/login','/register','/register?type=reseller','/forgot-password',
  '/portal/delta-voice/login','/portal/delta-voice/register','/portal/delta-voice/forgot-password',
  '/portal/nile-voice/login','/portal/nile-voice/register'];
const roles=[
  {email:'admin@ontrackegy.com',prefix:'/saas/console',tabs:['overview','accounts','plans','billing','staff','voices','integrations','keys','whatsapp_admin','releases','settings']},
  {email:'reseller@delta.test',prefix:'/portal/delta-voice',tabs:['overview','accounts','plans','billing','staff','voices','domains','integrations','keys','releases','settings']},
  {email:'user@direct.test',prefix:'/saas/console',tabs:['overview','sites','agents','flows','answering','whatsapp','campaigns','calls','contacts','devices','voices','staff','keys','integrations','billing','releases','settings']}
];
const failures=[];let measured=0;
function assertPage(label,w,m){
  measured++;
  if(measured%40===0)console.log('RESPONSIVE_AUDIT_PROGRESS '+measured);
  // The public login intentionally clips decorative background orbs with
  // body {overflow-x:hidden}; body.scrollWidth alone is not a scroll defect.
  const dx=Math.max(m.scroll,m.bodyOverflowX==='visible'?m.bodyScroll:0)-w;
  if(dx>4||m.fields.length || (m.main&&(!m.main.visible||m.main.w<60)))
    failures.push({label,w,document_overflow:dx,fields:m.fields,esc:m.esc,main:m.main});
}
async function signIn(email,prefix){
  await load(prefix+'/login');
  await evaluate('(function(){const f=document.querySelector("form");if(!f)throw Error("no login form");'+
    'f.querySelector("[name=email]").value='+JSON.stringify(email)+';'+
    'f.querySelector("[name=password]").value="ResponsiveUITest2026";f.requestSubmit();})()');
  await wait(460);
  for(let i=0;i<20;i++){
    if(await evaluate('!!document.querySelector(".app-shell")'))return;
    await wait(100);
  }
  throw Error('login did not reach dashboard: '+email+' at '+(await evaluate('location.href')));
}
async function main(){
  // Fixed debug port caused intermittent "fetch failed" when Chrome failed to
  // bind or a previous test was still closing. Chrome writes its OS-assigned
  // port into the private profile; never proceed after a failed startup.
  const activeFile=path.join(tmp,'DevToolsActivePort');
  let debugPort=0;
  for(let i=0;i<160;i++){
    if(chrome.exitCode!==null)
      throw Error('CHROME_HEADLESS_EXITED_BEFORE_CDP status='+chrome.exitCode);
    if(fs.existsSync(activeFile)){
      const candidate=Number(fs.readFileSync(activeFile,'utf8').split(/\r?\n/)[0]);
      if(Number.isSafeInteger(candidate)&&candidate>1024&&candidate<65536){
        try{
          const r=await fetch('http://127.0.0.1:'+candidate+'/json/version');
          if(r.ok){debugPort=candidate;break}
        }catch{}
      }
    }
    await wait(120);
  }
  if(!debugPort)throw Error('CHROME_REMOTE_DEBUGGER_UNAVAILABLE after health gate');
  const endpoint='http://127.0.0.1:'+debugPort;
  const target=await fetch(endpoint+'/json/new?about:blank',{method:'PUT'}).then(r=>{
    if(!r.ok)throw Error('CHROME_TARGET_CREATE_HTTP_'+r.status);
    return r.json();
  });
  ws=new WebSocket(target.webSocketDebuggerUrl);
  await new Promise((resolve,reject)=>{ws.addEventListener('open',resolve,{once:true});ws.addEventListener('error',reject,{once:true})});
  ws.addEventListener('message',e=>{
    let m;try{m=JSON.parse(e.data)}catch{return}
    if(!m.id||!pending.has(m.id))return;
    const p=pending.get(m.id);pending.delete(m.id);clearTimeout(p.t);
    if(m.error)p.reject(Error(m.error.message));else p.resolve(m);
  });
  await cdp('Runtime.enable');await cdp('Page.enable');
  // Navigate once per page, then use real browser viewport resizing for all breakpoints.
  for(const page of publicPages){
    await width(1440);
    await load(page);
    for(const w of widths){
      await width(w);
      assertPage(page,w,await evaluate(expr));
    }
  }
  for(const role of roles){
    await cdp('Network.clearBrowserCookies');
    await width(1440);
    await signIn(role.email,role.prefix);
    // Real interaction: theme icon must change the interface and survive reload.
    const initialTheme=await evaluate('document.body.classList.contains("light")');
    await evaluate('document.getElementById("themeToggle").click()');
    const toggledTheme=await evaluate('document.body.classList.contains("light")');
    await load(role.prefix+'/?tab=overview');
    const restoredTheme=await evaluate('document.body.classList.contains("light")');
    if(toggledTheme===initialTheme||restoredTheme!==toggledTheme)
      failures.push({label:role.email,error:'theme icon toggle or saved appearance failed'});
    await evaluate('document.getElementById("themeToggle").click()');
    for(const tab of role.tabs){
      const route=role.prefix+'/?tab='+tab;
      await width(1440);
      await load(route);
      // Earlier audits measured a valid overview layout even when a requested
      // tab was missing from nav_entries() and silently fell back to overview.
      // Assert the ACTUAL route and screen, not just the viewport dimensions.
      const reached=await evaluate('(function(){const tab='+JSON.stringify(tab)+';const selected=document.querySelector(".nav-link.selected");const main=document.querySelector("main.content");return {selected:!!selected&&new URL(selected.href,location.href).searchParams.get("tab")===tab,serverError:!!main&&main.textContent.includes("تعذر عرض البيانات"),heading:document.title}})()');
      if(!reached.selected||reached.serverError)
        failures.push({label:role.email+' '+tab,error:'requested feature silently hidden or rendering failed',reached});
      if(role.email==='admin@ontrackegy.com'&&tab==='billing'){
        const ownerPlan=await evaluate('document.querySelector("main.content")?.innerText.includes("حساب مالك المنصة")');
        if(!ownerPlan)failures.push({label:role.email,error:'platform owner billing misidentified as a customer plan'});
      }
      for(const w of compact){
        await width(w);
        const m=await evaluate(expr);
        if(!m.main)failures.push({label:role.email+' '+tab,w,error:'missing content'});
        assertPage(role.email+' '+tab,w,m);
      }
    }
    await width(390);await load(role.prefix+'/?tab=overview');
    await evaluate('document.getElementById("navToggle")?.click()');
    // Drawer animates for 200ms; measure final position rather than first frame.
    await wait(280);
    const drawer=await evaluate('(function(){const t=document.getElementById("navToggle");if(!t)return {error:"missing toggle"};const s=document.querySelector(".sidebar").getBoundingClientRect();return {open:document.body.classList.contains("nav-open"),inside:s.left>=-2&&s.right<=window.innerWidth+2,rect:[s.left,s.right]}})()');
    if(!drawer.open||!drawer.inside)failures.push({label:role.email,w:390,error:'mobile drawer invalid',drawer});
    await evaluate('document.getElementById("navClose")?.click()');
    if(await evaluate('document.body.classList.contains("nav-open")'))failures.push({label:role.email,error:'drawer not closing'});
    if(role.email==='admin@ontrackegy.com'){
      // Exercise a real mobile navigation click using the AJAX path (not
      // just screenshot measurements), verifying the new sidebar selection.
      await load('/saas/console/?tab=overview');
      await evaluate('(function(){const l=[...document.querySelectorAll(".nav-link")].find(a=>a.getAttribute("href")?.endsWith("tab=settings"));'+
        'if(!l)throw Error("settings nav missing");l.closest("details").open=true;'+
        'document.getElementById("navToggle").click();l.click();})()');
      for(let attempt=0;attempt<35;attempt++){
        const ready=await evaluate('location.search.includes("tab=settings") && !!document.querySelector(".nav-link.selected")?.getAttribute("href")?.endsWith("tab=settings")');
        if(ready)break;
        await wait(100);
      }
      const state=await evaluate('(function(){return {url:location.search,'+
        'selected:!!document.querySelector(".nav-link.selected")?.getAttribute("href")?.endsWith("tab=settings"),'+
        'drawerOpen:document.body.classList.contains("nav-open"),'+
        'visible:!!document.querySelector("main.content")}})()');
      if(!state.selected||!state.url.includes('tab=settings')||state.drawerOpen||!state.visible)
        failures.push({label:role.email,error:'AJAX sidebar navigation state invalid',state});
    }
  }
  console.log('SAAS_RESPONSIVE_AUDIT '+JSON.stringify({measured,failures:failures.slice(0,55),count:failures.length}));
  if(failures.length)process.exitCode=2;else console.log('SAAS_RESPONSIVE_BROWSER_PASS');
}
main().catch(e=>{console.error('RESPONSIVE_BROWSER_ERROR '+(e.stack||e));process.exitCode=3}).finally(()=>{
  if(ws)try{ws.close()}catch{}
  for(const p of pending.values())clearTimeout(p.t);
  try{chrome.kill()}catch{}
  try{fs.rmSync(tmp,{recursive:true,force:true})}catch{}
});
