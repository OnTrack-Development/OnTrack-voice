import { GoogleGenAI, Modality } from "https://cdn.jsdelivr.net/npm/@google/genai@2.25.0/+esm";
import { validateMission, CustomerSpeechGate, EXAMPLE_MISSION } from './outbound.mjs?v=060';

const $ = s => document.querySelector(s);
const $$ = s => [...document.querySelectorAll(s)];

const callBtn = $('#callBtn');
const stopBtn = $('#stopBtn');
const muteBtn = $('#muteBtn');
const muteText = $('#muteText');
const muteIcon = $('#muteIcon');
const micState = $('#micState');
const liveState = $('#liveState');
const modelState = $('#modelState');
const voiceState = $('#voiceState');
const voiceSelect = $('#voice');
const transcript = $('#transcript');
const clearTranscript = $('#clearTranscript');
const orb = $('#orb');
const engineBadge = $('#engineBadge');
const statusDot = $('#statusDot');
const sessionBadge = $('#sessionBadge');
const callTimer = $('#callTimer');
const chatPopup = $('#chatPopup');
const popupMuteBtn = $('#popupMuteBtn');
const popupMuteIcon = $('#popupMuteIcon');
const popupMuteText = $('#popupMuteText');
const popupStopBtn = $('#popupStopBtn');
const chatCloseBtn = $('#chatCloseBtn');
const chatReopenBtn = $('#chatReopenBtn');
const callMode = $('#callMode');
const editMissionBtn = $('#editMissionBtn');
const missionDialog = $('#missionDialog');
const missionForm = $('#missionForm');
const armCallBtn = $('#armCallBtn');
let mission = null;
let sessionMission = null;
let sessionMode = 'outbound';
let outputGate = null;
let callGeneration = 0;
let messageQueue = Promise.resolve();

function showError(message, target = $('#callError')) {
  target.textContent = message;
  target.hidden = !message;
}

function syncMissionUI() {
  const outbound = callMode.value === 'outbound';
  editMissionBtn.hidden = !outbound;
  $('#callTitle').textContent = outbound ? 'جهّز مكالمة العميل.' : 'اتكلم مع أون تراك.';
  $('#callDescription').textContent = outbound
    ? 'حدد هوية الإيجنت والعرض وحدود التفاوض، ثم اتصل بالعميل من Phone Link.'
    : 'مكالمة صوتية مباشرة مع مساعد يعرف خدمات أون تراك وبيانات الديمو.';
  $('#callBtnText').textContent = outbound ? 'جهّز الإيجنت' : 'ابدأ المكالمة';
  $('#missionSummary').hidden = !outbound;
  $('#missionSummary').textContent = mission
    ? `${mission.agent_name} · ${mission.company_name} ← ${mission.customer_name} | ${mission.offer_name}${mission.customer_phone ? ' | ' + mission.customer_phone : ''}`
    : 'ابدأ بإعداد بيانات العميل والعرض.';
  $('#missionSummary').title = $('#missionSummary').textContent;
  $('#callNote').textContent = outbound
    ? 'جهّز الإيجنت، رن من Phone Link، وبعد الرد فعّل السماع. إنهاء الإيجنت لا يقفل مكالمة الهاتف.'
    : 'تجربة ببيانات Demo فقط — متبعتش كلمات مرور أو بيانات بنكية.';
}

function openMission() {
  if (active) return;
  showError('', $('#missionError'));
  missionDialog.showModal();
}

editMissionBtn.addEventListener('click', openMission);
$('#closeMissionBtn').addEventListener('click', () => missionDialog.close());
$('#exampleMissionBtn').addEventListener('click', () => {
  for (const [key, value] of Object.entries(EXAMPLE_MISSION)) missionForm.elements.namedItem(key).value = value;
  showError('', $('#missionError'));
});
missionForm.addEventListener('submit', event => {
  event.preventDefault();
  if (active || !missionForm.reportValidity()) return;
  try {
    mission = Object.freeze(validateMission(Object.fromEntries([...new FormData(missionForm)].map(([key, value]) => [key, value.trim()]))));
    missionDialog.close();
    showError('');
    syncMissionUI();
  } catch (error) { showError(error.message, $('#missionError')); }
});
callMode.addEventListener('change', syncMissionUI);

