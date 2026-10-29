@extends('layouts.auth')

@section('title', 'إنشاء حساب — OnTrack AI')

@section('content')
    <h1 style="font-size:22px;margin:0 0 4px">أنشئ حسابك</h1>
    <p class="subtle" style="font-size:13px;margin:0 0 22px">مساحتك الأولى بتحتاج اسم شركتك — يمكنك تفصيلها لاحقًا.</p>

    <form method="POST" action="{{ route('register.store') }}" class="fields">
        @csrf
        <div class="field">
            <label for="name">الاسم بالكامل</label>
            <input id="name" class="input" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
            @error('name')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="company">اسم الشركة / المساحة</label>
            <input id="company" class="input" type="text" name="company" value="{{ old('company') }}" required autocomplete="organization">
            @error('company')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="email">البريد الإلكتروني</label>
            <input id="email" class="input" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" dir="ltr" style="text-align:right">
            @error('email')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="password">كلمة المرور</label>
            <input id="password" class="input" type="password" name="password" required autocomplete="new-password">
            <small>10 أحرف على الأقل، بها أرقام وحروف.</small>
            @error('password')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="password_confirmation">تأكيد كلمة المرور</label>
            <input id="password_confirmation" class="input" type="password" name="password_confirmation" required autocomplete="new-password">
        </div>
        <button class="btn btn-primary" type="submit" style="width:100%;padding:13px">إنشاء حساب وبدء البناء</button>
    </form>

    <p style="text-align:center;font-size:12px;color:var(--ot-muted);margin-top:20px">
        لديك حساب بالفعل؟ <a href="{{ route('login') }}" style="color:var(--ot-red);font-weight:700">سجل الدخول</a>
    </p>
@endsection