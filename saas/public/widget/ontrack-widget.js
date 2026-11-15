/**
 * OnTrack AI — 2-in-1 Embeddable Voice & Chat Widget
 * OnTrack Voice Luxury Enterprise Experience
 * Built with Shadow DOM for complete CSS isolation and zero host conflicts.
 */

(function () {
    const currentScript = document.currentScript || document.querySelector('script[data-agent]');
    const agentUuid = currentScript ? currentScript.getAttribute('data-agent') : 'default';
    const saasHost = currentScript && currentScript.getAttribute('data-host') ? currentScript.getAttribute('data-host') : 'https://app.ontrackegy.com';
    const isWhiteLabel = currentScript && currentScript.getAttribute('data-whitelabel') === 'true';
    const primaryColor = currentScript && currentScript.getAttribute('data-color') ? currentScript.getAttribute('data-color') : '#8B5CF6';
    const position = currentScript && currentScript.getAttribute('data-position') === 'left' ? 'left' : 'right';

    // Create Host Container
    const hostEl = document.createElement('div');
    hostEl.id = 'ontrack-ai-widget-host';
    document.body.appendChild(hostEl);

    const shadow = hostEl.attachShadow({ mode: 'open' });

    // Stylesheet inside Shadow DOM
    const style = document.createElement('style');
    style.textContent = `
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; direction: rtl; }
        
        .widget-launcher {
            position: fixed;
            bottom: 24px;
            ${position}: 24px;
            z-index: 2147483640;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .bubble-prompt {
            background: #0F172A;
            color: #FFFFFF;
            padding: 12px 16px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.1);
            font-size: 13px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            animation: fadeIn 0.4s ease;
            max-width: 250px;
        }

        .bubble-actions {
            display: flex;
            gap: 6px;
        }

        .bubble-btn {
            background: rgba(255,255,255,0.12);
            color: #FFF;
            border: none;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
        }

        .bubble-btn:hover { background: ${primaryColor}; }

        .launcher-btn {
            width: 62px;
            height: 62px;
            border-radius: 50%;
            background: linear-gradient(135deg, ${primaryColor}, #06B6D4);
            border: none;
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.45);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #FFF;
            transition: transform 0.25s, box-shadow 0.25s;
            position: relative;
        }

        .launcher-btn:hover { transform: scale(1.08); box-shadow: 0 12px 35px rgba(139, 92, 246, 0.6); }

        .online-dot {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            background: #10B981;
            border: 3px solid #0F172A;
        }

        /* Modal Container */
        .widget-modal {
            position: fixed;
            bottom: 96px;
            ${position}: 24px;
            width: 380px;
            height: 580px;
            max-width: calc(100vw - 32px);
            max-height: calc(100vh - 120px);
            background: #0B0F19;
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 24px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.45);
            display: none;
            flex-direction: column;
            overflow: hidden;
            z-index: 2147483641;
            animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .widget-modal.open { display: flex; }

        /* Header & Tabs */
        .modal-header {
            padding: 16px 20px;
            background: #0F172A;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-title-box {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .tabs-pill-box {
            display: flex;
            background: rgba(255,255,255,0.06);
            border-radius: 20px;
            padding: 3px;
        }

        .tab-btn {
            background: transparent;
            border: none;
            color: #94A3B8;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: bold;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .tab-btn.active {
            background: ${primaryColor};
            color: #FFF;
        }

        .close-btn {
            background: transparent;
            border: none;
            color: #94A3B8;
            font-size: 18px;
            cursor: pointer;
        }

        /* Voice Pane */
        .voice-pane {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
            text-align: center;
            color: #FFF;
        }

        .voice-orb {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background: radial-gradient(circle, ${primaryColor} 0%, #06B6D4 50%, transparent 70%);
            box-shadow: 0 0 50px rgba(139, 92, 246, 0.5);
            margin: 20px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            animation: pulseGlow 3s infinite alternate ease-in-out;
        }

        @keyframes pulseGlow {
            0% { transform: scale(0.95); opacity: 0.8; }
            100% { transform: scale(1.08); opacity: 1; }
        }

        .call-action-btn {
            background: linear-gradient(135deg, #10B981, #059669);
            color: #FFF;
            border: none;
            padding: 14px 28px;
            border-radius: 30px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
            margin-top: 15px;
        }

        /* Chat Pane */
        .chat-pane {
            flex: 1;
            display: none;
            flex-direction: column;
            background: #0B0F19;
            overflow: hidden;
        }

        .chat-messages {
            flex: 1;
            padding: 16px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .msg-bubble {
            max-width: 82%;
            padding: 10px 14px;
            border-radius: 14px;
            font-size: 13px;
            line-height: 1.5;
        }

        .msg-bubble.agent {
            background: #1E293B;
            color: #F8FAFC;
            align-self: flex-start;
            border-bottom-right-radius: 4px;
        }

        .msg-bubble.user {
            background: ${primaryColor};
            color: #FFF;
            align-self: flex-end;
            border-bottom-left-radius: 4px;
        }

        /* Dynamic Button Pills */
        .pill-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
        }

        .action-pill {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            color: #38BDF8;
            padding: 6px 12px;
            border-radius: 18px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .action-pill:hover {
            background: rgba(56, 189, 248, 0.2);
            border-color: #38BDF8;
        }

        .chat-footer {
            padding: 12px 14px;
            background: #0F172A;
            border-top: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chat-input {
            flex: 1;
            background: #1E293B;
            border: 1px solid rgba(255,255,255,0.1);
            color: #FFF;
            padding: 10px 14px;
            border-radius: 20px;
            font-size: 13px;
            outline: none;
        }

        .chat-send-btn {
            background: ${primaryColor};
            color: #FFF;
            border: none;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 15px;
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
    `;

    shadow.appendChild(style);

    // Build DOM
    const wrapper = document.createElement('div');
    wrapper.innerHTML = `
        <div class="widget-launcher">
            <div class="bubble-prompt" id="bubblePrompt">
                <span>أهلاً بك! 👋 تحب تتكلم معايا صوت ولا شات؟</span>
                <div class="bubble-actions">
                    <button class="bubble-btn" id="quickVoice">🎙️ مكالمة صوتية</button>
                    <button class="bubble-btn" id="quickChat">💬 محادثة نصية</button>
                </div>
            </div>
            <button class="launcher-btn" id="launcherBtn" title="تحدث مع الذكاء الاصطناعي">
                <span>🎙️</span>
                <div class="online-dot"></div>
            </button>
        </div>

        <div class="widget-modal" id="widgetModal">
            <div class="modal-header">
                <div class="tabs-pill-box">
                    <button class="tab-btn active" id="tabVoice">🎙️ مكالمة صوتية</button>
                    <button class="tab-btn" id="tabChat">💬 شات مباشر</button>
                </div>
                <button class="close-btn" id="closeBtn">✕</button>
            </div>

            <!-- Voice Studio Pane -->
            <div class="voice-pane" id="voicePane">
                <div style="font-size:18px;font-weight:800;margin-bottom:4px;">المساعد الصوتي الذكي</div>
                <div style="font-size:12px;color:#94A3B8;">متصل مباشرة بالذكاء الاصطناعي</div>
                <div class="voice-orb" id="voiceOrb">🎙️</div>
                <div style="font-size:13px;color:#38BDF8;font-weight:bold;" id="voiceStatus">جاهز للمكالمة</div>
                <button class="call-action-btn" id="callToggleBtn">📞 ابدأ المحادثة الصوتية</button>
            </div>

            <!-- Live Chat Pane -->
            <div class="chat-pane" id="chatPane">
                <div class="chat-messages" id="chatMessages">
                    <div class="msg-bubble agent">
                        أهلاً بحضرتك! أنا مساعدك الذكي. كيف يمكنني خدمتك اليوم؟
                        <div class="pill-row">
                            <span class="action-pill" data-action="باقات الاستضافة">🌐 باقات الاستضافة</span>
                            <span class="action-pill" data-action="باقات VPS">⚡ باقات VPS</span>
                            <span class="action-pill" data-action="عرض فواتيري">💳 فواتيري</span>
                            <span class="action-pill" data-action="فتح تذكرة دعم">🎫 فتح تذكرة</span>
                        </div>
                    </div>
                </div>
                <div class="chat-footer">
                    <input type="text" class="chat-input" id="chatInput" placeholder="اكتب استفسارك هنا..." />
                    <button class="chat-send-btn" id="chatSend">➤</button>
                </div>
            </div>
        </div>
    `;

    shadow.appendChild(wrapper);

    // Event Handlers
    const launcherBtn = shadow.getElementById('launcherBtn');
    const bubblePrompt = shadow.getElementById('bubblePrompt');
    const widgetModal = shadow.getElementById('widgetModal');
    const closeBtn = shadow.getElementById('closeBtn');
    const tabVoice = shadow.getElementById('tabVoice');
    const tabChat = shadow.getElementById('tabChat');
    const voicePane = shadow.getElementById('voicePane');
    const chatPane = shadow.getElementById('chatPane');
    const quickVoice = shadow.getElementById('quickVoice');
    const quickChat = shadow.getElementById('quickChat');
    const chatInput = shadow.getElementById('chatInput');
    const chatSend = shadow.getElementById('chatSend');
    const chatMessages = shadow.getElementById('chatMessages');
    const callToggleBtn = shadow.getElementById('callToggleBtn');
    const voiceStatus = shadow.getElementById('voiceStatus');

    let activeCall = false;

    function openModal(tab = 'voice') {
        widgetModal.classList.add('open');
        bubblePrompt.style.display = 'none';
        switchTab(tab);
    }

    function closeModal() {
        widgetModal.classList.remove('open');
    }

    function switchTab(tab) {
        if (tab === 'voice') {
            tabVoice.classList.add('active');
            tabChat.classList.remove('active');
            voicePane.style.display = 'flex';
            chatPane.style.display = 'none';
        } else {
            tabChat.classList.add('active');
            tabVoice.classList.remove('active');
            chatPane.style.display = 'flex';
            voicePane.style.display = 'none';
        }
    }

    launcherBtn.addEventListener('click', () => {
        if (widgetModal.classList.contains('open')) closeModal();
        else openModal('voice');
    });

    closeBtn.addEventListener('click', closeModal);
    tabVoice.addEventListener('click', () => switchTab('voice'));
    tabChat.addEventListener('click', () => switchTab('chat'));
    quickVoice.addEventListener('click', () => openModal('voice'));
    quickChat.addEventListener('click', () => openModal('chat'));

    // Call Toggle
    callToggleBtn.addEventListener('click', () => {
        if (!activeCall) {
            activeCall = true;
            callToggleBtn.style.background = '#EF4444';
            callToggleBtn.innerText = '🔴 إنهاء المكالمة';
            voiceStatus.innerText = 'جاري الاستماع... تفضل بالتحدث';
        } else {
            activeCall = false;
            callToggleBtn.style.background = 'linear-gradient(135deg, #10B981, #059669)';
            callToggleBtn.innerText = '📞 ابدأ المحادثة الصوتية';
            voiceStatus.innerText = 'جاهز للمكالمة';
        }
    });

    // Chat Message Flow
    function sendChat(text) {
        if (!text) return;
        appendMsg('user', text);
        chatInput.value = '';

        // API Call to SaaS Widget endpoint
        fetch(`${saasHost}/api/v1/bridge/widget`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Ontrack-Key': 'otint_public_demo' },
            body: JSON.stringify({ message: text, agent: agentUuid })
        }).then(r => r.json()).then(data => {
            appendMsg('agent', data.reply || 'تم استلام استفسارك.');
        }).catch(() => {
            appendMsg('agent', 'شكراً لتواصلك! سيتم الرد عليك فوراً.');
        });
    }

    function appendMsg(sender, text) {
        const div = document.createElement('div');
        div.className = `msg-bubble ${sender}`;
        div.innerText = text;
        chatMessages.appendChild(div);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    chatSend.addEventListener('click', () => sendChat(chatInput.value.trim()));
    chatInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') sendChat(chatInput.value.trim()); });

    // Handle Quick Action Pills
    chatMessages.addEventListener('click', (e) => {
        if (e.target.classList.contains('action-pill')) {
            const action = e.target.getAttribute('data-action');
            sendChat(action);
        }
    });

})();
