(()=>{
'use strict';
const TYPES={start:'بداية',say:'رسالة',listen:'استماع',ask:'سؤال',instruction:'تعليمات AI / معرفة',condition:'شرط',knowledge_lookup:'بحث في المعرفة',collect_field:'جمع بيانات',crm_lookup:'بحث CRM',crm_write:'تحديث CRM',schedule:'حجز موعد',handoff:'تحويل بشري',end:'إنهاء'};
const NS='http://www.w3.org/2000/svg';
const make=(tag,cls,value)=>{const e=document.createElement(tag);if(cls)e.className=cls;if(value!==undefined)e.textContent=value;return e};
const S=(tag,attrs)=>{const e=document.createElementNS(NS,tag);Object.entries(attrs||{}).forEach(([k,v])=>e.setAttribute(k,String(v)));return e};
document.querySelectorAll('[data-flow-editor]').forEach(root=>{
 const source=root.querySelector('[name=flow_json]');if(!source)return;
 let data;try{data=JSON.parse(source.value)}catch{data={nodes:[],edges:[]}};
 if(!Array.isArray(data.nodes))data.nodes=[];if(!Array.isArray(data.edges))data.edges=[];
 const uuid=()=>('n'+Math.random().toString(16).slice(2,11));
 const seen=new Set();
 data.nodes.forEach((n,i)=>{
   if(!n.id||seen.has(n.id))n.id=uuid();seen.add(n.id);
   if(!TYPES[n.type])n.type='say';if(typeof n.text!=='string')n.text='';
   if(!Number.isFinite(+n.x))n.x=750;if(!Number.isFinite(+n.y))n.y=90+i*180;
 });
 let selected=data.nodes[0]?.id||null,connecting=false,scale=1,x=-420,y=22,pan=null,drag=null;
 const shell=make('div','graph-shell'),toolbar=make('div','graph-toolbar');
 toolbar.append(make('div','graph-heading','Flow Builder · تصميم مسار المحادثة'));
 const tools=make('div','graph-tools'),type=make('select','graph-type');
 Object.entries(TYPES).forEach(([k,v])=>type.add(new Option(v,k)));type.value='ask';
 const button=(txt,action,klass)=>{const b=make('button','graph-btn '+(klass||''),txt);b.type='button';b.addEventListener('click',action);return b};
 const add=button('+ خطوة',()=>addNode(),'primary'),connect=button('ربط',()=>{connecting=!connecting;connect.classList.toggle('selected-tool',connecting);notice.textContent=connecting?'اختار العقدة التي تريد الربط بها':'اسحب العقد أو اضغط لتعديلها'}),auto=button('ترتيب',()=>arrange());
 const zoomIn=button('+',()=>{scale=Math.min(1.5,scale+.1);transform()}),zoomOut=button('−',()=>{scale=Math.max(.4,scale-.1);transform()});
 const raw=button('{}',()=>{source.hidden=!source.hidden;raw.classList.toggle('selected-tool',!source.hidden)});
 tools.append(type,add,connect,auto,zoomIn,zoomOut,raw);toolbar.append(tools);shell.append(toolbar);
  // Templates only replace the local editable graph; publishing remains a deliberate form submit.
  let templates=[];
  try{templates=JSON.parse(document.getElementById('ontrack-flow-templates')?.textContent||'[]')}catch{}
  const templateSelect=make('select','graph-type');
  templateSelect.setAttribute('aria-label','مكتبة السيناريوهات');
  templateSelect.append(new Option('اختار سيناريو للتعديل',''));
  templates.forEach(t=>templateSelect.append(new Option(t.title,t.id)));
  const applyTemplate=button('استخدام السيناريو',()=>{
    const candidate=templates.find(t=>t.id===templateSelect.value);
    if(!candidate)return;
    if(!confirm('استبدال التصميم الحالي بنسخة من السيناريو المختار؟'))return;
    data=JSON.parse(JSON.stringify(candidate.graph));selected=data.nodes[0]?.id||null;
    arrange();notice.textContent='تم تحميل '+candidate.title+'؛ عدله أو اختبره ثم احفظ مسودة أو انشر مباشرة.';
  },'primary');
  tools.prepend(templateSelect,applyTemplate);
 const layout=make('div','graph-layout'),viewport=make('div','graph-viewport'),world=make('div','graph-world'),lines=S('svg',{class:'graph-edges',width:2500,height:3000,viewBox:'0 0 2500 3000'}),cards=make('div','graph-nodes'),inspector=make('aside','graph-inspector');
 world.append(lines,cards);viewport.append(world);layout.append(viewport,inspector);shell.append(layout);
 const footer=make('div','graph-footer'),counter=make('span','',''),notice=make('span','','اسحب العقد أو اضغط لتعديلها');
 footer.append(counter,notice);shell.append(footer);
 // Real graph traversal with deterministic text input; no external AI/CRM/voice actions.
 const previewButton=button('▶ اختبار Flow نصيًا',()=>openPreview(),'primary');
 tools.append(previewButton);
 const preview=make('section','flow-preview'),previewTitle=make('strong','','اختبار السيناريو — محاكاة نصية فقط');
 const previewClose=button('إغلاق',()=>{preview.hidden=true});
 const previewHeader=make('div','flow-preview-header');
 previewHeader.append(previewTitle,previewClose);
 const previewHelp=make('p','','المحاكاة تختبر العقد والشروط فقط، ولا تتصل بالذكاء الاصطناعي أو الهاتف أو CRM ولا تحفظ بيانات عميل.');
 const previewLog=make('div','flow-preview-log');
 previewLog.setAttribute('role','log');previewLog.setAttribute('aria-live','polite');
 const previewForm=make('div','flow-preview-form'),previewInput=make('input','flow-preview-input');
 previewInput.type='text';previewInput.maxLength=500;previewInput.placeholder='اكتب رد العميل هنا';
 previewInput.setAttribute('aria-label','رد العميل التجريبي');
 const previewSend=button('إرسال',()=>sendPreview(),'primary');
 previewForm.append(previewInput,previewSend);
 preview.append(previewHeader,previewHelp,previewLog,previewForm);
 preview.hidden=true;previewInput.disabled=true;previewSend.disabled=true;
 const previewStyle=make('style');
 previewStyle.textContent='.flow-preview[hidden]{display:none!important}.flow-preview{direction:rtl;margin:18px 0;padding:18px;border:1px solid #57515a;border-radius:16px;background:var(--card,#1d1b20);color:var(--text,#f6f4f6)}.flow-preview-header{display:flex;align-items:center;justify-content:space-between;gap:12px}.flow-preview p{font-size:13px;opacity:.85}.flow-preview-log{display:flex;flex-direction:column;gap:8px;max-height:300px;overflow-y:auto;margin:14px 0;padding:12px;border:1px solid #57515a;border-radius:12px;min-height:75px}.flow-preview-line{padding:9px 12px;border-radius:12px;max-width:85%;white-space:pre-wrap;overflow-wrap:anywhere;font-size:14px;background:#3b343d;color:#fff}.flow-preview-line.user{align-self:flex-start;background:#a81f35}.flow-preview-line.notice{align-self:center;background:#584d35}.flow-preview-form{display:flex;gap:8px}.flow-preview-input{flex:1;min-width:0;border-radius:9px;padding:10px;background:var(--card,#242127);color:inherit;border:1px solid #777}.flow-preview-input:disabled{opacity:.6}';
 shell.append(previewStyle,preview);
 let previewId=null,previewBusy=false;
 const previewLine=(kind,message)=>{
   const line=make('div','flow-preview-line '+kind,String(message));
   previewLog.append(line);previewLog.scrollTop=previewLog.scrollHeight;
 };
 async function previewRequest(operation,reply){
   if(previewBusy)return;
   const form=source.closest('form');
   const csrf=form?.querySelector('[name="csrf"]')?.value;
   const agentId=form?.querySelector('[name="agent_id"]')?.value;
   if(!csrf||!agentId){previewLine('notice','لا توجد جلسة تعديل إيجنت صالحة.');return}
   previewBusy=true;previewSend.disabled=true;previewInput.disabled=true;
   try{
     store();
     const params=new URLSearchParams({csrf,agent_id:agentId,flow_json:source.value,operation});
     if(operation==='reply'){params.set('preview_id',previewId||'');params.set('reply',reply||'')}
     const response=await fetch('/saas/console/flow-preview.php',{
       method:'POST',credentials:'same-origin',cache:'no-store',redirect:'error',
       headers:{'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
       body:params.toString()
     });
     const result=await response.json();
     if(!response.ok||result.ok!==true)throw Error('تعذر الاختبار ('+(result.error||response.status)+')');
     previewId=result.preview_id;
     (result.events||[]).forEach(event=>previewLine(event.kind==='agent'?'agent':'notice',event.text||''));
     if(result.done)previewLine('notice','انتهى هذا المسار التجريبي.');
     previewInput.disabled=!result.waiting;previewSend.disabled=!result.waiting;
     if(result.waiting)previewInput.focus();
   }catch(err){
     previewLine('notice',err?.message||'تعذر الاتصال بخدمة المحاكاة');
     previewInput.disabled=false;previewSend.disabled=false;
   }finally{previewBusy=false}
 }
 function openPreview(){
   preview.hidden=false;previewId=null;previewLog.replaceChildren();
   previewInput.value='';previewInput.disabled=true;previewSend.disabled=true;
   previewRequest('start',null);
 }
 function sendPreview(){
   const value=previewInput.value.trim();
   if(!value||previewBusy||!previewId)return;
   previewLine('user',value);previewInput.value='';
   previewRequest('reply',value);
 }
 previewInput.addEventListener('keydown',event=>{
   if(event.key==='Enter'){event.preventDefault();sendPreview()}
 });
 root.replaceChildren(shell,source);source.hidden=true;
 const store=()=>{source.value=JSON.stringify(data);counter.textContent=data.nodes.length+' خطوات · '+data.edges.length+' روابط'};
 const focus=()=>data.nodes.find(n=>n.id===selected)||null;
 const transform=()=>{world.style.transform='translate('+x+'px,'+y+'px) scale('+scale+')'};
 function edges(){
   lines.replaceChildren();
   data.edges.forEach(edge=>{
     const a=data.nodes.find(n=>n.id===edge.from),b=data.nodes.find(n=>n.id===edge.to);if(!a||!b)return;
     const x1=a.x+110,y1=a.y+112,x2=b.x+110,y2=b.y,m=Math.max(45,Math.abs(y2-y1)*.45);
     lines.append(S('path',{d:'M'+x1+' '+y1+' C'+x1+' '+(y1+m)+','+x2+' '+(y2-m)+','+x2+' '+y2,class:'graph-connector'}));
     lines.append(S('path',{d:'M'+(x2-5)+' '+(y2-8)+' L'+x2+' '+y2+' L'+(x2+5)+' '+(y2-8),class:'graph-arrow'}));
     if(edge.label){
       const mx=(x1+x2)/2,my=(y1+y2)/2,v=String(edge.label).slice(0,55),w=Math.min(220,v.length*8+28),g=S('g',{class:'graph-edge-pill'});
       g.append(S('rect',{x:mx-w/2,y:my-14,width:w,height:28,rx:14}));
       const t=S('text',{x:mx,y:my+4,'text-anchor':'middle'});t.textContent=v;g.append(t);lines.append(g);
     }
   });
 }
 function nodes(){
   cards.replaceChildren();
   data.nodes.forEach(n=>{
     const box=make('button','graph-node'+(n.id===selected?' selected':''));box.type='button';box.style.left=n.x+'px';box.style.top=n.y+'px';
     box.append(make('strong','',TYPES[n.type]),make('p','',n.text||'اكتب نص الخطوة'));
     box.append(make('small','','● '+String(n.id).slice(-6)));
     box.addEventListener('pointerdown',evt=>{
       if(evt.button!==0)return;evt.preventDefault();
       if(connecting){
         const from=selected;
         if(from&&from!==n.id&&!data.edges.some(e=>e.from===from&&e.to===n.id))data.edges.push({from,to:n.id,label:''});
         connecting=false;connect.classList.remove('selected-tool');store();
       }
       selected=n.id;drag={id:n.id,x:evt.clientX,y:evt.clientY,ox:n.x,oy:n.y};
       box.setPointerCapture(evt.pointerId);inspect();edges();
     });
     box.addEventListener('pointermove',evt=>{
       if(!drag||drag.id!==n.id)return;
       n.x=Math.max(0,Math.min(2250,drag.ox+(evt.clientX-drag.x)/scale));
       n.y=Math.max(0,Math.min(2750,drag.oy+(evt.clientY-drag.y)/scale));
       box.style.left=n.x+'px';box.style.top=n.y+'px';edges();
     });
     box.addEventListener('pointerup',()=>{drag=null;store();nodes();edges()});
     cards.append(box);
   });
 }
 function field(label,input){const wrap=make('label','graph-field');wrap.append(make('span','',label),input);inspector.append(wrap);return input}
 function inspect(){
   inspector.replaceChildren(make('h3','','خصائص الخطوة'));
   const n=focus();if(!n){inspector.append(make('p','','اختار خطوة من اللوحة'));return}
   const sel=make('select');Object.entries(TYPES).forEach(([k,v])=>sel.add(new Option(v,k)));sel.value=n.type;
   field('نوع العقدة',sel).addEventListener('change',()=>{n.type=sel.value;store();nodes()});
   const area=make('textarea');area.rows=5;area.maxLength=900;area.value=n.text;
   field('رسالة أو سؤال الإيجنت',area).addEventListener('input',()=>{n.text=area.value;store();nodes()});
   inspector.append(make('h4','','مسارات الانتقال'));
   data.edges.filter(e=>e.from===n.id).forEach(edge=>{
     const row=make('div','graph-outgoing-row'),target=data.nodes.find(t=>t.id===edge.to);
     row.append(make('small','', '← '+(target?TYPES[target.type]:'غير معروف')));
     const txt=make('input');txt.placeholder='شرط الانتقال';txt.maxLength=70;txt.value=edge.label||'';
     txt.addEventListener('input',()=>{edge.label=txt.value;store();edges()});
     row.append(txt,button('×',()=>{data.edges=data.edges.filter(e=>e!==edge);render()},'danger'));
     inspector.append(row);
   });
   inspector.append(button('ربط بعقدة أخرى',()=>{connecting=true;connect.classList.add('selected-tool');notice.textContent='اختار العقدة المستهدفة'}));
   inspector.append(button('حذف الخطوة',()=>{
     if(!confirm('حذف الخطوة والروابط التابعة؟'))return;
     data.nodes=data.nodes.filter(t=>t.id!==n.id);data.edges=data.edges.filter(t=>t.from!==n.id&&t.to!==n.id);
     selected=data.nodes[0]?.id||null;render();
   },'danger'));
 }
 function render(){store();transform();edges();nodes();inspect()}
 function addNode(){
   if(data.nodes.length>=100)return alert('100 خطوة كحد أقصى');
   const a=focus(),n={id:uuid(),type:type.value,text:'',x:a?a.x+250:1050,y:a?a.y+230:100};
   data.nodes.push(n);if(a)data.edges.push({from:a.id,to:n.id,label:''});selected=n.id;render();
 }
 function arrange(){
   const processed=new Set(),levels=new Map(),queue=[];
   data.nodes.forEach(n=>{if(n.type==='start'||!data.edges.some(e=>e.to===n.id))queue.push([n.id,0])});
   while(queue.length){const [id,level]=queue.shift();if(processed.has(id))continue;processed.add(id);levels.set(id,Math.min(12,level));
     data.edges.filter(e=>e.from===id).forEach(e=>queue.push([e.to,level+1]))}
   const grouped=new Map();
   data.nodes.forEach((n,i)=>{const level=levels.get(n.id)??i;if(!grouped.has(level))grouped.set(level,[]);grouped.get(level).push(n)});
   grouped.forEach((group,level)=>group.forEach((n,i)=>{n.x=1100+(i-(group.length-1)/2)*320;n.y=100+level*235}));
   scale=.85;x=-760;y=30;render();
 }
 viewport.addEventListener('pointerdown',evt=>{
   if(evt.target.closest('.graph-node'))return;pan={cx:evt.clientX,cy:evt.clientY,ox:x,oy:y};viewport.setPointerCapture(evt.pointerId)
 });
 viewport.addEventListener('pointermove',evt=>{if(pan){x=pan.ox+evt.clientX-pan.cx;y=pan.oy+evt.clientY-pan.cy;transform()}});
 viewport.addEventListener('pointerup',()=>pan=null);
 viewport.addEventListener('wheel',evt=>{if(!evt.ctrlKey)return;evt.preventDefault();scale=Math.max(.4,Math.min(1.5,scale+(evt.deltaY<0?.08:-.08)));transform()},{passive:false});
 source.addEventListener('change',()=>{if(source.hidden)return;try{const value=JSON.parse(source.value);if(!Array.isArray(value.nodes)||!Array.isArray(value.edges))throw Error();data=value;render()}catch{alert('JSON غير صالح')}});
 if(!data.nodes.length){data.nodes=[{id:'start',type:'start',text:'أهلاً بك',x:1000,y:100},{id:'end',type:'end',text:'شكراً لتواصلك',x:1000,y:350}];data.edges=[{from:'start',to:'end',label:''}]}
 arrange();root.closest('form')?.addEventListener('submit',store);
});
})();