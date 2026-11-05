<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
  <meta name="theme-color" content="#000000"/>
  <title>AYO | OnTrack Voice Lab</title>
  <link rel="stylesheet" href="style.css?v=9"/>
</head>
<body>
<div class="ambient ambient-a"></div><div class="ambient ambient-b"></div>
<div class="shell">
  <header class="topbar">
    <div class="brand"><div class="brand-mark ayo-brand-mark"><img src="assets/ayo-character.webp" alt="شعار AYO" width="44" height="44"/></div><div><strong>AYO <small>VOICE LAB</small></strong><p>مختبر المحادثات الصوتية · OnTrack Development</p></div></div>
    <div class="top-actions"><span class="stage-pill"><i></i> DEMO / INTERNAL</span><span class="connection" id="connection"><b></b> غير متصل</span></div>
  </header>
  <div class="headline"><div><span class="eyebrow">VOICE CLONING × STREAMING</span><h1>خلّي آيو <em>تتكلم.</em></h1><p>اختبار مباشر لصوت AYO المستنسخ، وردود APInex أو Omnira، وتدفّق صوتي لحظي. كل حاجة هنا منفصلة عن النظام الأساسي.</p></div><div class="model-chip" id="modelChip">INWORLD TTS-2 <span>+</span> APINEX LLM</div></div>
  <main class="dashboard">
    <section class="stage-panel panel">
      <div class="panel-head"><div><span class="dot-on" id="callLed"></span><span class="subheading" id="callStatus">جاهزة للتجربة</span></div><span class="subtitle" id="sessionLabel">جلسة اختبار مستقلة · حد أقصى دقيقتان</span></div>
      <div class="stage-center">
        <div class="orb-wrap ayo-avatar" id="orbWrap">
          <div class="ayo-avatar-glow" aria-hidden="true"></div>
          <img id="ayoCharacterImage" class="ayo-avatar-image" src="assets/ayo-character.webp" alt="شخصية آيو الرسمية، وجه بشري مضيء بنقاط بيضاء وموجة صوت حمراء على الفم" width="512" height="512" fetchpriority="high"/>
          <canvas id="ayoCharacterCanvas" class="ayo-avatar-canvas" aria-hidden="true"></canvas>
        </div>
        <div class="stage-persona"><h2>آيو <span>AYO</span></h2><p id="stageHint">الشخصية الرسمية · تفاعلية مع صوتك وصوت آيو</p></div>
        <button type="button" id="enableMicBtn" class="ayo-enable-mic"><span aria-hidden="true">🎙</span> تفعيل الميكروفون <small>اضغط واسمح للمتصفح</small></button>
        <div class="ayo-mic-state" aria-live="polite"><span class="ayo-mic-dot" id="micIndicator"></span><span id="micState">الميكروفون غير متصل</span><div class="ayo-mic-track"><div id="micMeter" class="ayo-mic-meter"></div></div></div>
        <div class="visualizer" id="visualizer" aria-hidden="true"></div>
        <div class="clock" id="clock">02:00</div><span class="clock-label">الوقت المتبقي</span>
      </div>
      <div class="call-controls">
        <button class="round-control" id="muteBtn" title="كتم الميكروفون" disabled><span id="muteIcon">◉</span><small>كتم</small></button>
        <button class="main-call" id="callBtn"><span id="callIcon">☎</span><strong id="callText">ابدأ المكالمة</strong></button>
        <button class="round-control" id="interruptBtn" title="اقطع رد الإيجنت" disabled><span>Ⅱ</span><small>مقاطعة</small></button>
      </div>
      <div class="test-mode"><label>نوع الاختبار <select id="testMode"><option value="live">مباشر — LLM خارجي + Inworld</option><option value="mock">محاكاة واجهة — بدون API</option></select></label><label>السماع <select id="sttMode"><option value="inworld">Inworld STT · يسمع المصري</option><option value="browser">تعرف كلام المتصفح (إن كان مدعومًا)</option></select></label></div>
      <section class="gateway-picker" aria-label="اختيار بوابة الذكاء الاصطناعي">
        <div class="gateway-title"><strong>اختار بوابة الـ AI</strong><small>التبديل مباشر ومحفوظ للجلسة · Inworld للصوت فقط</small></div>
        <div class="gateway-options" role="group" aria-label="بوابة النموذج">
          <button type="button" class="gateway-card" data-gateway="apinex" aria-pressed="false"><strong>APInex</strong><small>20 مليون توكن / يوم</small></button>
          <button type="button" class="gateway-card" data-gateway="omnira-a" aria-pressed="false"><strong>Omnira A</strong><small>Unlimited · 1× Weighted</small></button>
          <button type="button" class="gateway-card" data-gateway="omnira-b" aria-pressed="false"><strong>Omnira B</strong><small>5,000 وحدة / يوم</small></button>
        </div>
        <div class="gateway-model-row"><label for="gatewayModel">موديل البوابة</label><select id="gatewayModel" aria-label="الموديل النشط"></select></div>
        <div class="gateway-bottom"><span class="gateway-url" id="gatewayEndpointDisplay" dir="ltr">—</span><span class="gateway-state" id="gatewayState" role="status" aria-live="polite">جاري تحميل الإعدادات</span><button type="button" class="gateway-settings" id="gatewaySettingsButton">مفاتيح API</button></div>
      </section>
      <div class="emotion-selector">
        <label for="emotionMode">مشاعر وصوت آيو</label>
        <select id="emotionMode" aria-label="اختار طريقة تعبير آيو">
          <option value="expressive">ذكي تلقائي + تنهيدات وضحك وقت اللزوم</option>
          <option value="auto">ذكي تلقائي · نبرة حسب سياق الكلام</option>
          <option value="off">طبيعي · بدون إضافة مشاعر</option>
        </select>
        <small id="voiceStyleStatus" role="status" aria-live="polite">Inworld TTS-2 يحدد المشاعر تلقائيًا</small>
      </div>
      <label class="switch-line"><input type="checkbox" id="bargeIn"><span class="check-visual"></span><span>السماح بمقاطعة صوت آيو بالكلام (قد يلتقط صوت السماعة)</span></label>
      <div class="warn-banner" id="banner">الأصوات المستنسخة تحتاج مفتاح Inworld وVoice ID. المحاكاة لا تختبر جودة الصوت.</div>
    </section>
    <section class="conversation-panel panel">
      <div class="panel-head"><div class="panel-label"><span class="speech-icon">⌘</span><h3>المحادثة المباشرة</h3></div><button class="btn-quiet" id="clearBtn">مسح الشاشة</button></div>
      <div class="conversation-scroll" id="transcript"><div class="empty-chat"><div class="empty-icon">◌</div><h3>ابدأ الحديث مع آيو</h3><p>اسأل عن باقات الاستضافة، الفرق بين الريسيلر والـVPS، أو أسعار الدومينات.</p></div></div>
      <div class="interim" id="interim"></div>
      <form class="composer" id="composer"><button class="send-btn" aria-label="إرسال" type="submit">➤</button><input id="question" autocomplete="off" placeholder="أو اكتب سؤالك هنا..." maxlength="1500"/><span class="composer-caption">ENTER</span></form>
      <p class="ayo-chat-note">تقدر تبعت رسالة نصية حتى لو الميكروفون مش متفعّل. اختيار Omnira أو APInex فوق هو اللي هيحدد الرد.</p><div class="chat-footer"><span>AR-EG / مصري</span><span id="audioStatus">PCM 24 kHz · HTTP Streaming</span><button class="link-button" id="downloadLast" disabled>↓ تحميل آخر رد WAV</button></div>
    </section>
    <aside class="side">
      <section class="panel config-panel"><div class="panel-head"><div class="panel-label"><span class="speech-icon">⚙</span><h3>إعدادات التجربة</h3></div><button id="toggleConfig" class="btn-quiet">إظهار</button></div>
        <div id="configBody" class="hidden">
          <form id="configForm" class="config-form">
            <div class="host-keys-info" id="hostKeyStorage">مفاتيح الخدمات تتحفظ مرة واحدة على الاستضافة، خارج ملفات الموقع، وما بتظهرش لأي زائر.</div>
            <div class="separator">INWORLD / VOICE</div>
            <label>Inworld API Key<input type="password" id="inworldKey" placeholder="Basic credential من Inworld Portal" autocomplete="off"/></label>
            <label>Voice ID<input id="voiceId" value="silver-quail-7878__ayo" dir="ltr"/></label>
            <div class="two-fields"><label>الصوت <select id="ttsLocale"><option value="ar-EG">ar-EG</option><option value="arz">arz</option><option value="auto">Auto</option></select></label><label>الموديل<select id="ttsModel"><option value="inworld-tts-2">TTS-2 · جودة أعلى · $25/M</option><option value="inworld-tts-2-flash">TTS-2 Flash · أسرع · $15/M</option></select></label></div>
            <label>طريقة الإلقاء<select id="ttsDelivery"><option value="BALANCED">BALANCED · طبيعي</option><option value="STABLE">STABLE · ثابت</option><option value="CREATIVE">CREATIVE · معبّر</option></select></label>
            <div class="separator">EXTERNAL LLM · APINEX / OMNIRA</div>
            <label>المزود<select id="llmProvider">
              <option value="apinex">APInex · 20M tokens/day</option>
              <option value="omnira-a">Omnira · Endpoint A · Unlimited weighted</option>
              <option value="omnira-b">Omnira · Endpoint B · 5,000 weighted/day</option>
            </select></label>
            <label>APInex API Key<input type="password" id="apinexKey" placeholder="APINEX_API_KEY على السيرفر" autocomplete="off"/></label>
            <label>Omnira API Key<input type="password" id="omniraKey" placeholder="OMNIRA_API_KEY على السيرفر" autocomplete="off"/></label>
            <label>موديلات متاحة حسب بيانات الحساب<select id="llmModelPreset"></select></label>
            <label>Model ID (قابل للتعديل)<input id="llmModel" dir="ltr" placeholder="free/gpt-6-luna"/></label>
            <small class="helper" id="llmEndpoint">API endpoint: —</small>
            <button class="btn-save" type="submit">حفظ إعدادات الجلسة</button>
            <small class="helper">بعد الحفظ المفاتيح تفضل موجودة على الاستضافة، مش محتاج تدخلها كل مرة. الملف خاص بالمالك وخارج public_html.</small>
          </form>
        </div>
        <div id="configSummary" class="status-grid"><div><span class="status-light" id="llmLight"></span> External LLM <strong id="llmReady">غير مضبوط</strong></div><div><span class="status-light" id="ttsLight"></span> Inworld <strong id="ttsReady">غير مضبوط</strong></div><div><span class="status-light" id="voiceLight"></span> Voice Clone <strong id="voiceReady">لم يُحدد</strong></div></div>
      </section>
      <section class="panel clone-panel"><div class="panel-head"><div class="panel-label"><span class="speech-icon">◉</span><h3>استنساخ صوت AYO</h3></div></div>
        <p class="panel-description">ارفع مقطع MP3 أو WAV واضح (الأفضل 10–30 ثانية)، أو سجّل عينة مباشرة من الميكروفون. لازم تكون مالك الصوت أو عندك إذن صاحبه.</p>
        <label class="file-drop" for="voiceFile"><span>⇧</span><b id="fileLabel">اختيار ملف عينة الصوت</b><small>MP3 / WAV · الأفضل بدون ضوضاء</small></label>
        <input type="file" id="voiceFile" accept=".mp3,.wav,audio/mpeg,audio/wav" hidden/>
        <label class="switch-line"><input id="sampleConsent" type="checkbox"><span>أملك الصوت أو لدي إذن صريح لاستنساخه</span></label><div class="clone-actions"><button id="recordBtn" class="btn-ghost">● تسجيل عينة</button><button id="cloneBtn" class="btn-clone">استنساخ الصوت</button></div>
        <div class="clone-result" id="cloneResult">هنا هيظهر Voice ID بمجرد نجاح الاستنساخ.</div>
        <div class="separator">LIVE VOICE PREVIEW · HTTP STREAMING</div>
        <label class="tts-preview-label">جرب صوت آيو من النص مباشرة<textarea id="previewText" rows="3" maxlength="1800" placeholder="اكتب أي نص مصري علشان تسمعه بصوت آيو...">أهلاً بحضرتك، معاك آيو من أون تراك. ممكن أساعدك تختار أنسب باقة استضافة لموقعك؟</textarea></label>
        <div class="emotion-examples" role="group" aria-label="تجربة الأصوات البشرية">
          <button type="button" data-voice-cue="sigh">تنهيدة</button>
          <button type="button" data-voice-cue="laugh">ضحكة</button>
          <button type="button" data-voice-cue="breathe">نَفَس</button>
          <button type="button" data-voice-cue="gasp">دهشة</button>
          <button type="button" data-voice-cue="groan">آهة</button>
        </div>
        <button id="previewBtn" class="btn-clone" type="button">▶ اسمع صوت آيو</button><audio id="ayoPlayback" class="ayo-native-player" controls preload="none" aria-label="مشغل الصوت المستنسخ بعد اكتمال التوليد"></audio>
        <small class="helper" id="previewStatus">HTTP Streaming مباشر من Inworld بدون تشغيل LLM، بتكلفة TTS فقط.</small>
      </section>
      <section class="panel knowledge-panel"><div class="panel-head"><div class="panel-label"><span class="speech-icon">▤</span><h3>معرفة آيو</h3></div><span class="source-pill">مصدر رسمي</span></div><p class="panel-description">بيانات باقات أون تراك حسب صفحات الموقع بتاريخ 5 نوفمبر 2026.</p><div id="knowledge"></div><a class="source-link" href="https://ontrackegy.com/web-hosting/" target="_blank" rel="noopener">عرض باقات أون تراك ↗</a></section>
    </aside>
  </main>
  <section class="metrics panel"><div class="panel-head"><div class="panel-label"><span class="speech-icon">↗</span><h3>مركز قياسات التجربة</h3></div><span class="subtitle">أرقام فعلية من أحداث الجلسة؛ التكلفة تقديرية</span></div>
    <div class="metric-grid"><div class="metric"><small>أول توكن LLM</small><strong id="metricLlm">—</strong><span>millisecond</span></div><div class="metric"><small>أول Audio Chunk</small><strong id="metricTts">—</strong><span>millisecond</span></div><div class="metric"><small>عدد الردود</small><strong id="metricTurns">0</strong><span>turns</span></div><div class="metric"><small>حروف TTS</small><strong id="metricChars">0</strong><span>characters</span></div><div class="metric"><small>تكلفة TTS تقديرية</small><strong id="metricCost">$0.00000</strong><span>حسب سعر الخطة</span></div><div class="metric"><small>تكلفة STT تقديرية</small><strong id="metricSttCost">$0.00000</strong><span>قد لا تُحاسَب فعليًا</span></div></div>
    <details class="debug"><summary>سجل الأحداث وتشخيص التأخير</summary><pre id="log"></pre></details>
  </section>
  <footer>AYO VOICE LAB <span>·</span> LOCAL EXPERIMENT ONLY <span>·</span> NO CHANGES TO PRODUCTION</footer>
