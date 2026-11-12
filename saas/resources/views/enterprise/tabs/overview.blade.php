<div class="card-head" style="margin-bottom:14px">
    <div>
        <h1 style="font-size:20px;margin:0;font-weight:700">نظرة عامة على التشغيل</h1>
        <p class="card-sub">متابعة المكالمات الحية، الشرائح المتصلة، واستهلاك الباقة في {{ $ctx['account']['name'] ?? '' }}</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="/?tab=agents" class="btn btn-primary btn-sm">+ استوديو الإيجنت والأصوات</a>
        <a href="/?tab=devices" class="btn btn-sm">ربط شريحة SIM</a>
    </div>
</div>

@php $k = $data['kpis'] ?? []; @endphp
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">إجمالي المكالمات الفعلية</div>
        <div class="kpi-value">{{ number_format((int) ($k['total_calls'] ?? 0)) }}</div>
        <div class="kpi-meta">اليوم: {{ (int) ($k['today_calls'] ?? 0) }} مكالمة · الحصة: {{ ($k['call_quota'] ?? 0) >= 999999999 ? 'غير محدودة' : number_format((int) ($k['call_quota'] ?? 0)) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">نسبة الاستجابة</div>
        <div class="kpi-value">{{ $k['answer_rate'] ?? 0 }}%</div>
        <div class="kpi-meta">متوسط المدة: {{ $k['avg_duration'] ?? '0 ثانية' }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">الشرائح النشطة</div>
        <div class="kpi-value">{{ $data['online_sims'] ?? 0 }} / {{ count($data['devices'] ?? []) }}</div>
        <div class="kpi-meta">إيجنت متاح: {{ count($data['agents'] ?? []) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">متوسط تقييم الذكاء / التصعيد</div>
        <div class="kpi-value">{{ ($k['avg_rating'] ?? 0) > 0 ? '★ '.$k['avg_rating'] : '—' }}</div>
        <div class="kpi-meta">طلبات متابعة بشرية معلقة: {{ (int) ($k['pending_handoffs'] ?? 0) }}</div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">حجم المكالمات (آخر 7 أيام)</h2>
                <p class="card-sub">عدد المكالمات اليومي المسجل عبر شرائح الحساب</p>
            </div>
        </div>
        @php
            $chart = $data['daily_chart'] ?? [];
            $maxC = max(1, (int) collect($chart)->max('count'));
        @endphp
        <div style="display:flex;align-items:flex-end;gap:10px;height:130px;padding:10px 4px 0">
            @foreach ($chart as $pt)
                @php $h = max(8, (int) round(($pt['count'] / $maxC) * 95)); @endphp
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:5px">
                    <span class="mono" style="font-size:11px;color:var(--muted)">{{ $pt['count'] }}</span>
                    <div style="width:100%;max-width:34px;height:{{ $h }}px;background:{{ $pt['count'] > 0 ? 'var(--crimson)' : 'var(--elevated)' }};border-radius:6px 6px 2px 2px;border:1px solid var(--border)"></div>
                    <span class="mono" style="font-size:10.5px;color:var(--dim)">{{ $pt['day'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">ربط سريع لشريحة هاتف Android</h2>
                <p class="card-sub">ولّد كوداً من 6 أرقام وأدخله في تطبيق IO Voice v{{ config('ontrack.apk_version_name', '0.3.39') }}</p>
            </div>
        </div>
        @if (!empty($data['agents']))
            <form method="POST" action="/console/action">
                @csrf
                <input type="hidden" name="action" value="create_device_pairing">
                <div class="form-grid">
                    <div class="field">
                        <span class="field-label">الإيجنت المسؤول</span>
                        <select name="agent_id">
                            @foreach ($data['agents'] as $ag)
                                <option value="{{ $ag['id'] }}">{{ $ag['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <span class="field-label">الشريحة (SIM)</span>
                        <select name="sim_slot">
                            <option value="0">SIM 1</option>
                            <option value="1">SIM 2</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">توليد كود الربط الآن</button>
            </form>
        @else
            <p class="card-sub">أنشئ إيجنت أولاً من <a href="/?tab=agents" style="color:var(--crimson)">استوديو الإيجنت</a> لربطه بالشرائح.</p>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-head">
        <div>
            <h2 class="card-title">أحدث المكالمات</h2>
            <p class="card-sub">اضغط على أي مكالمة لعرض التفريغ الصوتي والنص والملخص</p>
        </div>
        <a href="/?tab=calls" class="btn btn-sm">عرض السجل الكامل</a>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>المتصل</th><th>الإيجنت / الشريحة</th><th>الحالة</th><th>المدة</th><th>الملخص</th><th>إجراء</th></tr>
            </thead>
            <tbody>
            @forelse ($data['recent_calls'] ?? [] as $c)
                <tr>
                    <td>
                        <div style="font-weight:600">{{ $c['name'] ?: ($c['phone'] ?: 'غير معروف') }}</div>
                        <div class="mono" style="font-size:11.5px;color:var(--muted)" dir="ltr">{{ $c['phone'] }}</div>
                    </td>
                    <td>
                        <div>{{ $c['resolved_agent_name'] ?? '—' }}</div>
                        <div class="mono" style="font-size:11px;color:var(--dim)">SIM {{ ((int) ($c['sim_slot'] ?? 0)) + 1 }}</div>
                    </td>
                    <td><span class="pill">{{ $c['status'] }}</span></td>
                    <td class="tnum">{{ $c['duration_formatted'] }}</td>
                    <td style="max-width:260px;color:var(--muted)">{{ \Illuminate\Support\Str::limit($c['report_summary'] ?: '—', 70) }}</td>
                    <td><button type="button" class="btn btn-sm" onclick="openCallDrawer({{ $c['id'] }})">التفاصيل</button></td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:24px">لا توجد مكالمات مسجلة بعد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
