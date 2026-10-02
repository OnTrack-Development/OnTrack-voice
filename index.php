<?php
$config = require __DIR__ . '/api/demo_config.php';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#08090c">
  <meta name="description" content="OnTrack Live — مساعد صوتي ذكي لخدمة العملاء والمبيعات">
  <title>OnTrack Live — Voice AI</title>
  <link rel="preconnect" href="https://cdn.jsdelivr.net">
  <link rel="stylesheet" href="assets/app.css?v=052">
</head>
<body>
<div class="ambient ambient-a"></div>
<div class="ambient ambient-b"></div>

<div class="app-shell">
  <header class="topbar">
    <a class="brand" href="https://ontrackegy.com/" target="_blank" rel="noopener">
      <span class="brand-mark">
        <img src="https://ontrackegy.com/wp-content/uploads/2026/05/image.svg" alt="OnTrack">
      </span>
      <span class="brand-copy">
        <strong>OnTrack Live</strong>
        <small>AI Voice Concierge</small>
      </span>
    </a>

    <div class="top-status">
      <span class="status-dot" id="statusDot"></span>
      <span id="engineBadge">جاهز للتجربة</span>
    </div>
  </header>

  <main class="main-grid">
    <section class="call-stage">
      <div class="stage-head">
        <div>
          <span class="eyebrow">CLIENT PREVIEW</span>
          <h1>اتكلم مع أون تراك طبيعي.</h1>
          <p>مكالمة صوتية مباشرة مع Gemini Live، متصلة بقاعدة معرفة تجريبية لخدمات واستضافة أون تراك.</p>
        </div>
        <div class="live-pill"><span></span> LIVE AI</div>
      </div>

      <div class="voice-core-wrap">
        <div class="voice-core" id="orb">
          <div class="core-glow"></div>
          <div class="wave wave-1"></div>
          <div class="wave wave-2"></div>
          <div class="wave wave-3"></div>
          <div class="core-center">
            <div class="core-icon" id="coreIcon">AI</div>
            <strong id="orbText">جاهز</strong>
            <small id="callTimer">00:00</small>
          </div>
        </div>
      </div>

      <div class="voice-picker">
        <div class="field-label">
          <span>اختار شخصية الصوت</span>
          <small>تقدر تغيّر الصوت قبل كل مكالمة</small>
        </div>
        <div class="select-wrap">
          <select id="voice" aria-label="اختيار صوت Gemini">
            <optgroup label="مقترحة للمكالمات">
              <option value="Charon" selected>Charon — واضح ومعلوماتي</option>
              <option value="Achird">Achird — ودود</option>
              <option value="Algieba">Algieba — ناعم</option>
              <option value="Gacrux">Gacrux — ناضج</option>
              <option value="Sulafat">Sulafat — دافئ</option>
              <option value="Schedar">Schedar — متزن</option>
              <option value="Kore">Kore — حازم</option>
              <option value="Puck">Puck — حيوي</option>
            </optgroup>
            <optgroup label="كل الأصوات">
              <option value="Zephyr">Zephyr — مشرق</option>
              <option value="Fenrir">Fenrir — متحمس</option>
              <option value="Leda">Leda — شبابي</option>
              <option value="Orus">Orus — حازم</option>
              <option value="Aoede">Aoede — خفيف</option>
              <option value="Callirrhoe">Callirrhoe — هادي</option>
              <option value="Autonoe">Autonoe — مشرق</option>
              <option value="Enceladus">Enceladus — هوائي</option>
              <option value="Iapetus">Iapetus — واضح</option>
              <option value="Umbriel">Umbriel — مريح</option>
              <option value="Despina">Despina — ناعم</option>
              <option value="Erinome">Erinome — واضح</option>
              <option value="Algenib">Algenib — خشن</option>
              <option value="Rasalgethi">Rasalgethi — معلوماتي</option>
              <option value="Laomedeia">Laomedeia — حيوي</option>
              <option value="Achernar">Achernar — ناعم</option>
              <option value="Alnilam">Alnilam — حازم</option>
              <option value="Pulcherrima">Pulcherrima — مباشر</option>
              <option value="Zubenelgenubi">Zubenelgenubi — كاجوال</option>
              <option value="Vindemiatrix">Vindemiatrix — لطيف</option>
              <option value="Sadachbia">Sadachbia — نشيط</option>
              <option value="Sadaltager">Sadaltager — خبير</option>
            </optgroup>
          </select>
          <span class="select-arrow">⌄</span>
        </div>
      </div>

      <div class="call-actions">
        <button id="callBtn" class="btn btn-primary">
          <span class="btn-icon">●</span>
          <span>ابدأ المكالمة</span>
        </button>
        <button id="muteBtn" class="btn btn-secondary" disabled>
          <span id="muteIcon">◉</span>
          <span id="muteText">كتم الميكروفون</span>
        </button>
        <button id="stopBtn" class="btn btn-danger" disabled>
          <span>■</span>
          <span>إنهاء</span>
        </button>
      </div>

      <div class="privacy-line">
        <span>🔒</span>
        <p>تجربة آمنة ببيانات Demo فقط. لا ترسل كلمات مرور أو بيانات بنكية أثناء التجربة.</p>
      </div>
    </section>

    <aside class="side-panel">
      <section class="panel-card status-card">
        <div class="panel-title">
          <div>
            <span class="eyebrow">SESSION</span>
            <h2>حالة المكالمة</h2>
          </div>
          <span class="mini-badge" id="sessionBadge">READY</span>
        </div>

        <div class="status-list">
          <div class="status-row">
            <span class="status-icon">◉</span>
            <div><small>Gemini Live</small><strong id="liveState">جاهز</strong></div>
          </div>
          <div class="status-row">
            <span class="status-icon">⌁</span>
            <div><small>الميكروفون</small><strong id="micState">متوقف</strong></div>
          </div>
          <div class="status-row">
            <span class="status-icon">◆</span>
            <div><small>الموديل</small><strong id="modelState">gemini-3.8-live</strong></div>
          </div>
          <div class="status-row">
            <span class="status-icon">♪</span>
            <div><small>الصوت</small><strong id="voiceState">Charon</strong></div>
          </div>
        </div>
      </section>

      <section class="panel-card ideas-card">
        <div class="panel-title">
          <div>
            <span class="eyebrow">TRY IT</span>
            <h2>جرّب تسأله</h2>
          </div>
        </div>
        <div class="idea-list">
          <button class="idea" data-prompt="رشحلي استضافة مناسبة لشركة صغيرة وموقع شركة وبريد أعمال">رشحلي استضافة لشركة صغيرة</button>
          <button class="idea" data-prompt="قولي تفاصيل الفاتورة DEMO-1001 وحالتها وإجماليها">قولي تفاصيل DEMO-1001</button>
          <button class="idea" data-prompt="إيه الفرق بين Starter Plan و Reseller 15 users؟">قارن Starter و Reseller</button>
          <button class="idea" data-prompt="أنا عاوز موقع وبريد لشركة أبو نخلة، ترشحلي إيه؟">رشحلي لأبو نخلة</button>
        </div>
        <small class="idea-note">ابدأ المكالمة الأول، وبعدها تقدر تضغط أي مثال أو تسأل بصوتك.</small>
      </section>

      <section class="panel-card quick-links-card">
        <div class="panel-title">
          <div>
            <span class="eyebrow">QUICK LINKS</span>
            <h2>روابط سريعة</h2>
          </div>
        </div>
        <div class="quick-links">
          <a href="https://ontrackegy.com/" target="_blank" rel="noopener noreferrer">
            <span>↗</span>
            <div><strong>موقع أون تراك</strong><small>ontrackegy.com</small></div>
          </a>
          <a href="https://services.ontrackegy.com/" target="_blank" rel="noopener noreferrer">
            <span>↗</span>
            <div><strong>بوابة العملاء</strong><small>الخدمات والفواتير</small></div>
          </a>
          <a href="https://whatsapp.ontrackegy.com/" target="_blank" rel="noopener noreferrer">
            <span>↗</span>
            <div><strong>WhatsApp Automation</strong><small>منصة أون تراك للواتساب</small></div>
          </a>
        </div>
      </section>
    </aside>

    <section class="conversation-card">
      <div class="conversation-head">
        <div>
          <span class="eyebrow">LIVE TRANSCRIPT</span>
          <h2>المحادثة</h2>
        </div>
        <button id="clearTranscript" class="ghost-btn">مسح</button>
      </div>

      <div class="conversation" id="transcript">
        <div class="empty-state" id="emptyTranscript">
          <div class="empty-icon">⌁</div>
          <strong>المحادثة هتظهر هنا</strong>
          <span>ابدأ المكالمة واتكلم طبيعي، والنص هيتسجل أثناء الجلسة.</span>
        </div>
      </div>
    </section>

    <section class="capabilities">
      <article>
        <span>01</span>
        <div><strong>خدمات وأسعار</strong><small>Shared, Reseller, VPS, Dedicated والمزيد</small></div>
      </article>
      <article>
        <span>02</span>
        <div><strong>فواتير تجريبية</strong><small>بحث وحالة وإجماليات من بيانات Demo</small></div>
      </article>
      <article>
        <span>03</span>
        <div><strong>مبيعات ذكية</strong><small>ترشيح خدمة حسب احتياج العميل</small></div>
      </article>
      <article>
        <span>04</span>
        <div><strong>محادثة طبيعية</strong><small>صوت لصوت مع إمكانية المقاطعة</small></div>
      </article>
    </section>
  </main>

  <footer>
    <span>OnTrack Development</span>
    <span>Client Voice AI Preview • Demo data only</span>
  </footer>
</div>

<script type="module" src="assets/app.js?v=052"></script>
</body>
</html>
