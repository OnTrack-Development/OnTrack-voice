'use strict';
import { createAyoAvatar } from './ayo-avatar.js';
import { createMicTurns } from './ayo-mic-stt.js';
let avatar=null, micStream=null, micSource=null, micAnalyser=null, micMeterTimer=null, recognitionUnavailable=false, micTurnDetector=null, startAfterMic=false;
const $=id=>document.getElementById(id);
const S={csrf:'',config:null,session:false,clock:null,remaining:120,busy:false,abort:null,muted:false,recog:null,recogWanted:false,preview:false,ctx:null,playAt:0,sources:[],audio:[],lastReply:'',turns:0,chars:0,cost:0,started:0,recording:null,history:[]};
const models={apinex:['free/gpt-6-luna'],'omnira-a':['claude-fable-5.1','claude-fable-5.1[1m]','claude-sonnet-5[1m]','gpt-6-astra[1m]','claude-sonnet-5'],'omnira-b':['glm-5-3-flash','glm-5-3','glm-5-2','deepseek-v4-flash','deepseek-v4-pro','qwen3-8-flash','qwen3-8-max','kimi-k3','claude-fable-5-1','claude-opus-5-5','gpt-6-sol','claude-sonnet-5-5','deepseek-v4-1-flash','deepseek-v4-flash-0731','deepseek-v4-pro-0813','qwen3-8-max-0902']};
const urls={apinex:'https://api.apinex.bond/v1/chat/completions','omnira-a':'https://ai.omnira-ai.net/v1/chat/completions','omnira-b':'https://v2.omnira-ai.net/v1/chat/completions'};
const gatewayLabels={apinex:'APInex · 20M tokens/day','omnira-a':'Omnira A · Unlimited Weighted','omnira-b':'Omnira B · 5,000 weighted/day'};
const weightedB={'claude-fable-5-1':'3×','claude-opus-5-5':'3×','gpt-6-sol':'3×','claude-sonnet-5-5':'2×','deepseek-v4-1-flash':'2×','deepseek-v4-flash-0731':'2×','deepseek-v4-pro-0813':'2×','glm-5-2':'2×','glm-5-3':'2×','glm-5-3-flash':'2×','kimi-k3':'2×','qwen3-8-flash':'2×','qwen3-8-max':'2×','qwen3-8-max-0902':'2×','deepseek-v4-flash':'1×','deepseek-v4-pro':'1×'};
const rememberedModels={};
let gatewaySaving=false;
const escape=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function log(s){$('log').textContent+=`[${new Date().toLocaleTimeString('ar-EG')}] ${s}\n`;$('log').scrollTop=$('log').scrollHeight;}
function note(s,err=false){$('banner').textContent=s;$('banner').style.borderColor=err?'#a3404b':'#6c432d';log(s);}
async function api(action,data){const req={method:data===undefined?'GET':'POST',headers:{'X-AYO-CSRF':S.csrf}};if(data!==undefined){req.headers['Content-Type']='application/json';req.body=JSON.stringify(data);}const r=await fetch('api.php?action='+action,req);const v=await r.json().catch(()=>({error:'HTTP '+r.status}));if(!r.ok)throw Error(v.error||'HTTP '+r.status);return v;}
function light(id,on){$(id).classList.toggle('ready',!!on);}
function presets(value){const p=$('llmProvider').value;const opts=models[p]||[];$('llmModelPreset').replaceChildren(...opts.map(m=>{const e=document.createElement('option');e.value=e.textContent=m;return e;}));if(value&&opts.includes(value))$('llmModelPreset').value=value;$('llmModel').value=value||opts[0]||'';$('llmEndpoint').textContent='API endpoint: '+urls[p];}
function renderConfig(c){if(S.config)rememberedModels[S.config.llmProvider]=S.config.llmModel;S.config=c;$('emotionMode').value=c.emotionMode||'auto';$('voiceId').value=c.voiceId;$('ttsModel').value=c.ttsModel;$('ttsDelivery').value=c.ttsDelivery;$('ttsLocale').value=c.ttsLocale;$('llmProvider').value=c.llmProvider;presets(c.llmModel);renderGateway(c);$('voiceStyleStatus').textContent=c.ttsModel==='inworld-tts-2-flash'&&c.emotionMode!=='off'?'Flash يدعم المؤثرات البشرية، لكن استنتاج المشاعر يحتاج TTS-2':c.emotionMode==='off'?'النطق الطبيعي فقط · التحكم العاطفي متوقف':c.emotionMode==='expressive'?'TTS-2 يختار النبرة تلقائيًا ويضيف أصواتًا بشرية عند اللزوم':'TTS-2 يختار النبرة المناسبة تلقائيًا';
 light('llmLight',c.hasActiveLlmKey);$('llmReady').textContent=c.hasActiveLlmKey?c.llmProvider+' / '+c.llmModel:'المفتاح غير موجود';light('ttsLight',c.hasInworldKey);$('ttsReady').textContent=c.hasInworldKey?'جاهز':'مفتاح Inworld غير موجود';light('voiceLight',c.voiceId);$('voiceReady').textContent=c.voiceId||'غير محدد';$('modelChip').innerHTML=escape(c.ttsModel.toUpperCase())+' <span>+</span> '+escape(c.llmProvider.toUpperCase());}
