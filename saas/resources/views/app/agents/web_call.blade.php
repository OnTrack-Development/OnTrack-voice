<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $agent->name }} — المكالمة الصوتية الذكية | OnTrack AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-deep: #0B0F19;
            --card-bg: rgba(17, 24, 39, 0.85);
            --card-border: rgba(255, 255, 255, 0.1);
            --accent-purple: #8B5CF6;
            --accent-cyan: #06B6D4;
            --accent-pink: #EC4899;
            --accent-emerald: #10B981;
            --accent-red: #EF4444;
            --text-main: #F8FAFC;
            --text-sub: #94A3B8;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'IBM Plex Sans Arabic', system-ui, sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background-color: var(--bg-deep);
            background-image: 
                radial-gradient(circle at 50% 20%, rgba(139, 92, 246, 0.18) 0%, transparent 60%),
                radial-gradient(circle at 80% 80%, rgba(6, 182, 212, 0.12) 0%, transparent 50%),
                radial-gradient(circle at 20% 90%, rgba(236, 72, 153, 0.12) 0%, transparent 50%);
            min-height: 100vh;
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-x: hidden;
        }

        .luxury-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-radius: 28px;
            width: 100%;
            max-width: 440px;
            padding: 36px 28px;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.7), 0 0 40px rgba(139, 92, 246, 0.15);
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 10;
        }

        .avatar-wrap {
            position: relative;
            margin-bottom: 16px;
        }

        .avatar {
            width: 84px;
            height: 84px;
            border-radius: 24px;
            background: linear-gradient(135deg, #1E1B4B 0%, #312E81 100%);
            border: 2px solid rgba(255, 255, 255, 0.18);
            display: grid;
            place-items: center;
            font-size: 38px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .avatar-badge {
            position: absolute;
            bottom: -4px;
            right: -4px;
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-cyan));
            color: #FFF;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 13px;
            border: 2px solid var(--bg-deep);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.5);
        }

        .agent-name {
            font-size: 23px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
            text-align: center;
            background: linear-gradient(135deg, #FFFFFF 60%, #CBD5E1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .agent-role {
            font-size: 13px;
            color: var(--text-sub);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .orb-stage {
            width: 220px;
            height: 220px;
            display: grid;
            place-items: center;
            position: relative;
            margin-bottom: 24px;
        }

        canvas#audioCanvas {
            width: 220px;
            height: 220px;
            border-radius: 50%;
        }

        .status-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 12.5px;
            font-weight: 600;
            color: #E2E8F0;
            margin-bottom: 26px;
            transition: all 0.3s ease;
            text-align: center;
            max-width: 90%;
        }

        .status-pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent-cyan);
            box-shadow: 0 0 10px var(--accent-cyan);
            flex-shrink: 0;
        }

        .controls-row {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
        }

        .btn-call {
            flex: 1;
            padding: 14px 20px;
            border-radius: 16px;
            border: none;
            font-size: 15px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-call.idle {
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-cyan));
            color: #FFFFFF;
            box-shadow: 0 8px 25px -4px rgba(139, 92, 246, 0.5);
        }

        .btn-call.idle:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px -4px rgba(139, 92, 246, 0.7);
        }

        .btn-call.active {
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: #FFFFFF;
            box-shadow: 0 8px 25px -4px rgba(239, 68, 68, 0.6);
        }

        .btn-call.active:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px -4px rgba(239, 68, 68, 0.8);
        }

        .btn-call:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #CBD5E1;
            display: grid;
            place-items: center;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .btn-icon:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #FFFFFF;
        }

        .btn-icon.muted {
            background: rgba(239, 68, 68, 0.15);
            border-color: rgba(239, 68, 68, 0.4);
            color: var(--accent-red);
        }

        .transcript-drawer {
            width: 100%;
            margin-top: 20px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .transcript-drawer.open {
            max-height: 220px;
        }

        .transcript-box {
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            padding: 12px 14px;
            font-size: 12.5px;
            line-height: 1.6;
            color: #E2E8F0;
            max-height: 190px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .msg-line {
            padding: 6px 10px;
            border-radius: 8px;
            max-width: 88%;
            word-break: break-word;
        }

        .msg-agent {
            background: rgba(139, 92, 246, 0.2);
            border: 1px solid rgba(139, 92, 246, 0.3);
            align-self: flex-start;
        }

        .msg-user {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            align-self: flex-end;
            text-align: right;
        }

        .footer-brand {
            margin-top: 24px;
            font-size: 11.5px;
            color: #64748B;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .footer-brand a {
            color: #94A3B8;
            text-decoration: none;
            font-weight: 600;
        }

        .footer-brand a:hover {
            color: #F8FAFC;
        }
    </style>
</head>
<body>

    <div class="luxury-card">
        <div class="avatar-wrap">
            <div class="avatar" id="agentAvatar">
                {{ mb_substr($agent->name ?? 'A', 0, 1, 'UTF-8') }}
            </div>
            <div class="avatar-badge">✨</div>
        </div>

        <h1 class="agent-name">{{ $agent->name }}</h1>
        <div class="agent-role">
            <span>{{ $agent->persona ?? 'المساعد الصوتي الذكي' }}</span>
            <span>•</span>
            <span style="color:#06B6D4;">OnTrack Voice AI</span>
        </div>

        <!-- 3D Glowing Audio Orb Stage -->
        <div class="orb-stage">
            <canvas id="audioCanvas" width="240" height="240"></canvas>
        </div>

        <div class="status-pill" id="statusPill">
            <span class="status-pulse-dot" id="statusDot"></span>
            <span id="statusText">جاهز للمحادثة الصوتية</span>
        </div>

        <!-- Action Controls -->
        <div class="controls-row">
            <button class="btn-icon" id="btnTranscript" title="نص الحوار الحي">💬</button>
            <button class="btn-call idle" id="btnCall">
                <span id="callIcon">📞</span>
                <span id="callText">ابدأ المكالمة الصوتية</span>
            </button>
            <button class="btn-icon" id="btnMute" title="كتم الميكروفون" style="display:none;">🎤</button>
        </div>

        <!-- Live Transcript Drawer -->
        <div class="transcript-drawer" id="transcriptDrawer">
            <div class="transcript-box" id="transcriptBox">
                <div class="msg-line msg-agent">أهلاً بك! اضغط «ابدأ المكالمة الصوتية» للتحدث معي مباشرة.</div>
            </div>
        </div>

        <div class="footer-brand">
            <span>Powered by</span>
            <a href="https://app.ontrackegy.com" target="_blank">OnTrack AI Voice</a>
            <span>• Realtime Multimodal Engine</span>
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

        const canvas = document.getElementById('audioCanvas');
        const ctx = canvas.getContext('2d');
        const btnCall = document.getElementById('btnCall');
        const btnMute = document.getElementById('btnMute');
        const btnTranscript = document.getElementById('btnTranscript');
        const statusText = document.getElementById('statusText');
        const statusDot = document.getElementById('statusDot');
        const transcriptDrawer = document.getElementById('transcriptDrawer');
        const transcriptBox = document.getElementById('transcriptBox');

        // Toggle Transcript Drawer
        btnTranscript.addEventListener('click', () => {
            transcriptDrawer.classList.toggle('open');
        });

        // Toggle Mute
        btnMute.addEventListener('click', () => {
            isMuted = !isMuted;
            if (micStream) {
                micStream.getAudioTracks().forEach(t => t.enabled = !isMuted);
            }
            btnMute.classList.toggle('muted', isMuted);
            btnMute.innerText = isMuted ? '🔇' : '🎤';
        });

        // Toggle Call
        btnCall.addEventListener('click', async () => {
            if (!isCalling) {
                await startCall();
            } else {
                endCall();
            }
        });

        async function startCall() {
            btnCall.disabled = true;
            updateStatus('جاري التحقق من إذن الميكروفون...', '#F59E0B');

            // 1. Check browser mediaDevices support
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                updateStatus('المتصفح لا يدعم الوصول للميكروفون أو الاتصال غير مشفر (HTTPS مطلوب)', '#EF4444');
                btnCall.disabled = false;
                return;
            }

            // 2. Request Microphone with distinct error handling
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
                btnCall.disabled = false;
                if (micErr.name === 'NotAllowedError' || micErr.name === 'PermissionDeniedError') {
                    updateStatus('تم رفض إذن الميكروفون من المتصفح. يرجى تفعيله من شريط العنوان.', '#EF4444');
                } else if (micErr.name === 'NotFoundError' || micErr.name === 'DevicesNotFoundError') {
                    updateStatus('لم يتم العثور على ميكروفون متصل بهذا الجهاز.', '#EF4444');
                } else if (micErr.name === 'NotReadableError' || micErr.name === 'TrackStartError') {
                    updateStatus('الميكروفون مستخدم حالياً بواسطة تطبيق آخر.', '#EF4444');
                } else if (micErr.name === 'SecurityError') {
                    updateStatus('سياسة الأمان تمنع تشغيل الميكروفون في هذا الإطار.', '#EF4444');
                } else {
                    updateStatus('تعذر الوصول للميكروفون: ' + (micErr.message || micErr.name), '#EF4444');
                }
                return;
            }

            // 3. Request Session Parameters from OnTrack Backend
            updateStatus('جاري تهيئة قناة الاتصال بالذكاء الاصطناعي...', '#38BDF8');
            let sessionData = null;
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
                    throw new Error(errPayload.error || ('خطأ في الخادم (' + sRes.status + ')'));
                }

                sessionData = await sRes.json();
                if (!sessionData.ok || !sessionData.gemini_ws_url) {
                    throw new Error(sessionData.error || 'تعذر استخراج بيانات الجلسة الصوتية.');
                }
            } catch (err) {
                console.error('Session error:', err);
                updateStatus('تعذر إنشاء جلسة الصوت: ' + err.message, '#EF4444');
                btnCall.disabled = false;
                if (micStream) {
                    micStream.getTracks().forEach(t => t.stop());
                    micStream = null;
                }
                return;
            }

            // 4. Initialize Audio Contexts (Input capture + Output playback)
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
                updateStatus('تعذر تهيئة نظام معالجة الصوت الرقمي: ' + audioErr.message, '#EF4444');
                btnCall.disabled = false;
                if (micStream) {
                    micStream.getTracks().forEach(t => t.stop());
                    micStream = null;
                }
                return;
            }

            // 5. Connect WebSocket directly to Gemini Multimodal Live API
            updateStatus('جاري الاتصال بنفق Gemini Live التفاعلي...', '#38BDF8');
            try {
                ws = new WebSocket(sessionData.gemini_ws_url);

                ws.onopen = () => {
                    // Send Setup Message
                    ws.send(JSON.stringify({
                        setup: sessionData.setup
                    }));

                    // Setup Mic PCM Stream Processor (Buffer 2048)
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

                    micSource.connect(scriptProcessor);
                    scriptProcessor.connect(inputAudioContext.destination);
                };

                ws.onmessage = (event) => {
                    let msg = null;
                    try {
                        msg = JSON.parse(event.data);
                    } catch (e) {
                        return;
                    }

                    if (msg.setupComplete) {
                        isWsReady = true;
                        isCalling = true;
                        btnCall.disabled = false;
                        btnCall.classList.remove('idle');
                        btnCall.classList.add('active');
                        btnCall.innerHTML = '<span>🔴</span><span>إنهاء المكالمة</span>';
                        btnMute.style.display = 'flex';
                        updateStatus('متصل بالذكاء الاصطناعي — تفضل بالتحدث الآن', '#10B981');
                        addTranscript('agent', 'أهلاً بك! أنا ' + sessionData.agent_name + '، سامعك بوضوح، اتفضل اسألني في أي وقت.');
                        drawOrb();
                    }

                    if (msg.serverContent) {
                        if (msg.serverContent.interrupted) {
                            stopAllPlayback();
                            updateStatus('جاري الاستماع لصوتك...', '#10B981');
                        }

                        if (msg.serverContent.modelTurn && msg.serverContent.modelTurn.parts) {
                            updateStatus('الإيجنت يتحدث الآن...', '#8B5CF6');
                            for (const part of msg.serverContent.modelTurn.parts) {
                                if (part.inlineData && part.inlineData.data) {
                                    playPcmChunk(part.inlineData.data);
                                }
                                if (part.text) {
                                    addTranscript('agent', part.text);
                                }
                            }
                        }

                        if (msg.serverContent.turnComplete) {
                            updateStatus('جاري الاستماع... تفضل بالتحدث', '#10B981');
                        }
                    }
                };

                ws.onerror = (e) => {
                    console.error('WebSocket Error:', e);
                    if (!isCalling) {
                        updateStatus('تعذر إكمال نفق الصوت مع Google Live.', '#EF4444');
                        btnCall.disabled = false;
                    }
                };

                ws.onclose = () => {
                    if (isCalling) {
                        endCall();
                    }
                };

            } catch (wsErr) {
                console.error('WS Exception:', wsErr);
                updateStatus('تعذر فتح نفق الاتصال: ' + wsErr.message, '#EF4444');
                btnCall.disabled = false;
                if (micStream) {
                    micStream.getTracks().forEach(t => t.stop());
                    micStream = null;
                }
            }
        }

        // Resample Audio Buffer to 16kHz
        function downsampleBuffer(buffer, inputSampleRate, outputSampleRate) {
            if (inputSampleRate === outputSampleRate) {
                return buffer;
            }
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

        // Convert Float32Array to 16-bit Int16 PCM
        function floatTo16BitPCM(float32Array) {
            const pcm16 = new Int16Array(float32Array.length);
            for (let i = 0; i < float32Array.length; i++) {
                const s = Math.max(-1, Math.min(1, float32Array[i]));
                pcm16[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
            }
            return pcm16;
        }

        // Gapless 24kHz PCM Playback
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
                    nextPlayTime = now + 0.02;
                }
                source.start(nextPlayTime);
                nextPlayTime += audioBuffer.duration;

                activeSources.push(source);
                source.onended = () => {
                    const idx = activeSources.indexOf(source);
                    if (idx > -1) activeSources.splice(idx, 1);
                };
            } catch (err) {
                console.error('Audio chunk decode error:', err);
            }
        }

        function stopAllPlayback() {
            activeSources.forEach(s => {
                try { s.stop(); } catch (e) {}
            });
            activeSources = [];
            nextPlayTime = 0;
        }

        function endCall() {
            isCalling = false;
            isWsReady = false;
            stopAllPlayback();

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

            btnCall.disabled = false;
            btnCall.classList.remove('active');
            btnCall.classList.add('idle');
            btnCall.innerHTML = '<span>📞</span><span>ابدأ المكالمة الصوتية</span>';
            btnMute.style.display = 'none';
            updateStatus('جاهز للمحادثة الصوتية', '#38BDF8');
            drawIdleOrb();
        }

        function updateStatus(text, color) {
            statusText.innerText = text;
            statusDot.style.background = color;
            statusDot.style.boxShadow = '0 0 10px ' + color;
        }

        function addTranscript(sender, text) {
            const div = document.createElement('div');
            div.className = 'msg-line ' + (sender === 'agent' ? 'msg-agent' : 'msg-user');
            div.innerText = text;
            transcriptBox.appendChild(div);
            transcriptBox.scrollTop = transcriptBox.scrollHeight;
        }

        // Draw Ambient Audio Orb Animation (60 FPS Canvas)
        let phase = 0;
        function drawOrb() {
            if (!isCalling) return;

            animationFrame = requestAnimationFrame(drawOrb);
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            let micAvg = 0;
            if (micAnalyser && !isMuted) {
                const micData = new Uint8Array(micAnalyser.frequencyBinCount);
                micAnalyser.getByteFrequencyData(micData);
                for (let i = 0; i < micData.length; i++) micAvg += micData[i];
                micAvg = micAvg / micData.length;
            }

            let outAvg = 0;
            if (outputAnalyser) {
                const outData = new Uint8Array(outputAnalyser.frequencyBinCount);
                outputAnalyser.getByteFrequencyData(outData);
                for (let i = 0; i < outData.length; i++) outAvg += outData[i];
                outAvg = outAvg / outData.length;
            }

            const activeEnergy = Math.max(micAvg * 0.9, outAvg * 1.35);
            const radius = 62 + (activeEnergy * 0.5);
            const cx = canvas.width / 2;
            const cy = canvas.height / 2;
            phase += 0.05;

            // Outer Radial Glow Wave
            const grad = ctx.createRadialGradient(cx, cy, radius * 0.3, cx, cy, radius * 1.45);
            grad.addColorStop(0, 'rgba(139, 92, 246, 0.95)');
            grad.addColorStop(0.5, 'rgba(6, 182, 212, 0.65)');
            grad.addColorStop(1, 'rgba(236, 72, 153, 0)');

            ctx.fillStyle = grad;
            ctx.beginPath();
            ctx.arc(cx, cy, radius * 1.35, 0, Math.PI * 2);
            ctx.fill();

            // Inner Core
            const coreGrad = ctx.createRadialGradient(cx, cy, 0, cx, cy, radius);
            coreGrad.addColorStop(0, '#FFFFFF');
            coreGrad.addColorStop(0.35, '#8B5CF6');
            coreGrad.addColorStop(0.85, '#06B6D4');
            coreGrad.addColorStop(1, '#1E1B4B');

            ctx.fillStyle = coreGrad;
            ctx.beginPath();
            ctx.arc(cx, cy, radius, 0, Math.PI * 2);
            ctx.fill();
        }

        function drawIdleOrb() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            const cx = canvas.width / 2;
            const cy = canvas.height / 2;
            const grad = ctx.createRadialGradient(cx, cy, 15, cx, cy, 75);
            grad.addColorStop(0, 'rgba(99, 102, 241, 0.85)');
            grad.addColorStop(0.65, 'rgba(6, 182, 212, 0.35)');
            grad.addColorStop(1, 'rgba(0, 0, 0, 0)');
            ctx.fillStyle = grad;
            ctx.beginPath();
            ctx.arc(cx, cy, 70, 0, Math.PI * 2);
            ctx.fill();
        }

        drawIdleOrb();
    </script>
</body>
</html>
