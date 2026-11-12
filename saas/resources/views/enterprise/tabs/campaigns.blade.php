@php
    $tzCairo = new \DateTimeZone('Africa/Cairo');
    $defaultStartCairo = (new \DateTimeImmutable('now', $tzCairo))->format('Y-m-d\TH:i');
    $defaultEndCairo = (new \DateTimeImmutable('+7 days', $tzCairo))->format('Y-m-d\TH:i');
    $selectableContacts = array_values(array_filter($data['contacts'] ?? [], fn ($ct) => empty($ct['dnc'])));
    $q = $data['queue_status'] ?? [];
    $qStatus = (string) ($q['campaign_status'] ?? 'idle');
    $qNext = $q['next_contact'] ?? null;
    $qSeconds = (int) ($qNext['seconds_until_dial'] ?? 0);
@endphp

<div class="card-head" style="margin-bottom:14px">
    <div>
        <h1 style="font-size:20px;margin:0;font-weight:700">الحملات الصادرة وجهات الاتصال</h1>
        <p class="card-sub">استيراد أرقام العملاء مع حماية تلقائية لقائمة عدم الإزعاج (DNC: {{ (int) ($data['dnc_count'] ?? 0) }} رقم) وتشغيل الحملات عبر شرائح الهاتف بتوقيت القاهرة</p>
    </div>
</div>

