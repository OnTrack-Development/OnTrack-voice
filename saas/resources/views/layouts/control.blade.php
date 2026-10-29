<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title', 'إدارة OnTrack')</title>
    <link rel="stylesheet" href="{{ asset('assets/ot.css') }}">
</head>
<body>
    <header class="app-top" style="position:static">
        <div style="display:flex;gap:12px;align-items:center">
            <b style="font-size:13px">لوحة إدارة المنصة — OnTrack</b>
            <span class="status orange">منطقة Platform-only</span>
        </div>
        <div class="profile">
            <span class="subtitle">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}" style="margin:0">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm">خروج</button>
            </form>
        </div>
    </header>
    <main class="app-content" style="max-width:1100px">
        @if (session('status'))
            <div class="alert" role="status" style="margin-bottom:16px">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert" role="alert" style="margin-bottom:16px">
                @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>