async function chooseEmotion(mode){
  if(!S.config||!['off','auto','expressive'].includes(mode))return;
  $('emotionMode').disabled=true;$('voiceStyleStatus').textContent='جاري حفظ أسلوب الصوت...';
  try{const d=await api('settings',{emotionMode:mode});renderConfig(d.config);
    note(mode==='off'?'تم إيقاف توجيه المشاعر.':'تم تفعيل أسلوب الصوت: '+(mode==='expressive'?'مشاعر وأصوات بشرية تلقائيًا':'مشاعر تلقائية من Inworld'));}
  catch(e){renderConfig(S.config);note('تعذر حفظ المشاعر: '+e.message,true);}
  finally{$('emotionMode').disabled=false;}
}
function renderGateway(c){
  document.querySelectorAll('[data-gateway]').forEach(button=>{
    const active=button.dataset.gateway===c.llmProvider;
    button.classList.toggle('selected',active);button.setAttribute('aria-pressed',String(active));button.disabled=gatewaySaving;
  });
  const select=$('gatewayModel');const choices=models[c.llmProvider]||[];
  select.replaceChildren(...choices.map(id=>{const o=document.createElement('option');o.value=id;o.textContent=id+(c.llmProvider==='omnira-b'?' · '+(weightedB[id]||'2×'):'');return o;}));
  if(c.llmModel&&!choices.includes(c.llmModel)){const o=document.createElement('option');o.value=c.llmModel;o.textContent=c.llmModel+' · Custom';select.append(o);}
  select.value=c.llmModel||choices[0]||'';select.disabled=gatewaySaving;
  $('gatewayEndpointDisplay').textContent=urls[c.llmProvider]||'—';
  $('gatewayState').textContent=c.hasActiveLlmKey?'المفتاح جاهز · '+gatewayLabels[c.llmProvider]:'مفتاح البوابة غير مهيأ';
  $('gatewayState').classList.toggle('is-ready',!!c.hasActiveLlmKey);
}
async function chooseGateway(provider,model){
  if(gatewaySaving||!models[provider]||!S.config)return;
  const desired=model||rememberedModels[provider]||models[provider][0];
  if(S.config?.llmProvider===provider&&S.config?.llmModel===desired)return;
  gatewaySaving=true;renderGateway(S.config);
  $('gatewayState').textContent='جاري تفعيل البوابة...';
  try{const result=await api('settings',{llmProvider:provider,llmModel:desired});
    rememberedModels[provider]=desired;renderConfig(result.config);
    note('البوابة النشطة: '+gatewayLabels[provider]+' · '+desired+(result.config.hasActiveLlmKey?'':' · يلزم إضافة مفتاح API'));
  }catch(e){if(S.config)renderConfig(S.config);note('تعذر تغيير البوابة: '+e.message,true);}
  finally{gatewaySaving=false;if(S.config)renderGateway(S.config);}
}
function bubble(kind,text){$('transcript').querySelector('.empty-chat')?.remove();const b=document.createElement('div');b.className='bubble '+kind;const n=document.createElement('span');n.className='who';n.textContent=kind==='user'?'العميل':'AYO · آيو';const x=document.createElement('span');x.textContent=text||'';b.append(n,x);$('transcript').append(b);$('transcript').scrollTop=$('transcript').scrollHeight;return x;}
function samples(bytes){const out=new Uint8Array(bytes.length);for(let i=0;i<bytes.length;i++)out[i]=bytes.charCodeAt(i);return out;}
function wav(pieces,rate=24000){const size=pieces.reduce((a,p)=>a+p.length,0);const buf=new ArrayBuffer(size+44);const d=new DataView(buf);const str=(i,s)=>{for(let j=0;j<s.length;j++)d.setUint8(i+j,s.charCodeAt(j));};str(0,'RIFF');d.setUint32(4,size+36,true);str(8,'WAVE');str(12,'fmt ');d.setUint32(16,16,true);d.setUint16(20,1,true);d.setUint16(22,1,true);d.setUint32(24,rate,true);d.setUint32(28,rate*2,true);d.setUint16(32,2,true);d.setUint16(34,16,true);str(36,'data');d.setUint32(40,size,true);let pos=44;for(const p of pieces){new Uint8Array(buf).set(p,pos);pos+=p.length;}return new Blob([buf],{type:'audio/wav'});}
async function audioStart(){S.ctx??=new(window.AudioContext||window.webkitAudioContext)({latencyHint:'interactive'});await S.ctx.resume();if(!S.ctx._ayoOutputAnalyser){const analyser=S.ctx.createAnalyser();analyser.fftSize=2048;analyser.smoothingTimeConstant=.72;analyser.connect(S.ctx.destination);S.ctx._ayoOutputAnalyser=analyser;}avatar?.setOutput(S.ctx._ayoOutputAnalyser);}
function stopAudio(){for(const x of S.sources){try{x.stop()}catch{}}S.sources=[];S.playAt=0;avatar?.setStatus(micStream&&S.session?'listening':'idle');$('visualizer').classList.remove('active');$('orbWrap').className=S.session?'orb-wrap listening':'orb-wrap';}
function pcm(bytes,rate){if(!S.ctx)return;if(S.ctx.state==='suspended'){S.ctx.resume().catch(()=>{});}const count=bytes.length>>1;if(!count)return;const data=new Float32Array(count),v=new DataView(bytes.buffer,bytes.byteOffset,bytes.byteLength);for(let i=0;i<count;i++)data[i]=v.getInt16(i*2,true)/32768;const b=S.ctx.createBuffer(1,count,rate);b.copyToChannel(data);const src=S.ctx.createBufferSource();src.buffer=b;src.connect(S.ctx._ayoOutputAnalyser||S.ctx.destination);avatar?.setStatus('speaking');const start=Math.max(S.ctx.currentTime+.04,S.playAt||0);src.start(start);S.playAt=start+count/rate;S.sources.push(src);src.onended=()=>{S.sources=S.sources.filter(x=>x!==src);if(!S.sources.length&&!S.busy){avatar?.setStatus(micStream&&S.session?'listening':'idle');$('callStatus').textContent=S.session?'آيو بتسمعك':'جاهزة للتجربة';$('visualizer').classList.remove('active');$('orbWrap').className=S.session?'orb-wrap listening':'orb-wrap';if(S.session)startListen();}};$('visualizer').classList.add('active');$('orbWrap').className='orb-wrap speaking';$('callStatus').textContent='آيو بتتكلم';S.audio.push(bytes);$('downloadLast').disabled=false;}
async function ndjson(action,payload,onEvent){const ctrl=new AbortController();S.abort=ctrl;const r=await fetch('api.php?action='+action,{method:'POST',headers:{'Content-Type':'application/json','X-AYO-CSRF':S.csrf},body:JSON.stringify(payload),signal:ctrl.signal});if(!r.ok){const j=await r.json().catch(()=>({error:'HTTP '+r.status}));throw Error(j.error||'HTTP '+r.status);}const reader=r.body.getReader();const decoder=new TextDecoder();let buf='';try{while(true){const {value,done}=await reader.read();if(done)break;buf+=decoder.decode(value,{stream:true});let i;while((i=buf.indexOf('\n'))>=0){const line=buf.slice(0,i);buf=buf.slice(i+1);if(line.trim())onEvent(JSON.parse(line));}}if(buf.trim())onEvent(JSON.parse(buf));}finally{if(S.abort===ctrl)S.abort=null;}}
function readEvent(e,to){if(e.type==='error')throw Error(e.message);
 if(e.type==='tts_diagnostic'){log('Inworld: '+e.status+' · chunks='+e.chunks+' · bytes='+e.bytes+' · code='+e.errorCode);}
 if(e.type==='tts_notice'){note(e.message,true);}
if(e.type==='voice_style'){
  const cue=e.cue?' · الصوت البشري: '+e.cue:'';
  $('voiceStyleStatus').textContent=e.reasoningEnabled?'Inworld بيحدد الإحساس تلقائيًا'+cue:(e.mode==='off'?'صوت طبيعي بدون توجيه':'توجيه المشاعر غير مدعوم على Flash'+cue);
}if(e.type==='reasoning_instruction'){
  $('voiceStyleStatus').textContent='التوجيه الفعلي من Inworld: '+e.instruction;
}if(e.type==='llm_delta'){to.textContent+=e.text;S.lastReply+=e.text;$('transcript').scrollTop=$('transcript').scrollHeight;}$('audioStatus').textContent='PCM 24 kHz · HTTP Streaming';if(e.type==='audio'){pcm(samples(atob(e.content)),e.sampleRate||24000);$('audioStatus').textContent='PCM · '+S.audio.length+' chunks · '+(S.ctx?.state||'?');}if(e.type==='latency'){$(e.metric==='llmFirstTokenMs'?'metricLlm':'metricTts').textContent=e.value+' ms';}if(e.type==='audio_done'){log('Inworld TTS مكتمل: '+(e.chunks||S.audio.length)+' chunks / '+(e.audioSeconds||'?')+' seconds');S.chars+=e.chars||0;S.cost+=e.estimatedUsd||0;$('metricChars').textContent=S.chars.toLocaleString('en-US');$('metricCost').textContent='$'+S.cost.toFixed(5);}if(e.type==='complete')log('انتهى الطلب في '+e.totalMs+'ms');}
async function sendTurn(question){
 if(!question.trim())return;
 if(S.busy){note('في طلب شغال حاليًا؛ استنى لحد ما يخلص أو اضغط إيقاف الصوت.',true);return;}
 if(!S.config?.hasActiveLlmKey){bubble('agent','⚠ مفتاح بوابة الذكاء المختارة مش متوفر. افتح «مفاتيح API» واحفظ المفتاح مرة واحدة.');note('بوابة الذكاء المختارة ملهاش مفتاح محفوظ.',true);return;}
 try{
   if(!S.session){
     await api('start',{});
     S.session=true;S.remaining=120;setClock();
     clearInterval(S.clock);
     S.clock=setInterval(()=>{S.remaining--;setClock();if(S.remaining<=0)toggleCall();},1000);
     $('callText').textContent='إنهاء الجلسة';
     $('callStatus').textContent='جلسة شات نصي · مش محتاجة ميكروفون';
     note('بدأت جلسة محادثة نصية مستقلة، من غير مايكروفون.');
   }
 }catch(err){bubble('agent','⚠ تعذر بدء الشات: '+err.message);note('بدء الشات: '+err.message,true);return;}
 stopListen();S.busy=true;S.audio=[];S.lastReply='';bubble('user',question);const to=bubble('agent','');$('callStatus').textContent='آيو بتفكر...';$('question').value='';$('metricTts').textContent='—';$('metricLlm').textContent='—';try{await audioStart();if($('testMode').value==='mock'){to.textContent='أهلاً بحضرتك، استضافة المواقع عندنا بتبدأ من خمسة دولار وتسعة وتسعين سنت شهريًا. تحب تسمع تفاصيل الباقات؟';note('محاكاة فقط — لا يوجد اتصال LLM أو صوت مستنسخ.');}else await ndjson('turn',{question},e=>readEvent(e,to));S.history.push({question,answer:S.lastReply});S.turns++;$('metricTurns').textContent=S.turns; }catch(e){if(e.name!=='AbortError'){const message='⚠ '+(S.lastReply?'النص وصل، لكن باقي الطلب فشل: ':'فشل إرسال الطلب: ')+e.message;to.textContent+=(to.textContent?'\n':'')+message;note('فشل الرد: '+e.message,true);}}finally{S.busy=false;if(!S.sources.length&&S.session&&micStream?.active)startListen();}}
