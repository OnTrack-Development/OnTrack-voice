@extends('layouts.auth')

@section('title', 'تسجيل الدخول — OnTrack AI')

@section('content')
    <h1 style="font-size:22px;margin:0 0 4px">تسجيل الدخول</h1>
    <p class="subtle" style="font-size:13px;margin:0 0 22px">أهلاً بيك من تاني في منصة OnTrack.</p>

    <form method="POST" action="{{ route('login.store') }}" class="fields">
        @csrf
        <div class="field">
            <label for="email">البريد الإلكتروني</label>
            <input id="email" class="input" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" dir="ltr" style="text-align:right">
            @error('email')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="password">كلمة المرور</label>
            <input id="password" class="input" type="password" name="password" required autocomplete="current-password">
            @error('password')<div class="error">{{ $message }}</div>@enderror
        </div>
        <label style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--ot-muted)">
            <input type="checkbox" name="remember" value="1"> تذكرني على هذا الجهاز
        </label>
        <button class="btn btn-primary" type="submit" style="width:100%;padding:13px">تسجيل الدخول</button>
        <button class="btn btn-outline" type="button" id="demoFill" style="width:100%;padding:11px;margin-top:8px">استخدام حساب العرض التجريبي</button>
        <small id="demoHint" class="subtle" style="display:block;text-align:center;margin-top:6px">يملأ البيانات محليًا فقط — لا تستخدمه في الإنتاج.</small>
    </form>

    <script>
        document.getElementById('demoFill')?.addEventListener('click', function () {
            document.getElementById('email').value = 'demo@ontrack.local';
            document.getElementById('password').value = 'DemoPass123!';
            document.getElementById('demoHint').textContent = 'تم ملء حساب العرض — اضغط تسجيل الدخول للمتابعة.';
            document.getElementById('demoHint').style.color = 'var(--ot-success)';
        });
    </script>

    <p style="text-align:center;font-size:12px;color:var(--ot-muted);margin-top:20px">
        ليس لديك حساب؟ <a href="{{ route('register') }}" style="color:var(--ot-red);font-weight:700">أنشئ حسابك</a>
    </p>
@endsection