const $ = s => document.querySelector(s);

const callBtn = $('#callBtn');
const stopBtn = $('#stopBtn');
const micState = $('#micState');
const liveState = $('#liveState');
const modelState = $('#modelState');
const transcript = $('#transcript');
const orb = $('#orb');
const voiceSelect = $('#voice');

let ws = null;
let micStream = null;
let inputCtx = null;
let outputCtx = null;
let inputSource = null;
let processor = null;
let muteGain = null;
let active = false;
let setupReady = false;
let setupTimer = null;
let greetingPending = false;
let nextPlayTime = 0;
const playingSources = new Set();

function state(text, cls='') {
  $('#orbText').textContent = text;
  orb.className = 'orb ' + cls;
}

function setStatus(el, text) {
  if (el) el.textContent = text;
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
  const outputRate = 16000;
  if (inputRate === outputRate) return input.slice();

  const ratio = inputRate / outputRate;
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

async function startMicrophone() {
  if (micStream) return;

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
    if (!active || !setupReady || !ws || ws.readyState !== WebSocket.OPEN) return;

    const input = ev.inputBuffer.getChannelData(0);
    const pcm16k = resampleTo16k(input, inputCtx.sampleRate);
    const pcm = floatToPCM16(pcm16k);
    const data = arrayBufferToBase64(pcm);

    ws.send(JSON.stringify({
      realtimeInput: {
        audio: {
          data,
          mimeType: 'audio/pcm;rate=16000'
        }
      }
    }));
  };

  inputSource.connect(processor);
  processor.connect(muteGain);
  muteGain.connect(inputCtx.destination);

  setStatus(micState, 'شغال');
  state('سامعك…', 'listening');
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

async function createLiveSession() {
  const r = await fetch('api/live_session.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: '{}',
    cache: 'no-store'
  });

  const j = await r.json();

  if (!r.ok || !j.ok) {
    throw new Error(j.detail || j.error || 'تعذر إنشاء Live session');
  }

  return j;
}

function handleServerMessage(msg) {
  console.debug('Gemini Live ←', msg);

  if (msg.setupComplete) {
    setupReady = true;

    if (setupTimer) {
      clearTimeout(setupTimer);
      setupTimer = null;
    }

    setStatus(liveState, 'Setup تم — اختبار الصوت');
    state('Gemini بيبدأ…');
    greetingPending = true;

    ws.send(JSON.stringify({
      realtimeInput: {
        text: 'ابدأ المكالمة الآن بتحية مصرية قصيرة جداً، وبعدها توقف واسمعني.'
      }
    }));

    return;
  }

  if (msg.serverContent?.interrupted) {
    stopPlayback();
    setStatus(liveState, 'سامع المقاطعة');
  }

  const parts = msg.serverContent?.modelTurn?.parts || [];

  for (const part of parts) {
    const inline = part.inlineData || part.inline_data;
    if (!inline?.data) continue;

    const mime = inline.mimeType || inline.mime_type || 'audio/pcm;rate=24000';
    if (!mime.startsWith('audio/pcm')) continue;

    const match = /rate=(\d+)/i.exec(mime);
    const rate = match ? Number(match[1]) : 24000;

    enqueueAudio(inline.data, rate).catch(err => console.error('Audio playback:', err));
    state('بيرد عليك…', 'speaking');
    setStatus(liveState, 'Gemini بيتكلم');
  }

  if (msg.serverContent?.turnComplete) {
    if (greetingPending) {
      greetingPending = false;
      state('بفتح الميكروفون…');
      setStatus(liveState, 'الصوت شغال — بفتح الميكروفون');

      startMicrophone().catch(err => {
        console.error('Microphone error:', err);
        setStatus(micState, 'فشل');
        setStatus(liveState, 'الصوت شغال — الميكروفون فشل');
        state('الميكروفون فشل');
      });

      return;
    }

    state('سامعك…', 'listening');
    setStatus(liveState, 'Live جاهز');
  }
}

