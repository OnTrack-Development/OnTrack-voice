@php
    $isPlatform = !empty($ctx['is_platform']);
    $section = match ($activeTab) {
        'reseller-tenants' => 'reseller_tenants',
        'reseller-quotas' => 'reseller_quotas',
        'platform-ai' => 'platform_ai',
        'platform-plans', 'plans' => 'plans',
        'releases' => 'releases',
        default => 'accounts',
    };
@endphp

<div class="card-head" style="margin-bottom:14px">
    <div>
        <h1 style="font-size:20px;margin:0;font-weight:700">
            @switch($section)
                @case('reseller_tenants') إدارة العملاء التابعين (Reseller) @break
                @case('reseller_quotas') الحصص والباقات المخصصة للعملاء @break
                @case('platform_ai') الذكاء الاصطناعي والأصوات المركزية @break
                @case('plans') إدارة الباقات، الفوترة، واعتماد السداد @break
                @case('releases') النظام وإصدارات Android APK @break
                @default إدارة الحسابات والوكلاء (Administration)
            @endswitch
        </h1>
        <p class="card-sub">تحكم معزول ومتكامل في الحسابات، الحصص، التبديل السريع لمساحات العمل، المزودين المركزيين، والإصدارات</p>
    </div>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
        <a href="/?tab=reseller-tenants" class="btn btn-sm {{ $section === 'reseller_tenants' ? 'btn-primary' : '' }}">العملاء التابعون</a>
        <a href="/?tab=reseller-quotas" class="btn btn-sm {{ $section === 'reseller_quotas' ? 'btn-primary' : '' }}">الحصص والتخصيص</a>
        @if ($isPlatform)
            <a href="/?tab=accounts" class="btn btn-sm {{ $section === 'accounts' ? 'btn-primary' : '' }}">كل الحسابات</a>
            <a href="/?tab=platform-ai" class="btn btn-sm {{ $section === 'platform_ai' ? 'btn-primary' : '' }}">الذكاء والأصوات</a>
            <a href="/?tab=platform-plans" class="btn btn-sm {{ $section === 'plans' ? 'btn-primary' : '' }}">الباقات والفواتير</a>
            <a href="/?tab=releases" class="btn btn-sm {{ $section === 'releases' ? 'btn-primary' : '' }}">إصدارات Android</a>
        @endif
    </div>
</div>

