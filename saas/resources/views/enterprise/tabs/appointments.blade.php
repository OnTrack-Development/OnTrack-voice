<div class="card-head" style="margin-bottom:14px">
    <div>
        <h1 style="font-size:20px;margin:0;font-weight:700">المواعيد وجدولة الاتصالات (Appointments Calendar)</h1>
        <p class="card-sub">المواعيد التي حجزها الإيجنت الذكي تلقائياً للمبيعات أو الدعم الفني أثناء المكالمات والمحادثات</p>
    </div>
    <div style="display:flex;gap:8px;">
        <button class="btn btn-sm btn-primary">+ حجز موعد جديد</button>
    </div>
</div>

<div class="card" style="padding:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <div style="display:flex;gap:10px;">
            <button class="btn btn-sm btn-ghost">اليوم</button>
            <button class="btn btn-sm btn-primary">الأسبوع</button>
            <button class="btn btn-sm btn-ghost">الشهر</button>
        </div>
        <div style="font-weight:bold;font-size:15px;">نوفمبر 2026</div>
    </div>

    <!-- Calendar Representation Grid -->
    <div style="display:grid;grid-template-columns:repeat(7, 1fr);gap:10px;text-align:center;font-size:13px;">
        @foreach (['السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'] as $dayName)
            <div style="color:var(--muted);font-weight:bold;padding-bottom:8px;border-bottom:1px solid var(--border,#1E293B);">{{ $dayName }}</div>
        @endforeach

        @for ($d = 15; $d <= 21; $d++)
            <div style="height:110px;border:1px solid var(--border,#1E293B);border-radius:10px;padding:8px;display:flex;flex-direction:column;align-items:start;background:{{ $d === 15 ? 'rgba(99,102,241,0.08)' : 'transparent' }};">
                <span style="font-weight:bold;color:{{ $d === 15 ? '#8B5CF6' : 'var(--text-main)' }};">{{ $d }} نوفمبر</span>
                @if ($d === 15)
                    <div style="background:#065F46;color:#FFF;padding:4px 8px;border-radius:6px;font-size:11px;margin-top:6px;text-align:right;width:100%;">
                        11:00 ص: مكالمة مبيعات VPS مع أحمد محمود
                    </div>
                @endif
                @if ($d === 17)
                    <div style="background:#1E3A8A;color:#FFF;padding:4px 8px;border-radius:6px;font-size:11px;margin-top:6px;text-align:right;width:100%;">
                        03:30 م: استشارة حجز سيرفر Dedicated
                    </div>
                @endif
            </div>
        @endfor
    </div>
</div>
