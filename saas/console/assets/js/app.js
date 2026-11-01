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
  // A visual editor for versioned, per-site agent flow drafts. No remote code execution.
  document.querySelectorAll('[data-flow-editor]').forEach(editor=>{
    const source=editor.querySelector('.flow-source');
    const preview=editor.querySelector('.flow-preview');
    const add=editor.querySelector('.add-flow-node');
    const toggle=editor.querySelector('.toggle-flow-json');
    if(!source||!preview||!add||!toggle)return;
    let data;
    try{data=JSON.parse(source.value)}catch{data=null}
    if(!data||!Array.isArray(data.nodes)||!Array.isArray(data.edges))data={nodes:[],edges:[]};
    const safeId=()=>('node_'+Math.random().toString(16).slice(2,10));
    const nodeTypes=['start','say','listen','ask','condition','knowledge_lookup','collect_field','crm_lookup','crm_write','schedule','handoff','end'];
    const textLabels={start:'بداية',say:'قول',listen:'استماع',ask:'سؤال',condition:'شرط',knowledge_lookup:'بحث في المعرفة',collect_field:'جمع بيانات',crm_lookup:'بحث CRM',crm_write:'تحديث CRM',schedule:'جدولة',handoff:'تحويل بشري',end:'إنهاء'};
    const save=()=>{source.value=JSON.stringify(data)};
    const draw=()=>{
      preview.replaceChildren();
      data.nodes.forEach((node,index)=>{
        const box=document.createElement('div');box.className='flow-card';
        const select=document.createElement('select');select.setAttribute('aria-label','نوع الخطوة');
        nodeTypes.forEach(type=>{const op=new Option(textLabels[type],type);select.appendChild(op)});
        select.value=node.type||'say';
        select.addEventListener('change',()=>{node.type=select.value;save()});
        const title=document.createElement('strong');title.textContent='خطوة '+(index+1);
        const text=document.createElement('textarea');text.value=String(node.text||'');
        text.placeholder='ماذا يقول أو يسأل الإيجنت هنا؟';
        text.addEventListener('input',()=>{node.text=text.value.slice(0,800);save()});
        const remove=document.createElement('button');remove.type='button';
        remove.textContent='حذف';remove.setAttribute('aria-label','حذف هذه الخطوة');
        remove.addEventListener('click',()=>{
          data.nodes.splice(index,1);
          data.edges=data.nodes.slice(1).map((n,i)=>({from:data.nodes[i].id,to:n.id}));
          save();draw();
        });
        const hint=document.createElement('small');hint.textContent='المسار: '+(index===0?'بداية':'←');
        box.append(title,select,text,hint,remove);
        preview.appendChild(box);
      });
    };
    add.addEventListener('click',()=>{
      if(data.nodes.length>=100)return alert('الحد الأقصى 100 خطوة');
      const node={id:safeId(),type:'ask',text:'ما هو السؤال التالي؟'};
      const last=data.nodes.at(-1);
      data.nodes.push(node);
      if(last)data.edges.push({from:last.id,to:node.id});
      save();draw();
    });
    toggle.addEventListener('click',()=>{
      editor.classList.toggle('raw');
      toggle.textContent=editor.classList.contains('raw')?'إخفاء JSON':'عرض JSON';
    });
    source.addEventListener('input',()=>{
      if(!editor.classList.contains('raw'))return;
      try{
        const parsed=JSON.parse(source.value);
        if(Array.isArray(parsed.nodes)&&Array.isArray(parsed.edges)){data=parsed;draw()}
      }catch{}
    });
    draw();
    // Any changes made visually update the serialized flow field submitted to the server.
    editor.closest('form')?.addEventListener('submit',()=>{
      if(!editor.classList.contains('raw'))save();
    });
  });
})();
