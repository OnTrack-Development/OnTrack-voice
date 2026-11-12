<!doctype html>
<html lang="ar" dir="rtl" data-theme="{{ $theme ?? 'dark' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>IO Voice — منصة الوكلاء الصوتيين الذكيين | OnTrack</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/obsidian.css?v=68">
</head>
<body>
<header class="topbar portal-topbar">
    <div class="topbar-left">
        <div class="brand-mark">
            <img src="/brand-logo" alt="IO Voice" style="width:28px;height:28px">
        </div>
        <div>
            <div class="brand-name">IO Voice</div>
            <div class="brand-sub">OnTrack Enterprise Voice AI</div>
        </div>
    </div>
    <div class="topbar-right">
        <a href="/platform" class="btn btn-ghost btn-sm">المنصة</a>
        <a href="/pricing" class="btn btn-ghost btn-sm">الباقات</a>
        <button type="button" class="btn btn-icon btn-ghost" data-theme-icon onclick="toggleIoTheme()" aria-label="تبديل المظهر">
            <svg class="icon-sun" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
            <svg class="icon-moon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <a href="/login" class="btn btn-sm">تسجيل الدخول</a>
        <a href="/register" class="btn btn-primary btn-sm">ابدأ مجاناً</a>
    </div>
</header>

<main class="portal-main">
    <div style="text-align:center;max-width:740px;margin:0 auto 48px">
        <span class="pill pill-crimson" style="margin-bottom:14px">
            <span class="dot online"></span> IO Voice · Android Dual-SIM & Live Cloud Studio
        </span>
        <h1 class="portal-hero-title">
            أتمتة المكالمات الهاتفية بذكاء اصطناعي فوري الاستجابة عبر شرائح الهاتف مباشرة
        </h1>
        <p style="color:var(--muted);font-size:16px;margin:0 0 26px">
            منصة <strong style="color:var(--text)">IO Voice</strong> من <strong style="color:var(--text)">OnTrack</strong> تمنح فريقك وكلاء صوتيين طبيعيين باللهجة المصرية والعربية، ربط شرائح SIM1/SIM2 خلال ثوانٍ، تفريغ صوتي مشفر، وحملات صادرة وأتمتة ما بعد المكالمة.
        </p>
        <div style="display:flex;justify-content:center;gap:12px;flex-wrap:wrap">
            <a href="/login" class="btn btn-primary" style="padding:11px 22px;font-size:14.5px">دخول لوحة التحكم</a>
            <a href="/register" class="btn" style="padding:11px 22px;font-size:14.5px">إنشاء حساب شركة</a>
            <a href="{{ config('ontrack.apk_download_path', '/mobile/updates/native/IOVoice-v62.apk') }}" download="OnTrack-Voice-{{ config('ontrack.apk_version_name', '0.3.44') }}.apk" class="btn btn-ghost">تحميل تطبيق Android v{{ config('ontrack.apk_version_name', '0.3.39') }}</a>
        </div>
    </div>

    <div class="grid-3">
        <div class="card">
            <div class="pill pill-crimson" style="margin-bottom:10px">استوديو الإيجنت والأصوات</div>
            <h3 class="card-title">سيناريوهات مرنة + أصوات IO Premium</h3>
            <p class="card-sub">التزام حرفي بالسيناريو، تكيّف تلقائي مع جنس المتصل، واستماع فوري لعينات Fish Audio و Gemini Live بدون هدر التوكنز.</p>
        </div>
        <div class="card">
            <div class="pill pill-green" style="margin-bottom:10px">ربط شرائح الهاتف SIM1 / SIM2</div>
            <h3 class="card-title">تبديل الإيجنت بضغطة واحدة</h3>
            <p class="card-sub">اربط أي هاتف Android عبر كود 6 أرقام وبدّل الإيجنت الفعال على كل شريحة لحظياً دون إعادة الربط.</p>
        </div>
        <div class="card">
            <div class="pill pill-blue" style="margin-bottom:10px">أتمتة ما بعد المكالمة</div>
            <h3 class="card-title">Webhooks آمنة + تحديث CRM</h3>
            <p class="card-sub">أرسل ملخص المكالمة والنتيجة والتقييم إلى نظامك تلقائياً بتوقيع HMAC-SHA256 وحماية كاملة ضد SSRF.</p>
        </div>
    </div>
</main>
<script src="/assets/js/obsidian.js?v=62"></script>
</body>
</html>
