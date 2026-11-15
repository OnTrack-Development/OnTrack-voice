<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حجز موعد مع {{ $account['name'] ?? 'OnTrack AI' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-canvas: #F8FAFC;
            --surface: #FFFFFF;
            --topbar-bg: #0B0F19;
            --primary: #4F46E5;
            --primary-hover: #4338CA;
            --text-main: #0F172A;
            --text-muted: #64748B;
            --border-light: #E2E8F0;
            --success: #10B981;
            --danger: #EF4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Cairo', 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-canvas);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .topbar {
            background: var(--topbar-bg);
            color: #FFFFFF;
            padding: 18px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            font-size: 1.15rem;
            letter-spacing: -0.02em;
        }
        .brand-badge {
            background: linear-gradient(135deg, #6366F1, #8B5CF6);
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 900;
            color: #FFFFFF;
        }
        .container {
            max-width: 640px;
            width: 100%;
            margin: 40px auto;
            padding: 0 20px;
            flex: 1;
        }
        .card {
            background: var(--surface);
            border-radius: 16px;
            border: 1px solid var(--border-light);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
            padding: 32px;
        }
        .card-header {
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border-light);
            padding-bottom: 20px;
        }
        .card-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 6px;
        }
        .card-subtitle {
            color: var(--text-muted);
            font-size: 0.95rem;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-label {
            display: block;
            font-size: 0.88rem;
            font-weight: 600;
            margin-bottom: 6px;
            color: #334155;
        }
        .form-control {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid var(--border-light);
            font-family: inherit;
            font-size: 0.95rem;
            background: #FFFFFF;
            transition: all 0.2s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
        }
        .row-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 500px) {
            .row-grid { grid-template-columns: 1fr; }
        }
        .btn-submit {
            background: var(--primary);
            color: #FFFFFF;
            border: none;
            border-radius: 10px;
            padding: 14px 24px;
            font-size: 1.05rem;
            font-weight: 700;
            width: 100%;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
            margin-top: 10px;
        }
        .btn-submit:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }
        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            font-weight: 600;
        }
        .alert-success {
            background: #ECFDF5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }
        .alert-danger {
            background: #FEF2F2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }
        .footer-note {
            text-align: center;
            font-size: 0.82rem;
            color: var(--text-muted);
            margin-top: 24px;
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="brand">
            <div class="brand-badge">⚡</div>
            <span>{{ $account['name'] ?? 'OnTrack AI' }}</span>
        </div>
        <div style="font-size: 0.85rem; opacity: 0.85;">حجز مباشر ومؤكد فوراً</div>
    </header>

    <main class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">حجز موعد استشارة جديدة</h1>
                <p class="card-subtitle">حدد الوقت والتفاصيل المناسبة لك وسيتم تأكيد الموعد فورياً مع الفريق.</p>
            </div>

            @if(session('success'))
                <div class="alert alert-success">
                    ✅ {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    @foreach($errors->all() as $error)
                        <div>• {{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form action="{{ route('public.booking.store', ['account_slug' => $account['slug'] ?? 'default']) }}" method="POST">
                @csrf
                <div class="form-group">
                    <label class="form-label" for="client_name">الاسم بالكامل *</label>
                    <input type="text" id="client_name" name="client_name" class="form-control" placeholder="مثال: أحمد محمود" value="{{ old('client_name') }}" required>
                </div>

                <div class="row-grid">
                    <div class="form-group">
                        <label class="form-label" for="client_phone">رقم الهاتف / الواتساب *</label>
                        <input type="tel" id="client_phone" name="client_phone" class="form-control" placeholder="+2010XXXXXXXX" value="{{ old('client_phone') }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="client_email">البريد الإلكتروني</label>
                        <input type="email" id="client_email" name="client_email" class="form-control" placeholder="name@domain.com" value="{{ old('client_email') }}">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="service">الخدمة المطلوبة</label>
                    <select id="service" name="service" class="form-control">
                        <option value="استشارة مجانية أولية">استشارة مجانية أولية (30 دقيقة)</option>
                        <option value="عرض توضيحي لمنظومة OnTrack AI">عرض توضيحي لمنظومة OnTrack AI</option>
                        <option value="جلسة دعم فني وإعداد السيرفر">جلسة دعم فني وإعداد السيرفر</option>
                        <option value="مناقشة خطة شراكة تجارية">مناقشة خطة شراكة تجارية</option>
                    </select>
                </div>

                <div class="row-grid">
                    <div class="form-group">
                        <label class="form-label" for="date">التاريخ المطلوب *</label>
                        <input type="date" id="date" name="date" class="form-control" min="{{ date('Y-m-d') }}" value="{{ old('date', date('Y-m-d', strtotime('+1 day'))) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="time">الوقت المناسب *</label>
                        <select id="time" name="time" class="form-control" required>
                            <option value="10:00 AM">10:00 صباحاً</option>
                            <option value="11:30 AM">11:30 صباحاً</option>
                            <option value="01:00 PM">01:00 ظهراً</option>
                            <option value="02:30 PM">02:30 ظهراً</option>
                            <option value="04:00 PM">04:00 عصراً</option>
                            <option value="06:00 PM">06:00 مساءً</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="notes">ملاحظات إضافية أو تفاصيل الاستفسار</label>
                    <textarea id="notes" name="notes" rows="3" class="form-control" placeholder="اكتب هنا أي تفاصيل تود مشاركتها مسبقاً...">{{ old('notes') }}</textarea>
                </div>

                <button type="submit" class="btn-submit">
                    تأكيد حجز الموعد الآن ✨
                </button>
            </form>
        </div>

        <p class="footer-note">
            مدعوم بتقنية OnTrack AI Voice & Omnichannel Platform — آمن ومشفر 100%
        </p>
    </main>
</body>
</html>
