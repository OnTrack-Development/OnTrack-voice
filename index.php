<?php
$config = require __DIR__ . '/api/demo_config.php';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#08090c">
  <meta name="description" content="OnTrack Live — مكالمة صوتية ذكية مباشرة">
  <title>OnTrack Live</title>
  <link rel="preconnect" href="https://cdn.jsdelivr.net">
  <link rel="stylesheet" href="assets/app.css?v=056">
</head>
<body>
<div class="ambient ambient-a"></div>
<div class="ambient ambient-b"></div>

<div class="call-app">
  <main class="call-screen">
    <section class="call-panel">
      <div class="call-kicker">CLIENT VOICE PREVIEW</div>

      <div class="call-title">
        <h1>اتكلم مع أون تراك.</h1>
        <p>مكالمة صوتية مباشرة مع مساعد ذكي يعرف خدمات أون تراك وبيانات الديمو.</p>
      </div>

      <div class="voice-core-wrap">
        <div class="voice-core" id="orb">
          <div class="core-glow"></div>
          <div class="wave wave-1"></div>
          <div class="wave wave-2"></div>
          <div class="wave wave-3"></div>

          <div class="core-center">
            <div class="core-icon core-logo">
              <img src="https://ontrackegy.com/wp-content/uploads/2026/05/image.svg" alt="OnTrack">
            </div>
            <strong id="orbText">جاهز</strong>
            <small id="callTimer">00:00</small>
          </div>
        </div>
      </div>

      <div class="call-info-line">
        <span class="call-info-item">
          <small>الحالة</small>
          <strong id="liveState">جاهز</strong>
        </span>
        <span class="call-info-separator"></span>
        <span class="call-info-item">
          <small>الميكروفون</small>
          <strong id="micState">متوقف</strong>
        </span>
        <span class="call-info-separator"></span>
        <span class="call-info-item">
          <small>الصوت</small>
          <strong id="voiceState">Charon</strong>
        </span>
      </div>

      <div class="voice-select-row">
        <label for="voice">شخصية الصوت</label>
        <div class="select-wrap">
          <select id="voice" aria-label="اختيار صوت Gemini">
            <optgroup label="مقترحة">
              <option value="Charon" selected>Charon — واضح ومعلوماتي</option>
              <option value="Achird">Achird — ودود</option>
              <option value="Algieba">Algieba — ناعم</option>
              <option value="Gacrux">Gacrux — ناضج</option>
              <option value="Sulafat">Sulafat — دافئ</option>
              <option value="Schedar">Schedar — متزن</option>
              <option value="Kore">Kore — حازم</option>
              <option value="Puck">Puck — حيوي</option>
            </optgroup>
            <optgroup label="أصوات إضافية">
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
        <button id="callBtn" class="call-btn call-btn-start">
          <span class="call-btn-icon">●</span>
          <span>ابدأ المكالمة</span>
        </button>

        <button id="muteBtn" class="call-btn call-btn-mute" disabled>
          <span id="muteIcon">◉</span>
          <span id="muteText">كتم</span>
        </button>

        <button id="stopBtn" class="call-btn call-btn-end" disabled>
          <span>■</span>
          <span>إنهاء</span>
        </button>
      </div>

      <div class="call-note">
        تجربة ببيانات Demo فقط — متبعتش كلمات مرور أو بيانات بنكية.
      </div>

      <div class="sr-only" id="modelState">gemini-3.8-live</div>
      <div class="sr-only" id="sessionBadge">READY</div>
    </section>
  </main>
</div>

<section class="chat-popup" id="chatPopup" aria-hidden="true">
  <div class="chat-popup-card">
    <div class="chat-popup-head">
      <div class="chat-person">
        <span class="chat-avatar chat-avatar-logo">
          <img src="https://ontrackegy.com/wp-content/uploads/2026/05/image.svg" alt="OnTrack">
        </span>
        <div>
          <strong>OnTrack AI</strong>
          <small><span class="chat-live-dot"></span> المكالمة جارية</small>
        </div>
      </div>

      <button id="clearTranscript" class="chat-clear" type="button" aria-label="مسح المحادثة">مسح</button>
    </div>

    <div class="conversation" id="transcript">
      <div class="empty-state" id="emptyTranscript">
        <div class="typing-bars"><i></i><i></i><i></i></div>
        <strong>ابدأ تتكلم</strong>
        <span>المحادثة هتظهر هنا أثناء المكالمة.</span>
      </div>
    </div>

    <div class="chat-call-controls">
      <button id="popupMuteBtn" class="popup-call-btn popup-mute" type="button" disabled>
        <span id="popupMuteIcon">◉</span>
        <span id="popupMuteText">كتم</span>
      </button>

      <button id="popupStopBtn" class="popup-call-btn popup-end" type="button" disabled>
        <span>■</span>
        <span>إنهاء المكالمة</span>
      </button>
    </div>
  </div>
</section>

<span id="statusDot" class="sr-only"></span>
<span id="engineBadge" class="sr-only">جاهز للمكالمة</span>

<script type="module" src="assets/app.js?v=056"></script>
</body>
</html>