let liveSession = null;
let micStream = null;
let inputCtx = null;
let outputCtx = null;
let inputSource = null;
let processor = null;
let muteGain = null;
let active = false;
let muted = false;
let greetingPending = false;
let liveReady = false;
let nextPlayTime = 0;
let callStartedAt = null;
let timerId = null;
let currentUserText = '';
let currentAiText = '';
let currentUserBubble = null;
let currentAiBubble = null;
let lastUserIntent = '';
let lastServiceContext = null;
const playingSources = new Set();

const VOICE_KEY = 'ontrack_live_voice_v1';

const LINK_CATALOG = {
  site: {
    label: 'فتح موقع أون تراك',
    url: 'https://ontrackegy.com/',
    primary: false
  },
  portal: {
    label: 'فتح بوابة العملاء',
    url: 'https://services.ontrackegy.com/',
    primary: false
  },
  whatsapp: {
    label: 'فتح WhatsApp Automation',
    url: 'https://whatsapp.ontrackegy.com/',
    primary: false
  },
  starter: {
    label: 'اطلب Starter Plan',
    url: 'https://services.ontrackegy.com/cart.php?a=add&pid=6',
    primary: true,
    aliases: ['starter plan', 'starter', 'shared hosting', 'استضافة مشتركة']
  },
  reseller: {
    label: 'اطلب Reseller 15 users',
    url: 'https://services.ontrackegy.com/cart.php?a=add&pid=75',
    primary: true,
    aliases: ['reseller 15 users', 'reseller 15', 'reseller', 'ريسلر']
  },
  support: {
    label: 'اطلب الدعم والصيانة',
    url: 'https://services.ontrackegy.com/cart.php?a=add&pid=74',
    primary: true,
    aliases: ['support & website maintenance', 'support and website maintenance', 'دعم وصيانة', 'صيانة الموقع', 'صيانة مواقع']
  }
};

function state(text, cls='') {
  $('#orbText').textContent = text;
  orb.className = 'voice-core ' + cls;
}

function setStatus(el, text) {
  if (el) el.textContent = text;
}

function setSessionState(mode, label) {
  sessionBadge.textContent = label;
  sessionBadge.classList.toggle('live', mode === 'live');
  statusDot.classList.toggle('live', mode === 'live');
  statusDot.classList.toggle('error', mode === 'error');
  document.body.classList.toggle('in-call', mode === 'live');
}

function toastState(text) {
  setStatus(engineBadge, text);
}

function openChatPopup(reset=true) {
  if (reset) resetTranscript();
  chatPopup.classList.add('is-open');
  chatPopup.setAttribute('aria-hidden', 'false');
  if (chatReopenBtn) chatReopenBtn.hidden = true;
}

function closeChatPopup(showReopen = active) {
  chatPopup.classList.remove('is-open');
  chatPopup.setAttribute('aria-hidden', 'true');
  if (chatReopenBtn) chatReopenBtn.hidden = !showReopen;
}

function formatTime(seconds) {
  const m = Math.floor(seconds / 60).toString().padStart(2, '0');
  const s = Math.floor(seconds % 60).toString().padStart(2, '0');
  return `${m}:${s}`;
}

function startTimer() {
  callStartedAt = Date.now();
  callTimer.textContent = '00:00';
  clearInterval(timerId);
  timerId = setInterval(() => {
    if (!callStartedAt) return;
    callTimer.textContent = formatTime((Date.now() - callStartedAt) / 1000);
  }, 500);
}

function stopTimer() {
  clearInterval(timerId);
  timerId = null;
  callStartedAt = null;
  callTimer.textContent = '00:00';
}

function arrayBufferToBase64(buffer) {
  const bytes = new Uint8Array(buffer);
  let binary = '';
  const chunk = 0x8000;
  for (let i = 0; i < bytes.length; i += chunk) {
    binary += String.fromCharCode(...bytes.subarray(i, Math.min(i + chunk, bytes.length)));
  }
  return btoa(binary);
}

