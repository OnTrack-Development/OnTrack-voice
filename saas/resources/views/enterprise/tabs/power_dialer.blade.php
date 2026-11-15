@php
    $devices = $data['devices'] ?? [];
    $campaigns = $data['campaigns'] ?? [];
@endphp

<div class="card-head" style="margin-bottom:16px;">
    <div>
        <h1 style="font-size:22px;margin:0 0 4px 0;font-weight:800;color:#0F172A;">المتصل الآلي السريع (Power Dialer & Multi-Line Engine)</h1>
        <p style="margin:0;font-size:13px;color:#64748B;">محرك اتصال خارجي متزامن عبر شرائح Android SIM المتعددة مع تقنية كشف الرد الآلي (AMD) وموسيقى الانتظار</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <form method="POST" action="{{ route('enterprise.action') }}" style="margin:0;">
            @csrf
            <input type="hidden" name="action" value="launch_power_dialer">
            <input type="hidden" name="concurrent_lines" value="3">
            <input type="hidden" name="pacing_mode" value="predictive">
            <input type="hidden" name="sim_slot" value="1">
            <button type="submit" class="btn btn-sm" style="background:#10B981;color:#FFFFFF;border:none;border-radius:8px;font-weight:700;display:flex;align-items:center;gap:6px;padding:8px 18px;box-shadow:0 4px 12px rgba(16,185,129,0.3);cursor:pointer;">
                <span>▶</span>
                <span>بدء الاتصال (Start Dialer)</span>
            </button>
        </form>
    </div>
</div>

<!-- Dialer Configuration & Live Channels Grid -->
<div class="grid-2" style="grid-template-columns: 1fr 1fr;gap:18px;margin-bottom:20px;">
    
    <!-- Dialer Settings Card -->
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:14px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,0.04);display:flex;flex-direction:column;gap:16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #F1F5F9;padding-bottom:12px;">
            <strong style="font-size:16px;color:#0F172A;">⚙️ إعدادات وتيرة الاتصال (Pacing Mode)</strong>
            <span class="pill pill-green" style="font-size:11px;">محرك AMD: نشط 🛡️</span>
        </div>

        <div style="display:flex;flex-direction:column;gap:12px;">
            <div>
                <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;">نمط وتيرة الاتصال (Pacing Strategy)</label>
                <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:8px;">
                    <button type="button" class="btn btn-sm" style="background:#0F172A;color:#FFF;border-radius:8px;font-weight:600;font-size:12px;">Progressive (1.2x)</button>
                    <button type="button" class="btn btn-sm btn-ghost" style="border:1px solid #E2E8F0;border-radius:8px;font-size:12px;color:#475569;">Predictive (1.5x)</button>
                    <button type="button" class="btn btn-sm btn-ghost" style="border:1px solid #E2E8F0;border-radius:8px;font-size:12px;color:#475569;">Preview (1.0x)</button>
                </div>
            </div>

            <div>
                <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;">عدد الخطوط المتزامنة (Concurrent Lines)</label>
                <div style="display:flex;align-items:center;gap:12px;">
                    <input type="range" min="1" max="4" value="2" style="flex:1;" id="rangeLines" oninput="document.getElementById('lblLines').innerText = this.value + ' خطوط متزامنة'">
                    <span id="lblLines" style="font-weight:700;font-size:13px;color:#0F172A;width:120px;text-align:left;">2 خطوط متزامنة</span>
                </div>
            </div>

            <div>
                <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;">موسيقى الانتظار حتى رد العميل (Hold Music)</label>
                <select style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
                    <option selected>Corporate Gentle Ringing (نغمة مؤسسية هادئة)</option>
                    <option>Upbeat Ambient Jazz</option>
                    <option>صوت رنين الهاتف الطبيعي (Standard Ringing)</option>
                </select>
            </div>

            <div>
                <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;">الحملة المستهدفة</label>
                <select style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
                    <option>حملة متابعة عملاء استضافة VPS (140 جهة اتصال)</option>
                    <option>عملاء ووردبريس غير المجددين (85 جهة اتصال)</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Active SIM Lines Real-Time Monitor -->
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:14px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,0.04);display:flex;flex-direction:column;gap:14px;">
        <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #F1F5F9;padding-bottom:12px;">
            <strong style="font-size:16px;color:#0F172A;">📡 مراقب القنوات والشرائح الحية (Active Lines)</strong>
            <span style="font-size:12px;color:#10B981;font-weight:700;">{{ count($devices) > 0 ? count($devices) : '2' }} شرائح جاهزة</span>
        </div>

        <div style="display:flex;flex-direction:column;gap:12px;">
            <!-- Line 1 -->
            <div style="border:1px solid #E2E8F0;border-radius:10px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;background:#F8FAFC;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:34px;height:34px;border-radius:8px;background:#10B981;color:#FFF;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;">
                        SIM 1
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:13px;color:#0F172A;">Xiaomi Note 12 Pro · Vodafone EG</div>
                        <div style="font-size:11.5px;color:#64748B;">جاري الاتصال بـ: <span dir="ltr" class="mono">+201099887766</span></div>
                    </div>
                </div>
                <span class="pill pill-green" style="font-size:11px;">Ringing (يرن) 📞</span>
            </div>

            <!-- Line 2 -->
            <div style="border:1px solid #E2E8F0;border-radius:10px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;background:#F8FAFC;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:34px;height:34px;border-radius:8px;background:#3B82F6;color:#FFF;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;">
                        SIM 2
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:13px;color:#0F172A;">Xiaomi Note 12 Pro · Orange EG</div>
                        <div style="font-size:11.5px;color:#64748B;">متصل مع العميل: سارة إبراهيم (0:45)</div>
                    </div>
                </div>
                <span style="background:#FAF5FF;color:#7C3AED;border:1px solid #E9D5FF;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;">In AI Conversation ✨</span>
            </div>

            <!-- Line 3 (Idle) -->
            <div style="border:1px solid #E2E8F0;border-radius:10px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;background:#FFFFFF;opacity:0.7;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:34px;height:34px;border-radius:8px;background:#64748B;color:#FFF;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;">
                        SIM 3
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:13px;color:#0F172A;">Spare Backup SIM</div>
                        <div style="font-size:11.5px;color:#94A3B8;">في وضع الاستعداد (Idle)</div>
                    </div>
                </div>
                <span class="pill" style="font-size:11px;background:#F1F5F9;color:#64748B;">Standby</span>
            </div>
        </div>

        <div style="background:#F0FDF4;border:1px solid #DCFCE7;border-radius:10px;padding:12px;font-size:12px;color:#166534;line-height:1.5;">
            ⚡ <strong>كشف الرد الآلي (AMD):</strong> يتجاوز النظام البريد الصوتي ورسائل «الهاتف مغلق» فوراً دون إضاعة دقيقة واحدة، ويوجه المكالمة للإيجنت Zoie فور تحدث العميل الحقيقي!
        </div>
    </div>
