<?php
$config = require __DIR__ . '/api/demo_config.php';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#07090d">
  <title>OnTrack Gemini 3.8 Live</title>
  <link rel="stylesheet" href="assets/app.css?v=040">
</head>
<body>
<div class="shell">
  <header>
    <div><span class="dot"></span><b>OnTrack Live</b><small>Gemini 3.8 Native Audio</small></div>
    <div id="engineBadge" class="badge">فحص Live…</div>
  </header>

  <main>
    <section class="hero">
      <div class="orb" id="orb">
        <div class="ring r1"></div>
        <div class="ring r2"></div>
        <span id="orbText">جاهز</span>
      </div>

      <h1>Gemini 3.8 Live — صوت لصوت</h1>
      <p>مفيش Speech Recognition منفصل، ومفيش TTS منفصل. صوتك بيروح للموديل Live والرد بيرجع Audio Streaming مباشرة.</p>

      <div class="controls">
        <label>الصوت
          <select id="voice">
            <option value="Puck" selected>Puck — Upbeat</option>
            <option value="Charon">Charon — Informative</option>
            <option value="Achird">Achird — Friendly</option>
            <option value="Sulafat">Sulafat — Warm</option>
            <option value="Gacrux">Gacrux — Mature</option>
            <option value="Algieba">Algieba — Smooth</option>
          </select>
        </label>

        <button id="callBtn" class="primary">ابدأ المكالمة</button>
        <button id="stopBtn" class="danger" disabled>إنهاء</button>
      </div>

      <div class="note">غيّر الصوت قبل بدء المكالمة. Gemini يحدد العربية تلقائياً، والـSystem Instruction مثبت على لهجة مصرية طبيعية.</div>
    </section>

    <section class="conversation" id="transcript"></section>

    <section class="debug">
      <span>الميكروفون: <b id="micState">متوقف</b></span>
      <span>Live: <b id="liveState">متوقف</b></span>
      <span>الموديل: <b id="modelState">gemini-3.8-live</b></span>
    </section>
  </main>
</div>

<script type="module" src="assets/app.js?v=040"></script>
</body>
</html>