function resampleTo16k(input, inputRate) {
  if (inputRate === 16000) return input.slice();

  const ratio = inputRate / 16000;
  const outLength = Math.max(1, Math.floor(input.length / ratio));
  const output = new Float32Array(outLength);

  for (let i = 0; i < outLength; i++) {
    const start = Math.floor(i * ratio);
    const end = Math.min(input.length, Math.floor((i + 1) * ratio));
    let sum = 0;
    let count = 0;

    for (let j = start; j < end; j++) {
      sum += input[j];
      count++;
    }

    output[i] = count ? sum / count : (input[Math.min(start, input.length - 1)] || 0);
  }

  return output;
}

function floatToPCM16(float32) {
  const buffer = new ArrayBuffer(float32.length * 2);
  const view = new DataView(buffer);

  for (let i = 0; i < float32.length; i++) {
    const s = Math.max(-1, Math.min(1, float32[i]));
    view.setInt16(i * 2, s < 0 ? s * 0x8000 : s * 0x7fff, true);
  }

  return buffer;
}

function base64ToFloat32PCM16(base64) {
  const binary = atob(base64);
  const len = Math.floor(binary.length / 2);
  const out = new Float32Array(len);

  for (let i = 0; i < len; i++) {
    const lo = binary.charCodeAt(i * 2);
    const hi = binary.charCodeAt(i * 2 + 1);
    let value = (hi << 8) | lo;
    if (value & 0x8000) value -= 0x10000;
    out[i] = value / 32768;
  }

  return out;
}

function stopPlayback() {
  for (const src of playingSources) {
    try { src.stop(); } catch (_) {}
  }
  playingSources.clear();
  nextPlayTime = outputCtx ? outputCtx.currentTime : 0;
}

async function enqueueAudio(base64, sampleRate=24000, generation=callGeneration) {
  if (!outputCtx) outputCtx = new (window.AudioContext || window.webkitAudioContext)();
  if (outputCtx.state === 'suspended') await outputCtx.resume();
  if (!active || generation !== callGeneration) return;

  const pcm = base64ToFloat32PCM16(base64);
  if (!pcm.length) return;

  const buffer = outputCtx.createBuffer(1, pcm.length, sampleRate);
  buffer.getChannelData(0).set(pcm);

  const src = outputCtx.createBufferSource();
  src.buffer = buffer;
  src.connect(outputCtx.destination);

  const now = outputCtx.currentTime;
  const startAt = Math.max(now + 0.02, nextPlayTime);
  src.start(startAt);
  nextPlayTime = startAt + buffer.duration;

  playingSources.add(src);
  src.onended = () => playingSources.delete(src);
}

function ensureTranscriptStarted() {
  $('#emptyTranscript')?.remove();
}

function makeBubble(role) {
  ensureTranscriptStarted();
  const el = document.createElement('div');
  el.className = 'msg ' + role;
  const label = document.createElement('small');
  label.textContent = role === 'user'
    ? (sessionMode === 'outbound' ? sessionMission.customer_name : 'أنت')
    : (sessionMode === 'outbound' ? sessionMission.agent_name : 'OnTrack AI');
  el.append(label, document.createElement('span'));
  transcript.appendChild(el);
  return el;
}

function mergeText(current, next) {
  next = String(next || '').trim();
  if (!next) return current;
  if (!current) return next;
  if (next.startsWith(current)) return next;
  if (current.endsWith(next)) return current;
  return (current + ' ' + next).replace(/\s+/g, ' ').trim();
}

function normalizeText(text) {
  return String(text || '').toLowerCase().replace(/\s+/g, ' ').trim();
}

function serviceFromText(text) {
  const normalized = normalizeText(text);

  for (const id of ['starter', 'reseller', 'support']) {
    const item = LINK_CATALOG[id];
    if (item.aliases?.some(alias => normalized.includes(alias))) {
      return id;
    }
  }

  return null;
}

