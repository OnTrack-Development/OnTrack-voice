<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title', 'OnTrack AI')</title>
    <link rel="stylesheet" href="{{ asset('assets/ot.css') }}">
    @stack('head')
</head>
<body class="auth-wrap">
    <main class="auth-card">
        <a class="brand" href="{{ url('/') }}" style="justify-content:center;margin-bottom:22px" aria-label="OnTrack AI">
            <span class="brand-mark">O</span><span>OnTrack <span class="red">AI</span></span>
        </a>
        @if ($errors->any())
            <div class="alert" role="alert" style="margin-bottom:18px">
                <strong style="display:block;margin-bottom:4px">تحقق من البيانات:</strong>
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif
        @if (session('status'))
            <div class="alert" role="status" style="margin-bottom:18px">
                {{ session('status') }}
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>