async function startCall() {
  if (active) return;

  if (!navigator.mediaDevices?.getUserMedia) {
    alert('المتصفح لا يدعم الميكروفون المطلوب للمكالمة.');
    return;
  }

  active = true;
  setupReady = false;
  callBtn.disabled = true;
  stopBtn.disabled = false;
  voiceSelect.disabled = true;
  setStatus($('#voiceMode'), 'Default Live voice');
  transcript.innerHTML = '';

  state('بجهز Live…');
  setStatus(micState, 'منتظر Setup');
  setStatus(liveState, 'بيطلع Session');

  try {
    if (!outputCtx) outputCtx = new (window.AudioContext || window.webkitAudioContext)();
    await outputCtx.resume();
    nextPlayTime = outputCtx.currentTime;

    const session = await createLiveSession();
    setStatus(modelState, session.model);

    const url =
      'wss://generativelanguage.googleapis.com/ws/' +
      'google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContentConstrained' +
      '?access_token=' + encodeURIComponent(session.token);

    ws = new WebSocket(url);

    ws.onopen = () => {
      setStatus(liveState, 'WebSocket متصل — Official Setup');

      ws.send(JSON.stringify({
        setup: {
          model: 'models/gemini-3.8-live',
          responseModalities: ['AUDIO'],
          systemInstruction: {
            parts: [{text: session.system_instruction}]
          }
        }
      }));

      setupTimer = setTimeout(() => {
        if (!setupReady && active) {
          setStatus(liveState, 'Setup timeout — Google مردّش');
          state('فشل Setup');
          try { ws.close(4000, 'setup timeout'); } catch (_) {}
        }
      }, 12000);
    };

    ws.onmessage = ev => {
      try {
        if (typeof ev.data !== 'string') {
          console.warn('Unexpected binary Live message');
          return;
        }
        handleServerMessage(JSON.parse(ev.data));
      } catch (err) {
        console.error('Live message parse error:', err, ev.data);
        setStatus(liveState, 'رسالة غير مفهومة من Google');
      }
    };

    ws.onerror = ev => {
      console.error('Live WebSocket error:', ev);
      setStatus(liveState, 'WebSocket error');
    };

    ws.onclose = ev => {
      if (setupTimer) {
        clearTimeout(setupTimer);
        setupTimer = null;
      }

      const reason = ev.reason ? ': ' + ev.reason : '';
      setStatus(liveState, 'اتقفل ' + ev.code + reason);

      if (active) {
        state('الاتصال اتقفل');
        stopCall(false);
      }
    };

  } catch (err) {
    console.error(err);
    setStatus(liveState, err.message || 'خطأ');
    state('فشل الاتصال');
    await stopCall(false);
  }
}

async function stopCall(userInitiated=true) {
  const wasActive = active;
  active = false;
  setupReady = false;
  greetingPending = false;

  if (setupTimer) {
    clearTimeout(setupTimer);
    setupTimer = null;
  }

  await stopMicrophone();
  stopPlayback();

  if (ws) {
    try {
      if (ws.readyState === WebSocket.OPEN && userInitiated) {
        ws.send(JSON.stringify({
          realtimeInput: {
            audioStreamEnd: true
          }
        }));
      }
    } catch (_) {}

    try { ws.close(1000, 'user ended call'); } catch (_) {}
    ws = null;
  }

  callBtn.disabled = false;
  stopBtn.disabled = true;
  voiceSelect.disabled = false;

  if (userInitiated && wasActive) {
    state('انتهت المكالمة');
    setStatus(liveState, 'متوقف');
  }
}

callBtn.addEventListener('click', startCall);
stopBtn.addEventListener('click', () => stopCall(true));

window.addEventListener('beforeunload', () => {
  active = false;
  try { ws?.close(); } catch (_) {}
  micStream?.getTracks().forEach(t => t.stop());
});

fetch('api/status.php', {cache:'no-store'})
  .then(r => r.json())
  .then(j => {
    setStatus(modelState, j.model || 'gemini-3.8-live');
    $('#engineBadge').textContent = j.gemini_configured
      ? 'Gemini 3.8 Live مهيأ'
      : 'مفتاح Gemini غير مضبوط';
  })
  .catch(() => {
    $('#engineBadge').textContent = 'تعذر فحص Live';
  });