function buildContextLinks(userText, aiText) {
  const user = normalizeText(userText);
  const ai = normalizeText(aiText);
  const combined = `${user} ${ai}`;
  const ids = new Set();

  const service = serviceFromText(combined);
  if (service) {
    ids.add(service);
    lastServiceContext = service;
  }

  if (/(موقع أون تراك|موقعكم|لينك الموقع|رابط الموقع|ontrack website)/i.test(combined)) {
    ids.add('site');
  }

  if (/(بوابة العملاء|بوابه العملاء|client portal|دخول العميل|حساب العميل)/i.test(combined)) {
    ids.add('portal');
  }

  if (/(whatsapp automation|منصة واتساب|منصه واتساب|خدمة واتساب|خدمه واتساب)/i.test(combined)) {
    ids.add('whatsapp');
  }

  const explicitLinkRequest = /(رابط|لينك|link|order|اطلب|طلب|شراء|اشتري)/i.test(user);
  if (explicitLinkRequest && ids.size === 0 && lastServiceContext) {
    ids.add(lastServiceContext);
  }

  return [...ids].map(id => ({id, ...LINK_CATALOG[id]})).filter(x => x.url);
}

function attachContextLinks(bubble, userText, aiText) {
  if (sessionMode === 'outbound') return;
  if (!bubble) return;

  const links = buildContextLinks(userText, aiText);
  if (!links.length) return;

  const old = bubble.querySelector('.message-links');
  if (old) old.remove();

  const wrap = document.createElement('div');
  wrap.className = 'message-links';

  links.forEach(link => {
    const a = document.createElement('a');
    a.className = 'message-link' + (link.primary ? ' primary' : '');
    a.href = link.url;
    a.target = '_blank';
    a.rel = 'noopener noreferrer';
    a.innerHTML = `<span>${link.label}</span><span class="arrow">↗</span>`;
    wrap.appendChild(a);
  });

  bubble.appendChild(wrap);
}

function updateTranscript(role, text) {
  if (!text) return;

  if (role === 'user') {
    currentUserText = mergeText(currentUserText, text);
    lastUserIntent = currentUserText;
    if (!currentUserBubble) currentUserBubble = makeBubble('user');
    currentUserBubble.querySelector('span').textContent = currentUserText;
    currentUserBubble.scrollIntoView({behavior:'smooth', block:'end'});
    return;
  }

  currentAiText = mergeText(currentAiText, text);
  const service = serviceFromText(currentAiText);
  if (service) lastServiceContext = service;

  if (!currentAiBubble) currentAiBubble = makeBubble('bot');
  currentAiBubble.querySelector('span').textContent = currentAiText;
  currentAiBubble.scrollIntoView({behavior:'smooth', block:'end'});
}

function closeTranscriptTurn() {
  currentUserText = '';
  currentAiText = '';
  currentUserBubble = null;
  currentAiBubble = null;
}

function resetTranscript() {
  transcript.innerHTML = `
    <div class="empty-state" id="emptyTranscript">
      <div class="empty-icon">⌁</div>
      <strong>المحادثة هتظهر هنا</strong>
      <span>ابدأ المكالمة واتكلم طبيعي، والنص هيتسجل أثناء الجلسة.</span>
    </div>`;
  currentUserText = '';
  currentAiText = '';
  currentUserBubble = null;
  currentAiBubble = null;
  lastUserIntent = '';
  lastServiceContext = null;
}

