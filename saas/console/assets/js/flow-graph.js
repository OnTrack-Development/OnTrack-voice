(()=>{
'use strict';
const TYPES={start:'بداية',say:'رسالة',listen:'استماع',ask:'سؤال',condition:'شرط',instruction:'تعليمات AI / معرفة',knowledge_lookup:'بحث في المعرفة',collect_field:'جمع بيانات',crm_lookup:'بحث CRM',crm_write:'تحديث CRM',schedule:'حجز موعد',handoff:'تحويل بشري',end:'إنهاء'};
const NS='http://www.w3.org/2000/svg';
const make=(tag,cls,value)=>{const e=document.createElement(tag);if(cls)e.className=cls;if(value!==undefined)e.textContent=value;return e};
const S=(tag,attrs)=>{const e=document.createElementNS(NS,tag);Object.entries(attrs||{}).forEach(([k,v])=>e.setAttribute(k,String(v)));return e};
const mountFlowEditors=(scope=document)=>scope.querySelectorAll('[data-flow-editor]').forEach(root=>{
 if(root.dataset.otFlowMounted==='1')return;
 root.dataset.otFlowMounted='1';
 const source=root.querySelector('[name=flow_json]');if(!source)return;
 const routing=window.OnTrackGraphRouting;
 if(!routing){root.insertAdjacentText('afterbegin','تعذر تشغيل محرك رسم الوصلات. حدّث الصفحة.');return;}
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
 let hovered=null,edgeFocus=data.edges.length>=12,sceneWidth=3100,sceneHeight=3300;
 const shell=make('div','graph-shell'),toolbar=make('div','graph-toolbar');
 toolbar.append(make('div','graph-heading','Flow Builder · تصميم مسار المحادثة'));
 const tools=make('div','graph-tools'),type=make('select','graph-type');
 Object.entries(TYPES).forEach(([k,v])=>type.add(new Option(v,k)));type.value='ask';
 const button=(txt,action,klass)=>{const b=make('button','graph-btn '+(klass||''),txt);b.type='button';b.addEventListener('click',action);return b};
 const fullscreen=button('⛶ ملء الشاشة',()=>toggleFullscreen());
 const add=button('+ خطوة',()=>addNode(),'primary'),connect=button('ربط',()=>{connecting=!connecting;connect.classList.toggle('selected-tool',connecting);notice.textContent=connecting?'اختار العقدة التي تريد الربط بها':'اسحب العقد أو اضغط لتعديلها'}),auto=button('ترتيب',()=>arrange());
 const zoomIn=button('+',()=>{scale=Math.min(1.6,scale+.1);transform()}),zoomOut=button('−',()=>{scale=Math.max(.25,scale-.1);transform()});
 const fit=button('⊡ احتواء',()=>fitGraph());
 const edgeToggle=button('◉ تركيز',()=>{
   edgeFocus=!edgeFocus;
   edgeToggle.textContent=edgeFocus?'◉ تركيز':'◎ كل الوصلات';
   edgeToggle.setAttribute('aria-pressed',String(edgeFocus));
   edges();refreshNodeFocus();
 });
 edgeToggle.setAttribute('aria-pressed',String(edgeFocus));
 edgeToggle.title='تركيز على وصلات العقدة المختارة أو إظهار كل الروابط';
 auto.textContent='↔ ترتيب تلقائي';
 const raw=button('{}',()=>{source.hidden=!source.hidden;raw.classList.toggle('selected-tool',!source.hidden)});
 tools.append(type,add,connect,auto,fit,edgeToggle,zoomIn,zoomOut,raw,fullscreen);toolbar.append(tools);shell.append(toolbar);
 async function toggleFullscreen(){
   try {
     if(document.fullscreenElement===shell){await document.exitFullscreen()}
     else if(!document.fullscreenElement&&shell.requestFullscreen){await shell.requestFullscreen()}
     else shell.classList.toggle('graph-maximized');
   }catch{shell.classList.toggle('graph-maximized')}
   syncFullscreen();
 }
 function syncFullscreen(){
   const on=document.fullscreenElement===shell||shell.classList.contains('graph-maximized');
   fullscreen.textContent=on?'⤡ تصغير':'⛶ ملء الشاشة';
   document.body.classList.toggle('graph-editor-maximized',on);
 }
 document.addEventListener('fullscreenchange',syncFullscreen);
  // Templates only replace the local editable graph; publishing remains a deliberate form submit.
  let templates=[];
  try{templates=JSON.parse(document.getElementById('ontrack-flow-templates')?.textContent||'[]')}catch{}
  const templateSelect=make('select','graph-type');
  templateSelect.setAttribute('aria-label','مكتبة السيناريوهات');
  templateSelect.append(new Option('اختار سيناريو للتعديل',''));
  templates.forEach(t=>templateSelect.append(new Option(t.title,t.id)));
  const applyTemplate=button('استخدام السيناريو',async()=>{
    const candidate=templates.find(t=>t.id===templateSelect.value);
    if(!candidate)return;
    if(!await window.ontrackConfirm('استبدال التصميم الحالي بننسخة من السيناريو المختار؟',{title:'تغيير السيناريو',confirmText:'استخدام السيناريو'}))return;
    data=JSON.parse(JSON.stringify(candidate.graph));selected=data.nodes[0]?.id||null;hovered=null;
    arrange();notice.textContent='تم تحميل '+candidate.title+'؛ عدله أو اختبره ثم احفظ مسودة أو انشر مباشرة.';
  },'primary');
  tools.prepend(templateSelect,applyTemplate);
 const layout=make('div','graph-layout'),viewport=make('div','graph-viewport'),world=make('div','graph-world'),
   lines=S('svg',{class:'graph-edges',width:sceneWidth,height:sceneHeight,viewBox:'0 0 '+sceneWidth+' '+sceneHeight}),
   cards=make('div','graph-nodes'),inspector=make('aside','graph-inspector');
 const arrowId='ot-flow-arrow-'+Math.random().toString(36).slice(2,11);
 const defs=S('defs'),marker=S('marker',{id:arrowId,markerWidth:11,markerHeight:11,
   refX:9,refY:5,orient:'auto',markerUnits:'userSpaceOnUse'});
 marker.append(S('path',{d:'M 1 1 L 9 5 L 1 9 Z',class:'graph-arrow-tip'}));
 defs.append(marker);
 world.append(lines,cards);viewport.append(world);layout.append(viewport,inspector);shell.append(layout);
 const footer=make('div','graph-footer'),counter=make('span','',''),notice=make('span','','اسحب العقد أو اضغط لتعديلها');
 // AI is a draft advisor: suggestions never mutate the graph until an explicit user command.
 const aiPanel=make('aside','graph-ai-panel');
 aiPanel.id='flow-ai-drawer-'+Math.random().toString(36).slice(2,10);
 aiPanel.hidden=true;
 aiPanel.setAttribute('role','dialog');
 aiPanel.setAttribute('aria-label','شات مساعد AI للمخطط');
 const aiLauncher=button('✦',()=>toggleAi(true),'graph-ai-launcher');
 aiLauncher.setAttribute('aria-label','فتح شات مساعد AI للمخطط');
 aiLauncher.setAttribute('title','مساعد AI للمخطط');
 aiLauncher.setAttribute('aria-controls',aiPanel.id);
 aiLauncher.setAttribute('aria-expanded','false');
 const aiTitle=make('h3','','مساعد AI للمخطط');
 const aiClose=button('×',()=>toggleAi(false),'graph-ai-close');
 const aiReset=button('محادثة جديدة',()=>resetAiChat(),'graph-ai-reset');
 aiReset.setAttribute('title','امسح ذاكرة هذا الإيجنت فقط بعد التأكيد');
 aiClose.setAttribute('aria-label','إغلاق شات المساعد');
 const aiHead=make('div','graph-ai-head'),aiHeadActions=make('div','graph-ai-head-actions');
 const aiModel=make('small','graph-ai-model','Google Gemini');aiModel.setAttribute('title','الموديل الفعلي لمساعد تصميم المخطط');
 aiHeadActions.append(aiModel,aiReset,aiClose);aiHead.append(aiTitle,aiHeadActions);
 const aiDescription=make('p','','المساعد بيقرأ المخطط المفتوح، ويشرح مساراته، ويضيف عليه حسب طلبك من غير ما يستبدله إلا بموافقتك.');
 const aiThread=make('div','graph-ai-thread');
 aiThread.setAttribute('role','log');aiThread.setAttribute('aria-live','polite');
 const aiInput=make('textarea');aiInput.rows=3;aiInput.maxLength=2500;
 aiInput.placeholder='اكتب اللي عايز الإيجنت يعمله…';
 aiInput.setAttribute('aria-label','رسالة إلى مساعد المخطط');
 const aiMode=make('select','graph-ai-mode');
 aiMode.setAttribute('aria-label','المطلوب من مساعد البناء');
 [['both','المخطط + خصائص الإيجنت'],['graph','المخطط البصري فقط'],['profile','خصائص الإيجنت فقط']].forEach(([value,label])=>aiMode.append(new Option(label,value)));
 const aiSubmit=button('اقترح تطوير الإيجنت',()=>requestAiProposal(),'primary');
 const aiStatus=make('p','graph-ai-status','');
 aiStatus.setAttribute('role','status');
 const aiPreview=make('div','graph-ai-preview');
 const aiApply=button('نفّذ المقترح كمسودة',()=>applyAiProposal(),'primary');aiApply.hidden=true;
 const aiCancel=button('إلغاء المقترح',()=>discardAiProposal());aiCancel.hidden=true;
 const aiActions=make('div','graph-ai-actions');aiActions.append(aiSubmit,aiApply,aiCancel);
 const aiCompose=make('div','graph-ai-compose');aiCompose.append(aiMode,aiInput,aiActions,aiStatus);
 aiThread.append(make('div','graph-ai-message assistant','أهلاً! اشرحلي السيناريو، هجهزلك مخطط تراجعه قبل ما تضغط «نفّذ».'));
 aiThread.append(aiPreview);
 aiPanel.append(aiHead,aiDescription,aiThread,aiCompose);
 shell.append(aiLauncher,aiPanel);
 function toggleAi(open){
   aiPanel.hidden=!open;
   aiLauncher.setAttribute('aria-expanded',String(open));
   aiLauncher.setAttribute('aria-label',open?'شات المساعد مفتوح':'فتح شات مساعد AI للمخطط');
   if(open){aiInput.focus();aiThread.scrollTop=aiThread.scrollHeight}
   else aiLauncher.focus();
 }
 aiPanel.addEventListener('keydown',event=>{
   if(event.key==='Escape'){event.stopPropagation();toggleAi(false)}
 });
 function aiMessage(kind,message){
   aiThread.insertBefore(make('div','graph-ai-message '+kind,message),aiPreview);
   aiThread.scrollTop=aiThread.scrollHeight;
 }
 let proposal=null,profileProposal=null,busy=false,hasHistory=false;
 function profileSnapshot(){
   const form=root.closest('form');
   const string=name=>form?.querySelector('[name="'+name+'"]')?.value||'';
   const checked=name=>!!form?.querySelector('[name="'+name+'"]')?.checked;
   return {instructions:string('instructions'),goal:string('agent_goal'),
      guardrails:string('agent_guardrails'),knowledge:string('knowledge'),
      use_knowledge:checked('use_knowledge'),use_signed_facts:checked('use_signed_facts'),
      human_handoff:checked('human_handoff')};
 }
 function showProfile(p){
   if(!p||typeof p!=='object')throw Error('اقتراح إعدادات غير صالح');
   profileProposal=p;
   const section=make('div','graph-ai-profile-preview');
   section.append(make('strong','','خصائص الإيجنت المقترحة'));
   for(const [key,label] of [['goal','الهدف'],['instructions','الشخصية والتعليمات'],
       ['guardrails','ضوابط الإيجنت'],['knowledge_append','معرفة جديدة']]){
     if(typeof p[key]==='string'&&p[key].trim())
       section.append(make('p','',label+': '+p[key].slice(0,350)));
   }
   for(const [key,label] of [['use_knowledge','البحث في المعرفة'],
       ['use_signed_facts','مصادر التكامل'],['human_handoff','التصعيد البشري']]){
     if(p[key]&&p[key]!=='keep')section.append(make('p','',label+': '+(p[key]==='on'?'تفعيل':'تعطيل')));
   }
   if(Array.isArray(p.integration_suggestions)&&p.integration_suggestions.length)
     section.append(make('p','','تكاملات مقترحة (لا تُربط تلقائيًا): '+p.integration_suggestions.join('، ')));
   aiPreview.append(section);
   aiApply.hidden=false;aiCancel.hidden=false;
 }
 function applyProfileToForm(p){
   const form=root.closest('form');
   if(!form)return;
   for(const [name,key] of [['instructions','instructions'],['agent_goal','goal'],
       ['agent_guardrails','guardrails']]){
     if(typeof p[key]==='string'&&p[key].trim()){
       const field=form.querySelector('[name="'+name+'"]');
       if(field){field.value=p[key];field.dispatchEvent(new Event('input',{bubbles:true}))}
     }
   }
   if(typeof p.knowledge_append==='string'&&p.knowledge_append.trim()){
     const field=form.querySelector('[name="knowledge"]');
     if(field){
       const combined=[field.value.trim(),p.knowledge_append.trim()].filter(Boolean).join('\n\n');
       if(combined.length>20000)throw Error('المعرفة أكبر من الحد المسموح؛ راجع محتوى الملف');
       field.value=combined;
     }
   }
   for(const key of ['use_knowledge','use_signed_facts','human_handoff']){
     const field=form.querySelector('[name="'+key+'"]');
     if(field&&p[key]!=='keep')field.checked=p[key]==='on';
   }
 }
 function chatIdentity(){
   const form=root.closest('form');
   const csrf=form?.querySelector('[name=csrf]')?.value;
   const agentId=form?.querySelector('[name=agent_id]')?.value;
   return form&&csrf&&agentId?{form,csrf,agentId}:null;
 }
 async function chatRequest(action,fields={}){
   const id=chatIdentity();
   if(!id)throw Error('لم يتم تحديد الإيجنت أو جلسة التعديل');
   const params=new URLSearchParams({csrf:id.csrf,agent_id:id.agentId,action,...fields});
   const res=await fetch(id.form.action,{method:'POST',credentials:'same-origin',cache:'no-store',
     headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','Accept':'application/json'},
     body:params.toString()});
   const payload=await res.json();
   if(!res.ok||payload.ok!==true)throw Error(payload.error||'تعذر استرجاع ذاكرة المساعد');
   return payload;
 }
 function showProposal(g){
   if(!g||!Array.isArray(g.nodes)||!Array.isArray(g.edges)||g.nodes.length>32)
     throw Error('مخطط غير صالح من المساعد');
   proposal=g;
   const indegree=new Map(),outdegree=new Map();
   g.edges.forEach(e=>{indegree.set(e.to,(indegree.get(e.to)||0)+1);
     outdegree.set(e.from,(outdegree.get(e.from)||0)+1)});
   const forks=[...outdegree.values()].filter(n=>n>=2).length;
   const merges=[...indegree.values()].filter(n=>n>=2).length;
   const title=make('strong','','شبكة مقترحة: '+g.nodes.length+' عقدة · '+g.edges.length+
     ' رابط · '+forks+' تشعبات · '+merges+' نقاط التقاء');
   const list=make('ol','graph-ai-list');
   g.nodes.forEach(n=>{
     const item=make('li');
     item.append(make('b','',TYPES[n.type]||n.type),make('span','',String(n.text||'').slice(0,180)));
     list.append(item);
   });
   aiPreview.replaceChildren(title,list);
   aiApply.hidden=false;aiCancel.hidden=false;
   aiThread.scrollTop=aiThread.scrollHeight;
 }
 async function loadAiChat(){
   try{
     const payload=await chatRequest('flow_ai_history',{current_graph_json:JSON.stringify(data)});
     if(busy)return;
     aiThread.replaceChildren(aiPreview);
     (payload.messages||[]).forEach(msg=>{
       if(msg?.role==='user'||msg?.role==='assistant')aiMessage(msg.role,String(msg.text||''));
     });
     hasHistory=(payload.messages||[]).length>0||payload.current_graph_read===true;
     if(payload.model)aiModel.textContent=payload.model;
     // Explain exactly what exists in the open editor BEFORE any new AI request.
     // Initial reading requires no model call, and never overwrites a draft.
     aiMessage('assistant',String(payload.current_explanation||
       'المخطط الحالي مش متاح للقراءة؛ ممكن تراجع العقد وروابطها.'));
     if(!(payload.messages||[]).length)
       aiMessage('assistant','قرأت الفلو الموجود. قولّي تضيف أو تعدل إيه، وهشتغل عليه بدل ما أبدأ من الصفر.');
     if(payload.pending_graph){
       showProposal(payload.pending_graph);
       aiStatus.textContent='آخر اقتراح لسه معروض للمراجعة. تقدر تطلب تعديله أو تنفّذه كمسودة.';
     }
     if(payload.pending_profile)showProfile(payload.pending_profile);
   }catch(error){
     aiStatus.textContent=error.message||'تعذر تحميل المحادثة القديمة.';
   }
 }
 async function resetAiChat(){
   if(busy||!await window.ontrackConfirm('مسح محادثة هذا الإيجنت نهائيًا؟ المخطط المحفوظ أو المنشور لن يتغير.',{title:'مسح ذاكرة المحادثة',confirmText:'مسح المحادثة',danger:true}))return;
   try{
     await chatRequest('flow_ai_reset');
     clearAiProposal();hasHistory=false;
     aiThread.replaceChildren(aiPreview);
     aiMessage('assistant','بدأنا محادثة جديدة. المخطط الحالي في المحرر لم يتغير.');
   }catch(error){aiStatus.textContent=error.message||'تعذر مسح المحادثة'}
 }
 function clearAiProposal(){
   proposal=null;profileProposal=null;aiPreview.replaceChildren();aiStatus.textContent='';aiApply.hidden=true;aiCancel.hidden=true;
 }
 async function discardAiProposal(){
   if((!proposal&&!profileProposal)||busy)return;
   try{
     await chatRequest('flow_ai_discard');
     clearAiProposal();
     aiMessage('assistant','ألغيت الاقتراح، لكن المحادثة محفوظة؛ ممكن نكمل تعديل المخطط.');
   }catch(error){aiStatus.textContent=error.message||'تعذر إلغاء الاقتراح'}
 }
 async function requestAiProposal(){
   const prompt=aiInput.value.trim();
   if((proposal||profileProposal)&&/^(نفذ|نفّذ|طبق|اعتمد|execute)$/i.test(prompt)){
     void applyAiProposal();return;
   }
   if(prompt.length<(hasHistory?3:15)){
     aiStatus.textContent='اشرح المطلوب، أو اكتب تعديلًا واضحًا على الإيجنت الحالي.';return;
   }
   if(busy)return;
   if(!chatIdentity()){aiStatus.textContent='لا توجد جلسة تعديل معتمدة.';return;}
   const mode=aiMode.value;
   busy=true;clearAiProposal();
   aiMessage('user',prompt);aiSubmit.disabled=true;
   aiStatus.textContent='جارٍ تصميم '+(mode==='both'?'المخطط والخصائص':mode==='graph'?'المخطط':'الخصائص')+'…';
   const errors=[];
   try{
     if(mode==='graph'||mode==='both'){
       try{
         const result=await chatRequest('flow_ai_propose',{
           description:prompt,current_graph_json:JSON.stringify(data)
         });
         if(result.model)aiModel.textContent=result.model;
         if(result.graph)showProposal(result.graph);
         if(result.explanation)aiMessage('assistant',result.explanation);
       }catch(error){errors.push('المخطط: '+(error.message||'تعذّر التوليد'))}
     }
     if(mode==='profile'||mode==='both'){
       try{
         const result=await chatRequest('flow_ai_profile_propose',{
           description:prompt,current_profile_json:JSON.stringify(profileSnapshot())
         });
         if(result.model)aiModel.textContent=result.model;
         if(result.profile)showProfile(result.profile);
         if(result.explanation)aiMessage('assistant',result.explanation);
       }catch(error){errors.push('الخصائص: '+(error.message||'تعذّر التوليد'))}
     }
     if(proposal||profileProposal){
       hasHistory=true;aiInput.value='';
       aiStatus.textContent='راجع كل اقتراح، ثم اضغط «نفّذ المقترح كمسودة». النشر يحتاج خطوة منفصلة.';
     }
     if(errors.length){
       for(const error of errors)aiMessage('assistant',error);
       aiStatus.textContent+=errors.join(' | ');
     }
   }finally{busy=false;aiSubmit.disabled=false}
 }
 async function applyAiProposal(){
   if((!proposal&&!profileProposal)||busy)return;
   const form=root.closest('form');
   if(!form){aiStatus.textContent='لا توجد استمارة حفظ.';return;}
   const accepted=await window.ontrackConfirm(
      'تطبيق التعديلات على البلدر وخصائص الإيجنت وحفظها كمسودة؟ لن يتم نشرها على المكالمات.',
      {title:'اعتماد اقتراح AI',confirmText:'طبّق واحفظ المسودة'});
   if(!accepted)return;
   busy=true;aiApply.disabled=true;
   try{
     // Persist reviewed selections to the same tenant/user/agent conversation
     // before making local changes, without performing external actions.
     if(profileProposal)await chatRequest('flow_ai_profile_applied',{
        profile_json:JSON.stringify(profileProposal)
     });
     if(proposal)await chatRequest('flow_ai_applied',{graph_json:JSON.stringify(proposal)});
     if(profileProposal)applyProfileToForm(profileProposal);
     if(proposal){
       data=JSON.parse(JSON.stringify(proposal));
       selected=data.nodes[0]?.id||null;hovered=null;
       if(data.edges.length>=12){edgeFocus=true;edgeToggle.textContent='◉ تركيز';
         edgeToggle.setAttribute('aria-pressed','true')}
       arrange();store();
     }
     aiMessage('assistant','تم تطبيق المقترح كمسودة. تقدر تطلب تعديلات إضافية من نفس المحادثة.');
     clearAiProposal();
     const draft=[...form.querySelectorAll('button[type=submit]')].find(b=>!b.hasAttribute('name')||b.name!=='publish_now');
     if(draft)form.requestSubmit(draft);
     else aiStatus.textContent='راجع المسودة واضغط حفظ.';
   }catch(error){
     aiStatus.textContent=error.message||'تعذر تطبيق مقترح AI';
   }finally{busy=false;aiApply.disabled=false;}
 }
 aiInput.addEventListener('keydown',e=>{
   if(e.key==='Enter'&&(e.ctrlKey||e.metaKey)){e.preventDefault();requestAiProposal()}
 });

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
 // Rebuild chat from the server every time the agent draft page is reopened.
 // This is account/user/agent scoped, not browser-global memory.
 void loadAiChat();
 const store=()=>{source.value=JSON.stringify(data);counter.textContent=data.nodes.length+' خطوات · '+data.edges.length+' روابط'};
 const focus=()=>data.nodes.find(n=>n.id===selected)||null;
 const transform=()=>{world.style.transform='translate('+x+'px,'+y+'px) scale('+scale+')'};
 function setSceneSize(w,h){
   sceneWidth=Math.max(2500,Math.ceil(w));sceneHeight=Math.max(1900,Math.ceil(h));
   world.style.width=sceneWidth+'px';world.style.height=sceneHeight+'px';
   cards.style.width=sceneWidth+'px';cards.style.height=sceneHeight+'px';
   lines.setAttribute('width',String(sceneWidth));
   lines.setAttribute('height',String(sceneHeight));
   lines.setAttribute('viewBox','0 0 '+sceneWidth+' '+sceneHeight);
   lines.style.width=sceneWidth+'px';lines.style.height=sceneHeight+'px';
 }
 function fitGraph(){
   if(!data.nodes.length)return;
   const width=viewport.clientWidth||830,height=viewport.clientHeight||600;
   const xMin=Math.min(...data.nodes.map(n=>n.x))-90;
   const xMax=Math.max(...data.nodes.map(n=>n.x+routing.WIDTH))+90;
   const yMin=Math.min(...data.nodes.map(n=>n.y))-80;
   const yMax=Math.max(...data.nodes.map(n=>n.y+routing.HEIGHT))+80;
   scale=Math.max(.08,Math.min(1.04,(width-35)/(xMax-xMin),(height-40)/(yMax-yMin)));
   x=(width-scale*(xMax-xMin))/2-scale*xMin;
   y=(height-scale*(yMax-yMin))/2-scale*yMin;
   transform();
 }
 function focusStart(){
   // In large graphs open at a readable working zoom instead of shrinking all
   // 54 connections into microscopic text. The Fit button still shows overview.
   const node=data.nodes.find(n=>n.type==='start')||data.nodes[0];
   if(!node)return;
   const width=viewport.clientWidth||840;
   scale=Math.max(.58,Math.min(.8,width/(routing.WIDTH*4)));
   x=38-node.x*scale;
   y=90-node.y*scale;
   transform();
 }
 function refreshNodeFocus(){
   const relation=routing.focusRelation(data.edges,selected,hovered);
   cards.querySelectorAll('.graph-node').forEach(box=>{
     const id=box.dataset.nodeId;
     box.classList.toggle('selected',id===selected);
     box.classList.toggle('is-neighbor',edgeFocus&&relation.related.has(id)&&id!==selected);
     box.classList.toggle('is-muted',edgeFocus&&relation.active&&
       !relation.related.has(id)&&id!==selected);
   });
 }
 function edges(){
   lines.replaceChildren(defs);
   const relation=routing.focusRelation(data.edges,selected,hovered);
   const tracks=routing.geometry(data.nodes,data.edges,{bottom:sceneHeight-320});
   tracks.forEach(track=>{
     const e=track.edge,active=relation.focused.has(track.index);
     const muted=edgeFocus&&!active;
     const route=S('path',{d:track.d,
       class:'graph-connector'+(active?' is-focused':'')+(muted?' is-muted':''),
       'marker-end':'url(#'+arrowId+')'});
     const tip=S('title');tip.textContent=(e.label||'مسار')+': '+e.from+' ← '+e.to;
     route.append(tip);lines.append(route);
     if(e.label&&active&&String(e.label).length){
       const mx=track.label[0],my=track.label[1],v=String(e.label).slice(0,37),
         width=Math.min(178,Math.max(52,v.length*6.7+22));
       const pill=S('g',{class:'graph-edge-pill is-focused'});
       pill.append(S('rect',{x:mx-width/2,y:my-12,width,height:24,rx:12}));
       const label=S('text',{x:mx,y:my+4,'text-anchor':'middle'});
       label.textContent=v;pill.append(label);lines.append(pill);
     }
   });
 }
 function nodes(){
   cards.replaceChildren();
   data.nodes.forEach(n=>{
     const box=make('button','graph-node'+(n.id===selected?' selected':''));box.type='button';
     box.dataset.nodeId=n.id;box.style.left=n.x+'px';box.style.top=n.y+'px';
     box.setAttribute('aria-label',(TYPES[n.type]||n.type)+': '+String(n.text||'').slice(0,100));
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
       box.setPointerCapture(evt.pointerId);inspect();edges();refreshNodeFocus();
     });
     box.addEventListener('pointermove',evt=>{
       if(!drag||drag.id!==n.id)return;
       n.x=Math.max(0,Math.min(sceneWidth-routing.WIDTH-30,drag.ox+(evt.clientX-drag.x)/scale));
       n.y=Math.max(0,Math.min(sceneHeight-routing.HEIGHT-30,drag.oy+(evt.clientY-drag.y)/scale));
       box.style.left=n.x+'px';box.style.top=n.y+'px';edges();
     });
     box.addEventListener('pointerup',()=>{drag=null;data.layout_version=2;store();nodes();edges()});
     box.addEventListener('pointerenter',()=>{if(drag)return;hovered=n.id;edges();refreshNodeFocus()});
     box.addEventListener('pointerleave',()=>{if(drag)return;hovered=null;edges();refreshNodeFocus()});
     cards.append(box);
   });
   refreshNodeFocus();
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
   inspector.append(button('حذف الخطوة',async()=>{
     if(!await window.ontrackConfirm('حذف الخطوة والروابط التابعة؟',{title:'حذف عقدة من المخطط',confirmText:'حذف الخطوة',danger:true}))return;
     data.nodes=data.nodes.filter(t=>t.id!==n.id);data.edges=data.edges.filter(t=>t.from!==n.id&&t.to!==n.id);
     selected=data.nodes[0]?.id||null;render();
   },'danger'));
 }
 function render(){store();transform();edges();nodes();inspect();refreshNodeFocus()}
 function addNode(){
   if(data.nodes.length>=100){window.ontrackToast('100 خطوة كحد أقصى',{error:true});return;}
   const a=focus(),n={id:uuid(),type:type.value,text:'',x:a?a.x+250:1050,y:a?a.y+230:100};
   data.nodes.push(n);if(a)data.edges.push({from:a.id,to:n.id,label:''});selected=n.id;render();
 }
 function arrange(){
   const result=routing.layout(data.nodes,data.edges);
   data.nodes.forEach(node=>{
     const position=result.positions[node.id];
     if(position){node.x=position.x;node.y=position.y}
   });
   data.layout_version=2;
   setSceneSize(result.width,result.height);
   render();if(data.nodes.length>15)focusStart();else fitGraph();
 }
 viewport.addEventListener('pointerdown',evt=>{
   if(evt.target.closest('.graph-node'))return;pan={cx:evt.clientX,cy:evt.clientY,ox:x,oy:y};viewport.setPointerCapture(evt.pointerId)
 });
 viewport.addEventListener('pointermove',evt=>{if(pan){x=pan.ox+evt.clientX-pan.cx;y=pan.oy+evt.clientY-pan.cy;transform()}});
 viewport.addEventListener('pointerup',()=>pan=null);
 viewport.addEventListener('wheel',evt=>{if(!evt.ctrlKey)return;evt.preventDefault();scale=Math.max(.4,Math.min(1.5,scale+(evt.deltaY<0?.08:-.08)));transform()},{passive:false});
 source.addEventListener('change',()=>{if(source.hidden)return;try{const value=JSON.parse(source.value);if(!Array.isArray(value.nodes)||!Array.isArray(value.edges))throw Error();data=value;render()}catch{window.ontrackToast('JSON غير صالح',{error:true})}});
 if(!data.nodes.length){data.nodes=[{id:'start',type:'start',text:'أهلاً بك',x:1000,y:100},{id:'end',type:'end',text:'شكراً لتواصلك',x:1000,y:350}];data.edges=[{from:'start',to:'end',label:''}]}
 if(data.layout_version!==2)arrange();
 else{
   setSceneSize(Math.max(...data.nodes.map(n=>n.x+routing.WIDTH+310),2500),
     Math.max(...data.nodes.map(n=>n.y+routing.HEIGHT+470),1900));
   render();if(data.nodes.length>15)focusStart();else fitGraph();
 }
 const editorForm=root.closest('form');
 if(editorForm)editorForm.addEventListener('submit',async event=>{
   event.preventDefault();event.stopPropagation();
   if(editorForm.dataset.otSaving==='1')return;
   store();
   const publishNow=event.submitter?.name==='publish_now';
   if(publishNow){
     const accepted=await window.ontrackConfirm('نشر هذه النسخة سيفعّل المخطط للمكالمات عند المزامنة. حفظ المسودة منفصل.',{
       title:'نشر السيناريو',confirmText:'نشر المخطط'});
     if(!accepted)return;
   }
   const submitter=event.submitter;
   editorForm.dataset.otSaving='1';if(submitter)submitter.disabled=true;
   try{
     const fields=new FormData(editorForm);
     if(publishNow)fields.set('publish_now','1');else fields.delete('publish_now');
     const response=await fetch(editorForm.action||location.pathname,{
       method:'POST',credentials:'same-origin',cache:'no-store',redirect:'error',
       headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},body:fields
     });
     const result=await response.json();
     if(!response.ok||result.ok!==true)throw Error(result.error||'تعذر حفظ السيناريو');
     notice.textContent=result.message||(publishNow?'تم النشر':'تم حفظ المسودة');
     window.ontrackToast(notice.textContent);
     // No navigation: preserve zoom, fullscreen and the assistant conversation.
   }catch(error){
     notice.textContent=error.message||'تعذر حفظ السيناريو';
     window.ontrackToast(notice.textContent,{error:true});
   }finally{delete editorForm.dataset.otSaving;if(submitter)submitter.disabled=false}
 });
});
window.ontrackMountFlowEditors=mountFlowEditors;
mountFlowEditors();
})();