@php
    $appointments = $data['appointments'] ?? [];
    if (empty($appointments)) {
        $appointments = [
            [
                'id' => 1,
                'client_name' => 'أحمد محمود',
                'client_phone' => '+201012345678',
                'booked_by' => 'Zoie',
                'service' => 'مكالمة استشارة سحابية مبيعات VPS',
                'time' => 'اليوم 11:00 ص - 11:30 ص',
                'status' => 'confirmed',
                'status_label' => 'مؤكد',
                'date' => '2026-11-15',
            ],
            [
                'id' => 2,
                'client_name' => 'م. طارق عبد الرحمن',
                'client_phone' => '+201199887766',
                'booked_by' => 'Zoie',
                'service' => 'استشارة خادم مخصص Dedicated',
                'time' => 'الثلاثاء 17 نوفمبر 03:30 م',
                'status' => 'confirmed',
                'status_label' => 'مؤكد',
                'date' => '2026-11-17',
            ],
            [
                'id' => 3,
                'client_name' => 'سارة إبراهيم',
                'client_phone' => '+201255443322',
                'booked_by' => 'Zoie',
                'service' => 'عرض توضيحي لمنصة أون تراك',
                'time' => 'الخميس 19 نوفمبر 01:00 م',
                'status' => 'pending',
                'status_label' => 'قيد التأكيد',
                'date' => '2026-11-19',
            ],
        ];
    }
@endphp

<div class="card-head" style="margin-bottom:16px;">
    <div>
        <h1 style="font-size:22px;margin:0 0 4px 0;font-weight:800;color:#0F172A;">المواعيد وجدولة الاتصالات (Appointments Calendar)</h1>
        <p style="margin:0;font-size:13px;color:#64748B;">المواعيد التي حجزها الإيجنت الذكي تلقائياً للمبيعات أو الدعم الفني أثناء المكالمات والمحادثات</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <button type="button" class="btn btn-sm btn-ghost" onclick="copyBookingLink()" style="background:#FFF;border:1px solid #E2E8F0;color:#0F172A;font-weight:600;display:flex;align-items:center;gap:6px;">
            <span>🔗</span>
            <span>نسخ رابط الحجز المباشر</span>
        </button>
        <button type="button" class="btn btn-sm" onclick="openNewAppointmentModal()" style="background:#0F172A;color:#FFFFFF;border:none;border-radius:8px;font-weight:600;padding:8px 16px;">
            + حجز موعد جديد
        </button>
    </div>
</div>

<!-- 4 Top Metric Cards (Matching Zoie.ai Appointments) -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:14px;margin-bottom:20px;">
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size:12px;color:#64748B;font-weight:600;margin-bottom:6px;">إجمالي المواعيد المحجوزة</div>
        <div style="font-size:24px;font-weight:800;color:#0F172A;">{{ $data['stats']['total'] ?? 18 }}</div>
        <div style="font-size:11.5px;color:#10B981;font-weight:600;margin-top:4px;">كلها مجدولة بواسطة AI</div>
    </div>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size:12px;color:#64748B;font-weight:600;margin-bottom:6px;">المواعيد القادمة (Upcoming)</div>
        <div style="font-size:24px;font-weight:800;color:#3B82F6;">{{ $data['stats']['today_ai'] ?? 6 }}</div>
        <div style="font-size:11.5px;color:#3B82F6;font-weight:600;margin-top:4px;">خلال الـ 7 أيام القادمة</div>
    </div>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size:12px;color:#64748B;font-weight:600;margin-bottom:6px;">المكتملة بنجاح (Completed)</div>
        <div style="font-size:24px;font-weight:800;color:#10B981;">11</div>
        <div style="font-size:11.5px;color:#10B981;font-weight:600;margin-top:4px;">نسبة حضور 92%</div>
    </div>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size:12px;color:#64748B;font-weight:600;margin-bottom:6px;">عدم حضور / ملغاة (No-shows)</div>
        <div style="font-size:24px;font-weight:800;color:#EF4444;">{{ $data['stats']['no_show_rate'] ?? '4.2%' }}</div>
        <div style="font-size:11.5px;color:#64748B;margin-top:4px;">تم إرسال رسالة اعتذار تلقائية</div>
    </div>
</div>

