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
  <link rel="stylesheet" href="assets/app.css?v=038">
</head>
<body>
<div class="shell">
  <header>
    <div><span class="dot"></span><b>OnTrack Voice Demo</b><small>Gemini 3.8 + VoiceTut Multi-Space</small></div>
    <div id="engineBadge" class="badge">فحص المحركات…</div>
  </header>
  <main>
    <section class="hero">
      <div class="orb" id="orb"><div class="ring r1"></div><div class="ring r2"></div><span id="orbText">جاهز</span></div>
      <h1>مكالمة صوتية تجريبية</h1>
      <p>العقل Gemini 3.8 بمسارين تلقائيين، والصوت VoiceTut المصري بيلف على أكتر من Space لو واحد مش متاح.</p>

      <div class="controls">
        <label>الصوت المصري
          <select id="speaker">
            <optgroup label="رجالة">
              <option value="Abdullah" selected>Abdullah</option>
              <option value="Mohamed">Mohamed</option>
              <option value="Sayed">Sayed</option>
              <option value="Hossam">Hossam</option>
              <option value="Omar">Omar</option>
              <option value="Ahmed">Ahmed</option>
              <option value="Abdelrahman">Abdelrahman</option>
              <option value="Kamal">Kamal</option>
              <option value="Zaki">Zaki</option>
              <option value="Aly">Aly</option>
              <option value="Essam">Essam</option>
            </optgroup>
            <optgroup label="ستات">
              <option value="Esraa">Esraa</option>
              <option value="Asmaa">Asmaa</option>
              <option value="Hanan">Hanan</option>
              <option value="Sarah">Sarah</option>
              <option value="Yasmin">Yasmin</option>
              <option value="Omnia">Omnia</option>
            </optgroup>
          </select>
        </label>

        <button id="callBtn" class="primary">ابدأ المكالمة</button>
        <button id="stopBtn" class="danger" disabled>إنهاء</button>
      </div>

      <div class="hint">VoiceTut بيجرب أكتر من Space تلقائيًا. لو كلهم مش متاحين يروح لـEdge، وصوت الجهاز آخر حل فقط.</div>
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
<script type="module" src="assets/app.js?v=038"></script>
</body>
</html>
