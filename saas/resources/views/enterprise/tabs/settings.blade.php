<div class="card-head" style="margin-bottom:14px">
    <div>
        <h1 style="font-size:20px;margin:0;font-weight:700">الإعدادات، المفاتيح، والفريق</h1>
        <p class="card-sub">بيانات المنشأة، مفاتيح Gemini / AI Relay المشفرة، مفاتيح API والتكاملات، وإدارة فريق العمل</p>
    </div>
</div>

@php $prof = $data['profile'] ?? []; @endphp
<div class="grid-2">
    <div class="card">
        <h2 class="card-title" style="margin-bottom:10px">بيانات المنشأة والهوية</h2>
        <form method="POST" action="/console/action">
            @csrf
            <input type="hidden" name="action" value="update_profile">
            <div class="form-grid">
                <div class="field"><span class="field-label">اسم العرض / العلامة</span><input type="text" name="brand_name" value="{{ $ctx['account']['brand_name'] ?? ($ctx['account']['name'] ?? '') }}"></div>
                <div class="field"><span class="field-label">اسم المسؤول</span><input type="text" name="contact_name" value="{{ $prof['contact_name'] ?? '' }}"></div>
                <div class="field"><span class="field-label">البريد التشغيلي</span><input type="email" name="contact_email" dir="ltr" value="{{ $prof['contact_email'] ?? '' }}"></div>
                <div class="field"><span class="field-label">رقم الهاتف</span><input type="text" name="phone" dir="ltr" value="{{ $prof['phone'] ?? '' }}"></div>
            </div>
            <div class="field"><span class="field-label">العنوان / المقر</span><input type="text" name="address" value="{{ $prof['address'] ?? '' }}"></div>
            <button type="submit" class="btn btn-primary btn-sm">حفظ البيانات</button>
        </form>
    </div>

    <div class="card">
        <h2 class="card-title" style="margin-bottom:10px">تغيير كلمة المرور (بدون 2FA)</h2>
        <form method="POST" action="/change-password">
            @csrf
            <div class="field"><span class="field-label">كلمة المرور الحالية</span><input type="password" name="current_password" required dir="ltr"></div>
            <div class="field"><span class="field-label">كلمة المرور الجديدة (8+ أحرف)</span><input type="password" name="password" required dir="ltr"></div>
            <div class="field"><span class="field-label">تأكيد كلمة المرور الجديدة</span><input type="password" name="password_confirmation" required dir="ltr"></div>
            <button type="submit" class="btn btn-sm">تحديث كلمة المرور</button>
        </form>
    </div>
</div>

