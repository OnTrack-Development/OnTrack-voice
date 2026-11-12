<!doctype html>
<html lang="ar" dir="rtl" data-theme="{{ $theme ?? 'dark' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $title ?? 'IO Voice' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/obsidian.css?v=62">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <h1 style="font-size:19px;margin:0 0 12px">{{ $title ?? 'إعادة تعيين كلمة المرور' }}</h1>
        @if (session('success'))
            <div class="alert alert-ok">{{ session('success') }}</div>
        @endif
        @if (($mode ?? '') === 'forgot')
            <form method="POST" action="/forgot-password">
                @csrf
                <div class="field">
                    <label class="field-label">البريد الإلكتروني المسجل</label>
                    <input type="email" name="email" required dir="ltr">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%">إرسال الطلب</button>
            </form>
        @endif
        <div style="margin-top:16px;text-align:center">
            <a href="/login" class="btn btn-ghost btn-sm">العودة لتسجيل الدخول</a>
        </div>
    </div>
</div>
</body>
</html>