async function startMicrophone(generation = callGeneration) {
  if (micStream || !liveSession) return;

  const stream = await navigator.mediaDevices.getUserMedia({
    audio: {
      channelCount: 1,
      echoCancellation: true,
      noiseSuppression: true,
      autoGainControl: true
    }
  });
  if (!active || generation !== callGeneration) {
    stream.getTracks().forEach(track => track.stop());
    return;
  }
  micStream = stream;

  inputCtx = new (window.AudioContext || window.webkitAudioContext)();
  await inputCtx.resume();
  if (!active || generation !== callGeneration) return;

  inputSource = inputCtx.createMediaStreamSource(micStream);
  processor = inputCtx.createScriptProcessor(4096, 1, 1);
  muteGain = inputCtx.createGain();
  muteGain.gain.value = 0;

  processor.onaudioprocess = ev => {
    if (!active || generation !== callGeneration || !liveSession || muted || !outputGate?.armed) return;

    const input = ev.inputBuffer.getChannelData(0);
    const pcm16k = resampleTo16k(input, inputCtx.sampleRate);
    const pcm = floatToPCM16(pcm16k);

    try { liveSession.sendRealtimeInput({
      audio: {
        data: arrayBufferToBase64(pcm),
        mimeType: 'audio/pcm;rate=16000'
      }
    }); } catch (error) {
      showError('انقطع إرسال الصوت. جهّز جلسة جديدة.');
      stopCall(false);
    }
  };

  inputSource.connect(processor);
  processor.connect(muteGain);
  muteGain.connect(inputCtx.destination);

  syncMicStatus();
  setStatus(liveState, sessionMode === 'outbound' ? 'جاهز — فعّل السماع بعد رد العميل' : 'متصل ومستعد');
  setStatus($('#chatCallState'), sessionMode === 'outbound' ? 'الإيجنت جاهز — السماع غير مفعّل' : 'المكالمة جارية');
  setSessionState('live', sessionMode === 'outbound' ? 'PREPARED' : 'LIVE');
  state(sessionMode === 'outbound' ? 'جاهز للعميل' : 'سامعك…', sessionMode === 'outbound' ? '' : 'listening');

  muteBtn.disabled = false;
  if (popupMuteBtn) popupMuteBtn.disabled = false;
}

async function stopMicrophone() {
  if (processor) {
    processor.onaudioprocess = null;
    try { processor.disconnect(); } catch (_) {}
    processor = null;
  }

  if (inputSource) {
    try { inputSource.disconnect(); } catch (_) {}
    inputSource = null;
  }

  if (muteGain) {
    try { muteGain.disconnect(); } catch (_) {}
    muteGain = null;
  }

  if (micStream) {
    micStream.getTracks().forEach(t => t.stop());
    micStream = null;
  }

  if (inputCtx) {
    try { await inputCtx.close(); } catch (_) {}
    inputCtx = null;
  }

  setStatus(micState, 'متوقف');
}

async function getEphemeralSession() {
  const r = await fetch('api/live_session.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(sessionMode === 'outbound' ? {mode: 'outbound', mission: sessionMission} : {mode: 'demo'}),
    cache: 'no-store'
  });

  const j = await r.json();

  if (!r.ok || !j.ok) {
    throw new Error(j.detail || j.error || 'تعذر تجهيز المكالمة');
  }

  return j;
}

async function handleLiveContent(content, generation) {
  if (!active || generation !== callGeneration) return;

  if (content?.inputTranscription?.text) {
    updateTranscript('user', content.inputTranscription.text);
    if (sessionMode === 'outbound' && outputGate.heardCustomer) {
      armCallBtn.textContent = 'السماع مفعّل — المحادثة جارية';
      setStatus($('#chatCallState'), 'المحادثة جارية');
    }
  }

  if (content?.outputTranscription?.text) {
    updateTranscript('bot', content.outputTranscription.text);
  }

  if (content?.interrupted) {
    stopPlayback();
    setStatus(liveState, 'سامع المقاطعة');
    state('سامعك…', 'listening');
  }

  const parts = content?.modelTurn?.parts || [];

  for (const part of parts) {
    const inline = part.inlineData;
    if (!inline?.data) continue;

    const mime = inline.mimeType || 'audio/pcm;rate=24000';
    if (!mime.startsWith('audio/pcm')) continue;

    const match = /rate=(\d+)/i.exec(mime);
    const rate = match ? Number(match[1]) : 24000;

    await enqueueAudio(inline.data, rate, generation);
    if (!active || generation !== callGeneration) return;
    setStatus(liveState, sessionMode === 'outbound' ? `${sessionMission.agent_name} بيرد` : 'أون تراك بيرد');
    state('بيرد عليك…', 'speaking');
  }

  if (content?.turnComplete) {
    attachContextLinks(currentAiBubble, lastUserIntent, currentAiText);
    closeTranscriptTurn();

    if (greetingPending) {
      greetingPending = false;
      setStatus(liveState, 'الصوت جاهز');
      state('بفتح الميكروفون…');

      try {
        await startMicrophone(generation);
      } catch (err) {
        console.error('Microphone error:', err);
        const permissionDenied = err?.name === 'NotAllowedError' || err?.name === 'SecurityError';

        setStatus(micState, permissionDenied ? 'الإذن مرفوض' : 'خطأ');
        setStatus(
          liveState,
          permissionDenied ? 'اسمح بالميكروفون وجرب تاني' : 'تعذر تجهيز الميكروفون'
        );
        setSessionState('error', 'MIC ERROR');
        state(permissionDenied ? 'محتاج إذن الميكروفون' : 'خطأ في الميكروفون');
        showError(permissionDenied ? 'اسمح باستخدام الميكروفون وجرب تاني.' : 'تعذر تشغيل مدخل الصوت. راجع إعدادات المتصفح.');
        await stopCall(false);
      }
      return;
    }

    if (active) {
      setListeningState();
    }
  }
}

