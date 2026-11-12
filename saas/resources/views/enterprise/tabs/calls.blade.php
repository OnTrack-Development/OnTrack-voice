@php
    $filters = $data['filters'] ?? [];
@endphp

<div class="card-head" style="margin-bottom:14px">
    <div>
        <h1 style="font-size:20px;margin:0;font-weight:700">سجل المكالمات والتفريغ الصوتي</h1>
        <p class="card-sub">متابعة دقيقة لكل مكالمة، المدة، جنس المتصل، التقييم، والتفريغ النصي المشفر</p>
    </div>
    <a href="/calls/csv?{{ http_build_query($filters) }}" class="btn btn-sm">تصدير CSV</a>
</div>

<form method="GET" action="/" class="card">
    <input type="hidden" name="tab" value="calls">
    <div class="form-grid" style="align-items:end">
        <div class="field" style="margin:0"><span class="field-label">بحث</span><input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="رقم، اسم، ملخص..."></div>
        <div class="field" style="margin:0"><span class="field-label">الحالة</span>
            <select name="status">
                <option value="">الكل</option>
                @foreach (['ended' => 'انتهت', 'answered' => 'تم الرد', 'completed' => 'مكتملة', 'missed' => 'فائتة', 'ringing' => 'رنين'] as $st => $lb)
                    <option value="{{ $st }}" {{ ($filters['status'] ?? '') === $st ? 'selected' : '' }}>{{ $lb }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="margin:0"><span class="field-label">الشريحة</span>
            <select name="sim">
                <option value="">كل الشرائح</option>
                <option value="0" {{ ($filters['sim'] ?? '') === '0' ? 'selected' : '' }}>SIM 1</option>
                <option value="1" {{ ($filters['sim'] ?? '') === '1' ? 'selected' : '' }}>SIM 2</option>
            </select>
        </div>
        <div class="field" style="margin:0"><span class="field-label">النتيجة</span>
            <select name="outcome">
                <option value="">الكل</option>
                @foreach ($data['outcomes'] ?? [] as $k => $v)
                    <option value="{{ $k }}" {{ ($filters['outcome'] ?? '') === $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <div style="display:flex;gap:8px">
            <button type="submit" class="btn btn-primary btn-sm">تصفية</button>
            <a href="/?tab=calls" class="btn btn-ghost btn-sm">إعادة ضبط</a>
        </div>
    </div>
</form>

<div class="card" style="padding:0;overflow:hidden">
    <div class="table-wrap" style="border:none">
        <table class="data-table">
            <thead>
                <tr><th>المتصل</th><th>التاريخ (القاهرة)</th><th>الإيجنت</th><th>الشريحة</th><th>المدة</th><th>ملخص المكالمة</th><th>النتيجة</th><th>التقييم</th><th>التفاصيل</th></tr>
            </thead>
            <tbody>
                @forelse ($data['calls'] ?? [] as $c)
                    <tr>
                        <td>
                            <div style="font-weight:600">{{ $c['name'] ?: ($c['phone'] ?: 'غير محدد') }}</div>
                            <div class="mono" style="font-size:11.5px;color:var(--muted)" dir="ltr">{{ $c['phone'] }}</div>
                        </td>
                        <td class="mono" style="font-size:12px" dir="ltr">{{ $c['created_at_cairo'] ?: $c['created_at'] }}</td>
                        <td>{{ $c['resolved_agent_name'] ?: '—' }}</td>
                        <td><span class="pill">SIM {{ ((int) ($c['sim_slot'] ?? 0)) + 1 }}</span></td>
                        <td class="tnum">{{ $c['duration_formatted'] }}</td>
                        <td style="max-width:260px;font-size:12.5px;color:var(--muted)">{{ \Illuminate\Support\Str::limit($c['effective_summary'] ?: '—', 95) }}</td>
                        <td><span class="pill pill-crimson">{{ $c['outcome_label'] }}</span></td>
                        <td class="mono">{{ !empty($c['ai_rating']) ? '★ '.$c['ai_rating'].'/5' : '—' }}</td>
                        <td><button type="button" class="btn btn-sm" onclick="openCallDrawer({{ $c['id'] }})">عرض النص والتقرير</button></td>
                    </tr>
                @empty
                    <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:28px">لا توجد مكالمات مطابقة للبحث.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if (!empty($data['paginator']) && $data['paginator']->hasPages())
    <div style="margin-top:12px;display:flex;gap:8px;justify-content:center">
        @if ($data['paginator']->previousPageUrl())
            <a href="{{ $data['paginator']->previousPageUrl() }}" class="btn btn-sm">السابق</a>
        @endif
        <span class="pill">صفحة {{ $data['paginator']->currentPage() }} من {{ $data['paginator']->lastPage() }}</span>
        @if ($data['paginator']->nextPageUrl())
            <a href="{{ $data['paginator']->nextPageUrl() }}" class="btn btn-sm">التالي</a>
        @endif
    </div>
@endif