async function preview(){if(S.busy){S.abort?.abort();stopAudio();return;}if(!S.config?.hasInworldKey)return note('اضبط مفتاح Inworld من إعدادات الجلسة.',true);const text=$('previewText').value.trim();if(!text)return;S.busy=true;S.audio=[];$('previewBtn').textContent='■ إيقاف الصوت';$('previewStatus').textContent='جاري الاتصال بـInworld...';$('ayoPlayback').removeAttribute('src');$('ayoPlayback').load();
 let done=false;
 try{await audioStart();await ndjson('preview',{text},e=>{readEvent(e,{textContent:''});if(e.type==='audio')$('previewStatus').textContent='استلمنا '+S.audio.length+' جزء صوت · تشغيل مباشر (Web Audio: '+(S.ctx?.state||'?')+')';if(e.type==='audio_done')done=true;});
 if(!done)throw Error('التدفق انتهى قبل وصول إشارة اكتمال التوليد من Inworld');
 if(S.audio.length){const result=URL.createObjectURL(wav(S.audio));const player=$('ayoPlayback');if(player.dataset.ayoUrl)URL.revokeObjectURL(player.dataset.ayoUrl);player.dataset.ayoUrl=result;player.src=result;player.load();}
 $('previewStatus').textContent='اكتمل توليد '+S.audio.length+' جزء صوتي · لو الصوت مش مسموع استخدم مشغل WAV اللي تحت.';}catch(e){if(e.name!=='AbortError'){note('اختبار الصوت: '+e.message,true);$('previewStatus').textContent='فشل توليد الصوت بعد '+S.audio.length+' أجزاء: '+e.message;
 if(S.audio.length){const result=URL.createObjectURL(wav(S.audio));const player=$('ayoPlayback');if(player.dataset.ayoUrl)URL.revokeObjectURL(player.dataset.ayoUrl);player.dataset.ayoUrl=result;player.src=result;player.load();$('previewStatus').textContent+=' · ممكن تسمع الجزء المستلم من المشغل اللي تحت.';}
 }}finally{S.busy=false;$('previewBtn').textContent='▶ اسمع صوت آيو';}}