@if (in_array($section, ['accounts', 'reseller_tenants'], true))
    <div class="grid-2">
        <div class="card">
            <h2 class="card-title" style="margin-bottom:10px">{{ $isPlatform ? 'إضافة حساب عميل أو وكيل (Reseller)' : 'إضافة عميل تابع جديد' }}</h2>
            <form method="POST" action="/console/action">
                @csrf
                <input type="hidden" name="action" value="create_account">
                <div class="form-grid">
                    <div class="field"><span class="field-label">اسم الحساب / الشركة</span><input type="text" name="name" required></div>
                    @if ($isPlatform && $section === 'accounts')
                        <div class="field"><span class="field-label">النوع</span>
                            <select name="kind"><option value="customer">عميل مباشر</option><option value="reseller">وكيل White-Label (Reseller)</option></select>
                        </div>
                    @endif
                </div>
                <button type="submit" class="btn btn-primary btn-sm">إنشاء الحساب والموقع</button>
            </form>
        </div>

        <div class="card">
            <h2 class="card-title" style="margin-bottom:10px">تخصيص سريع لباقة عميل</h2>
            <form method="POST" action="/console/action">
                @csrf
                <input type="hidden" name="action" value="reseller_allocate_plan">
                <div class="form-grid">
                    <div class="field">
                        <span class="field-label">الحساب المستهدف</span>
                        <select name="target_account_id" required>
                            @foreach ($data['accounts_list'] ?? [] as $acct)
                                @if ($acct['kind'] !== 'platform')
                                    <option value="{{ $acct['id'] }}">{{ $acct['name'] }} ({{ $acct['kind'] }})</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <span class="field-label">الباقة</span>
                        <select name="plan_id" required>
                            @foreach ($data['all_plans'] ?? [] as $pl)
                                <option value="{{ $pl['id'] }}">{{ $pl['name'] }} ({{ number_format((int) $pl['call_quota']) }} مكالمة)</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-sm">تفعيل وتخصيص الباقة فوراً</button>
            </form>
        </div>
    </div>

    <div class="card">
        <h2 class="card-title" style="margin-bottom:10px">قائمة الحسابات</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>#</th><th>الاسم</th><th>النوع</th><th>الباقة الحالية</th><th>الحالة</th><th>دخول سريع / إدارة</th></tr></thead>
                <tbody>
                    @forelse ($data['accounts_list'] ?? [] as $acct)
                        <tr>
                            <td class="mono">{{ $acct['id'] }}</td>
                            <td><strong>{{ $acct['name'] }}</strong> <div class="mono" style="font-size:11px;color:var(--muted)">{{ $acct['slug'] }}</div></td>
                            <td><span class="pill">{{ $acct['kind'] }}</span></td>
                            <td style="font-size:12.5px;color:var(--muted)">{{ $acct['active_plan_name'] ?? '—' }}</td>
                            <td><span class="pill {{ $acct['status'] === 'active' ? 'pill-green' : 'pill-crimson' }}">{{ $acct['status'] }}</span></td>
                            <td style="display:flex;gap:6px;flex-wrap:wrap">
                                @if ($acct['kind'] !== 'platform')
                                    <form method="POST" action="/console/action" style="margin:0">@csrf<input type="hidden" name="action" value="quick_switch_tenant"><input type="hidden" name="target_account_id" value="{{ $acct['id'] }}"><button class="btn btn-sm btn-primary">تبديل مباشر</button></form>
                                    <form method="POST" action="/console/action" style="margin:0">@csrf<input type="hidden" name="action" value="support_enter"><input type="hidden" name="target_account_id" value="{{ $acct['id'] }}"><button class="btn btn-sm">دخول دعم</button></form>
                                    @if ($acct['status'] !== 'archived')
                                    <form method="POST" action="/console/action" style="margin:0">@csrf<input type="hidden" name="action" value="toggle_account_status"><input type="hidden" name="target_account_id" value="{{ $acct['id'] }}"><button class="btn btn-sm btn-danger">{{ $acct['status'] === 'active' ? 'إيقاف' : 'تفعيل' }}</button></form>
                                    @endif
                                    @if ($acct['status'] === 'suspended')
                                        <form method="POST" action="/console/action" style="margin:0;display:flex;gap:5px;align-items:center;flex-wrap:wrap">@csrf
                                            <input type="hidden" name="action" value="archive_account">
                                            <input type="hidden" name="target_account_id" value="{{ $acct['id'] }}">
                                            <input name="confirm_name" placeholder="اكتب اسم الحساب للتأكيد" aria-label="تأكيد اسم الحساب المراد أرشفته" required style="max-width:165px;font-size:11px">
                                            <button class="btn btn-sm btn-danger">أرشفة / حذف من القائمة</button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:20px">لا توجد حسابات تابعة بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif

@if (in_array($section, ['reseller_quotas', 'plans'], true))
    <div class="grid-2">
        <div class="card">
            <h2 class="card-title" style="margin-bottom:10px">إنشاء باقة اشتراك جديدة</h2>
            <form method="POST" action="/console/action">
                @csrf
                <input type="hidden" name="action" value="create_plan">
                <div class="form-grid">
                    <div class="field"><span class="field-label">اسم الباقة</span><input type="text" name="name" required placeholder="مثال: باقة الأعمال 1000"></div>
                    @if ($isPlatform)
                        <div class="field"><span class="field-label">نوع الباقة</span>
                            <select name="kind"><option value="user">باقة عملاء</option><option value="reseller">باقة وكيل (Reseller)</option></select>
                        </div>
                    @endif
                    <div class="field"><span class="field-label">السعر (EGP)</span><input type="number" name="price_egp" value="490" min="0" step="1"></div>
                    <div class="field"><span class="field-label">حصة المكالمات</span><input type="number" name="call_quota" value="1000" min="10"></div>
                    <div class="field"><span class="field-label">حد الإيجنت</span><input type="number" name="agent_limit" value="5" min="1"></div>
                    <div class="field"><span class="field-label">حد المواقع</span><input type="number" name="site_limit" value="2" min="1"></div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">إضافة الباقة</button>
            </form>
        </div>

        <div class="card">
            <h2 class="card-title" style="margin-bottom:10px">تخصيص باقة وحصة لحساب</h2>
            <form method="POST" action="/console/action">
                @csrf
                <input type="hidden" name="action" value="reseller_allocate_plan">
                <div class="field">
                    <span class="field-label">الحساب</span>
                    <select name="target_account_id" required>
                        @foreach ($data['accounts_list'] ?? [] as $acct)
                            @if ($acct['kind'] !== 'platform')
                                <option value="{{ $acct['id'] }}">{{ $acct['name'] }} ({{ $acct['active_plan_name'] ?? '—' }})</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="form-grid">
                    <div class="field">
                        <span class="field-label">الباقة المطلوبة</span>
                        <select name="plan_id" required>
                            @foreach ($data['all_plans'] ?? [] as $pl)
                                <option value="{{ $pl['id'] }}">{{ $pl['name'] }} — {{ number_format((int) $pl['call_quota']) }} مكالمة</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><span class="field-label">المدة (بالأيام)</span><input type="number" name="days" value="30" min="1" max="365"></div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">اعتماد وتفعيل الاشتراك</button>
            </form>
        </div>
    </div>

    <div class="card">
        <h2 class="card-title" style="margin-bottom:10px">الباقات المتاحة</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>#</th><th>الباقة</th><th>الفئة</th><th>السعر</th><th>حصة المكالمات</th><th>حد الإيجنت / المواقع</th></tr></thead>
                <tbody>
                    @foreach ($data['all_plans'] ?? [] as $pl)
                        <tr>
                            <td class="mono">{{ $pl['id'] }}</td>
                            <td style="font-weight:600">{{ $pl['name'] }}</td>
                            <td><span class="pill">{{ $pl['kind'] }}</span></td>
                            <td class="mono">{{ number_format(((int) $pl['price_minor']) / 100, 0) }} {{ $pl['currency'] }}</td>
                            <td class="mono">{{ number_format((int) $pl['call_quota']) }}</td>
                            <td class="mono">{{ $pl['agent_limit'] }} إيجنت · {{ $pl['site_limit'] }} موقع</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($isPlatform && !empty($data['manual_pending']))
        <div class="card">
            <h2 class="card-title" style="margin-bottom:10px">طلبات الفواتير اليدوية المعلقة</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>#</th><th>الحساب</th><th>المبلغ</th><th>مرجع الدفع</th><th>إجراء</th></tr></thead>
                    <tbody>
                        @foreach ($data['manual_pending'] as $inv)
                            <tr>
                                <td class="mono">#{{ $inv['id'] }}</td>
                                <td>{{ $inv['account_name'] ?? ('#'.$inv['account_id']) }}</td>
                                <td class="mono">{{ number_format(((int) $inv['amount_minor']) / 100, 2) }} {{ $inv['currency'] }}</td>
                                <td class="mono">{{ $inv['provider_ref'] ?: '—' }}</td>
                                <td>
                                    <form method="POST" action="/console/action" style="display:flex;gap:6px;margin:0">
                                        @csrf
                                        <input type="hidden" name="action" value="approve_manual_invoice">
                                        <input type="hidden" name="invoice_id" value="{{ $inv['id'] }}">
                                        <input type="text" name="payment_reference" placeholder="مرجع التحويل" value="{{ $inv['provider_ref'] ?: '' }}" required style="width:140px;padding:5px 8px">
                                        <button type="submit" class="btn btn-primary btn-sm">اعتماد وتفعيل</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endif

