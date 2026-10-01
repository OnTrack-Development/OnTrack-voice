<?php
$cfgPath = __DIR__ . '/api/demo_config.php';
$config = is_readable($cfgPath) ? require $cfgPath : ['app_name' => 'OnTrack Voice Demo'];
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#07090d">
  <title><?= htmlspecialchars((string)($config['app_name'] ?? 'OnTrack Voice Demo')) ?></title>
  <link rel="stylesheet" href="assets/app.css?v=034">
</head>
<body>
<div class="shell">
  <header>
    <div><span class="dot"></span><b>OnTrack Voice Demo</b><small>Gemini 3.8 Brain + Gemini 3.8 TTS</small></div>
    <div id="engineBadge" class="badge">فحص المحركات…</div>
  </header>

  <main>
    <section class="hero">
      <div class="orb" id="orb"><div class="ring r1"></div><div class="ring r2"></div><span id="orbText">جاهز</span></div>
      <h1>مكالمة صوتية تجريبية</h1>
      <p>Gemini 3.8 للعقل، وGemini 3.8 TTS للصوت المصري الطبيعي.</p>

      <div class="controls">
        <label>الصوت
          <select id="gender">
            <option value="male">راجل مصري — Charon</option>
            <option value="female">ست مصرية — Sulafat</option>
          </select>
        </label>
        <label>سرعة الصوت
          <select id="rate">
            <option value="-8%">أهدى</option>
            <option value="+0%" selected>طبيعي</option>
            <option value="+8%">أسرع</option>
          </select>
        </label>
        <button id="callBtn" class="primary">ابدأ المكالمة</button>
        <button id="stopBtn" class="danger" disabled>إنهاء</button>
      </div>

      <div class="hint">جرّب: «رشحلي استضافة لشركة أبو نخلة» — «قولي تفاصيل DEMO-1001» — «إيه الفرق بين Starter والـReseller؟»</div>
    </section>

    <section class="conversation" id="conversation"></section>

    <section class="debug">
      <span>الميكروفون: <b id="micState">متوقف</b></span>
      <span>العقل: <b id="aiState">—</b></span>
      <span>الصوت: <b id="ttsState">—</b></span>
      <span>التعرف: <b id="sttState">—</b></span>
    </section>
  </main>
</div>
<script type="module" src="assets/app.js?v=034"></script>
</body>
</html>
