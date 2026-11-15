@php
    $appointments = $data['appointments'] ?? [];
    $today = date('Y-m-d');
@endphp

<div class="card-head" style="margin-bottom:16px;">
    <div>
        <h1 style="font-size:22px;margin:0 0 4px 0;font-weight:800;color:#0F172A;">المواعيد وجدولة الاتصالات (Appointments Calendar)</h1>
        <p style="margin:0;font-size:13px;color:#64748B;">المواعيد التي حجزها الإيجنت الذكي تلقائياً للمبيعات أو الدعم الفني أثناء المكالمات والمحادثات</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <a href="{{ route('public.booking.show', ['account_slug' => $ctx['account']['slug'] ?? 'demo']) }}" target="_blank" class="btn btn-sm btn-ghost" style="background:#FFF;border:1px solid #E2E8F0;color:#0F172A;font-weight:600;display:flex;align-items:center;gap:6px;text-decoration:none;">
            <span>🔗</span>
            <span>رابط الحجز المباشر (/book)</span>
        </a>
        <button type="button" class="btn btn-sm" onclick="openNewAppointmentModal()" style="background:#0F172A;color:#FFFFFF;border:none;border-radius:8px;font-weight:600;padding:8px 16px;">
            + حجز موعد جديد
        </button>
    </div>
</div>

<!-- 4 Top Metric Cards (Matching Zoie.ai Appointments) -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:14px;margin-bottom:20px;">
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size:12px;color:#64748B;font-weight:600;margin-bottom:6px;">إجمالي المواعيد المحجوزة</div>
        <div style="font-size:24px;font-weight:800;color:#0F172A;">{{ count($appointments) }}</div>
        <div style="font-size:11.5px;color:#10B981;font-weight:600;margin-top:4px;">مسجلة في قاعدة البيانات</div>
    </div>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size:12px;color:#64748B;font-weight:600;margin-bottom:6px;">مواعيد اليوم (Today)</div>
        <div style="font-size:24px;font-weight:800;color:#3B82F6;">{{ collect($appointments)->where('date', $today)->count() }}</div>
        <div style="font-size:11.5px;color:#3B82F6;font-weight:600;margin-top:4px;">{{ $data['stats']['next_up'] ?? 'مجدولة لليوم' }}</div>
    </div>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size:12px;color:#64748B;font-weight:600;margin-bottom:6px;">المكتملة بنجاح (Completed)</div>
        <div style="font-size:24px;font-weight:800;color:#10B981;">{{ collect($appointments)->where('status', 'completed')->count() }}</div>
        <div style="font-size:11.5px;color:#10B981;font-weight:600;margin-top:4px;">تم حضورها بنجاح</div>
    </div>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size:12px;color:#64748B;font-weight:600;margin-bottom:6px;">ملغاة / لم يحضروا (Cancelled)</div>
        <div style="font-size:24px;font-weight:800;color:#EF4444;">{{ collect($appointments)->whereIn('status', ['cancelled', 'no-show'])->count() }}</div>
        <div style="font-size:11.5px;color:#64748B;margin-top:4px;">متاحة لإعادة الجدولة</div>
    </div>
</div>

