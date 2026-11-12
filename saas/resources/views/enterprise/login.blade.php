<!doctype html>
<html lang="ar" dir="rtl" data-theme="{{ $theme ?? 'dark' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>تسجيل الدخول — IO Voice</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/obsidian.css?v=68">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
            <div style="display:flex;align-items:center;gap:10px">
                <div class="brand-mark"><img src="/brand-logo" alt="IO Voice" style="width:28px;height:28px"></div>
                <div>
                    <div class="brand-name">IO Voice</div>
                    <div class="brand-sub">لوحة إدارة الوكلاء الصوتيين</div>
                </div>
            </div>
            <button type="button" class="btn btn-icon btn-ghost" data-theme-icon onclick="toggleIoTheme()" aria-label="تبديل المظهر">
                <svg class="icon-sun" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                <svg class="icon-moon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
        </div>

        <h1 style="font-size:20px;font-weight:700;margin:0 0 6px">تسجيل الدخول</h1>
        <p style="font-size:13px;color:var(--muted);margin:0 0 18px">أدخل بريدك الإلكتروني وكلمة المرور للوصول إلى مساحة العمل.</p>

        @if ($errors->any())
            <div class="alert alert-err">{{ $errors->first() }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-err">{{ session('error') }}</div>
        @endif
        @if (session('success'))
            <div class="alert alert-ok">{{ session('success') }}</div>
        @endif

        <form method="POST" action="/login">
            @csrf
            <div class="field">
                <label class="field-label" for="email">البريد الإلكتروني</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" dir="ltr">
            </div>
            <div class="field">
                <label class="field-label" for="password">كلمة المرور</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" dir="ltr">
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin:10px 0 16px;font-size:12.5px">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
                    <input type="checkbox" name="remember" value="1" style="width:auto;accent-color:var(--crimson)"> تذكرني
                </label>
                <a href="/forgot-password" style="color:var(--crimson)">نسيت كلمة المرور؟</a>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;padding:10px">دخول</button>
        </form>

        @if (!empty($showDemoFill))
            <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border)">
                <button type="button" id="demoFill" class="btn btn-sm" style="width:100%"
                    onclick="document.getElementById('email').value='demo@ontrack.local';document.getElementById('password').value='DemoPass123!';">
                    تعبئة بيانات التجربة المحلية (demo@ontrack.local)
                </button>
            </div>
        @endif

        <div style="margin-top:18px;text-align:center;font-size:13px;color:var(--muted)">
            ليس لديك حساب؟ <a href="/register" style="color:var(--crimson);font-weight:600">إنشاء حساب جديد</a>
        </div>
    </div>
</div>
<script src="/assets/js/obsidian.js?v=62"></script>
</body>
</html>