<div class="card" id="liveCampaignQueueCard" style="border-color:rgba(225,29,72,.35);margin-bottom:14px">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:10px">
        <div>
            <h2 class="card-title" style="margin:0 0 4px">حالة جدولة الحملة الحية (Live Queue)</h2>
            <div id="liveQueueHeadline" style="font-size:13.5px;color:#F4F4F5;font-weight:600">
                @if (!empty($q['campaign_id']))
                    @if ($qStatus === 'running')
                        جدولة نشطة: حملة «{{ $q['campaign_name'] }}» — متبقي {{ (int) ($q['remaining_contacts'] ?? 0) }} من أصل {{ (int) ($q['total_contacts'] ?? 0) }} أرقام
                    @elseif ($qStatus === 'paused')
                        حملة موقوفة مؤقتاً: «{{ $q['campaign_name'] }}» — متبقي {{ (int) ($q['remaining_contacts'] ?? 0) }} رقم
                    @elseif ($qStatus === 'completed')
                        اكتملت الحملة: «{{ $q['campaign_name'] }}» ({{ (int) ($q['completed_calls'] ?? 0) }} / {{ (int) ($q['total_contacts'] ?? 0) }})
                    @else
                        الحملة: «{{ $q['campaign_name'] }}» ({{ $qStatus }})
                    @endif
                @else
                    لا توجد حملة مجدولة على الشريحة حالياً
                @endif
            </div>
        </div>
        <span id="liveQueueBadge" class="pill {{ $qStatus === 'running' ? 'pill-green' : ($qStatus === 'paused' ? 'pill-amber' : '') }}">
            {{ $qStatus === 'running' ? 'نشطة (Running)' : ($qStatus === 'paused' ? 'متوقفة مؤقتاً' : ($qStatus === 'completed' ? 'مكتملة' : 'غير نشطة')) }}
        </span>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:10px;margin-bottom:12px">
        <div style="background:#09090B;border:1px solid #27272A;border-radius:10px;padding:10px 12px">
            <div style="font-size:11.5px;color:#A1A1AA;margin-bottom:4px">الرقم التالي للاتصال</div>
            <div id="liveNextContact" style="font-size:13.5px;color:#F4F4F5;font-weight:600">
                @if ($qNext)
                    {{ $qNext['name'] }} — <span class="mono" dir="ltr">{{ $qNext['phone'] }}</span>
                @else
                    لا يوجد رقم متبقٍ في الطابور
                @endif
            </div>
        </div>
        <div style="background:#09090B;border:1px solid #27272A;border-radius:10px;padding:10px 12px">
            <div style="font-size:11.5px;color:#A1A1AA;margin-bottom:4px">العد التنازلي للمكالمة التالية</div>
            <div id="liveQueueCountdown" class="mono" style="font-size:15px;color:#E11D48;font-weight:700">
                @if ($qNext)
                    {{ $qSeconds > 0 ? sprintf('%02d:%02d', intdiv($qSeconds, 60), $qSeconds % 60) : 'جاهز للاتصال الآن' }}
                @else
                    —
                @endif
            </div>
        </div>
    </div>

    @if (!empty($q['uncertain_pending']))
        <div id="liveQueueWarn" class="alert alert-warn" style="margin-bottom:10px">
            يوجد {{ (int) $q['uncertain_pending'] }} محاولة سابقة بحالة غير مؤكدة أوقفت السحب التلقائي؛ اضغط «اتصال بالرقم التالي فوراً» لتصفير العائق وإرسال إشارة الاتصال للهاتف.
        </div>
    @else
        <div id="liveQueueWarn" class="alert alert-warn" style="margin-bottom:10px;display:none"></div>
    @endif

    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        @if (!empty($q['campaign_id']))
            @if ($qStatus === 'running')
                <form method="POST" action="/console/action" style="margin:0">
                    @csrf
                    <input type="hidden" name="action" value="campaign_status">
                    <input type="hidden" name="campaign_id" value="{{ $q['campaign_id'] }}">
                    <input type="hidden" name="status" value="paused">
                    <button type="submit" class="btn">⏸️ إيقاف مؤقت</button>
                </form>
            @else
                <form method="POST" action="/console/action" style="margin:0">
                    @csrf
                    <input type="hidden" name="action" value="campaign_status">
                    <input type="hidden" name="campaign_id" value="{{ $q['campaign_id'] }}">
                    <input type="hidden" name="status" value="running">
                    <button type="submit" class="btn">▶️ بدء الحملة الآن</button>
                </form>
            @endif
        @endif
        <form method="POST" action="/console/action" id="liveDialNextForm" style="margin:0">
            @csrf
            <input type="hidden" name="action" value="campaign_dial_next">
            @if (!empty($q['campaign_id']))
                <input type="hidden" name="campaign_id" value="{{ $q['campaign_id'] }}">
            @endif
            <button type="submit" class="btn btn-primary" id="liveDialNextBtn">📞 اتصال بالرقم التالي فوراً</button>
        </form>
        <span id="liveDialFeedback" style="font-size:12px;color:#A1A1AA"></span>
    </div>
</div>