function setListeningState() {
  const waiting = sessionMode === 'outbound' && !outputGate?.heardCustomer;
  const prepared = sessionMode === 'outbound' && !outputGate?.armed;
  setStatus(liveState, prepared ? 'جاهز — فعّل السماع بعد رد العميل' : waiting ? 'منتظر كلام العميل' : 'متصل ومستعد');
  state(muted ? 'السماع مكتوم' : prepared ? 'جاهز للعميل' : waiting ? 'منتظر العميل…' : 'سامعك…', muted || prepared ? '' : 'listening');
}

async function armOutbound() {
  if (!active || sessionMode !== 'outbound' || !liveSession || !micStream || outputGate.armed) return;
  outputGate.arm();
  syncMicStatus();
  armCallBtn.disabled = true;
  armCallBtn.textContent = 'السماع مفعّل — منتظر كلام العميل';
  startTimer();
  setStatus($('#chatCallState'), 'السماع مفعّل');
  setListeningState();
  openChatPopup(false);
}
armCallBtn.addEventListener('click', armOutbound);

function syncMicStatus() {
  const track = micStream?.getAudioTracks?.()[0];
  const isLive = !!track && track.readyState === 'live';

  if (!isLive) {
    setStatus(micState, active ? 'غير متاح' : 'متوقف');
    return;
  }

  setStatus(micState, muted || !track.enabled ? 'مكتوم' : sessionMode === 'outbound' && !outputGate?.armed ? 'جاهز — لا يرسل صوتاً' : 'شغال');
}

function setMuted(next) {
  muted = !!next;

  if (micStream) {
    micStream.getAudioTracks().forEach(track => {
      track.enabled = !muted;
    });
  }

  muteText.textContent = muted ? 'فتح الميكروفون' : 'كتم الميكروفون';
  muteIcon.textContent = muted ? '○' : '◉';

  if (popupMuteText) popupMuteText.textContent = muted ? 'فتح الميكروفون' : 'كتم';
  if (popupMuteIcon) popupMuteIcon.textContent = muted ? '○' : '◉';

  syncMicStatus();

  if (active) {
    setListeningState();
  }
}

