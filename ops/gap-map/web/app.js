'use strict';
(function () {
  var map = null, area='all', query='', selected=null, fetching=false;
  var ns='http://www.w3.org/2000/svg';
  var $=function(id){return document.getElementById(id);};
  function txt(value){return value===null||value===undefined?'—':String(value);}
  function h(tag,className,label){
    var node=document.createElement(tag);
    if(className)node.className=className;
    if(label!==undefined)node.textContent=txt(label);
    return node;
  }
  function clear(el){while(el.firstChild)el.removeChild(el.firstChild);}
  function labelStatus(stage){
    var text={code_patch_evidenced:'إصلاحات كود',awaiting_code_gap_closure:'غير مكتمل',
      deferred_10:'مؤجل',code_complete:'الكود مكتمل',host_verified:'مثبت على الهوست',
      user_verified:'تحقق مستخدم',complete:'مكتمل',blocked:'متعطل',
      confirmed_by_owner:'مؤكدة من المالك',recheck_required:'تحتاج إعادة التحقق',
      not_confirmed:'لم تؤكد',PASS:'PASS',FAIL:'FAIL',BLOCKED:'BLOCKED',NOT_TESTED:'NOT TESTED'};
    return text[stage]||txt(stage);
  }
  function badge(stage){return h('span','status '+txt(stage),labelStatus(stage));}
  function match(o){return !query||JSON.stringify(o).toLowerCase().indexOf(query)!==-1;}
  function areaMatches(x){
    return area==='all'||x.area===area;
  }
  function countTasks(){
    var tasks=map.tasks||[], active=tasks.filter(function(t){return t.stage!=='deferred_10';});
    $('stat-all').textContent=txt(active.length);
    $('stat-coded').textContent=txt(active.filter(function(t){return ['code_patch_evidenced','code_complete','host_verified','user_verified','complete'].indexOf(t.stage)!==-1;}).length);
    $('stat-open').textContent=txt(active.filter(function(t){return ['awaiting_code_gap_closure','blocked'].indexOf(t.stage)!==-1;}).length);
    $('stat-gaps').textContent=txt(map.gaps.length);
  }
  function loadMeta(){
    $('branch').textContent=txt(map.branch);
    $('revision').textContent=txt(map.commit?map.commit.slice(0,12):'未 يُثبت');
    $('checked-at').textContent=map.generated_at?new Date(map.generated_at).toLocaleString('ar-EG'):'غير مسجل';
    var ci=map.ci||[],box=$('ci');clear(box);
    if(!ci.length){box.appendChild(h('div','empty','نتائج CI لم تصل في Snapshot. لا دليل جديد.'));return;}
    ci.forEach(function(r){
      var div=h('div','ci-item'),row=h('div');
      row.appendChild(badge(r.conclusion||r.status||'NOT_TESTED'));
      row.appendChild(h('span','', ' '+txt(r.name||r.workflow)));
      div.appendChild(row);
      if(r.url&&/^https:\/\/github\.com\/OnTrack-Development\/OnTrack-voice\/actions\/runs\/\d+$/.test(r.url)){
        var link=h('a','', 'الدليل #'+txt(r.id));link.href=r.url;link.target='_blank';link.rel='noopener noreferrer';div.appendChild(link);
      }
      box.appendChild(div);
    });
  }
  function filteredModules(){
    return(map.modules||[]).filter(function(m){return areaMatches(m)&&match(m);});
  }
  function renderLanes(){
    var lanes=$('lanes');clear(lanes);
    var visible=filteredModules(),groups=[
      {area:'saas',name:'SaaS / حسابات وباقات ولوحة الإدارة'},
      {area:'shared',name:'Shared / API + Backend + Voice Critical Path'},
      {area:'mobile',name:'Mobile / Android — متابعة فقط'}
    ];
    groups.forEach(function(group){
      var members=visible.filter(function(m){return m.area===group.area;});
      if(!members.length)return;
      var wrapper=h('section','lanegroup'),head=h('div','lane-title '+group.area,group.name);
      wrapper.appendChild(head);
      var grid=h('div','modules');
      members.forEach(function(m){
        var node=h('button','module'+(selected&&selected.type==='module'&&selected.id===m.id?' active':'')),caption=h('span','unit-kind',txt(m.status));
        node.type='button';node.id='mapnode-'+m.id;node.appendChild(caption);
        node.appendChild(h('div','',txt(m.label)));
        node.appendChild(h('small','',txt(m.path)));
        node.addEventListener('click',function(){selected={type:'module',id:m.id};draw();renderDetail();});
        grid.appendChild(node);
      });
      wrapper.appendChild(grid);lanes.appendChild(wrapper);
    });
    window.requestAnimationFrame(drawEdges);
  }
  function drawEdges(){
    var svg=$('connections'),parent=$('architecture');clear(svg);
    var rect=parent.getBoundingClientRect();if(rect.width<1)return;
    svg.setAttribute('viewBox','0 0 '+rect.width+' '+rect.height);
    var paths={};
    (map.gaps||[]).forEach(function(gap){
      var visible=gap.modules.filter(function(id){return document.getElementById('mapnode-'+id);});
      if(visible.length<2)return;
      var anchor=document.getElementById('mapnode-'+visible[0]);if(!anchor)return;
      for(var i=1;i<visible.length;i++){
        var target=document.getElementById('mapnode-'+visible[i]);if(!target)continue;
        var a=anchor.getBoundingClientRect(),b=target.getBoundingClientRect();
        var x1=a.left+a.width/2-rect.left,y1=a.top+a.height/2-rect.top;
        var x2=b.left+b.width/2-rect.left,y2=b.top+b.height/2-rect.top;
        var mid=(x1+x2)/2,selectedGap=selected&&selected.type==='gap'&&selected.id===gap.id;
        var path=document.createElementNS(ns,'path');
        path.setAttribute('d','M '+x1+' '+y1+' C '+mid+' '+y1+', '+mid+' '+y2+', '+x2+' '+y2);
        path.setAttribute('stroke',gap.area==='shared'?'#78b5ef':(gap.severity==='critical'?'#f04451':'#f1ae57'));
        if(selectedGap)path.setAttribute('class','highlight');
        svg.appendChild(path);
        paths[gap.id]=(paths[gap.id]||0)+1;
      }
    });
  }
  function draw(){renderLanes();renderGaps();}
  function renderGaps(){
    var box=$('gaps');clear(box);
    var gaps=(map.gaps||[]).filter(function(g){
      return (area==='all'||g.area===area||g.modules.some(function(id){
        var m=(map.modules||[]).find(function(x){return x.id===id;});
        return m&&m.area===area;
      }))&&match(g);
    });
    if(!gaps.length){box.appendChild(h('div','empty','لا توجد فجوات مطابقة للبحث الحالي.'));return;}
    gaps.forEach(function(g){
      var btn=h('button','gapcard'+(selected&&selected.type==='gap'&&selected.id===g.id?' active':''));btn.type='button';
      var row=h('div','gaptop');row.appendChild(h('span','gap-id',g.id));row.appendChild(badge(g.severity));btn.appendChild(row);
      btn.appendChild(h('h3','',g.name));
      btn.appendChild(h('p','',g.description));
      if(g.verification&&g.verification.state==='confirmed_by_owner'){
        btn.appendChild(badge('confirmed_by_owner'));
      }else if(g.verification&&g.verification.state==='recheck_required'){
        btn.appendChild(badge('recheck_required'));
      }
      btn.addEventListener('click',function(){selected={type:'gap',id:g.id};draw();renderDetail();window.requestAnimationFrame(drawEdges);});
      box.appendChild(btn);
    });
  }
  function appendProperty(target,title,value){
    var d=h('div','property');d.appendChild(h('b','',title));d.appendChild(h('div','',value));target.appendChild(d);
  }
  function fileListing(target,files){
    var box=h('ul','filelist');(files||[]).forEach(function(path){box.appendChild(h('li','',path));});target.appendChild(box);
  }
  function linkGap(target,id){
    var btn=h('button','linkchip',id);btn.type='button';
    btn.addEventListener('click',function(){selected={type:'gap',id:id};draw();renderDetail();});
    target.appendChild(btn);
  }
  function renderDetail(){
    var box=$('detail');clear(box);
    if(!selected){box.appendChild(h('h2','','تفاصيل الفجوات'));box.appendChild(h('p','', 'اختر أي GAP أو Module من المخطط لعرض المصادر والتأثير.'));return;}
    if(selected.type==='module'){
      var m=(map.modules||[]).find(function(z){return z.id===selected.id;});
      if(!m){selected=null;renderDetail();return;}
      box.appendChild(h('h2','',m.label));box.appendChild(badge(m.status));
      appendProperty(box,'المسار المسؤول',m.path);
      appendProperty(box,'وظيفة المكوّن',m.detail);
      var associated=(map.gaps||[]).filter(function(g){return g.modules.indexOf(m.id)!==-1;});
      box.appendChild(h('h3','','الفجوات المتصلة'));
      var links=h('div','links');associated.forEach(function(g){linkGap(links,g.id);});box.appendChild(links);return;
    }
    var gap=(map.gaps||[]).find(function(z){return z.id===selected.id;});
    if(!gap){selected=null;renderDetail();return;}
    box.appendChild(h('h2','',gap.id+' — '+gap.name));box.appendChild(badge(gap.severity));
    appendProperty(box,'حالة الاكتشاف',gap.state+' · '+txt(gap.confidence));
    appendProperty(box,'المشكلة والحدود',gap.description);
    appendProperty(box,'الإجراء التالي',gap.next);
    box.appendChild(h('h3','','الملفات المرتبطة'));
    fileListing(box,gap.files);
    box.appendChild(h('h3','','التذاكر المرتبطة'));
    var tags=h('div','links');
    (gap.tasks||[]).forEach(function(t){tags.appendChild(h('span','linkchip',t));});box.appendChild(tags);
    box.appendChild(h('h3','','العلاقات مع فجوات أخرى'));
    var rel=h('div','links');(gap.links||[]).forEach(function(id){linkGap(rel,id);});box.appendChild(rel);
    if(gap.voiceHotPath){
      box.appendChild(h('h3','','صوت وارد / صادر — مسار سريع'));
      ['inbound','outbound'].forEach(function(key){
        var list=(gap.voiceHotPath[key]||[]).map(function(x){return x.phase+' ('+x.owner+')';});
        appendProperty(box,key==='inbound'?'الوارد':'الصادر',list.join(' ← '));
      });
      appendProperty(box,'الأداء الفعلي','غير مقاس؛ هدف الاستجابة ≤2 ثانية وليس نتيجة اختبار.');
    }
    box.appendChild(h('h3','','تحقق المالك'));
    var state=gap.verification&&gap.verification.state||'not_confirmed';
    box.appendChild(badge(state));
    if(gap.verification&&gap.verification.verified_at)box.appendChild(h('p','', 'آخر إقرار: '+gap.verification.verified_at));
    var action=h('div','action'),confirm=h('button','btn confirm','تم التحقق منها'),revoke=h('button','btn revoke','إلغاء التأكيد');
    confirm.type='button';revoke.type='button';
    confirm.disabled=!gap.basis_hash||state==='confirmed_by_owner';
    revoke.disabled=!gap.basis_hash||state==='not_confirmed';
    confirm.addEventListener('click',function(){verify(gap,'confirm',confirm);});
    revoke.addEventListener('click',function(){verify(gap,'revoke',revoke);});
    action.appendChild(confirm);action.appendChild(revoke);box.appendChild(action);
    var note=h('p','footnote','التأكيد دليل مراجعة المالك للمصدر عند بصمته الحالية فقط؛ ليس تقرير نجاح CI أو مكالمة أو نشر.');
    box.appendChild(note);
  }
  function renderFindings(){
    var box=$('findings');clear(box);
    var items=(map.findings||[]).filter(function(f){
      return (area==='all'||f.area===area)&&match(f);
    });
    $('finding-count').textContent=txt((map.findings||[]).length);
    if(!items.length){
      box.appendChild(h('div','empty','لا توجد ملاحظات مصدر مطابقة للفلتر. هذا لا يعني خلو التشغيل الفعلي من المشاكل.'));
      return;
    }
    items.forEach(function(f){
      var card=h('div','gapcard'),top=h('div','gaptop');
      top.appendChild(h('span','gap-id',f.id));top.appendChild(badge(f.severity));
      card.appendChild(top);card.appendChild(h('h3','',f.title));
      card.appendChild(h('p','',f.explanation));
      var evidence=h('small','', 'المسؤول: '+f.area+' · مصدر: '+(f.evidence_paths||[]).slice(0,2).join('، '));
      card.appendChild(evidence);
      if(f.linked_gap){
        var link=h('button','btn','افتح '+f.linked_gap);link.type='button';
        link.addEventListener('click',function(){selected={type:'gap',id:f.linked_gap};draw();renderDetail();});
        var actions=h('div','action');actions.appendChild(link);card.appendChild(actions);
      }
      box.appendChild(card);
    });
  }
  function renderTasks(){
    var list=$('tasks');clear(list);
    (map.tasks||[]).filter(function(t){return t.stage!=='deferred_10';}).forEach(function(t){
      var card=h('div','task');card.appendChild(h('code','',t.id));card.appendChild(badge(t.stage));
      if(t.fullClosure===true)card.appendChild(badge('complete'));
      if(t.next_action)card.appendChild(h('small','',t.next_action));
      list.appendChild(card);
    });
  }
  function renderSimulation(){
    var s=map.simulation||{},steps=s.steps||[];
    $('sim-meta').textContent=s.generated_at?
      'آخر محاكاة: '+new Date(s.generated_at).toLocaleString('ar-EG')+' · نوع الدليل: '+txt(s.environment||'CI isolated'):
      'محاكاة متاحة بعد ربط Job CI بالـSnapshot. دون بيانات تجريبية موهومة.';
    if(!steps.length){
      steps=[
        {title:'تسجيل عميل جديد + تأكيد البريد',status:'NOT_TESTED'},
        {title:'تسجيل ريسيلر وصلاحيات الحسابات',status:'NOT_TESTED'},
        {title:'Login واستعادة كلمة المرور',status:'NOT_TESTED'},
        {title:'إنشاء خطة وتعديل باقة وتجديد',status:'NOT_TESTED'},
        {title:'Agent / Flow / Knowledge Base',status:'NOT_TESTED'},
        {title:'DNC / حملة / سجل المكالمة',status:'NOT_TESTED'},
        {title:'مكالمة حقيقية — مؤجلة للتطبيق',status:'BLOCKED'}
      ];
    }
    var box=$('simulation');clear(box);
    steps.forEach(function(step,i){
      var row=h('div','step'),number=h('span','stepnum',i+1);
      var body=h('div','stepbody');body.appendChild(h('b','',step.title||step.id));
      body.appendChild(h('small','',step.detail||step.owner_component||'لم يتم اختبارها على الهوست الفعلي'));
      row.appendChild(number);row.appendChild(body);row.appendChild(badge(step.status||'NOT_TESTED'));
      box.appendChild(row);
    });
  }
  async function verify(gap,action,button){
    if(!map||!map.csrf||!gap.basis_hash)return;
    button.disabled=true;
    try{
      var response=await fetch('verify.php',{method:'POST',credentials:'same-origin',
        headers:{'Content-Type':'application/json','X-Gap-Csrf':map.csrf},
        body:JSON.stringify({gap_id:gap.id,basis_hash:gap.basis_hash,action:action})});
      var reply=await response.json();
      if(!response.ok||reply.ok!==true)throw new Error(reply.error||('HTTP '+response.status));
      await refresh();
    }catch(err){alert('فشل حفظ التأكيد: '+err.message);}finally{button.disabled=false;}
  }
  function mermaid(){
    var lines=['flowchart TB',' subgraph SAAS["SaaS"]'];
    (map.modules||[]).filter(function(m){return m.area==='saas';}).forEach(function(m){lines.push(' '+m.id+'["'+m.id.replace(/"/g,'')+'"]');});
    lines.push(' end',' subgraph SHARED["SaaS ↔ Mobile"]');
    (map.modules||[]).filter(function(m){return m.area==='shared';}).forEach(function(m){lines.push(' '+m.id+'["'+m.id.replace(/"/g,'')+'"]');});
    lines.push(' end',' subgraph MOBILE["Mobile — observe only"]');
    (map.modules||[]).filter(function(m){return m.area==='mobile';}).forEach(function(m){lines.push(' '+m.id+'["'+m.id.replace(/"/g,'')+'"]');});
    lines.push(' end');
    (map.gaps||[]).forEach(function(g){(g.modules||[]).slice(1).forEach(function(id){
      lines.push(' '+g.modules[0]+' -. "'+g.id+'" .-> '+id);
    });});
    return lines.join('\n');
  }
  async function refresh(){
    if(fetching)return;fetching=true;
    $('sync-label').textContent='جارٍ مزامنة Snapshot…';
    try{
      var response=await fetch('status.php',{credentials:'same-origin',cache:'no-store'});
      var data=await response.json();
      if(!response.ok||data.ok===false)throw new Error(data.error||('HTTP '+response.status));
      if(data.schema!==2||!Array.isArray(data.gaps)||!Array.isArray(data.modules))
        throw new Error('Invalid architecture snapshot');
      map=data;countTasks();loadMeta();renderLanes();renderGaps();renderDetail();renderFindings();renderTasks();renderSimulation();
      $('health').className='health ready';
      $('sync-label').textContent='آخر Snapshot متاح';
    }catch(error){
      $('health').className='health error';$('sync-label').textContent='المزامنة فشلت: '+error.message;
    }finally{fetching=false;}
  }
  $('refresh').addEventListener('click',refresh);
  $('filters').querySelectorAll('button[data-area]').forEach(function(button){
    button.addEventListener('click',function(){
      area=button.dataset.area;
      $('filters').querySelectorAll('button[data-area]').forEach(function(b){b.classList.toggle('selected',b===button);});
      if(map){draw();renderDetail();}
    });
  });
  $('search').addEventListener('input',function(){query=this.value.trim().toLowerCase();if(map)draw();});
  $('copy-mermaid').addEventListener('click',function(){
    if(!map)return;
    navigator.clipboard.writeText(mermaid()).then(function(){
      $('copy-mermaid').textContent='تم النسخ';setTimeout(function(){$('copy-mermaid').textContent='نسخ المخطط بصيغة Mermaid';},1600);
    }).catch(function(){alert('المتصفح منع النسخ؛ افتح الصفحة عبر HTTPS.');});
  });
  window.addEventListener('resize',function(){if(map)window.requestAnimationFrame(drawEdges);});
  refresh();
  setInterval(refresh,20000);
})();
