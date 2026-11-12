<div class="card-head" style="margin-bottom:14px">
    <div>
        <h1 style="font-size:20px;margin:0;font-weight:700">شرائح الهاتف والأجهزة المربوطة</h1>
        <p class="card-sub">إدارة شرائح SIM1 و SIM2 على هواتف Android وتبديل الإيجنت الفعال على كل شريحة لحظياً دون فك الربط</p>
    </div>
</div>

<div class="card">
    <h2 class="card-title" style="margin-bottom:10px">توليد كود ربط شريحة جديدة (6 أرقام · صالح 5 دقائق)</h2>
    @if (!empty($data['agents']))
        <form method="POST" action="/console/action" class="form-grid" style="align-items:end">
            @csrf
            <input type="hidden" name="action" value="create_device_pairing">
            <div class="field" style="margin:0">
                <span class="field-label">الإيجنت المسؤول</span>
                <select name="agent_id">
                    @foreach ($data['agents'] as $ag)
                        <option value="{{ $ag['id'] }}">{{ $ag['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="margin:0">
                <span class="field-label">منفذ الشريحة</span>
                <select name="sim_slot">
                    <option value="0">SIM 1 (الشريحة الأولى)</option>
                    <option value="1">SIM 2 (الشريحة الثانية)</option>
                </select>
            </div>
            <div><button type="submit" class="btn btn-primary">توليد كود الربط</button></div>
        </form>
    @else
        <p class="card-sub">أنشئ إيجنت أولاً من استوديو الإيجنت لتوليد كود ربط.</p>
    @endif
</div>

<div class="grid-2">
    @forelse ($data['devices'] ?? [] as $dev)
        <div class="card">
            <div class="card-head">
                <div>
                    <div style="display:flex;align-items:center;gap:8px">
                        <span class="dot {{ !empty($dev['online']) ? 'online' : 'offline' }}"></span>
                        <h3 class="card-title">{{ $dev['device_name'] }}</h3>
                        <span class="pill pill-crimson">SIM {{ ((int) $dev['sim_slot']) + 1 }}</span>
                    </div>
                    <p class="card-sub mono" dir="ltr">Rev #{{ $dev['agent_revision'] ?? 1 }} · آخر اتصال: {{ $dev['last_seen_at'] ?: '—' }}</p>
                </div>
                <form method="POST" action="/console/action" style="margin:0">
                    @csrf
                    <input type="hidden" name="action" value="revoke_device">
                    <input type="hidden" name="device_id" value="{{ $dev['id'] }}">
                    <button type="submit" class="btn btn-sm btn-danger">فك الربط</button>
                </form>
            </div>

            <div class="field" style="margin-top:8px">
                <span class="field-label">الإيجنت المعين على هذه الشريحة (Hot-Swap فوري)</span>
                <select onchange="hotSwapDeviceAgent({{ $dev['id'] }}, this.value, this)">
                    @foreach ($data['agents'] as $ag)
                        <option value="{{ $ag['id'] }}" {{ (int) $dev['agent_id'] === (int) $ag['id'] ? 'selected' : '' }}>
                            {{ $ag['name'] }} ({{ $ag['status'] === 'published' ? 'منشور' : 'مسودة' }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    @empty
        <div class="card" style="grid-column:1 / -1">
            <p class="card-sub" style="text-align:center;padding:18px">لا توجد شرائح مربوطة حالياً. استخدم نموذج توليد الكود بالأعلى وأدخله في تطبيق IO Voice على الهاتف.</p>
        </div>
    @endforelse
</div>

@if (!empty($data['pending_pairings']))
    <div class="card">
        <h2 class="card-title" style="margin-bottom:10px">أكواد الربط المعلقة</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>الشريحة</th><th>الإيجنت</th><th>ينتهي في UTC</th><th>إلغاء</th></tr></thead>
                <tbody>
                    @foreach ($data['pending_pairings'] as $pp)
                        <tr>
                            <td><span class="pill">{{ $pp['status'] === 'pending_sim1' ? 'SIM 2' : 'SIM 1' }}</span></td>
                            <td>#{{ $pp['agent_id'] }}</td>
                            <td class="mono">{{ $pp['expires_at'] }}</td>
                            <td>
                                <form method="POST" action="/console/action">
                                    @csrf
                                    <input type="hidden" name="action" value="cancel_device_pairing">
                                    <input type="hidden" name="pairing_id" value="{{ $pp['id'] }}">
                                    <button type="submit" class="btn btn-sm btn-danger">إلغاء</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