@if ($section === 'platform_ai' && $isPlatform)
    <div class="grid-2">
        <div class="card">
            <h2 class="card-title" style="margin-bottom:10px">مفاتيح Gemini Live المركزية (Platform AI Relay)</h2>
            <form method="POST" action="/console/action">
                @csrf
                <input type="hidden" name="action" value="add_relay_entry">
                <div class="form-grid">
                    <div class="field"><span class="field-label">الاسم التعريفي</span><input type="text" name="label" required value="Platform Gemini Primary"></div>
                    <div class="field"><span class="field-label">الموديل</span><input type="text" name="model" dir="ltr" value="{{ config('ontrack.gemini_live_model') }}"></div>
                </div>
                <div class="field"><span class="field-label">Gemini API Key</span><input type="password" name="secret" required dir="ltr" placeholder="AIza..."></div>
                <button type="submit" class="btn btn-primary btn-sm">تشفير وإضافة للـ Pool المركزي</button>
            </form>
            <div style="margin-top:12px">
                @foreach ($data['platform_relay_entries'] ?? [] as $rel)
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;padding:8px 10px;border:1px solid var(--border);border-radius:8px;margin-bottom:6px">
                        <div style="min-width:0;max-width:100%"><strong>{{ $rel['label'] }}</strong> <span class="mono" style="font-size:11px;color:var(--muted);overflow-wrap:anywhere">{{ $rel['model'] }}</span></div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <span class="pill {{ $rel['enabled'] ? 'pill-green' : 'pill-crimson' }}">{{ $rel['enabled'] ? 'مفعل' : 'متوقف' }}</span>
                            <form method="POST" action="/console/action" style="margin:0">@csrf<input type="hidden" name="action" value="toggle_relay_entry"><input type="hidden" name="relay_id" value="{{ $rel['id'] }}"><button class="btn btn-sm">{{ $rel['enabled'] ? 'تعطيل' : 'تفعيل' }}</button></form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card">
            <h2 class="card-title" style="margin-bottom:10px">مزامنة كتالوج الأصوات العالمي</h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
                <form method="POST" action="/console/action" style="margin:0">@csrf<input type="hidden" name="action" value="sync_google_voices"><button class="btn btn-sm">مزامنة Google Live (30+)</button></form>
                <form method="POST" action="/console/action" style="margin:0">@csrf<input type="hidden" name="action" value="sync_fish_catalog"><input type="hidden" name="mark_ready" value="1"><button class="btn btn-sm btn-primary">جلب ومزامنة Fish Audio</button></form>
            </div>
            <p class="card-sub">عدد الأصوات المتاحة في الكتالوج الآن: <strong class="mono">{{ count($data['global_voices'] ?? []) }}</strong></p>
            <p class="card-sub">جملة المعاينة الثابتة لكل صوت: «{{ \App\Services\Voice\VoiceCatalogService::PREVIEW_TEXT }}». أول معاينة تُولَّد من المزود وتُخزّن لإعادة تشغيلها بدون استهلاك متكرر. عند غياب مفتاح مزود صالح ستظهر رسالة واضحة.</p>
            <div class="table-wrap" style="max-height:510px;overflow-y:auto;margin-top:12px">
                <table class="data-table" aria-label="التحكم في أصوات المنصة">
                    <thead><tr><th>الصوت</th><th>المزود</th><th>النوع/اللهجة</th><th>الحالة</th><th>المعاينة والإدارة</th></tr></thead>
                    <tbody>
                    @forelse ($data['global_voices'] ?? [] as $v)
                    <tr>
                        <td><strong>{{ $v['name'] }}</strong><div class="mono" style="font-size:11px;overflow-wrap:anywhere">{{ $v['code'] }}</div></td>
                        <td>{{ $v['provider'] }}</td>
                        <td>{{ $v['gender'] ?? 'neutral' }} · {{ $v['dialect'] ?? '—' }}</td>
                        <td><span class="pill {{ (int) $v['ready'] === 1 ? 'pill-green' : 'pill-crimson' }}">{{ (int) $v['ready'] === 1 ? 'مفعّل' : 'معطّل' }}</span></td>
                        <td style="display:flex;gap:6px;flex-wrap:wrap">
                            <button type="button" class="btn btn-sm" data-cached-url="{{ $v['sample_local_url'] ?? '' }}" onclick="playVoicePreview({{ (int) $v['id'] }},this)">▶ معاينة</button>
                            <form method="POST" action="/console/action" style="margin:0">@csrf<input type="hidden" name="action" value="toggle_voice_ready"><input type="hidden" name="voice_id" value="{{ (int) $v['id'] }}"><button class="btn btn-sm">{{ (int) $v['ready'] === 1 ? 'تعطيل' : 'تفعيل' }}</button></form>
                            <form method="POST" action="/console/action" style="margin:0" onsubmit="return confirm('إخفاء هذا الصوت من الكتالوج؟ لن نحذف التسجيلات أو بيانات الإيجنتات القديمة.')" >@csrf<input type="hidden" name="action" value="archive_voice"><input type="hidden" name="voice_id" value="{{ (int) $v['id'] }}"><button class="btn btn-sm btn-danger">حذف من القائمة</button></form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5">لا توجد أصوات؛ شغّل المزامنة أولًا.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <form method="POST" action="/console/action" style="margin-top:10px">
                @csrf
                <input type="hidden" name="action" value="save_io_premium_access">
                <div class="form-grid">
                    <div class="field"><span class="field-label">سياسة إتاحة IO Premium</span>
                        <select name="io_access_mode">
                            <option value="all" {{ ($data['io_access']['mode'] ?? 'all') === 'all' ? 'selected' : '' }}>متاح لكل الحسابات</option>
                            <option value="granted" {{ ($data['io_access']['mode'] ?? '') === 'granted' ? 'selected' : '' }}>للحسابات المصرّح لها فقط</option>
                        </select>
                    </div>
                    <div class="field"><span class="field-label">حد المكالمات الشهري</span><input type="number" name="io_access_limit" value="{{ (int) ($data['io_access']['monthly_limit'] ?? 0) }}" min="0"></div>
                </div>
                <button type="submit" class="btn btn-sm">حفظ سياسة IO Premium</button>
            </form>
        </div>
    </div>
@endif

@if ($section === 'releases')
    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">إصدار تطبيق الهاتف الرسمي (IO Voice Android)</h2>
                <p class="card-sub mono" dir="ltr">Version {{ config('ontrack.apk_version_name') }} · versionCode={{ config('ontrack.apk_version_code') }} · OTA Rev {{ config('ontrack.ota_revision') }}</p>
            </div>
            <a href="{{ config('ontrack.apk_download_path') }}" download="OnTrack-Voice-{{ config('ontrack.apk_version_name', '0.3.44') }}.apk" class="btn btn-primary btn-sm">تحميل APK الرسمي الآن</a>
        </div>
    </div>
@endif
