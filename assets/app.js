import { Client } from "https://cdn.jsdelivr.net/npm/@gradio/client/dist/index.min.js";

const $ = s => document.querySelector(s);
const convo = $('#conversation');
const callBtn = $('#callBtn');
const stopBtn = $('#stopBtn');
const orb = $('#orb');
const SR = window.SpeechRecognition || window.webkitSpeechRecognition;

const VOICETUT_SPACES = [
  'mohammedaly22/VoiceTut-TTS',
  'a7mid/VoiceTut-TTS',
  'danibrahim/VoiceTut-TTS',
  'ahmedffffff/VoiceTut-TTS'
];

let rec = null;
let active = false;
let speaking = false;
let busy = false;
let restartTimer = null;
let currentAudio = null;
let previousInteractionId = null;
let chatHistory = [];
let lastTranscript = '';
let lastTranscriptAt = 0;
const clients = new Map();

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
    $('#ttsState').textContent = 'VoiceTut متعدد المصادر';
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

function withTimeout(promise, ms, label) {
  let timer;
  return Promise.race([
    promise.finally(() => clearTimeout(timer)),
    new Promise((_, reject) => {
      timer = setTimeout(() => reject(new Error(label + ' timeout')), ms);
    })
  ]);
}

async function connectSpace(space) {
  if (!clients.has(space)) {
    clients.set(space, withTimeout(Client.connect(space), 18000, space + ' connect'));
  }
  return clients.get(space);
}

async function resolveEndpoint(client) {
  try {
    const api = await withTimeout(client.view_api(), 10000, 'view_api');
    const names = Object.keys(api?.named_endpoints || {});
    return names.find(n => /run_b_oneshot/i.test(n))
        || names.find(n => /oneshot/i.test(n))
        || names.find(n => /run_b/i.test(n))
        || '/run_b_oneshot';
  } catch (e) {
    return '/run_b_oneshot';
  }
}

function findAudioUrl(node) {
  if (!node) return null;
  if (typeof node === 'string') {
    return /^https?:\/\//i.test(node) ? node : null;
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
  const speaker = $('#speaker').value;
  const errors = [];

  for (const space of VOICETUT_SPACES) {
    try {
      $('#ttsState').textContent = 'VoiceTut: ' + space.split('/')[0] + '…';
      const client = await connectSpace(space);
      const endpoint = await resolveEndpoint(client);

      const result = await withTimeout(
        client.predict(endpoint, [
          speaker,
          text,
          'العربية (Egyptian)',
          48,
          2.5,
          0.95,
          true
        ]),
        50000,
        space + ' predict'
      );

      const url = findAudioUrl(result?.data);
      if (!url) throw new Error('no audio url');

      $('#ttsState').textContent = 'VoiceTut: ' + speaker;
      await playUrl(url);
      return;
    } catch (e) {
      errors.push(space + ': ' + (e?.message || e));
      clients.delete(space);
    }
  }

  throw new Error(errors.join(' | '));
}

async function edgeFallback(text) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 30000);
  try {
    const r = await fetch('api/tts.php', {
      method: 'POST',
      signal: controller.signal,
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        text,
        speaker: $('#speaker').value,
        rate: '-4%'
      })
    });

    if (!r.ok) {
      const j = await r.json().catch(() => ({}));
      throw new Error(j.detail || j.error || 'edge fallback failed');
    }

    $('#ttsState').textContent = r.headers.get('X-TTS-Engine') || 'Edge Egyptian fallback';
    const blob = await r.blob();
    if (!blob.size) throw new Error('empty Edge audio');

    const url = URL.createObjectURL(blob);
    try {
      await playUrl(url);
    } finally {
      URL.revokeObjectURL(url);
    }
  } finally {
    clearTimeout(timer);
  }
}

async function speak(text) {
  speaking = true;
  state('برد عليك…', 'speaking');
  safeStopRec();

  try {
    await voiceTutSpeak(text);
  } catch (e) {
    console.warn('All VoiceTut spaces failed:', e);
    try {
      $('#ttsState').textContent = 'VoiceTut غير متاح — Edge…';
      await edgeFallback(text);
    } catch (e2) {
      console.warn('Edge fallback failed:', e2);
      $('#ttsState').textContent = 'Device fallback';
      await browserSpeak(text);
    }
  } finally {
    speaking = false;
    busy = false;
    if (active) setTimeout(startRec, 500);
  }
}

function shortError(j) {
  if (j?.status === 429 || j?.detail?.code === 429 || j?.interactions_http === 429) return '429: حصة Gemini';
  if (j?.status) return 'HTTP ' + j.status;
  if (j?.interactions_http) return 'HTTP ' + j.interactions_http;
  return j?.error || 'Gemini error';
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
        previous_interaction_id: previousInteractionId,
        history: chatHistory.slice(-10)
      })
    });

    const j = await r.json();
    if (!r.ok || !j.ok) {
      $('#aiState').textContent = shortError(j);
      console.error('Gemini API detail:', j);
      throw Object.assign(new Error(shortError(j)), {api: j});
    }

    if (j.interaction_id) previousInteractionId = j.interaction_id;
    $('#aiState').textContent = (j.model || 'Gemini 3.8') + ' · ' + (j.route || '');
    msg('bot', j.answer);

    chatHistory.push({role:'user', text});
    chatHistory.push({role:'assistant', text:j.answer});
    chatHistory = chatHistory.slice(-12);

    await speak(j.answer);
  } catch (e) {
    if (!e?.api) $('#aiState').textContent = e?.message || 'Gemini error';
    const t = 'حصل خطأ وأنا بكلم جوجل. جرّب تاني.';
    msg('bot', t);
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
    if (!t) return;

    const now = Date.now();
    if (t === lastTranscript && now - lastTranscriptAt < 5000) return;
    lastTranscript = t;
    lastTranscriptAt = now;

    busy = true;
    safeStopRec();
    msg('user', t);
    think(t);
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
  chatHistory = [];
  lastTranscript = '';
  lastTranscriptAt = 0;
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

  connectSpace(VOICETUT_SPACES[0]).catch(() => {});
});