function setClock(){const m=String(Math.floor(S.remaining/60)).padStart(2,'0'),s=String(S.remaining%60).padStart(2,'0');$('clock').textContent=m+':'+s;}

function micState(text,state='') {
  $('micState').textContent=text;
  const el=$('micState').parentElement;
  el.classList.toggle('active',state==='active');
  el.classList.toggle('error',state==='error');
}
async function micPermissionDiagnostics(){
  const secure=!!window.isSecureContext;
  const supported=!!navigator.mediaDevices?.getUserMedia;
  let browserPermission='غير معروف';
  try{
    if(navigator.permissions?.query){
      const response=await navigator.permissions.query({name:'microphone'});
      browserPermission=({denied:'محظور من إعدادات المتصفح',prompt:'الموافقة مطلوبة من Chrome',granted:'مسموح في إعدادات المتصفح'})[response.state]||response.state;
    }
  }catch{browserPermission='المتصفح لا يوفر حالة الإذن';}
  const policy=document.permissionsPolicy||document.featurePolicy;
  let policyStatus='غير متاح';
  try{
    if(policy?.allowsFeature)policyStatus=policy.allowsFeature('microphone')?'مسموح على الصفحة':'محظور بسياسة أذونات الموقع';
  }catch{}
  const text='HTTPS: '+(secure?'نعم':'لا')+' · دعم الميكروفون: '+(supported?'نعم':'لا')+
     ' · حالة الإذن: '+browserPermission+' · سياسة الموقع: '+policyStatus;
  $('micDiagnosticResult').textContent=text;
  return {secure,supported,browserPermission,policyStatus};
}
async function openMic(){
  if(micStream?.active)return;
  if(!navigator.mediaDevices?.getUserMedia)throw Error('المتصفح لا يدعم فتح الميكروفون من هذه الصفحة؛ استخدم Chrome وافتح الموقع عبر HTTPS.');
  const micPromise=navigator.mediaDevices.getUserMedia({audio:{echoCancellation:true,noiseSuppression:true,autoGainControl:true}});
  micStream=await micPromise;
  await audioStart();
  micSource=S.ctx.createMediaStreamSource(micStream);
  micAnalyser=S.ctx.createAnalyser();
  micAnalyser.fftSize=2048;micAnalyser.smoothingTimeConstant=.62;
  micSource.connect(micAnalyser);
  avatar?.setMicrophone(micAnalyser);
  micState('الميكروفون متصل · الإذن متاح','active');
  $('enableMicBtn').classList.add('enabled');$('enableMicBtn').innerHTML='✓ الميكروفون متفعل <small>اضغط لتغيير الأذونات</small>';
  const data=new Uint8Array(micAnalyser.fftSize);
  if(micMeterTimer)clearInterval(micMeterTimer);
  micMeterTimer=setInterval(()=>{
    if(!micAnalyser)return;
    micAnalyser.getByteTimeDomainData(data);
    let sum=0;for(let i=0;i<data.length;i+=4){const x=(data[i]-128)/128;sum+=x*x;}
    const level=Math.min(1,Math.sqrt(sum/(data.length/4))*10);
    $('micMeter').style.transform='scaleX('+level.toFixed(3)+')';
  },85);
}
function closeMic(){
  micTurnDetector?.stop();micTurnDetector=null;
  clearInterval(micMeterTimer);micMeterTimer=null;
  try{micSource?.disconnect();}catch{}
  micSource=null;micAnalyser=null;
  for(const track of micStream?.getTracks()||[])track.stop();
  micStream=null;avatar?.setMicrophone(null);avatar?.setStatus('idle');
  $('micMeter').style.transform='scaleX(0)';
  micState('الميكروفون غير متصل');
  $('enableMicBtn').classList.remove('enabled');$('enableMicBtn').innerHTML='🎙 تفعيل الميكروفون <small>اضغط واسمح للمتصفح</small>';
}

