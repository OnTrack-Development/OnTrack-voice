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
  <link rel="stylesheet" href="assets/app.css?v=035">
</head>
<body>
<div class="shell">
  <header>
    <div><span class="dot"></span><b>OnTrack Voice Demo</b><small>Gemini 3.8 Brain + Free No-Key Voice</small></div>
    <div id="engineBadge" class="badge">فحص المحركات…</div>
  </header>
  <main>
    <section class="hero">
      <div class="orb" id="orb"><div class="ring r1"></div><div class="ring r2"></div><span id="orbText">جاهز</span></div>
      <h1>مكالمة صوتية تجريبية</h1>
      <p>العقل Gemini 3.8، والصوت من غير Voice API Key أو رصيد صوت.</p>
      <div class="controls">
        <label>محرك الصوت
          <select id="voiceEngine">
            <option value="device" selected>صوت الجهاز — جرّبه الأول</option>
            <option value="edge">Edge مصري — Shakir/Salma</option>
          </select>
        </label>
        <label>الصوت
          <select id="gender">
            <option value="male">راجل</option>
            <option value="female">ست</option>
          </select>
        </label>
        <label>السرعة
          <select id="rate">
            <option value="-8%">أهدى</option>
            <option value="-4%" selected>طبيعي هادي</option>
            <option value="+0%">طبيعي</option>
            <option value="+6%">أسرع</option>
          </select>
        </label>
        <button id="callBtn" class="primary">ابدأ المكالمة</button>
        <button id="stopBtn" class="danger" disabled>إنهاء</button>
      </div>
      <div class="hint">صوت الجهاز مفيهوش API أصلًا. لو مش عاجبك بدّل لـ Edge وقارن الاتنين.</div>
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
<script type="module" src="assets/app.js?v=035"></script>
</body>
</html>