</div>
<div class="ayo-modal" id="ownerOverlay" hidden role="dialog" aria-modal="true" aria-labelledby="ownerTitle">
  <div class="ayo-modal-content"><div class="ayo-modal-icon">AYO</div>
    <h3 id="ownerTitle">مختبر AYO خاص بالمالك</h3>
    <p>أدخل رمز المالك مرة واحدة. بعدها مفاتيح Inworld وOmnira وAPInex تتحفظ على الاستضافة وتتفتح تلقائيًا على جهازك.</p>
    <form id="ownerForm"><label for="ownerCode">رمز المالك</label><input id="ownerCode" type="password" autocomplete="off" required placeholder="رمز دخول الديمو"/><button type="submit" class="ayo-modal-primary">دخول</button></form>
    <small id="ownerError" aria-live="polite"></small>
  </div>
</div>
<div class="ayo-modal" id="micPermissionDialog" hidden role="dialog" aria-modal="true" aria-labelledby="micDialogTitle">
  <div class="ayo-modal-content"><div class="ayo-modal-icon">🎙</div>
    <h3 id="micDialogTitle">تفعيل ميكروفون آيو</h3>
    <p>هنطلب إذن استخدام الميكروفون من المتصفح علشان آيو تسمعك. اضغط «تفعيل» وبعدها اختار «سماح» من نافذة المتصفح.</p>
    <div class="ayo-modal-actions"><button type="button" id="micDialogCancel" class="ayo-modal-secondary">إلغاء</button><button type="button" id="micDialogApprove" class="ayo-modal-primary">تفعيل الميكروفون</button></div>
    <small id="micPermissionError" aria-live="polite"></small>
    <section class="ayo-mic-recovery" id="micRecoveryPanel" hidden aria-labelledby="micRecoveryTitle">
      <h4 id="micRecoveryTitle">كيفية السماح بالميكروفون على Android Chrome</h4>
      <p id="micDiagnosticResult" class="ayo-mic-diagnostic" aria-live="polite">جاري فحص إذن الميكروفون...</p>
      <ol>
        <li>في Chrome اضغط <b>الثلاث نقط ⋮</b> اللي فوق جنب العنوان، ثم <b>الإعدادات</b>.</li>
        <li>افتح <b>إعدادات المواقع ← الميكروفون</b>، واختر السماح للمواقع بطلب استخدام الميكروفون.</li>
        <li>لو لقيت <b>app.ontrackegy.com</b> في المواقع المحظورة، افتحه وغيّره إلى <b>السماح</b>.</li>
        <li>راجع <b>إعدادات الموبايل ← التطبيقات ← Chrome ← الأذونات ← الميكروفون ← سماح</b>، وتأكد إن زر الميكروفون العام في لوحة اختصارات أندرويد مش مقفول.</li>
        <li>ارجع للديمو واضغط <b>إعادة طلب الإذن</b>. لو Chrome احتفظ بالحظر، امسح أذونات الموقع من Chrome ثم جرّب.</li>
      </ol>
      <div class="ayo-mic-recovery-actions"><button type="button" id="micRetryBtn" class="ayo-modal-primary">↻ إعادة طلب الإذن</button><button type="button" id="micHelpToggle" class="ayo-modal-secondary">إخفاء الإرشادات</button></div>
      <p class="ayo-mic-disclaimer">مهم: الموقع ما يقدرش يفتح إعدادات Chrome أو يمنح نفسه صلاحية الميكروفون تلقائيًا. لازم توافق من المتصفح أو إعدادات الموبايل.</p>
    </section>
    <button type="button" id="micShowGuide" class="ayo-mic-help-link">الموافقة مش بتظهر؟ شوف خطوات Chrome على الموبايل</button>
  </div>
</div>
<script type="module" src="app.js?v=9"></script>
</body>
</html>