async function toggleCall(){
 if(S.session){
   S.abort?.abort();stopListen();stopAudio();S.session=false;clearInterval(S.clock);closeMic();
   $('callText').textContent='ابدأ المكالمة';$('callStatus').textContent='جاهزة للتجربة';
   $('muteBtn').disabled=true;$('interruptBtn').disabled=true;
   await api('end',{}).catch(()=>{});return;
 }
 if(!micStream?.active){startAfterMic=true;$('micPermissionDialog').hidden=false;return;}
 if(!S.config?.hasInworldKey||!S.config?.hasActiveLlmKey){
   note('لازم تحفظ مفاتيح Inworld والبوابة المختارة مرة واحدة في «مفاتيح API» قبل المكالمة الحقيقية.',true);
   $('configBody').classList.remove('hidden');return;
 }
 try {
   await audioStart();await api('start',{});
   S.session=true;recognitionUnavailable=false;S.remaining=120;setClock();
   S.clock=setInterval(()=>{S.remaining--;setClock();if(S.remaining<=0)toggleCall();},1000);
   $('callText').textContent='إنهاء المكالمة';$('muteBtn').disabled=false;$('interruptBtn').disabled=false;
   micState('الميكروفون جاهز · منتظر الكلام','active');
   note('ابدأ الكلام. مستوى الصوت بيتحرك، والكلام هيتحول لنص قبل الرد.');
   startListen();
 }catch(e){note('بدء المكالمة: '+e.message,true);}
}
function stopListen(){
 micTurnDetector?.pause();
 S.recogWanted=false;try{S.recog?.abort()}catch{}
}
function startListen(){
 if(!S.session||S.muted||S.busy||S.sources.length||!micStream)return;
 avatar?.setStatus('listening');
 if($('sttMode').value==='inworld'){
   if(!S.config?.hasInworldKey){micState('مفتاح Inworld STT غير متاح','error');return;}
   try{
     if(!micTurnDetector)micTurnDetector=createMicTurns({
       stream:micStream,analyser:micAnalyser,csrf:()=>S.csrf,
       onText:async text=>{
         $('interim').textContent='سمعت: '+text;
         micState('تم فهم الكلام: '+text,'active');
         sendTurn(text);
       },
       onStatus:state=>{
         const states={listening:'آيو بتسمعك · اتكلم',recording:'آيو سامعاك · جاري تسجيل الكلام',transcribing:'جاري تحويل كلامك لنص',recognized:'تم فهم الكلام',paused:'متوقف أثناء رد آيو',idle:'في انتظار تفعيل الميكروفون','no-speech':'ماسمعتش كلام واضح، جرّب تاني'};
         if(['listening','recording','transcribing','recognized','no-speech'].includes(state)){
           $('callStatus').textContent=states[state];
           micState(states[state],state==='no-speech'?'error':'active');
         }
         log('STT: '+state);
       },
       onError:message=>{
         micState('خطأ في التفريغ الصوتي','error');
         note('Inworld STT: '+message,true);
       }
     });
     micTurnDetector.resume();$('orbWrap').className='orb-wrap listening';
   }catch(e){note('تعذر تشغيل التفريغ الصوتي: '+e.message,true);micState('STT غير متاح','error');}
   return;
 }
 const R=window.SpeechRecognition||window.webkitSpeechRecognition;
 if(!R||recognitionUnavailable){micState('SpeechRecognition غير مدعوم؛ اختار Inworld STT','error');return;}
 if(S.recogWanted)return;
 const rec=new R();S.recog=rec;rec.lang='ar-EG';rec.interimResults=true;rec.continuous=false;S.recogWanted=true;
 rec.onresult=e=>{let final='',interim='';for(let i=e.resultIndex;i<e.results.length;i++){
   const txt=e.results[i][0].transcript;if(e.results[i].isFinal)final+=txt;else interim+=txt;
 }$('interim').textContent=interim;
 if(final.trim()){$('interim').textContent='سمعت: '+final.trim();S.recogWanted=false;sendTurn(final.trim());}};
 rec.onerror=e=>{if(e.error!=='aborted'){recognitionUnavailable=true;micState('SpeechRecognition فشل؛ التحويل إلى Inworld STT','error');$('sttMode').value='inworld';setTimeout(startListen,300);}};
 rec.onend=()=>{S.recogWanted=false;if(S.session&&!S.busy&&!S.muted&&!S.sources.length&&!recognitionUnavailable)setTimeout(startListen,450);};
 try{rec.start();$('callStatus').textContent='آيو بتسمعك';}
 catch(e){recognitionUnavailable=true;$('sttMode').value='inworld';startListen();}
}
async function record(){if(S.recording){const r=S.recording;S.recording=null;clearTimeout(r.timer);r.worklet.port.postMessage('flush');await new Promise(x=>setTimeout(x,150));r.stream.getTracks().forEach(x=>x.stop());await r.ctx.close();S.sample=new File([wav(r.parts,r.rate)],'ayo-sample.wav',{type:'audio/wav'});$('fileLabel').textContent='تسجيل العينة جاهز';$('recordBtn').textContent='● تسجيل عينة';return;}try{const stream=await navigator.mediaDevices.getUserMedia({audio:true});const ctx=new AudioContext({sampleRate:16000});await ctx.audioWorklet.addModule('audio-worklet.js');const source=ctx.createMediaStreamSource(stream),worklet=new AudioWorkletNode(ctx,'ayo-pcm-capture'),mute=ctx.createGain();mute.gain.value=0;source.connect(worklet).connect(mute).connect(ctx.destination);const parts=[];worklet.port.onmessage=e=>parts.push(new Uint8Array(e.data));S.recording={stream,ctx,worklet,parts,rate:ctx.sampleRate,timer:setTimeout(record,30000)};$('recordBtn').textContent='■ وقف التسجيل';note('سجّل عينة واضحة 10–30 ثانية');}catch(e){note('التسجيل: '+e.message,true);}}
async function clone(){const f=S.sample||$('voiceFile').files[0];if(!f)return note('اختر ملف MP3 أو WAV أولاً.',true);if(!$('sampleConsent').checked)return note('لازم تؤكد إن الصوت ملكك أو لديك تصريح.',true);if(!S.config?.hasInworldKey)return note('اضبط مفتاح Inworld.',true);const form=new FormData();form.append('sample',f);form.append('consent','yes');$('cloneResult').textContent='جارٍ استنساخ الصوت...';try{const r=await fetch('api.php?action=clone',{method:'POST',headers:{'X-AYO-CSRF':S.csrf},body:form});const d=await r.json();if(!r.ok)throw Error(d.error||'خطأ');$('cloneResult').textContent='Voice ID: '+d.voiceId;renderConfig(d.config);note('تم استنساخ الصوت بنجاح');}catch(e){note('استنساخ الصوت: '+e.message,true);}}
async function main(){avatar=createAyoAvatar($('ayoCharacterCanvas'),$('orbWrap'));for(let i=0;i<44;i++){const bar=document.createElement('i');bar.style.setProperty('--h',`${7+(i*13%29)}px`);bar.style.setProperty('--dur',`${450+i*61%600}ms`);$('visualizer').append(bar);}$('toggleConfig').onclick=()=>{$('configBody').classList.toggle('hidden');$('toggleConfig').textContent=$('configBody').classList.contains('hidden')?'إظهار':'إخفاء';};document.querySelectorAll('[data-gateway]').forEach(button=>button.onclick=()=>chooseGateway(button.dataset.gateway));$('gatewayModel').onchange=()=>chooseGateway(S.config.llmProvider,$('gatewayModel').value);$('gatewaySettingsButton').onclick=()=>{$('configBody').classList.remove('hidden');$('toggleConfig').textContent='إخفاء';$('configForm').scrollIntoView({behavior:'smooth',block:'start'});};$('emotionMode').onchange=()=>chooseEmotion($('emotionMode').value);document.querySelectorAll('[data-voice-cue]').forEach(button=>button.onclick=()=>{const cue=button.dataset.voiceCue;if(!['sigh','laugh','breathe','gasp','groan'].includes(cue))return;const current=$('previewText').value.trim().replace(/^\[(?:sigh|laugh|breathe|gasp|groan)\]\s*/i,'');$('previewText').value='['+cue+'] '+current;$('previewText').focus();$('previewStatus').textContent='مؤثر ['+cue+'] أُضيف لبداية النص. اضغط «اسمع صوت آيو» لتجربته.';});$('llmProvider').onchange=()=>presets();$('llmModelPreset').onchange=()=>$('llmModel').value=$('llmModelPreset').value;$('configForm').onsubmit=async e=>{e.preventDefault();const payload={voiceId:$('voiceId').value,ttsModel:$('ttsModel').value,ttsLocale:$('ttsLocale').value,ttsDelivery:$('ttsDelivery').value,llmProvider:$('llmProvider').value,llmModel:$('llmModel').value};for(const [id,k] of [['inworldKey','inworldKey'],['apinexKey','apinexKey'],['omniraKey','omniraKey']])if($(id).value.trim())payload[k]=$(id).value.trim();try{const d=await api('settings',payload);renderConfig(d.config);for(const k of ['inworldKey','apinexKey','omniraKey'])$(k).value='';note('اتحفظت مفاتيح الخدمات بشكل دائم على الاستضافة (خارج public_html). مش هتحتاج تدخلها مرة تانية.');}catch(e){note(e.message,true);}};$('ownerForm').onsubmit=async e=>{
   e.preventDefault();$('ownerError').textContent='';
   const code=$('ownerCode').value.trim();
   try{const result=await api('auth',{ownerCode:code});$('ownerCode').value='';$('ownerOverlay').hidden=true;
     renderConfig(result.config);$('connection').textContent='تم دخول مالك الديمو';
     note('تم تفعيل جلسة المالك. لو المفاتيح كانت في نفس الجلسة القديمة، اتنقلت للتخزين الدائم على الاستضافة.');
     if(!result.config.hasInworldKey||(!result.config.hasApinexKey&&!result.config.hasOmniraKey)){
       $('configBody').classList.remove('hidden');$('toggleConfig').textContent='إخفاء';
       note('ضيف المفاتيح الناقصة مرة واحدة فقط واضغط «حفظ إعدادات الجلسة».');
     }
   }catch(error){$('ownerError').textContent=error.message;}
 };
 $('enableMicBtn').onclick=()=>{
   if(micStream?.active){note('الميكروفون متفعّل؛ لما تبدأ المكالمة آيو هتسمع كلامك.');return;}
   $('micPermissionError').textContent='';$('micPermissionDialog').hidden=false;micPermissionDiagnostics();
 };
 $('micShowGuide').onclick=async()=>{
   $('micRecoveryPanel').hidden=false;await micPermissionDiagnostics();
 };
 $('micHelpToggle').onclick=()=>{$('micRecoveryPanel').hidden=true;};
 $('micRetryBtn').onclick=()=>{$('micDialogApprove').click();};
 document.addEventListener('visibilitychange',()=>{
   if(!document.hidden&&!$('micPermissionDialog').hidden)micPermissionDiagnostics();
 });
 $('micDialogCancel').onclick=()=>{startAfterMic=false;$('micPermissionDialog').hidden=true;};
 $('micDialogApprove').onclick=async()=>{
   const btn=$('micDialogApprove');btn.disabled=true;$('micPermissionError').textContent='';
   micState('جاري طلب إذن الميكروفون...');
   try{await openMic();$('micPermissionDialog').hidden=true;note('المايكروفون اتفعّل وبيستقبل صوتك بالفعل.');
     if(startAfterMic){startAfterMic=false;toggleCall();}
   }catch(err){
      const category=err?.name||'UnknownError';
      const messages={
        NotAllowedError:'Chrome أو أندرويد مانع الميكروفون. مش لازم يظهر رمز القفل في شريط العنوان؛ افتح إرشادات أندرويد اللي تحت.',
        SecurityError:'المتصفح حظر وصول الصفحة للميكروفون بسبب إعدادات الأمان.',
        NotFoundError:'الموبايل مش شايف جهاز ميكروفون متاح.',
        NotReadableError:'الميكروفون مشغول أو تعذّر فتحه. اقفل تطبيقات التسجيل والمكالمات وجرّب تاني.',
        OverconstrainedError:'المايكروفون مش متوافق مع إعدادات التسجيل المطلوبة.',
        AbortError:'تم إلغاء محاولة فتح الميكروفون.'
      };
      const msg=messages[category]||('فشل فتح الميكروفون ('+category+'): '+(err.message||'تعذّر الوصول'));
      $('micPermissionError').textContent=msg;
      $('micRecoveryPanel').hidden=false;
      const checks=await micPermissionDiagnostics();
      if(!checks.secure)$('micDiagnosticResult').textContent+=' · الصفحة لازم تفتح بـ HTTPS';
      micState('فشل إذن الميكروفون · '+category,'error');
      note('الميكروفون: '+msg,true);
   }finally{btn.disabled=false;}
 };
 $('sttMode').onchange=()=>{stopListen();if(S.session)startListen();};
 $('callBtn').onclick=toggleCall;$('muteBtn').onclick=()=>{S.muted=!S.muted;$('muteIcon').textContent=S.muted?'×':'◉';if(S.muted)stopListen();else startListen();};$('interruptBtn').onclick=()=>{S.abort?.abort();stopAudio();S.busy=false;startListen();};$('clearBtn').onclick=()=>{$('transcript').replaceChildren();};$('composer').onsubmit=e=>{e.preventDefault();sendTurn($('question').value.trim());};$('previewBtn').onclick=preview;$('recordBtn').onclick=record;$('cloneBtn').onclick=clone;$('voiceFile').onchange=e=>{$('fileLabel').textContent=e.target.files[0]?.name||'اختيار ملف عينة الصوت';S.sample=null;};$('downloadLast').onclick=()=>{if(!S.audio.length)return;const url=URL.createObjectURL(wav(S.audio));const a=document.createElement('a');a.href=url;a.download='AYO-output.wav';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);};
 try{const d=await api('status');S.csrf=d.csrf;renderConfig(d.config);$('connection').classList.add('online');$('connection').textContent='السيرفر متصل';if(!d.ownerAuthorized){$('ownerOverlay').hidden=false;$('ownerCode').focus();}const k=await api('knowledge');$('knowledge').innerHTML=k.plans.filter(p=>['shared','reseller','vps'].includes(p.category)).slice(0,10).map(p=>`<div class="knowledge-item"><span>${escape(p.name)} <small>${escape(p.category)}</small></span><strong>$${p.monthly_usd}<small> / شهر</small></strong></div>`).join('');note(d.ownerAuthorized?'AYO جاهز؛ تقدر تفعّل الميكروفون وتبدأ المكالمة. المفاتيح محفوظة على الاستضافة عند إعدادها.':'ادخل برمز المالك مرة واحدة، وبعدها نحتفظ بالمفاتيح على الاستضافة.');}catch(e){note('السيرفر: '+e.message,true);}}
main();
