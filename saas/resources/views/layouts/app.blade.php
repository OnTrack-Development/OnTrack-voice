<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title', 'OnTrack AI')</title>
    <link rel="stylesheet" href="{{ asset('assets/ot.css') }}">
    @stack('head')
</head>
<body>
    <div class="app-root">
        <aside class="sidebar" id="sidebar">
            <div class="side-brand">
                <a class="brand" href="{{ route('app.home') }}"><span class="brand-mark">O</span>OnTrack <span class="red">AI</span></a>
            </div>
            <div class="switcher">
                <div>@yield('workspace-name', 'المساحة')<small>@yield('workspace-sub', '')</small></div>
                <span aria-hidden="true">⌄</span>
            </div>
            <p class="side-section">الرئيسية</p>
            <a class="side-link @if(request()->routeIs('app.overview')) active @endif" href="{{ route('app.overview', ['workspace' => $workspace->slug ?? '']) }}">
                <span aria-hidden="true">▦</span> نظرة عامة
            </a>
            <p class="side-section">التشغيل</p>
            <a class="side-link @if(request()->routeIs('app.projects.*')) active @endif" href="{{ route('app.projects.index', ['workspace' => $workspace->slug ?? '']) }}">
                <span aria-hidden="true">▦</span> المشروعات
            </a>
            <a class="side-link @if(request()->routeIs('app.agents.*')) active @endif" href="{{ route('app.agents.index', ['workspace' => $workspace->slug ?? '']) }}">
                <span aria-hidden="true">◧</span> استوديو الوكلاء
            </a>
            <a class="side-link @if(request()->routeIs('app.knowledge.*')) active @endif" href="{{ route('app.knowledge.index', ['workspace' => $workspace->slug ?? '']) }}">
                <span aria-hidden="true">▤</span> قواعد المعرفة
            </a>
            <a class="side-link @if(request()->routeIs('app.smart-actions.*')) active @endif" href="{{ route('app.smart-actions.index', ['workspace' => $workspace->slug ?? '']) }}">
                <span aria-hidden="true">↗</span> الأزرار الذكية
            </a>
            <span class="side-link" style="cursor:not-allowed;opacity:.6"><span aria-hidden="true">⌁</span> التكاملات</span>
            <span class="side-link" style="cursor:not-allowed;opacity:.6"><span aria-hidden="true">☏</span> القنوات</span>
            <a class="side-link @if(request()->routeIs('app.inbox.*')) active @endif" href="{{ route('app.inbox.index', ['workspace' => $workspace->slug ?? '']) }}">
                <span aria-hidden="true">▣</span> صندوق الوارد
            </a>
            <span class="side-link" style="cursor:not-allowed;opacity:.6"><span aria-hidden="true">♧</span> إدارة العملاء</span>
            <p class="side-section">الإدارة</p>
            <span class="side-link" style="cursor:not-allowed;opacity:.6"><span aria-hidden="true">◴</span> غرفة العمليات</span>
            <span class="side-link" style="cursor:not-allowed;opacity:.6"><span aria-hidden="true">⚙</span> الإعدادات</span>
            <div class="alert" style="margin-top:30px">هذه المرحلة تعرض شريحة التشغيل الأساسية؛ باقي الوحدات قيد البناء.</div>
        </aside>

        <div class="app-main">
            <header class="app-top">
                <div style="display:flex;gap:12px;align-items:center">
                    <button class="mobile-menu menu-trigger" onclick="document.getElementById('sidebar').classList.toggle('open')" style="display:none" aria-label="فتح القائمة">☰</button>
                    <div>
                        <b style="font-size:13px">@yield('page-title', 'المساحة')</b>
                        <div class="subtitle">@yield('page-sub', '')</div>
                    </div>
                </div>
                <div class="profile">
                    <span class="subtitle">{{ auth()->user()->name }}</span>
                    <span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                    <form method="POST" action="{{ route('logout') }}" style="margin:0">
                        @csrf
                        <button type="submit" class="btn btn-outline btn-sm">خروج</button>
                    </form>
                </div>
            </header>
            <main class="app-content">
                @yield('content')
            </main>
        </div>
    </div>
    <script>
        (function () {
            var toggle = document.querySelector('.menu-trigger');
            if (toggle) toggle.addEventListener('click', function (e) {
                e.preventDefault();
                document.getElementById('sidebar').classList.toggle('open');
            });
        })();
    </script>
</body>
</html>