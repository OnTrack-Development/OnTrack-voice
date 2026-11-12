@php
    $sub = $data['subscription'] ?? null;
    $quota = (int) ($data['call_quota'] ?? 0);
    $used = (int) ($data['used_calls'] ?? 0);
@endphp

<div class="card-head" style="margin-bottom:14px">
    <div>
        <h1 style="font-size:20px;margin:0;font-weight:700">الباقات والفواتير والاشتراكات</h1>
        <p class="card-sub">متابعة حصة المكالمات الشهرية، الترقية الفورية عبر Kashier، وطلبات السداد اليدوي والفواتير</p>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <div>
            <h2 class="card-title">الاشتراك الحالي: {{ $sub['plan_name'] ?? ($ctx['is_platform'] ? 'حساب المنصة (مفتوح)' : 'بدون باقة نشطة') }}</h2>
            <p class="card-sub mono" dir="ltr">{{ $sub ? ('Ends: '.$sub['ends_at']) : '' }}</p>
        </div>
        <span class="pill pill-green">المستهلك: {{ number_format($used) }} / {{ $quota >= 999999999 ? 'غير محدود' : number_format($quota) }} مكالمة</span>
    </div>
</div>

<div class="grid-3" style="margin-bottom:16px">
    @foreach ($data['plans'] ?? [] as $plan)
        <div class="card" style="display:flex;flex-direction:column;justify-content:space-between;margin-bottom:0">
            <div>
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <h3 class="card-title">{{ $plan['name'] }}</h3>
                    <span class="pill pill-crimson">{{ ((int) ($plan['price_minor'] ?? 0)) === 0 ? 'مجاناً' : number_format(((int) $plan['price_minor']) / 100, 0).' EGP/شهر' }}</span>
                </div>
                <ul style="padding-right:18px;color:var(--muted);font-size:13px;line-height:1.9">
                    <li>حصة المكالمات: <strong class="tnum">{{ number_format((int) $plan['call_quota']) }}</strong></li>
                    <li>المواقع: <strong>{{ (int) $plan['site_limit'] }}</strong> · الإيجنت: <strong>{{ (int) $plan['agent_limit'] }}</strong></li>
                    <li>الموظفون: <strong>{{ (int) $plan['staff_limit'] }}</strong></li>
                </ul>
            </div>
            <div>
                @if (((int) ($plan['price_minor'] ?? 0)) === 0)
                    <form method="POST" action="/console/action">@csrf<input type="hidden" name="action" value="select_free_plan"><input type="hidden" name="plan_id" value="{{ $plan['id'] }}"><button class="btn btn-primary btn-sm" style="width:100%">تفعيل المجانية</button></form>
                @else
                    <div style="display:flex;flex-direction:column;gap:6px">
                        <form method="POST" action="/console/action">@csrf<input type="hidden" name="action" value="checkout_kashier"><input type="hidden" name="plan_id" value="{{ $plan['id'] }}"><button class="btn btn-primary btn-sm" style="width:100%">دفع إلكتروني (Kashier)</button></form>
                        <form method="POST" action="/console/action" style="display:flex;gap:5px;flex-wrap:wrap">
                            @csrf
                            <input type="hidden" name="action" value="request_manual_invoice">
                            <input type="hidden" name="plan_id" value="{{ $plan['id'] }}">
                            <input type="text" name="payment_reference" placeholder="مرجع إنستاباي/تحويل" style="padding:5px 8px;font-size:12px;flex:1 1 130px">
                            <button class="btn btn-sm">فاتورة</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</div>

<div class="card">
    <h2 class="card-title" style="margin-bottom:10px">سجل الفواتير</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>رقم الفاتورة</th><th>الباقة</th><th>المبلغ</th><th>الحالة</th><th>التاريخ</th><th>طباعة</th></tr></thead>
            <tbody>
                @forelse ($data['invoices'] ?? [] as $inv)
                    <tr>
                        <td class="mono">{{ $inv['invoice_no'] }}</td>
                        <td>{{ $inv['plan_name'] }}</td>
                        <td class="mono">{{ number_format(((int) $inv['amount_minor']) / 100, 2) }} {{ $inv['currency'] }}</td>
                        <td><span class="pill {{ $inv['status'] === 'paid' ? 'pill-green' : 'pill-amber' }}">{{ $inv['status'] }}</span></td>
                        <td class="mono" style="font-size:12px">{{ $inv['issued_at'] }}</td>
                        <td><a href="/invoices/{{ $inv['id'] }}/print" target="_blank" class="btn btn-sm">عرض / PDF</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:18px">لا توجد فواتير مسجلة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if (!empty($data['manual_pending']))
<div class="card">
    <h2 class="card-title" style="margin-bottom:10px">فواتير يدوية بانتظار اعتماد الأدمن</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>رقم الفاتورة</th><th>العميل</th><th>المبلغ</th><th>المرجع</th><th>اعتماد</th></tr></thead>
            <tbody>
                @foreach ($data['manual_pending'] as $mp)
                    <tr>
                        <td class="mono">{{ $mp['invoice_no'] }}</td>
                        <td>{{ $mp['bill_to_name'] }}</td>
                        <td class="mono">{{ number_format(((int) $mp['amount_minor']) / 100, 2) }} EGP</td>
                        <td class="mono">{{ $mp['payment_reference'] ?: '—' }}</td>
                        <td>
                            <form method="POST" action="/console/action" style="display:flex;gap:6px">
                                @csrf
                                <input type="hidden" name="action" value="approve_manual_invoice">
                                <input type="hidden" name="invoice_id" value="{{ $mp['id'] }}">
                                <input type="text" name="payment_reference" required placeholder="تأكيد المرجع..." value="{{ $mp['payment_reference'] }}" style="width:140px;padding:5px 8px">
                                <button class="btn btn-primary btn-sm">اعتماد وتفعيل</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
