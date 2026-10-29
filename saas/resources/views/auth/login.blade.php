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
    </form>

    <p style="text-align:center;font-size:12px;color:var(--ot-muted);margin-top:20px">
        ليس لديك حساب؟ <a href="{{ route('register') }}" style="color:var(--ot-red);font-weight:700">أنشئ حسابك</a>
    </p>
@endsection