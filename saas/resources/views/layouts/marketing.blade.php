<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title', 'OnTrack AI — اصنع موظفك الذكي')</title>
    <meta name="description" content="@yield('description', 'منصة لبناء وتشغيل وكلاء الذكاء الاصطناعي للشركات والتكامل مع أي مشروع.')">
    <meta property="og:title" content="@yield('title', 'OnTrack AI')">
    <meta property="og:locale" content="ar_EG">
    <link rel="stylesheet" href="{{ asset('assets/ot.css') }}">
</head>
<body>
<header class="topnav">
    <div class="navinner container">
        <a class="brand" href="{{ url('/') }}" aria-label="OnTrack AI">
            <span class="brand-mark">O</span><span>OnTrack <span class="red">AI</span></span>
        </a>
        <nav class="navlinks" aria-label="القائمة الرئيسية">
            <a href="{{ url('/#features') }}">المنصة</a>
            <a href="{{ url('/#workflow') }}">كيف تعمل</a>
            <a href="{{ url('/#buttons') }}">الأزرار الذكية</a>
            <a href="{{ url('/#integrations') }}">التكاملات</a>
            <a href="{{ url('/#industries') }}">القطاعات</a>
        </nav>
        <div class="nav-actions">
            @auth
                <a href="{{ route('app.overview') }}" class="btn btn-primary btn-sm">الدخول للمنصة</a>
            @else
                <a href="{{ route('login') }}" style="font-size:12px;font-weight:600">تسجيل الدخول</a>
                <a href="{{ route('register') }}" class="btn btn-primary btn-sm">أنشئ وكيلك</a>
            @endauth
            <button class="mobile-menu" id="navToggle" aria-label="فتح القائمة" aria-expanded="false">☰</button>
        </div>
    </div>
    <nav id="mobileLinks" class="hide" style="padding:12px 20px;background:#fff;border-bottom:1px solid #e5e7eb" aria-label="قائمة الجوال">
        <a href="{{ url('/#features') }}">المميزات</a> ·
        <a href="{{ url('/#buttons') }}">الأزرار</a> ·
        <a href="{{ url('/#integrations') }}">التكاملات</a> ·
        <a href="{{ url('/#industries') }}">القطاعات</a>
    </nav>
</header>

<main>
    @yield('content')
</main>

<footer class="footer">
    <div class="container">
        <div class="brand" style="font-size:17px">
            <span class="brand-mark" style="width:28px;height:28px;font-size:15px">O</span>OnTrack AI
        </div>
        <nav aria-label="روابط التذييل">
            <a href="{{ url('/#features') }}">المميزات</a>
            <a href="{{ url('/#integrations') }}">التكاملات</a>
            <a href="{{ url('/#industries') }}">القطاعات</a>
            <a href="{{ route('login') }}">الدخول</a>
        </nav>
        <span>© {{ date('Y') }} OnTrack Development</span>
    </div>
</footer>

<script>
    (function () {
        var toggle = document.getElementById('navToggle');
        var links = document.getElementById('mobileLinks');
        if (!toggle || !links) return;
        toggle.addEventListener('click', function () {
            var open = links.classList.toggle('hide') === false;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    })();
</script>
</body>
</html>