async function startCall() {
  if (active) return;
  if (callMode.value === 'outbound' && !mission) { openMission(); return; }

  if (!navigator.mediaDevices?.getUserMedia) {
    showError('المتصفح لا يدعم الميكروفون. افتح الصفحة في Chrome على HTTPS.');
    return;
  }

  active = true;
  const generation = ++callGeneration;
  sessionMode = callMode.value;
  sessionMission = sessionMode === 'outbound' ? Object.freeze({...mission}) : null;
  outputGate = new CustomerSpeechGate(sessionMode === 'outbound');
  messageQueue = Promise.resolve();
  showError('');
  $('#chatAgentName').textContent = sessionMission?.agent_name || 'OnTrack AI';
  setStatus($('#chatCallState'), 'جاري تجهيز الجلسة');
  liveReady = false;
  greetingPending = sessionMode === 'demo';
  setMuted(false);
  openChatPopup(true);
  if (sessionMode === 'outbound') closeChatPopup(true);

  callBtn.disabled = true;
  stopBtn.disabled = false;
  muteBtn.disabled = true;
  if (popupStopBtn) popupStopBtn.disabled = false;
  if (popupMuteBtn) popupMuteBtn.disabled = true;
  voiceSelect.disabled = true;
  callMode.disabled = true;
  editMissionBtn.disabled = true;
  armCallBtn.hidden = sessionMode !== 'outbound';
  armCallBtn.disabled = true;
  armCallBtn.textContent = 'العميل رد — فعّل انتظار صوته';

  const selectedVoice = voiceSelect.value;
  localStorage.setItem(VOICE_KEY, selectedVoice);
  setStatus(voiceState, selectedVoice);

  if (sessionMode === 'demo') startTimer();
  setSessionState('live', 'CONNECTING');
  state('بجهز المكالمة…');
  setStatus(micState, 'منتظر الاتصال');
  setStatus(liveState, 'بيجهز جلسة آمنة');
  toastState('جاري بدء المكالمة');

  try {
    if (!outputCtx) outputCtx = new (window.AudioContext || window.webkitAudioContext)();
    await outputCtx.resume();
    if (!active || generation !== callGeneration) return;
    nextPlayTime = outputCtx.currentTime;

    const info = await getEphemeralSession();
    if (!active || generation !== callGeneration) return;
    setStatus(modelState, info.model);

    const ai = new GoogleGenAI({ apiKey: info.token });

    setStatus(liveState, 'بيتصل بـ Gemini Live');

    const connectedSession = await ai.live.connect({
      model: info.model,
      config: {
        responseModalities: [Modality.AUDIO],
        speechConfig: {
          voiceConfig: {
            prebuiltVoiceConfig: {
              voiceName: selectedVoice
            }
          }
        },
        systemInstruction: info.system_instruction,
        inputAudioTranscription: {},
        outputAudioTranscription: {}
      },
      callbacks: {
        onopen: () => {
          if (!active || generation !== callGeneration) return;
          liveReady = true;
          setStatus(liveState, 'تم الاتصال');
          toastState('المكالمة متصلة');
        },
        onmessage: message => {
          messageQueue = messageQueue.then(async () => {
            if (!active || generation !== callGeneration || !message.serverContent) return;
            for (const content of outputGate.accept(message.serverContent)) {
              await handleLiveContent(content, generation);
            }
          }).catch(async error => {
            if (!active || generation !== callGeneration) return;
            console.error('Live message error:', error);
            showError('تعذر تشغيل الرد الصوتي أو التعرف على كلام العميل. راجع مدخل الصوت وجهّز جلسة جديدة.');
            await stopCall(false);
          });
        },
        onerror: error => {
          if (!active || generation !== callGeneration) return;
          console.error('Gemini Live error:', error);
          setStatus(liveState, 'حصل خطأ في الاتصال');
          setSessionState('error', 'ERROR');
          toastState('حصل خطأ — جرب تاني');
          state('خطأ في الاتصال');
          showError('حصل خطأ في الاتصال بخدمة الصوت. جهّز جلسة جديدة.');
          stopCall(false);
        },
        onclose: event => {
          if (!active || generation !== callGeneration) return;
          const reason = event?.reason ? ': ' + event.reason : '';
          console.debug('Gemini Live closed', event);
          setStatus(liveState, 'انتهى الاتصال' + reason);

          if (active) {
            showError('انتهت جلسة الإيجنت. لو مكالمة الهاتف ما زالت شغالة، اقفلها من Phone Link أو جهّز جلسة جديدة.');
            state('انتهت الجلسة');
            stopCall(false);
          }
        }
      }
    });
    if (!active || generation !== callGeneration) { connectedSession.close(); return; }
    liveSession = connectedSession;

    if (sessionMode === 'outbound') {
      await startMicrophone(generation);
      if (!active || generation !== callGeneration) return;
      armCallBtn.disabled = false;
      toastState('الإيجنت جاهز؛ اتصل بالعميل ثم فعّل السماع');
      return;
    }

    setStatus(liveState, 'بيجهز الصوت');
    state('أون تراك بيبدأ…');

    liveSession.sendRealtimeInput({
      text: 'ابدأ المكالمة بتحية مصرية قصيرة واحترافية باسم أون تراك، وبعدها توقف واسمع العميل.'
    });

  } catch (err) {
    if (!active || generation !== callGeneration) return;
    console.error('Start Live failed:', err);
    setStatus(liveState, 'تعذر بدء المكالمة');
    setSessionState('error', 'FAILED');
    toastState('تعذر بدء المكالمة');
    state('جرب مرة تانية');
    showError(err?.name === 'NotAllowedError' ? 'اسمح باستخدام الميكروفون في المتصفح وجرب تاني.' : (err.message || 'تعذر تجهيز الجلسة. جرب تاني.'));
    await stopCall(false);
  }
}

