@php
    $leads = $data['leads'] ?? [];
@endphp

<div class="card-head" style="margin-bottom:14px">
    <div>
        <h1 style="font-size:20px;margin:0;font-weight:700">العملاء المحتملون وإدارة العلاقات (Leads CRM)</h1>
        <p class="card-sub">سجل موحد لجميع العملاء القادمين من المكالمات الهاتفية، محادثات واتساب، وتذاكر الدعم</p>
    </div>
    <div style="display:flex;gap:8px;">
        <button class="btn btn-sm btn-ghost">تصدير Excel</button>
        <button class="btn btn-primary btn-sm">+ إضافة Lead يدوي</button>
    </div>
</div>

<div class="card" style="padding:0;overflow:hidden">
    <div class="table-wrap" style="border:none">
        <table class="data-table">
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th>الهاتف</th>
                    <th>المصدر</th>
                    <th>الاهتمام / الخدمة</th>
                    <th>حالة المتابعة</th>
                    <th>الإيجنت المسؤول</th>
                    <th>تاريخ التسجيل</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($leads as $l)
                    <tr>
                        <td><strong>{{ $l['name'] ?: 'عميل محتمل' }}</strong></td>
                        <td class="mono" dir="ltr">{{ $l['phone'] ?: '—' }}</td>
                        <td><span class="pill" style="font-size:11px;">{{ $l['source'] ?? 'مكالمة هاتفية' }}</span></td>
                        <td>{{ $l['interest'] ?? 'استضافة مشتركة / VPS' }}</td>
                        <td><span class="pill pill-green" style="font-size:11px;">مهتم (Qualified)</span></td>
                        <td>سارة (AI Voice)</td>
                        <td class="mono" style="font-size:12px;">{{ $l['created_at'] ?? now()->toDateTimeString() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;padding:24px;color:var(--muted);">لا يوجد عملاء مسجلون حالياً. تظهر جهات الاتصال هنا فور انتهاء كل مكالمة أو محادثة.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