<!-- AI Relay Keys Pool -->
<div class="card">
    <div class="card-head">
        <div>
            <h2 class="card-title">مفاتيح مزود الصوت (Gemini Live / AI Relay Pool)</h2>
            <p class="card-sub">يتم تشفير المفاتيح بـ AES-256-GCM ولا تُعرض أو تُرسل للهاتف مباشرة بل تصدر تذاكر مؤقتة فقط</p>
        </div>
    </div>
    <form method="POST" action="/console/action" class="form-grid" style="align-items:end;margin-bottom:14px">
        @csrf
        <input type="hidden" name="action" value="add_relay_entry">
        <div class="field" style="margin:0"><span class="field-label">الاسم التعريفي</span><input type="text" name="label" required value="Gemini Live Primary"></div>
        <div class="field" style="margin:0"><span class="field-label">الموديل</span><input type="text" name="model" dir="ltr" value="{{ config('ontrack.gemini_live_model') }}"></div>
        <div class="field" style="margin:0"><span class="field-label">مفتاح Gemini API Key</span><input type="password" name="secret" required dir="ltr" placeholder="AIza..."></div>
        <div><button type="submit" class="btn btn-primary btn-sm">تشفير وإضافة للـ Pool</button></div>
    </form>

    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>الاسم</th><th>النطاق / الموديل</th><th>الحالة</th><th>إدارة</th></tr></thead>
            <tbody>
                @forelse ($data['relay_entries'] ?? [] as $rel)
                    <tr>
                        <td style="font-weight:600">{{ $rel['label'] }}</td>
                        <td class="mono" style="font-size:12px">{{ $rel['scope'] }} · {{ $rel['model'] }}</td>
                        <td><span class="pill {{ $rel['enabled'] ? 'pill-green' : 'pill-crimson' }}">{{ $rel['enabled'] ? 'مفعل' : 'متوقف' }}</span></td>
                        <td style="display:flex;gap:6px">
                            <form method="POST" action="/console/action" style="margin:0">@csrf<input type="hidden" name="action" value="toggle_relay_entry"><input type="hidden" name="relay_id" value="{{ $rel['id'] }}"><button class="btn btn-sm">{{ $rel['enabled'] ? 'تعطيل' : 'تفعيل' }}</button></form>
                            <form method="POST" action="/console/action" style="margin:0">@csrf<input type="hidden" name="action" value="delete_relay_entry"><input type="hidden" name="relay_id" value="{{ $rel['id'] }}"><button class="btn btn-sm btn-danger">حذف</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:16px">يستخدم الحساب حصة المنصة الافتراضية (أو أضف مفتاحاً خاصاً بالأعلى عند تفعيل الميزة).</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">مفاتيح API والتكاملات</h2>
            <form method="POST" action="/console/action" style="display:flex;gap:6px;margin:0">
                @csrf<input type="hidden" name="action" value="create_api_key"><input type="hidden" name="name" value="CRM Key">
                <button type="submit" class="btn btn-sm">+ إصدار API Key</button>
            </form>
        </div>
        @forelse ($data['api_keys'] ?? [] as $k)
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;border:1px solid var(--border);border-radius:8px;margin-bottom:6px">
                <div><strong>{{ $k['name'] }}</strong> <span class="mono" style="font-size:11.5px;color:var(--muted)">{{ $k['prefix'] }}***</span></div>
                @if ($k['status'] === 'active')
                    <form method="POST" action="/console/action" style="margin:0">@csrf<input type="hidden" name="action" value="revoke_api_key"><input type="hidden" name="key_id" value="{{ $k['id'] }}"><button class="btn btn-sm btn-danger">إلغاء</button></form>
                @else
                    <span class="pill">ملغي</span>
                @endif
            </div>
        @empty
            <p class="card-sub">لا توجد مفاتيح API مصدرة بعد.</p>
        @endforelse
    </div>

    <div class="card">
        <h2 class="card-title" style="margin-bottom:10px">فريق العمل والصلاحيات</h2>
        <form method="POST" action="/console/action" class="form-grid" style="margin-bottom:12px">
            @csrf<input type="hidden" name="action" value="invite_staff">
            <div class="field" style="margin:0"><span class="field-label">الاسم</span><input type="text" name="name" required></div>
            <div class="field" style="margin:0"><span class="field-label">البريد</span><input type="email" name="email" required dir="ltr"></div>
            <div class="field" style="margin:0"><span class="field-label">الصلاحية</span>
                <select name="role"><option value="manager">مدير</option><option value="operator">مشغل</option><option value="reception">استقبال</option><option value="finance">مالية</option><option value="viewer">عرض فقط</option></select>
            </div>
            <div style="display:flex;align-items:end"><button type="submit" class="btn btn-sm btn-primary">دعوة موظف</button></div>
        </form>
        @foreach ($data['staff_members'] ?? [] as $m)
            <div style="padding:7px 10px;border:1px solid var(--border);border-radius:8px;margin-bottom:6px;display:flex;justify-content:space-between;align-items:center">
                <span><strong>{{ $m['name'] }}</strong> <span class="mono" style="font-size:11.5px;color:var(--muted)">({{ $m['email'] }})</span></span>
                <span class="pill pill-crimson">{{ $m['role'] }}</span>
            </div>
        @endforeach
    </div>
</div>