<div class="grid-2" style="grid-template-columns: 1fr 340px;gap:18px;">
    <!-- Appointments Table / Schedule View -->
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:14px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <strong style="font-size:15px;color:#0F172A;">جدول المواعيد القادمة والمسجلة</strong>
            <div style="font-size:12px;color:#64748B;">تاريخ اليوم: {{ $today }}</div>
        </div>

        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;text-align:right;font-size:13px;">
                <thead>
                    <tr style="background:#F8FAFC;border-bottom:1px solid #E2E8F0;color:#64748B;font-size:11.5px;font-weight:700;">
                        <th style="padding:10px 14px;">العميل</th>
                        <th style="padding:10px 14px;">الخدمة</th>
                        <th style="padding:10px 14px;">التاريخ والوقت</th>
                        <th style="padding:10px 14px;">الحالة</th>
                        <th style="padding:10px 14px;text-align:center;">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($appointments as $app)
                        @php
                            $aArr = (array) $app;
                            $st = $aArr['status'] ?? 'confirmed';
                        @endphp
                        <tr style="border-bottom:1px solid #F1F5F9;">
                            <td style="padding:12px 14px;">
                                <div style="font-weight:700;color:#0F172A;">{{ $aArr['client_name'] }}</div>
                                <div style="font-size:11.5px;color:#64748B;" dir="ltr">{{ $aArr['client_phone'] }}</div>
                            </td>
                            <td style="padding:12px 14px;color:#334155;font-weight:600;">
                                {{ $aArr['service'] ?? 'استشارة عامة' }}
                            </td>
                            <td style="padding:12px 14px;color:#0F172A;">
                                <div style="font-weight:700;">{{ $aArr['date'] }}</div>
                                <div style="font-size:11px;color:#64748B;">🕒 {{ $aArr['time'] }}</div>
                            </td>
                            <td style="padding:12px 14px;">
                                <form method="POST" action="{{ route('enterprise.action') }}" style="margin:0;">
                                    @csrf
                                    <input type="hidden" name="action" value="update_appointment_status">
                                    <input type="hidden" name="appointment_id" value="{{ $aArr['id'] }}">
                                    <select name="status" onchange="this.form.submit()" style="padding:4px 8px;border-radius:6px;border:1px solid #E2E8F0;font-size:11px;font-weight:700;cursor:pointer;background:{{ $st === 'confirmed' ? '#F0FDF4' : ($st === 'completed' ? '#EFF6FF' : '#FEF2F2') }};color:{{ $st === 'confirmed' ? '#16A34A' : ($st === 'completed' ? '#2563EB' : '#EF4444') }};">
                                        <option value="confirmed" {{ $st === 'confirmed' ? 'selected' : '' }}>مؤكد (Confirmed)</option>
                                        <option value="completed" {{ $st === 'completed' ? 'selected' : '' }}>مكتمل (Completed)</option>
                                        <option value="no-show" {{ $st === 'no-show' ? 'selected' : '' }}>لم يحضر (No-Show)</option>
                                        <option value="cancelled" {{ $st === 'cancelled' ? 'selected' : '' }}>ملغي (Cancelled)</option>
                                    </select>
                                </form>
                            </td>
                            <td style="padding:12px 14px;text-align:center;">
                                <div style="display:flex;gap:6px;justify-content:center;align-items:center;">
                                    <a href="tel:{{ $aArr['client_phone'] }}" class="btn btn-sm btn-icon" style="background:#F1F5F9;border:none;border-radius:6px;text-decoration:none;padding:4px 8px;" title="اتصال">📞</a>
                                    <form method="POST" action="{{ route('enterprise.action') }}" style="margin:0;display:inline;" onsubmit="return confirm('هل أنت متأكد من حذف الموعد؟');">
                                        @csrf
                                        <input type="hidden" name="action" value="delete_appointment">
                                        <input type="hidden" name="appointment_id" value="{{ $aArr['id'] }}">
                                        <button type="submit" class="btn btn-sm btn-icon" style="background:#FEE2E2;color:#EF4444;border:none;border-radius:6px;padding:4px 8px;cursor:pointer;" title="حذف">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="padding:32px;text-align:center;color:#64748B;">
                                لا توجد مواعيد مسجلة حتى الآن. استخدم زر "+ حجز موعد جديد" أو شارك رابط الحجز العام مع عملائك.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Side: Booking Link & Quick Help Card -->
    <div style="display:flex;flex-direction:column;gap:16px;">
        <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:14px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
            <strong style="font-size:14px;color:#0F172A;display:block;margin-bottom:8px;">🔗 صفحة الحجز العامة المباشرة</strong>
            <p style="font-size:12px;color:#64748B;line-height:1.5;margin-bottom:12px;">
                يمكنك مشاركة هذا الرابط مع عملائك في حملات الواتساب أو البريد أو دمجه في زر حجز مباشر على موقعك:
            </p>
            <div style="background:#F8FAFC;border:1px solid #CBD5E1;border-radius:8px;padding:8px 12px;font-size:12px;color:#0F172A;font-family:monospace;word-break:break-all;margin-bottom:10px;">
                https://app.ontrackegy.com/book/{{ $ctx['account']['slug'] ?? 'demo' }}
            </div>
            <a href="{{ route('public.booking.show', ['account_slug' => $ctx['account']['slug'] ?? 'demo']) }}" target="_blank" class="btn btn-sm" style="width:100%;text-align:center;background:#0F172A;color:#FFFFFF;text-decoration:none;border-radius:8px;font-weight:600;padding:8px;display:block;">
                فتح صفحة الحجز في نافذة جديدة ↗
            </a>
        </div>

        <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:14px;padding:18px;font-size:12.5px;color:#166534;line-height:1.6;">
            <strong>✨ ميزة الحجز التلقائي الذكي:</strong><br>
            يقوم مساعد OnTrack الصوتي أثناء المكالمة والدردشة بالتحقق من خانات الوقت المتاحة، وتسجيل اسم العميل وهاتفه وموعده تلقائياً في هذا الجدول دون أي تدخل بشري.
        </div>
    </div>
