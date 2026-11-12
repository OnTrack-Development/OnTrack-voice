<div class="card-head" style="margin-bottom:14px">
    <div>
        <h1 style="font-size:20px;margin:0;font-weight:700">إدارة المنصة، الوكلاء (Resellers)، والباقات</h1>
        <p class="card-sub">التحكم المركزي في حسابات العملاء والوكلاء، الدعم الفني الفوري، وإصدارات تطبيق IO Voice</p>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <h2 class="card-title" style="margin-bottom:10px">إضافة حساب عميل أو وكيل (Reseller)</h2>
        <form method="POST" action="/console/action">
            @csrf
            <input type="hidden" name="action" value="create_account">
            <div class="form-grid">
                <div class="field"><span class="field-label">اسم الحساب / الشركة</span><input type="text" name="name" required></div>
                @if ($ctx['is_platform'])
                    <div class="field"><span class="field-label">النوع</span>
                        <select name="kind"><option value="customer">عميل مباشر</option><option value="reseller">وكيل White-Label (Reseller)</option></select>
                    </div>
                @endif
            </div>
            <button type="submit" class="btn btn-primary btn-sm">إنشاء الحساب والموقع</button>
        </form>
    </div>

    <div class="card">
        <h2 class="card-title" style="margin-bottom:10px">إنشاء باقة اشتراك جديدة</h2>
        <form method="POST" action="/console/action">
            @csrf
            <input type="hidden" name="action" value="create_plan">
            <div class="form-grid">
                <div class="field"><span class="field-label">اسم الباقة</span><input type="text" name="name" required placeholder="مثال: باقة الأعمال 1000"></div>
                <div class="field"><span class="field-label">السعر (EGP)</span><input type="number" name="price_egp" value="490" min="0" step="1"></div>
                <div class="field"><span class="field-label">حصة المكالمات</span><input type="number" name="call_quota" value="1000" min="10"></div>
                <div class="field"><span class="field-label">حد الإيجنت / المواقع</span><input type="number" name="agent_limit" value="5" min="1"></div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">إضافة الباقة</button>
        </form>
    </div>
</div>

<div class="card">
    <h2 class="card-title" style="margin-bottom:10px">قائمة الحسابات</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>#</th><th>الاسم</th><th>النوع</th><th>الحالة</th><th>دعم فني / إدارة</th></tr></thead>
            <tbody>
                @foreach ($data['accounts_list'] ?? [] as $acct)
                    <tr>
                        <td class="mono">{{ $acct['id'] }}</td>
                        <td><strong>{{ $acct['name'] }}</strong> <div class="mono" style="font-size:11px;color:var(--muted)">{{ $acct['slug'] }}</div></td>
                        <td><span class="pill">{{ $acct['kind'] }}</span></td>
                        <td><span class="pill {{ $acct['status'] === 'active' ? 'pill-green' : 'pill-crimson' }}">{{ $acct['status'] }}</span></td>
                        <td style="display:flex;gap:6px;flex-wrap:wrap">
                            @if ($acct['kind'] !== 'platform')
                                <form method="POST" action="/console/action" style="margin:0">@csrf<input type="hidden" name="action" value="support_enter"><input type="hidden" name="target_account_id" value="{{ $acct['id'] }}"><button class="btn btn-sm">دخول دعم فني</button></form>
                                <form method="POST" action="/console/action" style="margin:0">@csrf<input type="hidden" name="action" value="toggle_account_status"><input type="hidden" name="target_account_id" value="{{ $acct['id'] }}"><button class="btn btn-sm btn-danger">{{ $acct['status'] === 'active' ? 'إيقاف' : 'تفعيل' }}</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <div>
            <h2 class="card-title">إصدار تطبيق الهاتف الرسمي (IO Voice Android)</h2>
            <p class="card-sub mono" dir="ltr">Version {{ config('ontrack.apk_version_name') }} · versionCode={{ config('ontrack.apk_version_code') }} · OTA Rev {{ config('ontrack.ota_revision') }}</p>
        </div>
        <a href="{{ config('ontrack.apk_download_path') }}" class="btn btn-primary btn-sm">تحميل APK الرسمي الآن</a>
    </div>
</div>
