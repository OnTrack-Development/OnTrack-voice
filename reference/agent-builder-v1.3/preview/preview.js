(() => {
 const toggle=document.getElementById('navToggle');
 toggle?.addEventListener('click',()=>{const nav=document.getElementById('mobileLinks');nav?.classList.toggle('hide');toggle.setAttribute('aria-expanded',String(!nav?.classList.contains('hide')));});
 const scriptStates={
  guest:{reply:'أهلاً بيك! نقدر نراجع حجزك بعد التحقق من الحساب. اختار الخطوة المناسبة:',trace:'● حالة تجريبية: الحساب غير متحقق — الإجراءات الحساسة مقفولة',actions:[['التحقق من الحساب','verified'],['التحدث مع موظف','human']]},
  verified:{reply:'تم التحقق داخل محاكاة الواجهة. حجزك التجريبي قيد المراجعة. اختار المطلوب:',trace:'● حالة تجريبية: المستخدم متحقق — الحجز قيد المعالجة',actions:[['تفاصيل الحجز','verified'],['تعديل الموعد','updated'],['طلب موظف','human']]},
  updated:{reply:'تم تغيير الحالة في البيانات التجريبية، وبالتالي اتغيّرت الخيارات المتاحة.',trace:'● حالة تجريبية: تم تحديث الحجز — الأزرار أعيد حسابها',actions:[['عرض الحجز','updated'],['حجز جديد','guest'],['التحدث مع موظف','human']]},
  human:{reply:'تم تحويل المحادثة لموظف داخل المحاكاة. لن يرسل الوكيل ردًا تلقائيًا حتى تنتهي السيطرة البشرية.',trace:'● حالة تجريبية: Human Lock نشط — أفعال AI مقفولة',actions:[['الرجوع للبداية','guest']]}
 };
 function heroState(state){const c=scriptStates[state];if(!c)return;document.getElementById('heroReply').textContent=c.reply;document.getElementById('heroTrace').textContent=c.trace;const el=document.getElementById('heroActions');el.innerHTML='';c.actions.forEach(([label,next])=>{const b=document.createElement('button');b.className='pill-action';b.textContent=label;b.onclick=()=>heroState(next);el.appendChild(b);});}
 if(document.getElementById('heroActions')){heroState('guest');document.getElementById('resetDemo')?.addEventListener('click',()=>heroState('guest'));}
 const tabButtons=[...document.querySelectorAll('[data-tab]')];
 function activateTab(tab){tabButtons.forEach(b=>b.classList.toggle('active',b.dataset.tab===tab));document.querySelectorAll('[data-panel]').forEach(el=>el.classList.toggle('hide',el.dataset.panel!==tab));}
 tabButtons.forEach(b=>b.addEventListener('click',()=>activateTab(b.dataset.tab)));
 document.querySelectorAll('[data-jump-tab]').forEach(el=>el.addEventListener('click',()=>activateTab(el.dataset.jumpTab)));
 let hash=window.location.hash.replace('#','');if(tabButtons.some(b=>b.dataset.tab===hash))activateTab(hash);
 document.getElementById('demoSave')?.addEventListener('click',()=>alert('معاينة فقط: ربط الحفظ الحقيقي يتم داخل تطبيق Laravel، ولا توجد بيانات محفوظة من هذا النموذج.'));
 const select=document.getElementById('stateSelect');
 function stateButtons(){let state=select?.value;if(!state)return;const options={guest:['التحقق من الحساب','موظف بشري'],pending:['تفاصيل الحجز','تعديل الموعد','موظف بشري'],updated:['عرض تفاصيل الحجز','حجز جديد','موظف بشري'],human:['متابعة مع الموظف']};const area=document.getElementById('studioButtons');area.innerHTML='';options[state].forEach(label=>{const b=document.createElement('span');b.className='pill-action';b.textContent=label;area.appendChild(b)});document.getElementById('stateIdentity').textContent=state==='guest'?'غير متحققة':'متحققة (افتراضيًا)';document.getElementById('statePerm').textContent=state==='human'?'AI متوقف':state==='guest'?'محدودة':'متاحة بحسب الحالة';}
 select?.addEventListener('change',stateButtons);stateButtons();
})();
