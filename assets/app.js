import { GoogleGenAI, Modality } from "https://cdn.jsdelivr.net/npm/@google/genai@2.25.0/+esm";

const $ = s => document.querySelector(s);

const callBtn = $('#callBtn');
const stopBtn = $('#stopBtn');
const micState = $('#micState');
const liveState = $('#liveState');
const modelState = $('#modelState');
const transcript = $('#transcript');
const orb = $('#orb');
const voiceSelect = $('#voice');

let liveSession = null;
let micStream = null;
let inputCtx = null;
let outputCtx = null;
let inputSource = null;
let processor = null;
let muteGain = null;
let active = false;
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

    output[i] = count
      ? sum / count
      : (input[Math.min(start, input.length - 1)] || 0);
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
  if (!outputCtx) {
    outputCtx = new (window.AudioContext || window.webkitAudioContext)();
  }

  if (outputCtx.state === 'suspended') {
    await outputCtx.resume();
  }

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
    if (!active || !liveSession) return;

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

  setStatus(micState, 'شغال');
  setStatus(liveState, 'Live جاهز');
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

async function getEphemeralSession() {
  const r = await fetch('api/live_session.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: '{}',
    cache: 'no-store'
  });

  const j = await r.json();

  if (!r.ok || !j.ok) {
    throw new Error(j.detail || j.error || 'تعذر استخراج Ephemeral Token');
  }

  return j;
}

async function handleLiveMessage(message) {
  console.debug('Gemini SDK ←', message);

  const content = message.serverContent;

  if (content?.interrupted) {
    stopPlayback();
    setStatus(liveState, 'سامع المقاطعة');
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
    setStatus(liveState, 'Gemini بيتكلم');
    state('بيرد عليك…', 'speaking');
  }

  if (content?.turnComplete) {
    if (greetingPending) {
      greetingPending = false;
      setStatus(liveState, 'الصوت شغال — بفتح الميكروفون');
      state('بفتح الميكروفون…');

      try {
        await startMicrophone();
      } catch (err) {
        console.error('Microphone error:', err);
        setStatus(micState, 'فشل');
        setStatus(liveState, 'Live شغال — الميكروفون فشل');
        state('الميكروفون فشل');
      }

      return;
    }

    if (active) {
      setStatus(liveState, 'Live جاهز');
      state('سامعك…', 'listening');
    }
  }
}

async function startCall() {
  if (active) return;

  if (!navigator.mediaDevices?.getUserMedia) {
    alert('المتصفح لا يدعم الميكروفون المطلوب للمكالمة.');
    return;
  }

  active = true;
  greetingPending = true;

  callBtn.disabled = true;
  stopBtn.disabled = false;
  voiceSelect.disabled = true;

  transcript.innerHTML = '';

  state('بجهز Gemini SDK…');
  setStatus(micState, 'منتظر Live');
  setStatus(liveState, 'بيطلع Ephemeral Token');

  try {
    if (!outputCtx) {
      outputCtx = new (window.AudioContext || window.webkitAudioContext)();
    }

    await outputCtx.resume();
    nextPlayTime = outputCtx.currentTime;

    const info = await getEphemeralSession();
    setStatus(modelState, info.model);

    const ai = new GoogleGenAI({
      apiKey: info.token
    });

    setStatus(liveState, 'Google SDK بيفتح Live…');

    liveSession = await ai.live.connect({
      model: info.model,
      config: {
        responseModalities: [Modality.AUDIO],
        systemInstruction: info.system_instruction
      },
      callbacks: {
        onopen: () => {
          console.debug('Gemini SDK Live opened');
          setStatus(liveState, 'SDK Live متصل');
        },
        onmessage: message => {
          handleLiveMessage(message).catch(err => {
            console.error('Message handler error:', err);
          });
        },
        onerror: error => {
          console.error('Gemini SDK Live error:', error);
          setStatus(liveState, 'SDK error: ' + (error?.message || 'unknown'));
          state('خطأ Live');
        },
        onclose: event => {
          console.debug('Gemini SDK Live close:', event);
          const reason = event?.reason ? ': ' + event.reason : '';
          setStatus(liveState, 'اتقفل' + reason);

          if (active) {
            state('الاتصال اتقفل');
            stopCall(false);
          }
        }
      }
    });

    setStatus(liveState, 'Live اتفتح — اختبار الصوت');
    state('Gemini بيبدأ…');

    liveSession.sendRealtimeInput({
      text: 'ابدأ بتحية مصرية قصيرة جداً فقط، وبعدها توقف واسمعني.'
    });

  } catch (err) {
    console.error('Start Live failed:', err);
    setStatus(liveState, err?.message || 'فشل Live');
    state('فشل الاتصال');
    await stopCall(false);
  }
}

async function stopCall(userInitiated=true) {
  const wasActive = active;

  active = false;
  greetingPending = false;

  await stopMicrophone();
  stopPlayback();

  if (liveSession) {
    try { liveSession.close(); } catch (_) {}
    liveSession = null;
  }

  callBtn.disabled = false;
  stopBtn.disabled = true;
  voiceSelect.disabled = true;

  if (userInitiated && wasActive) {
    setStatus(liveState, 'متوقف');
    state('انتهت المكالمة');
  }
}

callBtn.addEventListener('click', startCall);
stopBtn.addEventListener('click', () => stopCall(true));

window.addEventListener('beforeunload', () => {
  active = false;

  try { liveSession?.close(); } catch (_) {}
  micStream?.getTracks().forEach(t => t.stop());
});

fetch('api/status.php', {cache:'no-store'})
  .then(r => r.json())
  .then(j => {
    setStatus(modelState, j.model || 'gemini-3.8-live');

    $('#engineBadge').textContent = j.gemini_configured
      ? 'Gemini 3.8 Live SDK جاهز'
      : 'مفتاح Gemini غير مضبوط';
  })
  .catch(() => {
    $('#engineBadge').textContent = 'تعذر فحص Live';
  });
