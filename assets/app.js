import { GoogleGenAI, Modality } from "https://cdn.jsdelivr.net/npm/@google/genai@2.25.0/+esm";

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
}

function closeChatPopup() {
  chatPopup.classList.remove('is-open');
  chatPopup.setAttribute('aria-hidden', 'true');
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

async function enqueueAudio(base64, sampleRate=24000) {
  if (!outputCtx) outputCtx = new (window.AudioContext || window.webkitAudioContext)();
  if (outputCtx.state === 'suspended') await outputCtx.resume();

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
  el.innerHTML = `<small>${role === 'user' ? 'أنت' : 'OnTrack AI'}</small><span></span>`;
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

async function startMicrophone() {
  if (micStream || !liveSession) return;

  micStream = await navigator.mediaDevices.getUserMedia({
    audio: {
      channelCount: 1,
      echoCancellation: true,
      noiseSuppression: true,
      autoGainControl: true
    }
  });

  inputCtx = new (window.AudioContext || window.webkitAudioContext)();
  await inputCtx.resume();

  inputSource = inputCtx.createMediaStreamSource(micStream);
  processor = inputCtx.createScriptProcessor(4096, 1, 1);
  muteGain = inputCtx.createGain();
  muteGain.gain.value = 0;

  processor.onaudioprocess = ev => {
    if (!active || !liveSession || muted) return;

    const input = ev.inputBuffer.getChannelData(0);
    const pcm16k = resampleTo16k(input, inputCtx.sampleRate);
    const pcm = floatToPCM16(pcm16k);

    liveSession.sendRealtimeInput({
      audio: {
        data: arrayBufferToBase64(pcm),
        mimeType: 'audio/pcm;rate=16000'
      }
    });
  };

  inputSource.connect(processor);
  processor.connect(muteGain);
  muteGain.connect(inputCtx.destination);

  setStatus(micState, 'بيسمعك');
  setStatus(liveState, 'متصل ومستعد');
  setSessionState('live', 'LIVE');
  state('سامعك…', 'listening');

  muteBtn.disabled = false;
  $$('.idea').forEach(b => b.disabled = false);
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
    body: '{}',
    cache: 'no-store'
  });

  const j = await r.json();

  if (!r.ok || !j.ok) {
    throw new Error(j.detail || j.error || 'تعذر تجهيز المكالمة');
  }

  return j;
}

async function handleLiveMessage(message) {
  const content = message.serverContent;

  if (content?.inputTranscription?.text) {
    updateTranscript('user', content.inputTranscription.text);
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

    await enqueueAudio(inline.data, rate);
    setStatus(liveState, 'أون تراك بيرد');
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
        await startMicrophone();
      } catch (err) {
        console.error('Microphone error:', err);
        setStatus(micState, 'مرفوض');
        setStatus(liveState, 'اسمح بالميكروفون وجرب تاني');
        setSessionState('error', 'MIC ERROR');
        state('محتاج إذن الميكروفون');
      }
      return;
    }

    if (active) {
      setStatus(liveState, 'متصل ومستعد');
      state(muted ? 'الميكروفون مكتوم' : 'سامعك…', muted ? '' : 'listening');
    }
  }
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
  setStatus(micState, muted ? 'مكتوم' : (active ? 'بيسمعك' : 'متوقف'));

  if (active) {
    state(muted ? 'الميكروفون مكتوم' : 'سامعك…', muted ? '' : 'listening');
  }
}