async function stopCall(userInitiated=true) {
  const wasActive = active;

  active = false;
  callGeneration++;
  liveReady = false;
  greetingPending = false;

  try {
    if (liveSession) {
      liveSession.sendRealtimeInput({ audioStreamEnd: true });
    }
  } catch (_) {}

  await stopMicrophone();
  stopPlayback();

  if (liveSession) {
    try { liveSession.close(); } catch (_) {}
    liveSession = null;
  }

  stopTimer();
  setMuted(false);

  callBtn.disabled = false;
  stopBtn.disabled = true;
  muteBtn.disabled = true;
  if (popupStopBtn) popupStopBtn.disabled = true;
  if (popupMuteBtn) popupMuteBtn.disabled = true;
  voiceSelect.disabled = false;
  callMode.disabled = false;
  editMissionBtn.disabled = false;
  armCallBtn.hidden = true;
  armCallBtn.disabled = true;

  setSessionState('ready', 'READY');
  closeChatPopup(!!transcript.querySelector('.msg'));
  setStatus($('#chatCallState'), 'الإيجنت متوقف — نص المكالمة للمراجعة');
  if (!userInitiated) { setStatus(liveState, 'الجلسة متوقفة — راجع التنبيه'); state('الجلسة متوقفة'); }

  if (userInitiated && wasActive) {
    setStatus(liveState, 'جاهز لمكالمة جديدة');
    toastState('جاهز للتجربة');
    state('جاهز');
  }
}

callBtn.addEventListener('click', startCall);
stopBtn.addEventListener('click', () => stopCall(true));
muteBtn.addEventListener('click', () => setMuted(!muted));

popupStopBtn?.addEventListener('click', () => stopCall(true));
popupMuteBtn?.addEventListener('click', () => setMuted(!muted));
chatCloseBtn?.addEventListener('click', () => closeChatPopup(true));
chatReopenBtn?.addEventListener('click', () => openChatPopup(false));

voiceSelect.addEventListener('change', () => {
  localStorage.setItem(VOICE_KEY, voiceSelect.value);
  setStatus(voiceState, voiceSelect.value);
});


clearTranscript.addEventListener('click', resetTranscript);

window.addEventListener('beforeunload', () => {
  active = false;
  try { liveSession?.close(); } catch (_) {}
  micStream?.getTracks().forEach(t => t.stop());
});

(function init() {
  syncMissionUI();
  const savedVoice = localStorage.getItem(VOICE_KEY);
  if (savedVoice && [...voiceSelect.options].some(o => o.value === savedVoice)) {
    voiceSelect.value = savedVoice;
  }

  setStatus(voiceState, voiceSelect.value);
  setSessionState('ready', 'READY');
  closeChatPopup(false);

  fetch('api/status.php', {cache:'no-store'})
    .then(r => r.json())
    .then(j => {
      setStatus(modelState, j.model || 'gemini-3.8-live');
      engineBadge.textContent = j.gemini_configured ? 'جاهز للتجربة' : 'الإعداد غير مكتمل';
    })
    .catch(() => {
      engineBadge.textContent = 'تعذر فحص الخدمة';
      setSessionState('error', 'OFFLINE');
    });
})();
