<div class="card-head" style="margin-bottom:14px">
    <div>
        <h1 style="font-size:20px;margin:0;font-weight:700">الحملات الصادرة وجهات الاتصال</h1>
        <p class="card-sub">استيراد أرقام العملاء مع حماية تلقائية لقائمة عدم الإزعاج (DNC: {{ (int) ($data['dnc_count'] ?? 0) }} رقم) وتشغيل الحملات عبر شرائح الهاتف</p>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <h2 class="card-title" style="margin-bottom:10px">إطلاق حملة مكالمات صادرة جديدة</h2>
        @if (!empty($data['devices']))
            <form method="POST" action="/console/action">
                @csrf
                <input type="hidden" name="action" value="create_campaign">
                <div class="field"><span class="field-label">اسم الحملة</span><input type="text" name="name" required placeholder="مثال: متابعة عملاء المعرض"></div>
                <div class="field">
                    <span class="field-label">الشريحة المنفذة للمكالمات</span>
                    <select name="device_binding_id" required>
                        @foreach ($data['devices'] as $d)
                            <option value="{{ $d['id'] }}">{{ $d['device_name'] }} — SIM {{ ((int) $d['sim_slot']) + 1 }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-grid">
                    <div class="field"><span class="field-label">تبدأ في (توقيت القاهرة)</span><input type="datetime-local" name="starts_at"></div>
                    <div class="field"><span class="field-label">تنتهي في (توقيت القاهرة)</span><input type="datetime-local" name="ends_at"></div>
                </div>
                <div class="field">
                    <span class="field-label">سيناريو أو هدف الحملة الصادرة</span>
                    <textarea name="scenario" required placeholder="مرحباً {name}، نود تأكيد موعدك مع فريق المبيعات..."></textarea>
                </div>
                <div class="field">
                    <span class="field-label">لصق أرقام الهواتف مباشرة (الاسم، الهاتف أو رقم في كل سطر)</span>
                    <textarea name="raw_contacts" placeholder="أحمد علي,01012345678&#10;01198765432"></textarea>
                </div>
                <label class="check-row" style="margin-bottom:10px"><input type="checkbox" name="arm_now" value="1" checked> <span>تفعيل وجدولة الحملة فوراً للتنفيذ على الشريحة</span></label>
                <button type="submit" class="btn btn-primary">إنشاء الحملة</button>
            </form>
        @else
            <p class="card-sub">يجب ربط شريحة SIM أولاً من تبويب <a href="/?tab=devices" style="color:var(--crimson)">الشرائح والأجهزة</a> قبل تشغيل الحملات.</p>
        @endif
    </div>

    <div class="card">
        <h2 class="card-title" style="margin-bottom:10px">استيراد سريع لجهات الاتصال فقط</h2>
        <form method="POST" action="/console/action">
            @csrf
            <input type="hidden" name="action" value="bulk_import_contacts">
            <div class="field">
                <span class="field-label">القائمة (CSV / TXT: اسم، رقم)</span>
                <textarea name="raw_contacts" required style="min-height:140px" placeholder="محمد محمود,01011223344&#10;سارة حسن,+201223344556"></textarea>
            </div>
            <label class="check-row" style="margin-bottom:10px"><input type="checkbox" name="with_consent" value="1" checked> <span>أؤكد وجود موافقة مسبقة من أصحاب الأرقام على التواصل الصوتي</span></label>
            <button type="submit" class="btn">استيراد وحفظ جهات الاتصال</button>
        </form>
    </div>
</div>

<div class="card">
    <h2 class="card-title" style="margin-bottom:10px">الحملات المسجلة</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>الحملة</th><th>الشريحة</th><th>الهدف / المنفذ</th><th>الفترة UTC</th><th>الحالة</th><th>إجراء</th></tr></thead>
            <tbody>
                @forelse ($data['campaigns'] ?? [] as $cmp)
                    <tr>
                        <td style="font-weight:600">{{ $cmp['name'] }}</td>
                        <td>{{ $cmp['device_name'] ?: ('#'.$cmp['device_binding_id']) }} (SIM {{ ((int) $cmp['sim_slot']) + 1 }})</td>
                        <td class="mono">{{ $cmp['attempts_done'] }} / {{ $cmp['target_count'] }}</td>
                        <td class="mono" style="font-size:11.5px">{{ $cmp['starts_at'] }} → {{ $cmp['ends_at'] }}</td>
                        <td><span class="pill {{ in_array($cmp['status'], ['ready', 'armed'], true) ? 'pill-green' : ($cmp['status'] === 'cancelled' ? 'pill-crimson' : 'pill-amber') }}">{{ $cmp['status'] }}</span></td>
                        <td style="display:flex;gap:5px;flex-wrap:wrap">
                            @if (! in_array($cmp['status'], ['ready', 'armed'], true))
                                <form method="POST" action="/console/action" style="margin:0">@csrf<input type="hidden" name="action" value="campaign_status"><input type="hidden" name="campaign_id" value="{{ $cmp['id'] }}"><input type="hidden" name="status" value="ready"><button class="btn btn-sm">تشغيل</button></form>
                            @else
                                <form method="POST" action="/console/action" style="margin:0">@csrf<input type="hidden" name="action" value="campaign_status"><input type="hidden" name="campaign_id" value="{{ $cmp['id'] }}"><input type="hidden" name="status" value="paused"><button class="btn btn-sm">إيقاف مؤقت</button></form>
                            @endif
                            <form method="POST" action="/console/action" style="margin:0">@csrf<input type="hidden" name="action" value="campaign_status"><input type="hidden" name="campaign_id" value="{{ $cmp['id'] }}"><input type="hidden" name="status" value="cancelled"><button class="btn btn-sm btn-danger">إلغاء</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:20px">لا توجد حملات مسجلة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2 class="card-title" style="margin-bottom:10px">جهات الاتصال وإدارة قائمة عدم الإزعاج (DNC)</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>الاسم</th><th>الهاتف</th><th>حالة العميل</th><th>DNC</th><th>تحكم</th></tr></thead>
            <tbody>
                @forelse ($data['contacts'] ?? [] as $ct)
                    <tr>
                        <td>{{ $ct['display_name'] ?: ($ct['name'] ?: '—') }}</td>
                        <td class="mono" dir="ltr">{{ $ct['phone_e164'] ?: $ct['phone'] }}</td>
                        <td><span class="pill">{{ $ct['lead_status'] ?? 'new' }}</span></td>
                        <td><span class="pill {{ !empty($ct['dnc']) ? 'pill-crimson' : 'pill-green' }}">{{ !empty($ct['dnc']) ? 'محظور (DNC)' : 'مسموح' }}</span></td>
                        <td>
                            <form method="POST" action="/console/action" style="margin:0">
                                @csrf
                                <input type="hidden" name="action" value="contact_dnc_toggle">
                                <input type="hidden" name="contact_id" value="{{ $ct['id'] }}">
                                <button type="submit" class="btn btn-sm">{{ !empty($ct['dnc']) ? 'رفع الحظر' : 'إدراج DNC' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:18px">لا توجد جهات اتصال بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
