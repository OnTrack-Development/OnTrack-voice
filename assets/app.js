import { Client } from "https://cdn.jsdelivr.net/npm/@gradio/client/dist/index.min.js";

const $ = s => document.querySelector(s);
const convo = $('#conversation');
const callBtn = $('#callBtn');
const stopBtn = $('#stopBtn');
const orb = $('#orb');
const SR = window.SpeechRecognition || window.webkitSpeechRecognition;

let rec = null;
let active = false;
let speaking = false;
let busy = false;
let restartTimer = null;
let currentAudio = null;
let previousInteractionId = null;
let voiceTutClientPromise = null;
let voiceTutEndpoint = null;

function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function msg(role, text) {
  const d = document.createElement('div');
  d.className = 'msg ' + role;
  d.innerHTML = `<small>${role === 'user' ? 'أنت' : 'OnTrack AI'}</small>${escapeHtml(text)}`;
  convo.appendChild(d);
  d.scrollIntoView({behavior: 'smooth', block: 'end'});
}

function state(text, cls = '') {
  $('#orbText').textContent = text;
  orb.className = 'orb ' + cls;
}

async function status() {
  try {
    const r = await fetch('api/status.php', {cache: 'no-store'});
    const j = await r.json();
    $('#engineBadge').textContent = j.gemini_configured ? 'Gemini 3.8 + VoiceTut' : 'Gemini غير مضبوط';
    $('#aiState').textContent = j.gemini_configured ? j.gemini_model : 'غير مضبوط';
    $('#ttsState').textContent = 'VoiceTut مباشر';
  } catch (e) {
    $('#engineBadge').textContent = 'تعذر فحص المحركات';
  }
}

function stopAudio() {
  if (currentAudio) {
    try { currentAudio.pause(); } catch (e) {}
    currentAudio = null;
  }
  try { speechSynthesis.cancel(); } catch (e) {}
}

function browserSpeak(text) {
  return new Promise(resolve => {
    if (!('speechSynthesis' in window)) return resolve();
    const u = new SpeechSynthesisUtterance(text);
    u.lang = 'ar-EG';
    const voices = speechSynthesis.getVoices();
    const arEg = voices.find(v => /ar[-_]EG/i.test(v.lang));
    const ar = arEg || voices.find(v => /^ar/i.test(v.lang));
    if (ar) u.voice = ar;
    u.rate = 0.96;
    u.onend = resolve;
    u.onerror = resolve;
    speechSynthesis.cancel();
    speechSynthesis.speak(u);
  });
}

async function getVoiceTutClient() {
  if (!voiceTutClientPromise) {
    voiceTutClientPromise = Client.connect('mohammedaly22/VoiceTut-TTS', {
      events: ['status', 'data']
    });
  }
  const client = await voiceTutClientPromise;
  if (!voiceTutEndpoint) {
    try {
      const api = await client.view_api();
      const named = api?.named_endpoints || {};
      const names = Object.keys(named);
      voiceTutEndpoint =
        names.find(n => n.includes('run_b_oneshot')) ||
        names.find(n => /oneshot/i.test(n)) ||
        '/run_b_oneshot';
    } catch (e) {
      voiceTutEndpoint = '/run_b_oneshot';
    }
  }
  return client;
}