async function startCall() {
  if (active) return;

  if (!navigator.mediaDevices?.getUserMedia) {
    toastState('المتصفح لا يدعم الميكروفون');
    return;
  }

  active = true;
  liveReady = false;
  greetingPending = true;
  setMuted(false);
  openChatPopup(true);

  callBtn.disabled = true;
  stopBtn.disabled = false;
  muteBtn.disabled = true;
  voiceSelect.disabled = true;
  $$('.idea').forEach(b => b.disabled = true);

  const selectedVoice = voiceSelect.value;
  localStorage.setItem(VOICE_KEY, selectedVoice);
  setStatus(voiceState, selectedVoice);

  startTimer();
  setSessionState('live', 'CONNECTING');
  state('بجهز المكالمة…');
  setStatus(micState, 'منتظر الاتصال');
  setStatus(liveState, 'بيجهز جلسة آمنة');
  toastState('جاري بدء المكالمة');

  try {
    if (!outputCtx) outputCtx = new (window.AudioContext || window.webkitAudioContext)();
    await outputCtx.resume();
    nextPlayTime = outputCtx.currentTime;

    const info = await getEphemeralSession();
    setStatus(modelState, info.model);

    const ai = new GoogleGenAI({ apiKey: info.token });

    setStatus(liveState, 'بيتصل بـ Gemini Live');

    liveSession = await ai.live.connect({
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
          liveReady = true;
          setStatus(liveState, 'تم الاتصال');
          toastState('المكالمة متصلة');
        },
        onmessage: message => {
          handleLiveMessage(message).catch(err => console.error('Live message error:', err));
        },
        onerror: error => {
          console.error('Gemini Live error:', error);
          setStatus(liveState, 'حصل خطأ في الاتصال');
          setSessionState('error', 'ERROR');
          toastState('حصل خطأ — جرب تاني');
          state('خطأ في الاتصال');
        },
        onclose: event => {
          const reason = event?.reason ? ': ' + event.reason : '';
          console.debug('Gemini Live closed', event);
          setStatus(liveState, 'انتهى الاتصال' + reason);

          if (active) {
            state('انتهت الجلسة');
            stopCall(false);
          }
        }
      }
    });

    setStatus(liveState, 'بيجهز الصوت');
    state('أون تراك بيبدأ…');

    liveSession.sendRealtimeInput({
      text: 'ابدأ المكالمة بتحية مصرية قصيرة واحترافية باسم أون تراك، وبعدها توقف واسمع العميل.'
    });

  } catch (err) {
    console.error('Start Live failed:', err);
    setStatus(liveState, 'تعذر بدء المكالمة');
    setSessionState('error', 'FAILED');
    toastState('تعذر بدء المكالمة');
    state('جرب مرة تانية');
    await stopCall(false);
  }
}

async function stopCall(userInitiated=true) {
  const wasActive = active;

  active = false;
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
  voiceSelect.disabled = false;
  $$('.idea').forEach(b => b.disabled = true);

  setSessionState('ready', 'READY');
  closeChatPopup();

  if (userInitiated && wasActive) {
    setStatus(liveState, 'جاهز لمكالمة جديدة');
    toastState('جاهز للتجربة');
    state('جاهز');
  }
}

callBtn.addEventListener('click', startCall);
stopBtn.addEventListener('click', () => stopCall(true));
muteBtn.addEventListener('click', () => setMuted(!muted));

voiceSelect.addEventListener('change', () => {
  localStorage.setItem(VOICE_KEY, voiceSelect.value);
  setStatus(voiceState, voiceSelect.value);
});

$$('.idea').forEach(btn => {
  btn.disabled = true;
  btn.addEventListener('click', () => {
    if (!active || !liveSession || !liveReady) {
      toastState('ابدأ المكالمة الأول');
      return;
    }

    const prompt = btn.dataset.prompt || btn.textContent.trim();
    if (!prompt) return;

    closeTranscriptTurn();
    lastUserIntent = prompt;

    const bubble = makeBubble('user');
    bubble.querySelector('span').textContent = prompt;
    bubble.scrollIntoView({behavior:'smooth', block:'end'});

    liveSession.sendRealtimeInput({ text: prompt });
  });
});

clearTranscript.addEventListener('click', resetTranscript);

window.addEventListener('beforeunload', () => {
  active = false;
  try { liveSession?.close(); } catch (_) {}
  micStream?.getTracks().forEach(t => t.stop());
});

(function init() {
  const savedVoice = localStorage.getItem(VOICE_KEY);
  if (savedVoice && [...voiceSelect.options].some(o => o.value === savedVoice)) {
    voiceSelect.value = savedVoice;
  }

  setStatus(voiceState, voiceSelect.value);
  setSessionState('ready', 'READY');
  closeChatPopup();

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
