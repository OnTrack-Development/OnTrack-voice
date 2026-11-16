<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="ws-endpoint" content="{{ $wsEndpoint ?? 'wss://app.ontrackegy.com/ws-voice' }}">
    <title>{{ $agent->name }} — مكالمة هاتفية ذكية | OnTrack Voice</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-pure-black: #050505;
            --text-white: #FFFFFF;
            --text-muted: #A1A1AA;
            --text-dim: #71717A;
            --zinc-dark: #18181B;
            --zinc-btn: #27272A;
            --zinc-btn-hover: #3F3F46;
            --green-call: #16A34A;
            --green-glow: rgba(22, 163, 74, 0.4);
            --red-end: #DC2626;
            --red-glow: rgba(220, 38, 38, 0.4);
            --accent-ruby: #E11D48;
            --accent-purple: #8B5CF6;
            --accent-cyan: #06B6D4;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'IBM Plex Sans Arabic', system-ui, -apple-system, sans-serif;
            -webkit-tap-highlight-color: transparent;
            user-select: none;
        }

        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: var(--bg-pure-black);
            color: var(--text-white);
        }

        .phone-call-container {
            width: 100vw;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            padding: calc(env(safe-area-inset-top, 24px) + 24px) 24px calc(env(safe-area-inset-bottom, 24px) + 28px);
            position: relative;
            background: #050505;
            z-index: 1;
        }

        /* 1. TOP CALL HEADER */
        .call-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            width: 100%;
            max-width: 420px;
            z-index: 10;
        }

        .agent-name {
            font-size: 28px;
            font-weight: 800;
            color: #FFFFFF;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }

        .call-status-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }

        .call-timer {
            font-family: 'JetBrains Mono', monospace;
            font-size: 16px;
            font-weight: 700;
            color: #E4E4E7;
            letter-spacing: 1px;
            display: none;
        }

        .call-status-text {
            font-size: 14.5px;
            font-weight: 500;
            color: var(--text-muted);
            transition: color 0.3s ease;
        }

        .call-quality-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(39, 39, 42, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 11.5px;
            color: #D4D4D8;
        }

        .badge-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #22C55E;
            box-shadow: 0 0 8px #22C55E;
        }

        /* 2. CENTRAL VISUALIZER STAGE */
        .visualizer-stage {
            flex: 1;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 5;
            min-height: 260px;
        }

        canvas#callCanvas {
            width: 280px;
            height: 280px;
            display: block;
        }

        /* 3. LIVE TRANSCRIPT DRAWER / FLOATING SNIPPET */
        .transcript-wrapper {
            width: 100%;
            max-width: 380px;
            margin-bottom: 18px;
            z-index: 10;
        }

        .transcript-bubble {
            background: rgba(24, 24, 27, 0.75);
            border: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 16px;
            padding: 12px 16px;
            max-height: 120px;
            overflow-y: auto;
            font-size: 13px;
            line-height: 1.5;
            color: #E4E4E7;
            display: flex;
            flex-direction: column;
            gap: 6px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .transcript-bubble.collapsed {
            display: none;
        }

        .msg-entry {
            display: flex;
            gap: 6px;
        }

        .msg-agent {
            color: #38BDF8;
        }

        .msg-user {
            color: #CBD5E1;
        }

        /* 4. BOTTOM ACTION DOCK */
        .action-dock {
            width: 100%;
            max-width: 360px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 28px;
            z-index: 10;
        }

        .action-slot {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .btn-round {
            border: none;
            outline: none;
            cursor: pointer;
            display: grid;
            place-items: center;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-side {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: var(--zinc-btn);
            color: #FFFFFF;
            font-size: 20px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
            visibility: hidden;
            opacity: 0;
        }

        .btn-side.visible {
            visibility: visible;
            opacity: 1;
        }

        .btn-side:hover {
            background: var(--zinc-btn-hover);
            transform: scale(1.05);
        }

        .btn-side.active-toggled {
            background: rgba(220, 38, 38, 0.25);
            border-color: rgba(220, 38, 38, 0.6);
            color: #EF4444;
        }

        /* Main Call / End Center Button */
        .btn-main {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            font-size: 28px;
            color: #FFFFFF;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.6);
        }

        .btn-main.start-state {
            background: var(--green-call);
            box-shadow: 0 6px 24px var(--green-glow);
        }

        .btn-main.start-state:hover {
            background: #15803D;
            transform: scale(1.06);
            box-shadow: 0 8px 30px rgba(22, 163, 74, 0.6);
        }

        .btn-main.end-state {
            background: var(--red-end);
            box-shadow: 0 6px 24px var(--red-glow);
        }

        .btn-main.end-state:hover {
            background: #B91C1C;
            transform: scale(1.06);
            box-shadow: 0 8px 30px rgba(220, 38, 38, 0.6);
        }

        .btn-main:active, .btn-side:active {
            transform: scale(0.94) !important;
        }

        .btn-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
        }

        .footer-tag {
            font-size: 11px;
            color: #52525B;
            margin-top: 14px;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>

    <div class="phone-call-container">
        <!-- TOP CALL HEADER -->
        <div class="call-header">
            <h1 class="agent-name">{{ $agent->name }}</h1>
            <div class="call-status-row">
                <span class="call-timer" id="callTimer">00:00</span>
                <span class="call-status-text" id="callStatusText">جاهز للاتصال</span>
            </div>
            <div class="call-quality-badge">
                <span class="badge-dot" id="badgeDot"></span>
                <span>HD Voice • خط صوتي مشفر</span>
            </div>
        </div>

        <!-- CENTRAL AUDIO VISUALIZER (Solid Circle + Spaced Reactive Outer Ring) -->
        <div class="visualizer-stage">
            <canvas id="callCanvas" width="300" height="300"></canvas>
        </div>

        <!-- FLOATING LIVE TRANSCRIPT SNIPPET -->
        <div class="transcript-wrapper">
            <div class="transcript-bubble collapsed" id="transcriptBubble">
                <div class="msg-entry msg-agent" id="lastAgentMsg">
                    <strong>{{ $agent->name }}:</strong>
                    <span>اضغط زر الاتصال لبدء التحدث معي مباشرة.</span>
                </div>
            </div>
        </div>

        <!-- AUTHENTIC PHONE CALL ACTION DOCK -->
        <div class="action-dock">
            <!-- Left Slot: Transcript Toggle -->
            <div class="action-slot">
                <button type="button" class="btn-round btn-side" id="btnTranscript" title="نص الحوار الحي">
                    💬
                </button>
                <span class="btn-label" id="lblTranscript" style="visibility:hidden">نص الحوار</span>
            </div>

            <!-- Center Slot: Call / End Button -->
            <div class="action-slot">
                <button type="button" class="btn-round btn-main start-state" id="btnMainCall" title="اتصال">
                    <span id="mainBtnIcon">📞</span>
                </button>
                <span class="btn-label" id="lblMainCall">اتصال</span>
            </div>

            <!-- Right Slot: Mute Button -->
            <div class="action-slot">
                <button type="button" class="btn-round btn-side" id="btnMute" title="كتم الميكروفون">
                    🎤
                </button>
                <span class="btn-label" id="lblMute" style="visibility:hidden">كتم الصوت</span>
            </div>
        </div>

        <div class="footer-tag">
            OnTrack AI Voice • Realtime Multimodal Telephony
        </div>
    </div>

    <script>
        const agentId = "{{ $agent->id }}";
        const csrfToken = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '';

        let isCalling = false;
        let isMuted = false;
        let isWsReady = false;

        let inputAudioContext = null;
        let outputAudioContext = null;
        let micAnalyser = null;
        let outputAnalyser = null;
        let outputGain = null;
        let micStream = null;
        let scriptProcessor = null;
        let ws = null;
        let animationFrame = null;

        let activeSources = [];
        let nextPlayTime = 0;

        let callSeconds = 0;
        let callTimerInterval = null;

        let endpointsList = [];
        let currentEndpointIdx = 0;
        let currentSessionData = null;

        // Visualizer State: 'idle' | 'user_speaking' | 'agent_speaking'
        let visualState = 'idle';

        // DOM Elements
        const canvas = document.getElementById('callCanvas');
        const ctx = canvas.getContext('2d');
        const callTimerEl = document.getElementById('callTimer');
        const callStatusText = document.getElementById('callStatusText');
        const badgeDot = document.getElementById('badgeDot');
        const btnMainCall = document.getElementById('btnMainCall');
        const mainBtnIcon = document.getElementById('mainBtnIcon');
        const lblMainCall = document.getElementById('lblMainCall');
        const btnMute = document.getElementById('btnMute');
        const lblMute = document.getElementById('lblMute');
        const btnTranscript = document.getElementById('btnTranscript');
        const lblTranscript = document.getElementById('lblTranscript');
        const transcriptBubble = document.getElementById('transcriptBubble');
        const lastAgentMsg = document.getElementById('lastAgentMsg');

        // Toggle Transcript
        btnTranscript.addEventListener('click', () => {
            transcriptBubble.classList.toggle('collapsed');
            btnTranscript.classList.toggle('active-toggled', !transcriptBubble.classList.contains('collapsed'));
        });

        // Toggle Mute
        btnMute.addEventListener('click', () => {
            isMuted = !isMuted;
            if (micStream) {
                micStream.getAudioTracks().forEach(t => t.enabled = !isMuted);
            }
            btnMute.classList.toggle('active-toggled', isMuted);
            btnMute.innerText = isMuted ? '🔇' : '🎤';
            lblMute.innerText = isMuted ? 'مكتوم' : 'كتم الصوت';
        });

        // Main Call Button Click Handler
        btnMainCall.addEventListener('click', async () => {
            if (!isCalling) {
                await initiateCall();
            } else {
                terminateCall();
            }
        });

        async function initiateCall() {
            btnMainCall.disabled = true;
            updateStatus('جاري التحقق من إذن الميكروفون...', '#F59E0B');

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                updateStatus('المتصفح لا يدعم الوصول للميكروفون أو الاتصال غير مشفر', '#EF4444');
                btnMainCall.disabled = false;
                return;
            }

            try {
                micStream = await navigator.mediaDevices.getUserMedia({
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true
                    }
                });
            } catch (micErr) {
                console.error('Microphone error:', micErr);
                btnMainCall.disabled = false;
                if (micErr.name === 'NotAllowedError' || micErr.name === 'PermissionDeniedError') {
                    updateStatus('تم رفض إذن الميكروفون من المتصفح', '#EF4444');
                } else if (micErr.name === 'NotFoundError' || micErr.name === 'DevicesNotFoundError') {
                    updateStatus('لم يتم العثور على ميكروفون متصل بهذا الجهاز', '#EF4444');
                } else {
                    updateStatus('تعذر الوصول للميكروفون: ' + (micErr.message || micErr.name), '#EF4444');
                }
                return;
            }

            updateStatus('جاري الرنين والاتصال...', '#38BDF8');

            // Fetch session credentials & failover endpoints from backend
            try {
                const sRes = await fetch('/aivoiceagent/' + encodeURIComponent(agentId) + '/session', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ share: '{{ $shareToken }}' })
                });

                if (!sRes.ok) {
                    const errPayload = await sRes.json().catch(() => ({}));
                    throw new Error(errPayload.error || ('خطأ في الخادم ' + sRes.status));
                }

                currentSessionData = await sRes.json();
                if (!currentSessionData.ok || !currentSessionData.gemini_ws_url) {
                    throw new Error(currentSessionData.error || 'تعذر استخراج بيانات الجلسة الصوتية.');
                }

                endpointsList = currentSessionData.failover_endpoints && currentSessionData.failover_endpoints.length > 0
                    ? currentSessionData.failover_endpoints
                    : [{ ws_url: currentSessionData.gemini_ws_url, label: 'Primary' }];

                currentEndpointIdx = 0;
            } catch (err) {
                console.error('Session error:', err);
                updateStatus('تعذر بدء الجلسة: ' + err.message, '#EF4444');
                btnMainCall.disabled = false;
                if (micStream) {
                    micStream.getTracks().forEach(t => t.stop());
                    micStream = null;
                }
                return;
            }

            // Initialize Audio Contexts
            try {
                inputAudioContext = new (window.AudioContext || window.webkitAudioContext)();
                if (inputAudioContext.state === 'suspended') {
                    await inputAudioContext.resume();
                }
                micAnalyser = inputAudioContext.createAnalyser();
                micAnalyser.fftSize = 64;
                const micSource = inputAudioContext.createMediaStreamSource(micStream);
                micSource.connect(micAnalyser);

                outputAudioContext = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 24000 });
                if (outputAudioContext.state === 'suspended') {
                    await outputAudioContext.resume();
                }
                outputGain = outputAudioContext.createGain();
                outputGain.connect(outputAudioContext.destination);

                outputAnalyser = outputAudioContext.createAnalyser();
                outputAnalyser.fftSize = 64;
                outputGain.connect(outputAnalyser);
            } catch (audioErr) {
                console.error('AudioContext error:', audioErr);
                updateStatus('تعذر تهيئة معالجة الصوت: ' + audioErr.message, '#EF4444');
                btnMainCall.disabled = false;
                if (micStream) {
                    micStream.getTracks().forEach(t => t.stop());
                    micStream = null;
                }
                return;
            }

            // Attempt WebSocket Connection with Failover
            attemptWebSocketConnection();
        }

        function attemptWebSocketConnection() {
            if (currentEndpointIdx >= endpointsList.length) {
                updateStatus('تعذر الاتصال بجميع مفاتيح الصوت الاحتياطية', '#EF4444');
                btnMainCall.disabled = false;
                if (micStream) {
                    micStream.getTracks().forEach(t => t.stop());
                    micStream = null;
                }
                return;
            }

            const activeEndpoint = endpointsList[currentEndpointIdx];
            console.log('Connecting to voice endpoint #' + currentEndpointIdx + ' (' + (activeEndpoint.label || 'Default') + ')...');

            try {
                ws = new WebSocket(activeEndpoint.ws_url);

                ws.onopen = () => {
                    // Send Setup Message with Audio Modality & Transcriptions
                    ws.send(JSON.stringify({
                        setup: currentSessionData.setup
                    }));

                    // Setup Mic Stream Processor (Buffer 2048)
                    scriptProcessor = inputAudioContext.createScriptProcessor(2048, 1, 1);
                    scriptProcessor.onaudioprocess = (e) => {
                        if (!isWsReady || !ws || ws.readyState !== WebSocket.OPEN || isMuted) {
                            return;
                        }
                        const inputChannel = e.inputBuffer.getChannelData(0);
                        const downsampled = downsampleBuffer(inputChannel, inputAudioContext.sampleRate, 16000);
                        const pcm16 = floatTo16BitPCM(downsampled);

                        const bytes = new Uint8Array(pcm16.buffer);
                        let binary = '';
                        for (let i = 0; i < bytes.byteLength; i++) {
                            binary += String.fromCharCode(bytes[i]);
                        }
                        const base64Audio = btoa(binary);

                        ws.send(JSON.stringify({
                            realtimeInput: {
                                mediaChunks: [{
                                    mimeType: "audio/pcm;rate=16000",
                                    data: base64Audio
                                }]
                            }
                        }));
                    };

                    const micSource = inputAudioContext.createMediaStreamSource(micStream);
                    micSource.connect(scriptProcessor);
                    scriptProcessor.connect(inputAudioContext.destination);
                };

                // Read incoming frames (supporting Blob & Text)
                ws.onmessage = async (event) => {
                    let rawText = '';
                    if (event.data instanceof Blob) {
                        rawText = await event.data.text();
                    } else {
                        rawText = event.data;
                    }

                    let msg = null;
                    try {
                        msg = JSON.parse(rawText);
                    } catch (e) {
                        return;
                    }

                    // Setup Complete: Handshake confirmed!
                    if (msg.setupComplete !== undefined) {
                        isWsReady = true;
                        isCalling = true;
                        btnMainCall.disabled = false;

                        // Switch button to End Call state
                        btnMainCall.classList.remove('start-state');
                        btnMainCall.classList.add('end-state');
                        mainBtnIcon.innerText = '📞';
                        mainBtnIcon.style.display = 'inline-block';
                        mainBtnIcon.style.transform = 'rotate(135deg)';
                        lblMainCall.innerText = 'إنهاء';

                        // Reveal side buttons
                        btnMute.classList.add('visible');
                        lblMute.style.visibility = 'visible';
                        btnTranscript.classList.add('visible');
                        lblTranscript.style.visibility = 'visible';
                        transcriptBubble.classList.remove('collapsed');

                        // Start Call Timer
                        callSeconds = 0;
                        callTimerEl.style.display = 'inline-block';
                        callStatusText.style.display = 'none';
                        clearInterval(callTimerInterval);
                        callTimerInterval = setInterval(() => {
                            callSeconds++;
                            const mins = String(Math.floor(callSeconds / 60)).padStart(2, '0');
                            const secs = String(callSeconds % 60).padStart(2, '0');
                            callTimerEl.innerText = mins + ':' + secs;
                        }, 1000);

                        badgeDot.style.background = '#22C55E';
                        badgeDot.style.boxShadow = '0 0 10px #22C55E';

                        // Send initial greeting trigger turn so the agent speaks immediately
                        ws.send(JSON.stringify({
                            clientContent: {
                                turns: [
                                    {
                                        role: "user",
                                        parts: [{ text: "أهلاً يا " + currentSessionData.agent_name + "، رحب بي باختصار واذكر كيف يمكنك مساعدتي اليوم." }]
                                    }
                                ],
                                turnComplete: true
                            }
                        }));

                        startVisualizerLoop();
                    }

                    if (msg.serverContent) {
                        // User Interruption
                        if (msg.serverContent.interrupted) {
                            stopAllPlayback();
                            visualState = 'user_speaking';
                        }

                        // Agent Model Turn Audio Chunks & Transcripts
                        if (msg.serverContent.modelTurn && msg.serverContent.modelTurn.parts) {
                            for (const part of msg.serverContent.modelTurn.parts) {
                                if (part.inlineData && part.inlineData.data) {
                                    playPcmChunk(part.inlineData.data);
                                }
                                if (part.text) {
                                    appendLiveTranscript('agent', part.text);
                                }
                            }
                        }

                        if (msg.serverContent.turnComplete) {
                            // Turn complete
                        }
                    }
                };

                ws.onerror = (e) => {
                    console.warn('WS Endpoint #' + currentEndpointIdx + ' failed. Trying next failover key...', e);
                    if (!isWsReady) {
                        currentEndpointIdx++;
                        if (ws) {
                            try { ws.close(); } catch(err){}
                            ws = null;
                        }
                        attemptWebSocketConnection();
                    }
                };

                ws.onclose = (e) => {
                    console.warn('WS closed with code:', e.code, e.reason);
                    if (!isWsReady && currentEndpointIdx < endpointsList.length - 1) {
                        currentEndpointIdx++;
                        attemptWebSocketConnection();
                    } else if (isCalling) {
                        terminateCall();
                    }
                };

            } catch (wsErr) {
                console.error('WS Connection Exception:', wsErr);
                currentEndpointIdx++;
                attemptWebSocketConnection();
            }
        }

        // Resample Audio Buffer to 16kHz
        function downsampleBuffer(buffer, inputSampleRate, outputSampleRate) {
            if (inputSampleRate === outputSampleRate) return buffer;
            const ratio = inputSampleRate / outputSampleRate;
            const newLength = Math.round(buffer.length / ratio);
            const result = new Float32Array(newLength);
            let offsetResult = 0;
            let offsetBuffer = 0;
            while (offsetResult < result.length) {
                const nextOffsetBuffer = Math.round((offsetResult + 1) * ratio);
                let accum = 0, count = 0;
                for (let i = offsetBuffer; i < nextOffsetBuffer && i < buffer.length; i++) {
                    accum += buffer[i];
                    count++;
                }
                result[offsetResult] = count > 0 ? (accum / count) : 0;
                offsetResult++;
                offsetBuffer = nextOffsetBuffer;
            }
            return result;
        }

        function floatTo16BitPCM(float32Array) {
            const pcm16 = new Int16Array(float32Array.length);
            for (let i = 0; i < float32Array.length; i++) {
                const s = Math.max(-1, Math.min(1, float32Array[i]));
                pcm16[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
            }
            return pcm16;
        }

        function playPcmChunk(base64Pcm) {
            if (!outputAudioContext || outputAudioContext.state === 'closed') return;

            try {
                const binary = atob(base64Pcm);
                const bytes = new Uint8Array(binary.length);
                for (let i = 0; i < binary.length; i++) {
                    bytes[i] = binary.charCodeAt(i);
                }
                const int16 = new Int16Array(bytes.buffer);
                const float32 = new Float32Array(int16.length);
                for (let i = 0; i < int16.length; i++) {
                    float32[i] = int16[i] / 32768.0;
                }

                const audioBuffer = outputAudioContext.createBuffer(1, float32.length, 24000);
                audioBuffer.getChannelData(0).set(float32);

                const source = outputAudioContext.createBufferSource();
                source.buffer = audioBuffer;
                source.connect(outputGain);

                const now = outputAudioContext.currentTime;
                if (nextPlayTime < now) {
                    nextPlayTime = now + 0.015;
                }
                source.start(nextPlayTime);
                nextPlayTime += audioBuffer.duration;

                activeSources.push(source);
                source.onended = () => {
                    const idx = activeSources.indexOf(source);
                    if (idx > -1) activeSources.splice(idx, 1);
                };
            } catch (err) {
                console.error('Audio decode error:', err);
            }
        }

        function stopAllPlayback() {
            activeSources.forEach(s => {
                try { s.stop(); } catch (e) {}
            });
            activeSources = [];
            nextPlayTime = 0;
        }

        function terminateCall() {
            isCalling = false;
            isWsReady = false;
            stopAllPlayback();
            clearInterval(callTimerInterval);

            if (scriptProcessor) {
                try { scriptProcessor.disconnect(); } catch (e) {}
                scriptProcessor = null;
            }
            if (micStream) {
                micStream.getTracks().forEach(t => t.stop());
                micStream = null;
            }
            if (ws) {
                try { ws.close(); } catch (e) {}
                ws = null;
            }
            if (inputAudioContext) {
                try { inputAudioContext.close(); } catch (e) {}
                inputAudioContext = null;
            }
            if (outputAudioContext) {
                try { outputAudioContext.close(); } catch (e) {}
                outputAudioContext = null;
            }
            cancelAnimationFrame(animationFrame);

            // Reset UI
            btnMainCall.disabled = false;
            btnMainCall.classList.remove('end-state');
            btnMainCall.classList.add('start-state');
            mainBtnIcon.innerText = '📞';
            mainBtnIcon.style.transform = 'none';
            lblMainCall.innerText = 'اتصال';

            btnMute.classList.remove('visible', 'active-toggled');
            lblMute.style.visibility = 'hidden';
            btnTranscript.classList.remove('visible', 'active-toggled');
            lblTranscript.style.visibility = 'hidden';
            transcriptBubble.classList.add('collapsed');

            callTimerEl.style.display = 'none';
            callStatusText.style.display = 'inline-block';
            callStatusText.innerText = 'تم إنهاء المكالمة';
            callStatusText.style.color = 'var(--text-muted)';
            badgeDot.style.background = '#71717A';
            badgeDot.style.boxShadow = 'none';

            visualState = 'idle';
            drawIdleVisualizer();
        }

        function updateStatus(text, color) {
            callStatusText.style.display = 'inline-block';
            callTimerEl.style.display = 'none';
            callStatusText.innerText = text;
            callStatusText.style.color = color || 'var(--text-muted)';
        }

        function appendLiveTranscript(speaker, text) {
            lastAgentMsg.innerHTML = '<strong>' + (speaker === 'agent' ? '{{ $agent->name }}' : 'أنت') + ':</strong> <span>' + escapeHtml(text) + '</span>';
            transcriptBubble.scrollTop = transcriptBubble.scrollHeight;
        }

        function escapeHtml(str) {
            return str.replace(/[&<>'"]/g, tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag));
        }

        // ========================================================
        // CENTRAL AUDIO VISUALIZER (Solid Center Circle + Spaced Reactive Outer Ring)
        // ========================================================
        let ringPulsePhase = 0;

        function startVisualizerLoop() {
            renderVisualizerFrame();
        }

        function renderVisualizerFrame() {
            if (!isCalling) return;
            animationFrame = requestAnimationFrame(renderVisualizerFrame);

            ctx.clearRect(0, 0, canvas.width, canvas.height);

            const cx = canvas.width / 2;
            const cy = canvas.height / 2;

            // 1. Measure Mic Energy (User Speaking)
            let micEnergy = 0;
            if (micAnalyser && !isMuted) {
                const micData = new Uint8Array(micAnalyser.frequencyBinCount);
                micAnalyser.getByteFrequencyData(micData);
                let sum = 0;
                for (let i = 0; i < micData.length; i++) sum += micData[i];
                micEnergy = sum / micData.length;
            }

            // 2. Measure Output Energy (Agent Speaking)
            let agentEnergy = 0;
            if (outputAnalyser) {
                const outData = new Uint8Array(outputAnalyser.frequencyBinCount);
                outputAnalyser.getByteFrequencyData(outData);
                let sum = 0;
                for (let i = 0; i < outData.length; i++) sum += outData[i];
                agentEnergy = sum / outData.length;
            }

            // Determine Visual State
            if (agentEnergy > 7) {
                visualState = 'agent_speaking';
            } else if (micEnergy > 7) {
                visualState = 'user_speaking';
            } else {
                visualState = 'idle';
            }

            ringPulsePhase += 0.05;

            // Geometry Parameters
            const baseCoreRadius = 65; // Solid Circle ~130px diameter
            const baseGap = 24;        // Spaced gap 20px-28px
            let coreRadius = baseCoreRadius;
            let outerRingRadius = baseCoreRadius + baseGap;
            let outerRingThickness = 2.5;

            if (visualState === 'agent_speaking') {
                // Vibrant Ruby/Purple/Cyan Glowing Mode
                const boost = Math.min(25, agentEnergy * 0.45);
                coreRadius = baseCoreRadius + (boost * 0.3);
                outerRingRadius = baseCoreRadius + baseGap + boost;
                outerRingThickness = 3.5;

                // Outer Ring Glow Waves
                const waveGrad = ctx.createRadialGradient(cx, cy, outerRingRadius - 8, cx, cy, outerRingRadius + 18);
                waveGrad.addColorStop(0, 'rgba(225, 29, 72, 0)');
                waveGrad.addColorStop(0.5, 'rgba(139, 92, 246, 0.45)');
                waveGrad.addColorStop(1, 'rgba(6, 182, 212, 0)');
                ctx.fillStyle = waveGrad;
                ctx.beginPath();
                ctx.arc(cx, cy, outerRingRadius + 16, 0, Math.PI * 2);
                ctx.fill();

                // Outer Ring Border
                ctx.strokeStyle = '#8B5CF6';
                ctx.lineWidth = outerRingThickness;
                ctx.beginPath();
                ctx.arc(cx, cy, outerRingRadius, 0, Math.PI * 2);
                ctx.stroke();

                // Solid Core Circle Gradient (Ruby Red -> Purple -> Cyan)
                const coreGrad = ctx.createRadialGradient(cx, cy, 0, cx, cy, coreRadius);
                coreGrad.addColorStop(0, '#FFFFFF');
                coreGrad.addColorStop(0.35, '#E11D48');
                coreGrad.addColorStop(0.8, '#8B5CF6');
                coreGrad.addColorStop(1, '#06B6D4');
                ctx.fillStyle = coreGrad;
                ctx.beginPath();
                ctx.arc(cx, cy, coreRadius, 0, Math.PI * 2);
                ctx.fill();

            } else if (visualState === 'user_speaking') {
                // Monochrome Silver-Grey & White Mode
                const boost = Math.min(20, micEnergy * 0.4);
                coreRadius = baseCoreRadius + (boost * 0.2);
                outerRingRadius = baseCoreRadius + baseGap + boost;

                // Outer Ring Waves (Monochrome)
                ctx.strokeStyle = 'rgba(228, 228, 231, 0.85)';
                ctx.lineWidth = 2.5;
                ctx.beginPath();
                ctx.arc(cx, cy, outerRingRadius, 0, Math.PI * 2);
                ctx.stroke();

                // Solid Core Circle (Monochrome Slate / Silver)
                const coreGrad = ctx.createRadialGradient(cx, cy, 0, cx, cy, coreRadius);
                coreGrad.addColorStop(0, '#F4F4F5');
                coreGrad.addColorStop(0.6, '#A1A1AA');
                coreGrad.addColorStop(1, '#52525B');
                ctx.fillStyle = coreGrad;
                ctx.beginPath();
                ctx.arc(cx, cy, coreRadius, 0, Math.PI * 2);
                ctx.fill();

            } else {
                // Idle / Listening State (Pure Minimalist Zinc Slate)
                const subtleBreath = Math.sin(ringPulsePhase) * 2;
                outerRingRadius = baseCoreRadius + baseGap + subtleBreath;

                // Spaced Outer Ring Border (Subtle Grey)
                ctx.strokeStyle = 'rgba(161, 161, 170, 0.35)';
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.arc(cx, cy, outerRingRadius, 0, Math.PI * 2);
                ctx.stroke();

                // Solid Core Circle (Clean Slate / Zinc Grey #3F3F46)
                ctx.fillStyle = '#3F3F46';
                ctx.beginPath();
                ctx.arc(cx, cy, coreRadius, 0, Math.PI * 2);
                ctx.fill();
            }
        }

        function drawIdleVisualizer() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            const cx = canvas.width / 2;
            const cy = canvas.height / 2;
            const coreRadius = 65;
            const outerRingRadius = 65 + 24;

            // Outer Ring
            ctx.strokeStyle = 'rgba(161, 161, 170, 0.35)';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(cx, cy, outerRingRadius, 0, Math.PI * 2);
            ctx.stroke();

            // Solid Core Circle
            ctx.fillStyle = '#3F3F46';
            ctx.beginPath();
            ctx.arc(cx, cy, coreRadius, 0, Math.PI * 2);
            ctx.fill();
        }

        drawIdleVisualizer();
    </script>
</body>
</html>