function findAudioUrl(node) {
  if (!node) return null;
  if (typeof node === 'string') {
    if (/^https?:\/\//i.test(node)) return node;
    return null;
  }
  if (Array.isArray(node)) {
    for (const child of node) {
      const found = findAudioUrl(child);
      if (found) return found;
    }
    return null;
  }
  if (typeof node === 'object') {
    if (typeof node.url === 'string' && /^https?:\/\//i.test(node.url)) return node.url;
    if (typeof node.path === 'string' && /^https?:\/\//i.test(node.path)) return node.path;
    for (const child of Object.values(node)) {
      const found = findAudioUrl(child);
      if (found) return found;
    }
  }
  return null;
}

async function playUrl(url) {
  await new Promise((resolve, reject) => {
    const a = new Audio(url);
    currentAudio = a;
    a.onended = () => { currentAudio = null; resolve(); };
    a.onerror = () => { currentAudio = null; reject(new Error('audio_playback_failed')); };
    a.play().catch(err => { currentAudio = null; reject(err); });
  });
}

async function voiceTutSpeak(text) {
  const client = await getVoiceTutClient();
  const speaker = $('#speaker').value;
  $('#ttsState').textContent = 'VoiceTut: في الطابور…';

  const result = await client.predict(voiceTutEndpoint, [
    speaker,
    text,
    'العربية (Egyptian)',
    48,
    2.5,
    0.95,
    true
  ]);

  const url = findAudioUrl(result?.data);
  if (!url) throw new Error('VoiceTut returned no playable audio URL');

  $('#ttsState').textContent = 'VoiceTut: ' + speaker;
  await playUrl(url);
}

async function speak(text) {
  speaking = true;
  state('برد عليك…', 'speaking');
  safeStopRec();

  try {
    await voiceTutSpeak(text);
  } catch (e) {
    console.warn('VoiceTut failed:', e);
    $('#ttsState').textContent = 'VoiceTut غير متاح — Device fallback';
    await browserSpeak(text);
  } finally {
    speaking = false;
    busy = false;
    if (active) setTimeout(startRec, 450);
  }
}

async function think(text) {
  busy = true;
  state('بفكر…');
  $('#aiState').textContent = 'Gemini 3.8 بيفكر…';

  try {
    const r = await fetch('api/brain.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        text,
        previous_interaction_id: previousInteractionId
      })
    });

    const j = await r.json();
    if (!r.ok || !j.ok) throw new Error(j.detail || j.error || 'brain_failed');

    if (j.interaction_id) previousInteractionId = j.interaction_id;
    $('#aiState').textContent = j.model || 'Gemini 3.8';
    msg('bot', j.answer);
    await speak(j.answer);
  } catch (e) {
    console.error(e);
    $('#aiState').textContent = 'Gemini error';
    busy = true;
    const detail = String(e?.message || 'unknown error');
    const t = 'حصل خطأ وأنا بكلم جوجل. جرّب تاني.';
    msg('bot', t);
    console.warn('Gemini detail:', detail);
    await speak(t);
  }
}

function buildRec() {
  if (!SR) return null;
  const r = new SR();
  r.lang = 'ar-EG';
  r.interimResults = false;
  r.continuous = false;
  r.maxAlternatives = 1;

  r.onstart = () => {
    $('#micState').textContent = 'بيسمعك';
    state('سامعك…', 'listening');
  };

  r.onresult = e => {
    if (busy || speaking) return;
    const t = e.results[0][0].transcript.trim();
    if (t) {
      busy = true;
      safeStopRec();
      msg('user', t);
      think(t);
    }
  };

  r.onerror = e => {
    if (e.error === 'aborted') {
      $('#sttState').textContent = 'جاهز';
      return;
    }
    $('#sttState').textContent = e.error;
    if (active && !speaking && !busy && e.error !== 'not-allowed' && e.error !== 'service-not-allowed') restart();
  };

  r.onend = () => {
    if (active && !speaking && !busy) restart();
  };

  return r;
}

function restart() {
  if (!active || speaking || busy) return;
  clearTimeout(restartTimer);
  restartTimer = setTimeout(startRec, 650);
}

function startRec() {
  if (!active || speaking || busy) return;
  if (!rec) rec = buildRec();
  if (!rec) return;
  try { rec.start(); } catch (e) { restart(); }
}

function safeStopRec() {
  clearTimeout(restartTimer);
  if (rec) {
    try { rec.abort(); } catch (e) {}
  }
  $('#micState').textContent = 'متوقف';
}

callBtn.onclick = async () => {
  if (!SR) {
    alert('المتصفح ده لا يدعم Speech Recognition. استخدم Chrome أو Edge حديث عبر HTTPS.');
    return;
  }

  active = true;
  busy = true;
  previousInteractionId = null;
  callBtn.disabled = true;
  stopBtn.disabled = false;
  $('#sttState').textContent = 'ar-EG';

  const hello = 'أهلاً بيك في أون تراك. أنا الديمو الصوتي، اتفضل اسألني عن الخدمات أو فواتير الديمو.';
  msg('bot', hello);
  await speak(hello);
};

stopBtn.onclick = () => {
  active = false;
  speaking = false;
  busy = false;
  previousInteractionId = null;
  safeStopRec();
  stopAudio();
  callBtn.disabled = false;
  stopBtn.disabled = true;
  state('انتهت المكالمة');
};

window.addEventListener('load', () => {
  status();
  if (!SR) $('#sttState').textContent = 'غير مدعوم';
  else $('#sttState').textContent = 'جاهز';
  getVoiceTutClient().catch(e => console.warn('VoiceTut preload:', e));
});
