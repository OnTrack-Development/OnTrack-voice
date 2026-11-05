<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
  <meta name="theme-color" content="#0d0f12"/>
  <title>AYO | OnTrack Voice Lab</title>
  <link rel="stylesheet" href="style.css?v=3"/>
</head>
<body>
<div class="ambient ambient-a"></div><div class="ambient ambient-b"></div>
<div class="shell">
  <header class="topbar">
    <div class="brand"><div class="brand-mark"><span class="bolt">✦</span></div><div><strong>AYO <small>VOICE LAB</small></strong><p>مختبر المحادثات الصوتية · OnTrack Development</p></div></div>
    <div class="top-actions"><span class="stage-pill"><i></i> DEMO / INTERNAL</span><span class="connection" id="connection"><b></b> غير متصل</span></div>
  </header>
  <div class="headline"><div><span class="eyebrow">VOICE CLONING × STREAMING</span><h1>خلّي آيو <em>تتكلم.</em></h1><p>اختبار مباشر لصوت AYO المستنسخ، وردود APInex أو Omnira، وتدفّق صوتي لحظي. كل حاجة هنا منفصلة عن النظام الأساسي.</p></div><div class="model-chip" id="modelChip">INWORLD TTS-2 <span>+</span> APINEX LLM</div></div>
  <main class="dashboard">
    <section class="stage-panel panel">
      <div class="panel-head"><div><span class="dot-on" id="callLed"></span><span class="subheading" id="callStatus">جاهزة للتجربة</span></div><span class="subtitle" id="sessionLabel">جلسة اختبار مستقلة · حد أقصى دقيقتان</span></div>
      <div class="stage-center">
        <div class="orb-wrap" id="orbWrap"><div class="orbit orbit1"></div><div class="orbit orbit2"></div><div class="orb" id="orb"><span class="orb-symbol">✦</span></div><span class="orb-flare flare1"></span><span class="orb-flare flare2"></span></div>
        <div class="stage-persona"><h2>آيو <span>AYO</span></h2><p id="stageHint">مساعدة أون تراك الصوتية · تجربة داخل المتصفح</p></div>
        <div class="visualizer" id="visualizer" aria-hidden="true"></div>
        <div class="clock" id="clock">02:00</div><span class="clock-label">الوقت المتبقي</span>
      </div>
      <div class="call-controls">
        <button class="round-control" id="muteBtn" title="كتم الميكروفون" disabled><span id="muteIcon">◉</span><small>كتم</small></button>
        <button class="main-call" id="callBtn"><span id="callIcon">☎</span><strong id="callText">ابدأ المكالمة</strong></button>
        <button class="round-control" id="interruptBtn" title="اقطع رد الإيجنت" disabled><span>Ⅱ</span><small>مقاطعة</small></button>
      </div>
      <div class="test-mode"><label>نوع الاختبار <select id="testMode"><option value="live">مباشر — LLM خارجي + Inworld</option><option value="mock">محاكاة واجهة — بدون API</option></select></label><label>السماع <select id="sttMode"><option value="browser">تعرف كلام المتصفح (مجاني)</option><option value="inworld" disabled>Inworld STT غير مفعل في ديمو PHP</option></select></label></div>
      <label class="switch-line"><input type="checkbox" id="bargeIn"><span class="check-visual"></span><span>السماح بمقاطعة صوت آيو بالكلام (قد يلتقط صوت السماعة)</span></label>
      <div class="warn-banner" id="banner">الأصوات المستنسخة تحتاج مفتاح Inworld وVoice ID. المحاكاة لا تختبر جودة الصوت.</div>
    </section>
    <section class="conversation-panel panel">
      <div class="panel-head"><div class="panel-label"><span class="speech-icon">⌘</span><h3>المحادثة المباشرة</h3></div><button class="btn-quiet" id="clearBtn">مسح الشاشة</button></div>
      <div class="conversation-scroll" id="transcript"><div class="empty-chat"><div class="empty-icon">◌</div><h3>ابدأ الحديث مع آيو</h3><p>اسأل عن باقات الاستضافة، الفرق بين الريسيلر والـVPS، أو أسعار الدومينات.</p></div></div>
      <div class="interim" id="interim"></div>
      <form class="composer" id="composer"><button class="send-btn" aria-label="إرسال" type="submit">➤</button><input id="question" autocomplete="off" placeholder="أو اكتب سؤالك هنا..." maxlength="1500"/><span class="composer-caption">ENTER</span></form>
      <div class="chat-footer"><span>AR-EG / مصري</span><span id="audioStatus">PCM 24 kHz · HTTP Streaming</span><button class="link-button" id="downloadLast" disabled>↓ تحميل آخر رد WAV</button></div>
    </section>
    <aside class="side">
      <section class="panel config-panel"><div class="panel-head"><div class="panel-label"><span class="speech-icon">⚙</span><h3>إعدادات التجربة</h3></div><button id="toggleConfig" class="btn-quiet">إظهار</button></div>
        <div id="configBody" class="hidden">
          <form id="configForm" class="config-form">
            <label>مفتاح دخول إضافي (لا يلزم في النسخة المعزولة)<input type="password" id="accessToken" disabled placeholder="فارغ على localhost" autocomplete="off"/></label>
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
            <small class="helper">المفاتيح لا تُعرض في HTML ولا في استجابة حالة السيرفر. يفضل وضعها في متغيرات بيئة السيرفر. لو دخلتها هنا ستُحفظ في جلسة PHP على الاستضافة ولا تُكتب في Git.</small>
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
        <button id="previewBtn" class="btn-clone" type="button">▶ اسمع صوت آيو</button>
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
<script type="module" src="app.js?v=3"></script>
</body>
</html>
