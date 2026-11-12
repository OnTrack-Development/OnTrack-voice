<!doctype html>
<html lang="ar" dir="rtl" data-theme="{{ $theme ?? 'dark' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>حساب جديد — IO Voice</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/obsidian.css?v=62">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px">
            <div class="brand-mark"><img src="/brand-logo" alt="IO Voice" style="width:28px;height:28px"></div>
            <div>
                <div class="brand-name">IO Voice</div>
                <div class="brand-sub">تجهيز مساحة العمل الصوتية</div>
            </div>
        </div>
        <h1 style="font-size:20px;font-weight:700;margin:0 0 14px">إنشاء حساب جديد</h1>
        @if ($errors->any())
            <div class="alert alert-err">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="/register">
            @csrf
            <div class="field">
                <label class="field-label">الاسم الكامل</label>
                <input name="name" type="text" value="{{ old('name') }}" required>
            </div>
            <div class="field">
                <label class="field-label">اسم الشركة أو النشاط</label>
                <input name="company" type="text" value="{{ old('company') }}" required>
            </div>
            <div class="field">
                <label class="field-label">البريد الإلكتروني</label>
                <input name="email" type="email" value="{{ old('email') }}" required dir="ltr">
            </div>
            <div class="field">
                <label class="field-label">كلمة المرور (8 أحرف على الأقل)</label>
                <input name="password" type="password" required dir="ltr">
            </div>
            <div class="field">
                <label class="field-label">تأكيد كلمة المرور</label>
                <input name="password_confirmation" type="password" required dir="ltr">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;padding:10px;margin-top:6px">إنشاء الحساب والدخول</button>
        </form>
        <div style="margin-top:16px;text-align:center;font-size:13px;color:var(--muted)">
            لديك حساب بالفعل؟ <a href="/login" style="color:var(--crimson);font-weight:600">تسجيل الدخول</a>
        </div>
    </div>
</div>
<script src="/assets/js/obsidian.js?v=62"></script>
</body>
</html>