<div class="grid-2" style="grid-template-columns: 1fr 340px;gap:18px;">
    <!-- Calendar Grid -->
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:14px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <div style="display:flex;gap:6px;background:#F1F5F9;padding:3px;border-radius:8px;">
                <button type="button" class="btn btn-sm btn-ghost" style="color:#64748B;font-size:12px;">اليوم</button>
                <button type="button" class="btn btn-sm" style="background:#FFF;color:#0F172A;font-weight:700;border-radius:6px;box-shadow:0 1px 2px rgba(0,0,0,0.05);font-size:12px;">الأسبوع</button>
                <button type="button" class="btn btn-sm btn-ghost" style="color:#64748B;font-size:12px;">الشهر</button>
            </div>
            <div style="font-weight:800;font-size:16px;color:#0F172A;">نوفمبر 2026</div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(7, 1fr);gap:10px;text-align:center;font-size:13px;">
            @foreach (['السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'] as $dayName)
                <div style="color:#64748B;font-weight:700;padding-bottom:10px;border-bottom:1px solid #E2E8F0;">{{ $dayName }}</div>
            @endforeach

            @for ($d = 15; $d <= 21; $d++)
                <div style="height:120px;border:1px solid #E2E8F0;border-radius:10px;padding:10px;display:flex;flex-direction:column;align-items:flex-start;background:{{ $d === 15 ? '#F8FAFC' : '#FFFFFF' }};">
                    <span style="font-weight:800;font-size:12px;color:{{ $d === 15 ? '#8B5CF6' : '#0F172A' }};">{{ $d }} نوفمبر</span>
                    @if ($d === 15)
                        <div style="background:#0F172A;color:#FFF;padding:5px 8px;border-radius:6px;font-size:10.5px;margin-top:8px;text-align:right;width:100%;line-height:1.4;">
                            11:00 ص: مكالمة مبيعات مع أحمد محمود
                        </div>
                    @endif
                    @if ($d === 17)
                        <div style="background:#2563EB;color:#FFF;padding:5px 8px;border-radius:6px;font-size:10.5px;margin-top:8px;text-align:right;width:100%;line-height:1.4;">
                            03:30 م: استشارة م. طارق
                        </div>
                    @endif
                    @if ($d === 19)
                        <div style="background:#7C3AED;color:#FFF;padding:5px 8px;border-radius:6px;font-size:10.5px;margin-top:8px;text-align:right;width:100%;line-height:1.4;">
                            01:00 م: ديمو OnTrack
                        </div>
                    @endif
                </div>
            @endfor
        </div>
    </div>

    <!-- Upcoming List Card -->
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:14px;padding:20px;display:flex;flex-direction:column;gap:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #F1F5F9;padding-bottom:12px;">
            <strong style="font-size:15px;color:#0F172A;">المواعيد القادمة</strong>
            <span style="font-size:11.5px;background:#F1F5F9;padding:2px 8px;border-radius:10px;font-weight:600;color:#475569;">{{ count($appointments) }} مواعيد</span>
        </div>

        <div style="display:flex;flex-direction:column;gap:12px;overflow-y:auto;max-height:480px;">
            @foreach ($appointments as $apt)
                @php 
                    $a = (array) $apt; 
                    $cName = $a['client_name'] ?? $a['customer_name'] ?? 'عميل جديد';
                    $cService = $a['service'] ?? 'استشارة سحابية';
                    $cTime = $a['time'] ?? $a['time_formatted'] ?? '10:30 ص';
                    $cStatus = $a['status_label'] ?? $a['status'] ?? 'مؤكد';
                    $cAgent = $a['booked_by'] ?? $a['agent_name'] ?? 'Zoie';
                @endphp
                <div style="border:1px solid #E2E8F0;border-radius:10px;padding:12px;background:#F8FAFC;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <strong style="font-size:13px;color:#0F172A;">{{ $cName }}</strong>
                        <span style="background:#F0FDF4;color:#16A34A;padding:2px 8px;border-radius:6px;font-size:10.5px;font-weight:700;">{{ $cStatus }}</span>
                    </div>
                    <div style="font-size:11.5px;color:#64748B;margin-bottom:6px;">
                        {{ $cService }}
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;font-size:11px;color:#94A3B8;">
                        <span>🕒 {{ $cTime }}</span>
                        <span style="color:#8B5CF6;font-weight:600;">✨ {{ $cAgent }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div style="background:#F1F5F9;border-radius:10px;padding:12px;font-size:12px;color:#475569;line-height:1.5;">
            💡 <strong>تزامن Google Calendar:</strong> المواعيد المحجوزة تتزامن لحظياً مع تقويم فريق المبيعات ويرسل رابط Google Meet للعميل في رسالة واتساب.
        </div>
    </div>
</div>

<!-- Modal: + New Appointment -->
<div id="newAppointmentModal" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.45);backdrop-filter:blur(4px);z-index:9999;display:none;align-items:center;justify-content:center;">
    <div style="background:#FFFFFF;border-radius:16px;width:100%;max-width:480px;box-shadow:0 20px 40px rgba(0,0,0,0.15);overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid #E2E8F0;display:flex;align-items:center;justify-content:space-between;">
            <strong style="font-size:16px;color:#0F172A;">حجز موعد يدوي جديد</strong>
            <button type="button" onclick="closeNewAppointmentModal()" style="background:none;border:none;font-size:18px;color:#64748B;cursor:pointer;">✕</button>
        </div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:14px;">
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">اسم العميل</label>
                <input type="text" placeholder="الاسم الكامل للعميل" style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">رقم الهاتف</label>
                <input type="text" placeholder="+2010xxxxxxxx" dir="ltr" style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
            </div>
            <div style="display:flex;gap:12px;">
                <div style="flex:1;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">التاريخ</label>
                    <input type="date" value="{{ date('Y-m-d') }}" style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
                </div>
                <div style="flex:1;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">الوقت</label>
                    <input type="time" value="12:00" style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
                </div>
            </div>
        </div>
        <div style="padding:14px 20px;border-top:1px solid #E2E8F0;display:flex;justify-content:flex-end;gap:10px;background:#F8FAFC;">
            <button type="button" class="btn btn-sm btn-ghost" onclick="closeNewAppointmentModal()">إلغاء</button>
            <button type="button" class="btn btn-sm btn-primary" onclick="alert('تم حجز الموعد بنجاح!'); closeNewAppointmentModal();" style="background:#0F172A;border:none;">تأكيد الحجز</button>
        </div>
    </div>
</div>

<script>
function copyBookingLink() {
    const url = "https://app.ontrackegy.com/book/{{ $ctx['account']['slug'] ?? 'sales' }}";
    navigator.clipboard.writeText(url).then(() => {
        alert("تم نسخ رابط الحجز المباشر:\n" + url);
    });
}
function openNewAppointmentModal() {
    document.getElementById('newAppointmentModal').style.display = 'flex';
}
function closeNewAppointmentModal() {
    document.getElementById('newAppointmentModal').style.display = 'none';
}
</script>