</div>

<!-- Live Dialing Queue Table -->
<div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
    <div style="padding:16px 20px;border-bottom:1px solid #E2E8F0;display:flex;justify-content:space-between;align-items:center;">
        <strong style="font-size:15px;color:#0F172A;">قائمة جهات الاتصال في طابور المتصل السريع</strong>
        <span style="font-size:12px;color:#64748B;">تم الاتصال بـ 42 من 140 اليوم</span>
    </div>
    <table style="width:100%;border-collapse:collapse;text-align:right;font-size:13px;">
        <thead>
            <tr style="background:#F8FAFC;border-bottom:1px solid #E2E8F0;color:#64748B;font-size:12px;">
                <th style="padding:12px 18px;">الاسم</th>
                <th style="padding:12px 16px;">الهاتف</th>
                <th style="padding:12px 16px;">المحاولات</th>
                <th style="padding:12px 16px;">حالة الاتصال الأخيرة</th>
                <th style="padding:12px 16px;">النتيجة المسجلة</th>
                <th style="padding:12px 16px;">الإيجنت</th>
            </tr>
        </thead>
        <tbody>
            <tr style="border-bottom:1px solid #F1F5F9;">
                <td style="padding:14px 18px;font-weight:700;color:#0F172A;">أحمد عبد العزيز</td>
                <td style="padding:14px 16px;font-family:monospace;" dir="ltr">+201012345678</td>
                <td style="padding:14px 16px;">1 / 3</td>
                <td style="padding:14px 16px;"><span class="pill pill-green" style="font-size:11px;">تم الرد (Answered)</span></td>
                <td style="padding:14px 16px;color:#10B981;font-weight:600;">حجز موعد مبيعات ✅</td>
                <td style="padding:14px 16px;color:#8B5CF6;font-weight:600;">Zoie</td>
            </tr>
            <tr style="border-bottom:1px solid #F1F5F9;">
                <td style="padding:14px 18px;font-weight:700;color:#0F172A;">خالد مصطفى</td>
                <td style="padding:14px 16px;font-family:monospace;" dir="ltr">+201122334455</td>
                <td style="padding:14px 16px;">2 / 3</td>
                <td style="padding:14px 16px;"><span class="pill" style="font-size:11px;background:#FEF2F2;color:#DC2626;">لم يرد (No Answer)</span></td>
                <td style="padding:14px 16px;color:#64748B;">إعادة المحاولة مجدولة بعد ساعتين</td>
                <td style="padding:14px 16px;color:#8B5CF6;font-weight:600;">Zoie</td>
            </tr>
        </tbody>
    </table>
</div>