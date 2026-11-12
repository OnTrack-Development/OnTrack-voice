<div class="card-head" style="margin-bottom:14px">
    <div>
        <h1 style="font-size:20px;margin:0;font-weight:700">الأتمتة ما بعد المكالمات والربط البرمجي (Webhooks)</h1>
        <p class="card-sub">نفّذ إجراءات تلقائية فور انتهاء المكالمة أو حفظ التقرير، مع توقيع HMAC-SHA256 وحماية كاملة ضد هجمات SSRF</p>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <h2 class="card-title" style="margin-bottom:10px">إنشاء قاعدة أتمتة جديدة</h2>
        <form method="POST" action="/console/action">
            @csrf
            <input type="hidden" name="action" value="create_automation">
            <div class="field"><span class="field-label">اسم القاعدة</span><input type="text" name="name" required placeholder="مثال: إرسال الحجوزات إلى n8n / CRM"></div>
            <div class="form-grid">
                <div class="field">
                    <span class="field-label">الحدث المشغّل (Trigger)</span>
                    <select name="trigger_event">
                        <option value="call.ended">انتهاء المكالمة (call.ended)</option>
                        <option value="report.saved">حفظ التقرير والنتيجة (report.saved)</option>
                    </select>
                </div>
                <div class="field">
                    <span class="field-label">الشرط</span>
                    <select name="condition_field">
                        <option value="any">دائماً (كل المكالمات)</option>
                        <option value="outcome">نتيجة التقرير (outcome)</option>
                        <option value="status">حالة المكالمة (status)</option>
                    </select>
                </div>
                <div class="field">
                    <span class="field-label">القيمة المطابقة</span>
                    <input type="text" name="condition_value" value="any" placeholder="any / booked / lead_qualified">
                </div>
                <div class="field">
                    <span class="field-label">الإجراء (Action)</span>
                    <select name="action_type">
                        <option value="webhook">إرسال Webhook (HTTPS + HMAC)</option>
                        <option value="crm_update">تحديث حالة العميل في CRM</option>
                        <option value="dnc_suppress">إدراج في قائمة DNC</option>
                        <option value="whatsapp_template">جدولة رسالة واتساب متابعة</option>
                    </select>
                </div>
            </div>
            <div class="field"><span class="field-label">رابط HTTPS للـ Webhook (إن وجد)</span><input type="url" name="webhook_url" dir="ltr" placeholder="https://hooks.example.com/iovoice"></div>
            <div class="field"><span class="field-label">حالة CRM الجديدة أو نص قالب الواتساب (اختياري)</span><input type="text" name="crm_status" placeholder="qualified / booked"></div>
            <button type="submit" class="btn btn-primary">تفعيل القاعدة</button>
        </form>
    </div>

    <div class="card">
        <h2 class="card-title" style="margin-bottom:10px">القواعد المفعلة</h2>
        @forelse ($data['rules'] ?? [] as $rule)
            <div style="border:1px solid var(--border);border-radius:10px;padding:12px;margin-bottom:10px;background:var(--bg)">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap">
                    <div>
                        <strong>{{ $rule['name'] }}</strong>
                        <span class="pill {{ $rule['enabled'] ? 'pill-green' : '' }}" style="margin-right:6px">{{ $rule['enabled'] ? 'نشطة' : 'متوقفة' }}</span>
                    </div>
                    <span class="mono" style="font-size:11.5px;color:var(--muted)">تنفيذ: {{ (int) $rule['trigger_count'] }}</span>
                </div>
                <div class="mono" style="font-size:11.5px;color:var(--muted);margin:6px 0" dir="ltr">
                    ON {{ $rule['trigger_event'] }} WHERE {{ $rule['condition_field'] }}={{ $rule['condition_value'] }} → {{ $rule['action_type'] }}
                    @if (!empty($rule['webhook_url'])) ({{ $rule['webhook_url'] }}) @endif
                </div>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                    @if ($rule['action_type'] === 'webhook')
                        <form method="POST" action="/console/action" style="margin:0">@csrf<input type="hidden" name="action" value="test_automation_webhook"><input type="hidden" name="rule_id" value="{{ $rule['id'] }}"><button class="btn btn-sm">اختبار حي</button></form>
                    @endif
                    <form method="POST" action="/console/action" style="margin:0">@csrf<input type="hidden" name="action" value="toggle_automation"><input type="hidden" name="rule_id" value="{{ $rule['id'] }}"><button class="btn btn-sm">{{ $rule['enabled'] ? 'إيقاف' : 'تفعيل' }}</button></form>
                    <form method="POST" action="/console/action" style="margin:0">@csrf<input type="hidden" name="action" value="delete_automation"><input type="hidden" name="rule_id" value="{{ $rule['id'] }}"><button class="btn btn-sm btn-danger">حذف</button></form>
                </div>
            </div>
        @empty
            <p class="card-sub">لا توجد قواعد أتمتة بعد.</p>
        @endforelse
    </div>
</div>

<div class="card">
    <h2 class="card-title" style="margin-bottom:10px">سجل إرسال Webhooks (آخر 40 محاولة)</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>الوقت UTC</th><th>الحدث</th><th>الهدف</th><th>الحالة / HTTP</th><th>الزمن</th></tr></thead>
            <tbody>
                @forelse ($data['webhook_logs'] ?? [] as $log)
                    <tr>
                        <td class="mono" style="font-size:11.5px">{{ $log['created_at'] }}</td>
                        <td class="mono">{{ $log['event_type'] }}</td>
                        <td class="mono" style="font-size:11.5px" dir="ltr">{{ $log['target_url'] }}</td>
                        <td><span class="pill {{ $log['status'] === 'delivered' ? 'pill-green' : 'pill-crimson' }}">{{ $log['status'] }} ({{ $log['http_status'] ?? '—' }})</span></td>
                        <td class="mono">{{ $log['duration_ms'] }}ms</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:18px">لم يتم إرسال أي طلبات Webhook بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
