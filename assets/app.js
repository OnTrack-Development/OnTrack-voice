const $ = s => document.querySelector(s);
const convo = $('#conversation');
const callBtn = $('#callBtn');
const stopBtn = $('#stopBtn');
const orb = $('#orb');
const SR = window.SpeechRecognition || window.webkitSpeechRecognition;

let rec = null;
let active = false;
let speaking = false;
let restartTimer = null;
let currentAudio = null;
let chatHistory = [];

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
    $('#engineBadge').textContent = j.gemini_configured ? 'Gemini + Free Edge TTS' : 'Gemini غير مضبوط';
    $('#aiState').textContent = j.gemini_configured ? j.gemini_model : 'غير مضبوط';
    $('#ttsState').textContent = j.tts_engine || 'No-key TTS';
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
    const gender = $('#gender')?.value || 'male';

    const egyptian = voices.filter(v => /ar[-_]EG/i.test(v.lang));
    const arabic = voices.filter(v => /^ar([-_]|$)/i.test(v.lang));

    const maleHints = /male|man|shakir|hossam|omar|ahmed|mohamed/i;
    const femaleHints = /female|woman|salma|hanan|yasmin|omnia|asmaa/i;

    let preferred = null;
    if (gender === 'female') {
      preferred = egyptian.find(v => femaleHints.test(v.name)) || egyptian[0] ||
                  arabic.find(v => femaleHints.test(v.name)) || arabic[0];
    } else {
      preferred = egyptian.find(v => maleHints.test(v.name)) || egyptian[0] ||
                  arabic.find(v => maleHints.test(v.name)) || arabic[0];
    }

    if (preferred) u.voice = preferred;

    const selectedRate = $('#rate')?.value || '-4%';
    const rateMap = {'-8%':0.90,'-4%':0.95,'+0%':1.0,'+6%':1.06};
    u.rate = rateMap[selectedRate] || 0.95;
    u.pitch = gender === 'male' ? 0.96 : 1.02;
    u.volume = 1;

    u.onend = resolve;
    u.onerror = resolve;

    speechSynthesis.cancel();
    speechSynthesis.speak(u);
  });
}

async function serverEdgeSpeak(text) {
  const r = await fetch('api/tts.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      text,
      speaker: $('#speaker').value,
      rate: $('#rate').value
    })
  });
  if (!r.ok) {
    let detail = 'tts_failed';
    try { const j = await r.json(); detail = j.detail || j.error || detail; } catch (e) {}
    throw new Error(detail);
  }
  const engine = r.headers.get('X-TTS-Engine') || 'VoiceTut';
  $('#ttsState').textContent = engine;
  const blob = await r.blob();
  if (!blob.size) throw new Error('empty_audio');
  const url = URL.createObjectURL(blob);
  await new Promise((resolve, reject) => {
    const a = new Audio(url);
    currentAudio = a;
    a.onended = () => { URL.revokeObjectURL(url); currentAudio = null; resolve(); };
    a.onerror = () => { URL.revokeObjectURL(url); currentAudio = null; reject(new Error('audio_playback_failed')); };
    a.play().catch(err => { URL.revokeObjectURL(url); currentAudio = null; reject(err); });
  });
}

async function speak(text) {
  speaking = true;
  state('برد عليك…', 'speaking');
  safeStopRec();
  try {
    $('#ttsState').textContent = 'VoiceTut…';
    await serverEdgeSpeak(text);
  } catch (e) {
    console.warn('VoiceTut/Edge server TTS failed:', e);
    $('#ttsState').textContent = 'Device fallback';
    await browserSpeak(text);
  } finally {
    speaking = false;
    if (active) setTimeout(startRec, 350);
  }
}

async function think(text) {
  state('بفكر…');
  $('#aiState').textContent = 'Gemini بيفكر…';
  try {
    const historyForApi = chatHistory.slice(-10);
    const r = await fetch('api/brain.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({text, history: historyForApi})
    });
    const j = await r.json();
    if (!r.ok || !j.ok) throw new Error(j.detail || j.error || 'brain_failed');

    $('#aiState').textContent = j.model || 'Gemini';
    msg('bot', j.answer);
    chatHistory.push({role: 'user', text});
    chatHistory.push({role: 'assistant', text: j.answer});
    chatHistory = chatHistory.slice(-12);
    await speak(j.answer);
  } catch (e) {
    console.error(e);
    $('#aiState').textContent = 'Gemini error';
    const t = 'حصل خطأ وأنا بكلم جوجل. جرّب تاني، ولو استمر افتح صفحة الفحص.';
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
    const t = e.results[0][0].transcript.trim();
    if (t) {
      msg('user', t);
      think(t);
    }
  };
  r.onerror = e => {
    $('#sttState').textContent = e.error;
    if (active && !speaking && e.error !== 'not-allowed' && e.error !== 'service-not-allowed') restart();
  };
  r.onend = () => {
    if (active && !speaking) restart();
  };
  return r;
}

function restart() {
  clearTimeout(restartTimer);
  restartTimer = setTimeout(startRec, 650);
}

function startRec() {
  if (!active || speaking) return;
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
  chatHistory = [];
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
});