<div class="alert alert-warn" style="margin-bottom:14px">
    <div>
        <strong>شرط التنفيذ الفعلي على الهاتف:</strong> السيرفر يُجدول الحملة فوراً بتوقيت القاهرة، ولكي يطلب الهاتف الأرقام وينفذ المكالمات تلقائياً يجب فتح تطبيق <strong>IO Voice</strong> وتفعيل الإيجنت ومنح صلاحية الهاتف (<code>CALL_PHONE</code>).
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
                    <div class="field"><span class="field-label">تبدأ في (توقيت القاهرة)</span><input type="datetime-local" name="starts_at" value="{{ $defaultStartCairo }}"></div>
                    <div class="field"><span class="field-label">تنتهي في (توقيت القاهرة)</span><input type="datetime-local" name="ends_at" value="{{ $defaultEndCairo }}"></div>
                </div>
                <div class="field">
                    <span class="field-label">سيناريو أو هدف الحملة الصادرة</span>
                    <textarea name="scenario" required placeholder="مرحباً {name}، نود تأكيد موعدك مع فريق المبيعات..."></textarea>
                </div>
                @if (!empty($selectableContacts))
                    <div class="field">
                        <span class="field-label">اختر من جهات الاتصال المسجلة ({{ count($selectableContacts) }})</span>
                        <div style="max-height:130px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:8px;display:grid;gap:4px">
                            @foreach ($selectableContacts as $sc)
                                <label class="check-row" style="font-size:12.5px">
                                    <input type="checkbox" name="contact_ids[]" value="{{ $sc['id'] }}">
                                    <span>{{ $sc['display_name'] ?: ($sc['name'] ?: 'عميل') }}</span>
                                    <span class="mono" style="color:var(--muted)" dir="ltr">{{ $sc['phone_e164'] ?: $sc['phone'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif
                <div class="field">
                    <span class="field-label">أو لصق أرقام إضافية مباشرة (الاسم، الهاتف أو رقم في كل سطر)</span>
                    <textarea name="raw_contacts" placeholder="أحمد علي,01012345678&#10;01198765432"></textarea>
                </div>
                <label class="check-row" style="margin-bottom:10px"><input type="checkbox" name="arm_now" value="1" checked> <span>تفعيل وجدولة الحملة فوراً للتنفيذ على الشريحة</span></label>
                <button type="submit" class="btn btn-primary">إنشاء الحملة</button>
            </form>
        @else
            <p class="card-sub">يجب ربط شريحة SIM أولاً من تبويب <a href="/?tab=devices" style="color:var(--crimson)">الشرائح والأجهزة</a> قبل تشغيل الحملات.</p>
        @endif
    </div>

    <div style="display:flex;flex-direction:column;gap:14px">
        <div class="card">
            <h2 class="card-title" style="margin-bottom:10px">استيراد سريع لجهات الاتصال فقط</h2>
            <form method="POST" action="/console/action">
                @csrf
                <input type="hidden" name="action" value="bulk_import_contacts">
                <div class="field">
                    <span class="field-label">القائمة (CSV / TXT: اسم، رقم)</span>
                    <textarea name="raw_contacts" required style="min-height:110px" placeholder="محمد محمود,01011223344&#10;سارة حسن,+201223344556"></textarea>
                </div>
                <label class="check-row" style="margin-bottom:10px"><input type="checkbox" name="with_consent" value="1" checked> <span>أؤكد وجود موافقة مسبقة من أصحاب الأرقام على التواصل الصوتي</span></label>
                <button type="submit" class="btn">استيراد وحفظ جهات الاتصال</button>
            </form>
        </div>

        @if (!empty($data['campaigns']))
            <div class="card">
                <h2 class="card-title" style="margin-bottom:10px">إضافة أرقام / جهات اتصال لحملة قائمة</h2>
                <form method="POST" action="/console/action">
                    @csrf
                    <input type="hidden" name="action" value="add_campaign_contacts">
                    <div class="field">
                        <span class="field-label">اختر الحملة</span>
                        <select name="campaign_id" required>
                            @foreach ($data['campaigns'] as $cmpOpt)
                                <option value="{{ $cmpOpt['id'] }}">{{ $cmpOpt['name'] }} ({{ $cmpOpt['target_count'] }} رقم)</option>
                            @endforeach
                        </select>
                    </div>
                    @if (!empty($selectableContacts))
                        <div class="field">
                            <span class="field-label">تحديد جهات اتصال مسجلة</span>
                            <div style="max-height:105px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:8px;display:grid;gap:4px">
                                @foreach ($selectableContacts as $sc)
                                    <label class="check-row" style="font-size:12.5px">
                                        <input type="checkbox" name="contact_ids[]" value="{{ $sc['id'] }}">
                                        <span>{{ $sc['display_name'] ?: ($sc['name'] ?: 'عميل') }}</span>
                                        <span class="mono" style="color:var(--muted)" dir="ltr">{{ $sc['phone_e164'] ?: $sc['phone'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <div class="field">
                        <span class="field-label">أو لصق أرقام جديدة</span>
                        <textarea name="raw_contacts" style="min-height:65px" placeholder="خالد حسن,01099887766"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">إضافة الأرقام للحملة</button>
                </form>
            </div>
        @endif
    </div>
</div>

<div class="card">
    <h2 class="card-title" style="margin-bottom:10px">الحملات المسجلة</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>الحملة</th><th>الشريحة</th><th>الهدف / المنفذ</th><th>الفترة (القاهرة)</th><th>الحالة</th><th>إجراء</th></tr></thead>
            <tbody>
                @forelse ($data['campaigns'] ?? [] as $cmp)
                    <tr>
                        <td style="font-weight:600">{{ $cmp['name'] }}</td>
                        <td>{{ $cmp['device_name'] ?: ('#'.$cmp['device_binding_id']) }} (SIM {{ ((int) $cmp['sim_slot']) + 1 }})</td>
                        <td class="mono">{{ $cmp['attempts_done'] }} / {{ $cmp['target_count'] }}</td>
                        <td class="mono" style="font-size:11.5px" dir="ltr">{{ $cmp['starts_at_cairo'] ?: $cmp['starts_at'] }} → {{ $cmp['ends_at_cairo'] ?: $cmp['ends_at'] }}</td>
                        <td><span class="pill {{ in_array($cmp['status'], ['ready', 'armed', 'running', 'active'], true) ? 'pill-green' : ($cmp['status'] === 'cancelled' ? 'pill-crimson' : 'pill-amber') }}">{{ $cmp['status'] }}</span></td>
                        <td style="display:flex;gap:5px;flex-wrap:wrap">
                            @if (! in_array($cmp['status'], ['ready', 'armed', 'running', 'active'], true))
                                <form method="POST" action="/console/action" style="margin:0">@csrf<input type="hidden" name="action" value="campaign_status"><input type="hidden" name="campaign_id" value="{{ $cmp['id'] }}"><input type="hidden" name="status" value="running"><button class="btn btn-sm">تشغيل</button></form>
                            @else
                                <form method="POST" action="/console/action" style="margin:0">@csrf<input type="hidden" name="action" value="campaign_status"><input type="hidden" name="campaign_id" value="{{ $cmp['id'] }}"><input type="hidden" name="status" value="paused"><button class="btn btn-sm">إيقاف مؤقت</button></form>
                            @endif
                            <form method="POST" action="/console/action" style="margin:0">@csrf<input type="hidden" name="action" value="campaign_dial_next"><input type="hidden" name="campaign_id" value="{{ $cmp['id'] }}"><button class="btn btn-sm btn-primary">اتصال فوراً</button></form>
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
<script>
(function(){
    let q = @json($q);
    let remainingSec = q && q.next_contact ? Math.max(0, Number(q.next_contact.seconds_until_dial || 0)) : 0;
    const headlineEl = document.getElementById('liveQueueHeadline');
    const badgeEl = document.getElementById('liveQueueBadge');
    const nextEl = document.getElementById('liveNextContact');
    const cdEl = document.getElementById('liveQueueCountdown');
    const warnEl = document.getElementById('liveQueueWarn');
    const feedbackEl = document.getElementById('liveDialFeedback');
    const dialForm = document.getElementById('liveDialNextForm');

    function fmtSec(sec) {
        if (sec <= 0) return 'جاهز للاتصال الآن';
        const m = String(Math.floor(sec / 60)).padStart(2, '0');
        const s = String(sec % 60).padStart(2, '0');
        return 'الاتصال التالي بعد: ' + m + ':' + s;
    }

    function renderQueue() {
        if (!q || !q.campaign_id) {
            if (headlineEl) headlineEl.textContent = 'لا توجد حملة مجدولة على الشريحة حالياً';
            if (badgeEl) { badgeEl.textContent = 'غير نشطة'; badgeEl.className = 'pill'; }
            if (nextEl) nextEl.textContent = 'لا يوجد رقم متبقٍ في الطابور';
            if (cdEl) cdEl.textContent = '—';
            return;
        }
        const st = String(q.campaign_status || 'idle');
        if (headlineEl) {
            if (st === 'running') headlineEl.textContent = 'جدولة نشطة: حملة «' + q.campaign_name + '» — متبقي ' + q.remaining_contacts + ' من أصل ' + q.total_contacts + ' أرقام';
            else if (st === 'paused') headlineEl.textContent = 'حملة موقوفة مؤقتاً: «' + q.campaign_name + '» — متبقي ' + q.remaining_contacts + ' رقم';
            else if (st === 'completed') headlineEl.textContent = 'اكتملت الحملة: «' + q.campaign_name + '» (' + q.completed_calls + ' / ' + q.total_contacts + ')';
            else headlineEl.textContent = 'الحملة: «' + q.campaign_name + '» (' + st + ')';
        }
        if (badgeEl) {
            badgeEl.textContent = st === 'running' ? 'نشطة (Running)' : st === 'paused' ? 'متوقفة مؤقتاً' : st === 'completed' ? 'مكتملة' : 'غير نشطة';
            badgeEl.className = 'pill ' + (st === 'running' ? 'pill-green' : st === 'paused' ? 'pill-amber' : '');
        }
        if (nextEl) {
            if (q.next_contact) {
                nextEl.textContent = '';
                const nameTxt = document.createTextNode((q.next_contact.name || 'عميل') + ' — ');
                const phSpan = document.createElement('span');
                phSpan.className = 'mono';
                phSpan.dir = 'ltr';
                phSpan.textContent = q.next_contact.phone || '';
                nextEl.appendChild(nameTxt);
                nextEl.appendChild(phSpan);
            } else {
                nextEl.textContent = 'لا يوجد رقم متبقٍ في الطابور';
            }
        }
        if (cdEl) {
            cdEl.textContent = q.next_contact ? (st === 'paused' ? 'متوقفة مؤقتاً' : fmtSec(remainingSec)) : '—';
        }
        if (warnEl) {
            if (Number(q.uncertain_pending || 0) > 0) {
                warnEl.style.display = 'block';
                warnEl.textContent = 'يوجد ' + q.uncertain_pending + ' محاولة سابقة بحالة غير مؤكدة أوقفت السحب التلقائي؛ اضغط «اتصال بالرقم التالي فوراً» لتصفير العائق وإرسال إشارة الاتصال للهاتف.';
            } else {
                warnEl.style.display = 'none';
            }
        }
    }

    setInterval(function () {
        if (q && q.next_contact && q.campaign_status === 'running' && remainingSec > 0) {
            remainingSec--;
            renderQueue();
        }
    }, 1000);

    setInterval(function () {
        fetch('/api/console/campaigns/queue', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (res) {
                if (res && res.ok && res.queue) {
                    q = res.queue;
                    remainingSec = q.next_contact ? Math.max(0, Number(q.next_contact.seconds_until_dial || 0)) : 0;
                    renderQueue();
                }
            }).catch(function () {});
    }, 5000);

    if (dialForm) {
        dialForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const fd = new FormData(dialForm);
            if (feedbackEl) feedbackEl.textContent = 'جاري إرسال إشارة الاتصال للهاتف…';
            fetch('/console/action', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            }).then(function (r) { return r.json(); }).then(function (res) {
                if (res && res.ok) {
                    if (res.queue) {
                        q = res.queue;
                        remainingSec = q.next_contact ? Math.max(0, Number(q.next_contact.seconds_until_dial || 0)) : 0;
                        renderQueue();
                    }
                    if (feedbackEl) feedbackEl.textContent = res.success || 'تم إرسال إشارة الاتصال الفوري للهاتف.';
                } else {
                    if (feedbackEl) feedbackEl.textContent = (res && res.error) || 'تعذر تنفيذ الطلب.';
                }
            }).catch(function () {
                dialForm.submit();
            });
        });
    }
    renderQueue();
})();
</script>