</div>

<!-- Modal: + New Appointment (Connected to Live MySQL) -->
<div id="newAppointmentModal" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.45);backdrop-filter:blur(4px);z-index:9999;display:none;align-items:center;justify-content:center;">
    <div style="background:#FFFFFF;border-radius:16px;width:100%;max-width:480px;box-shadow:0 20px 40px rgba(0,0,0,0.15);overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid #E2E8F0;display:flex;align-items:center;justify-content:space-between;">
            <strong style="font-size:16px;color:#0F172A;">حجز موعد يدوي جديد</strong>
            <button type="button" onclick="closeNewAppointmentModal()" style="background:none;border:none;font-size:18px;color:#64748B;cursor:pointer;">✕</button>
        </div>
        <form method="POST" action="{{ route('enterprise.action') }}">
            @csrf
            <input type="hidden" name="action" value="create_appointment">
            <div style="padding:20px;display:flex;flex-direction:column;gap:14px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">اسم العميل *</label>
                    <input type="text" name="client_name" placeholder="الاسم الكامل للعميل" required style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">رقم الهاتف *</label>
                        <input type="tel" name="client_phone" placeholder="+2010xxxxxxxx" dir="ltr" required style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">البريد الإلكتروني</label>
                        <input type="email" name="client_email" placeholder="client@domain.com" style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
                    </div>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">الخدمة المطلوبة</label>
                    <input type="text" name="service" placeholder="مثال: استشارة استضافة سحابية" value="استشارة عامة" style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">التاريخ *</label>
                        <input type="date" name="date" value="{{ date('Y-m-d') }}" required style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">الوقت *</label>
                        <input type="time" name="time" value="12:00" required style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
                    </div>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">ملاحظات</label>
                    <textarea name="notes" rows="2" placeholder="ملاحظات حول الموعد..." style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;"></textarea>
                </div>
            </div>
            <div style="padding:14px 20px;border-top:1px solid #E2E8F0;display:flex;justify-content:flex-end;gap:10px;background:#F8FAFC;">
                <button type="button" class="btn btn-sm btn-ghost" onclick="closeNewAppointmentModal()">إلغاء</button>
                <button type="submit" class="btn btn-sm btn-primary" style="background:#0F172A;color:#FFF;border:none;padding:8px 18px;border-radius:8px;font-weight:600;">حفظ وتأكيد الموعد</button>
            </div>
        </form>
    </div>
</div>

<script>
function openNewAppointmentModal() {
    document.getElementById('newAppointmentModal').style.display = 'flex';
}
function closeNewAppointmentModal() {
    document.getElementById('newAppointmentModal').style.display = 'none';
}
</script>
