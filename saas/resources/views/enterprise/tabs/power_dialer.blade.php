@php
    $devices = $data['devices'] ?? [];
    $campaigns = $data['campaigns'] ?? [];
    $queue = $data['queue'] ?? [];
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
            <input type="hidden" name="concurrent_lines" value="{{ max(1, count($devices)) }}">
            <input type="hidden" name="pacing_mode" value="progressive">
            <input type="hidden" name="sim_slot" value="1">
            <button type="submit" class="btn btn-sm" style="background:#10B981;color:#FFFFFF;border:none;border-radius:8px;font-weight:700;display:flex;align-items:center;gap:6px;padding:8px 18px;box-shadow:0 4px 12px rgba(16,185,129,0.3);cursor:pointer;" {{ empty($devices) ? 'disabled title="يتطلب ربط جهاز أندرويد أولاً"' : '' }}>
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
                    <button type="button" class="btn btn-sm" style="background:#0F172A;color:#FFF;border-radius:8px;font-weight:600;font-size:12px;">Progressive (1.0x)</button>
                    <button type="button" class="btn btn-sm btn-ghost" style="border:1px solid #E2E8F0;border-radius:8px;font-size:12px;color:#475569;">Predictive (1.5x)</button>
                    <button type="button" class="btn btn-sm btn-ghost" style="border:1px solid #E2E8F0;border-radius:8px;font-size:12px;color:#475569;">Preview (0.8x)</button>
                </div>
            </div>

            <div>
                <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;">عدد الخطوط المتزامنة (Concurrent Lines)</label>
                <div style="display:flex;align-items:center;gap:12px;">
                    <input type="range" min="1" max="4" value="{{ max(1, count($devices)) }}" style="flex:1;" id="rangeLines" oninput="document.getElementById('lblLines').innerText = this.value + ' خطوط متزامنة'">
                    <span id="lblLines" style="font-weight:700;font-size:13px;color:#0F172A;width:120px;text-align:left;">{{ max(1, count($devices)) }} خطوط متزامنة</span>
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
                    @forelse ($campaigns as $camp)
                        <option value="{{ $camp['id'] ?? '' }}">{{ $camp['name'] ?? 'حملة اتصال' }}</option>
                    @empty
                        <option value="">لا توجد حملات اتصال منشأة بعد</option>
                    @endforelse
                </select>
            </div>
        </div>
    </div>

    <!-- Active SIM Lines Real-Time Monitor -->
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:14px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,0.04);display:flex;flex-direction:column;gap:14px;">
        <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #F1F5F9;padding-bottom:12px;">
            <strong style="font-size:16px;color:#0F172A;">📡 مراقب القنوات والشرائح الحية (Active Lines)</strong>
            <span style="font-size:12px;color:{{ count($devices) > 0 ? '#10B981' : '#64748B' }};font-weight:700;">{{ count($devices) }} شرائح جاهزة</span>
        </div>

        <div style="display:flex;flex-direction:column;gap:12px;">
            @forelse ($devices as $idx => $dev)
                <div style="border:1px solid #E2E8F0;border-radius:10px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;background:#F8FAFC;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:34px;height:34px;border-radius:8px;background:#10B981;color:#FFF;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;">
                            SIM {{ $idx + 1 }}
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:13px;color:#0F172A;">{{ $dev['device_label'] ?? $dev['model'] ?? 'Android Device' }}</div>
                            <div style="font-size:11.5px;color:#64748B;">الحالة: <span class="mono">{{ $dev['status'] ?? 'ready' }}</span></div>
                        </div>
                    </div>
                    <span class="pill pill-green" style="font-size:11px;">متصل بالخدمة ✅</span>
                </div>
            @empty
                <div style="padding:32px 16px;text-align:center;color:#64748B;background:#F8FAFC;border-radius:10px;border:1px dashed #CBD5E1;">
                    <div style="font-size:32px;margin-bottom:8px;">📡</div>
                    <strong style="color:#0F172A;font-size:14px;display:block;margin-bottom:4px;">لا توجد شرائح متصلة حالياً</strong>
                    <p style="font-size:12px;color:#94A3B8;margin:0 0 12px;">قم بربط جهاز أندرويد عبر تطبيق OnTrack Voice لتفعيل المتصل الآلي.</p>
                    <a href="/?tab=devices" class="btn btn-sm btn-primary" style="text-decoration:none;">+ ربط جهاز الآن</a>
                </div>
            @endforelse
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
        <span style="font-size:12px;color:#64748B;">إجمالي في الطابور: {{ count($queue) }}</span>
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
            @forelse ($queue as $q)
                <tr style="border-bottom:1px solid #F1F5F9;">
                    <td style="padding:14px 18px;font-weight:700;color:#0F172A;">{{ $q['name'] ?? '' }}</td>
                    <td style="padding:14px 16px;font-family:monospace;" dir="ltr">{{ $q['phone'] ?? '' }}</td>
                    <td style="padding:14px 16px;">{{ $q['attempts'] ?? 0 }}</td>
                    <td style="padding:14px 16px;"><span class="pill pill-green" style="font-size:11px;">{{ $q['status'] ?? 'pending' }}</span></td>
                    <td style="padding:14px 16px;color:#10B981;font-weight:600;">{{ $q['outcome'] ?? 'قيد الانتظار' }}</td>
                    <td style="padding:14px 16px;color:#8B5CF6;font-weight:600;">{{ $q['agent_name'] ?? 'AI' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="padding:32px;text-align:center;color:#64748B;">
                        طابور المتصل الآلي فارغ حالياً. يمكنك تحديد جهات اتصال وبدء الحملة من قسم الحملات.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
