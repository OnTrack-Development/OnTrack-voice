@php
    $relayEntries = $data['relay_entries'] ?? [];
    $googleKeys = collect($relayEntries)->whereIn('driver', ['google_live', 'gemini_live'])->values();
    $fishKeys = collect($relayEntries)->where('driver', 'fish_audio')->values();
    $nvidiaKeys = collect($relayEntries)->where('driver', 'nvidia')->values();
    $customKeys = collect($relayEntries)->whereNotIn('driver', ['google_live', 'gemini_live', 'fish_audio', 'nvidia'])->values();

    $firstGoogle = $googleKeys->first();
    $googleActiveModel = is_array($firstGoogle) ? ($firstGoogle['model'] ?? '') : (is_object($firstGoogle) ? ($firstGoogle->model ?? '') : '');
    if (empty($googleActiveModel)) $googleActiveModel = 'gemini-3.8-live';

    $firstFish = $fishKeys->first();
    $fishActiveModel = is_array($firstFish) ? ($firstFish['model'] ?? '') : (is_object($firstFish) ? ($firstFish->model ?? '') : '');
    if (empty($fishActiveModel)) $fishActiveModel = 'حسب اختيارك / مخصص';

    $firstNvidia = $nvidiaKeys->first();
    $nvidiaActiveModel = is_array($firstNvidia) ? ($firstNvidia['model'] ?? '') : (is_object($firstNvidia) ? ($firstNvidia->model ?? '') : '');
    if (empty($nvidiaActiveModel)) $nvidiaActiveModel = 'parakeet-ctc-1.1b';
@endphp

<style>
.ontrack-keys-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
@media (max-width: 768px) {
    .ontrack-keys-grid {
        grid-template-columns: 1fr !important;
    }
    .ontrack-key-table {
        min-width: 580px;
    }
}
.ontrack-provider-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 18px 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    transition: transform 0.15s, border-color 0.15s;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.ontrack-provider-card:hover {
    border-color: #CBD5E1;
    transform: translateY(-2px);
}
.ontrack-tab-filter-btn {
    padding: 7px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    border: 1px solid #E2E8F0;
    background: #FFFFFF;
    color: #475569;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s;
    white-space: nowrap;
}
.ontrack-tab-filter-btn.active, .ontrack-tab-filter-btn:hover {
    background: #0F172A;
    color: #FFFFFF;
    border-color: #0F172A;
}

.ontrack-key-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    overflow: hidden;
}
.ontrack-key-table th {
    background: #F8FAFC;
    padding: 12px 16px;
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    text-align: right;
    border-bottom: 1px solid #E2E8F0;
}
.ontrack-key-table td {
    padding: 14px 16px;
    font-size: 13px;
    color: #0F172A;
    border-bottom: 1px solid #F1F5F9;
    vertical-align: middle;
}
.ontrack-key-table tr:last-child td {
    border-bottom: none;
}
.ontrack-key-table tr:hover td {
    background: #FAFAFA;
}

/* Modal styling */
.ontrack-key-modal-backdrop {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
}
.ontrack-key-modal-box {
    background: #FFFFFF;
    border-radius: 16px;
    width: 95%;
    max-width: 580px;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
</style>

<div style="display:flex;flex-direction:column;gap:20px;">
    <!-- 1. Header with Actions -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;background:#FFFFFF;border:1px solid #E2E8F0;border-radius:14px;padding:20px 24px;">
        <div>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                <div style="width:36px;height:36px;border-radius:10px;background:#0F172A;color:#FFFFFF;display:grid;place-items:center;font-size:18px;">
                    🔑
                </div>
                <h2 style="font-size:20px;font-weight:800;color:#0F172A;margin:0;">
                    إدارة مفاتيح ومزودي الذكاء الاصطناعي (AI Providers & API Keys)
                </h2>
            </div>
            <p style="font-size:13px;color:#64748B;margin:0;line-height:1.5;">
                مركز موحد وآمن لتشفير وإدارة مفاتيح مزودي Google Gemini Live و Fish Audio و NVIDIA والمزودين المخصصين، مع دعم الدوران التلقائي للأعطال (Failover Rotation) واختبار الاتصال اللحظي.
            </p>
        </div>

        <div style="display:flex;align-items:center;gap:10px;">
            <button type="button" onclick="testAllKeys()" class="btn btn-sm" style="background:#FFFFFF;color:#0F172A;border:1px solid #E2E8F0;border-radius:8px;font-weight:600;padding:8px 14px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                <span>⚡</span>
                <span>فحص الاتصال بالكل</span>
            </button>
            <button type="button" onclick="openKeyModal('add')" class="btn btn-sm" style="background:#0F172A;color:#FFFFFF;border:none;border-radius:8px;font-weight:700;padding:8px 16px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;box-shadow:0 2px 4px rgba(15,23,42,0.15);">
                <span>+</span>
                <span>إضافة مفتاح جديد</span>
            </button>
        </div>
    </div>

    <!-- 2. Provider Summary KPI Cards (Google, Fish Audio, NVIDIA, Custom) -->
    <div class="ontrack-keys-grid">
        <!-- Google Gemini Live Card -->
        <div class="ontrack-provider-card" style="border-right:4px solid #3B82F6;">
            <div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <strong style="font-size:15px;color:#0F172A;display:flex;align-items:center;gap:6px;">
                        <span>✨</span> Google Gemini Live
                    </strong>
                    <span style="background:#EFF6FF;color:#2563EB;font-size:11px;font-weight:700;padding:2px 8px;border-radius:6px;">
                        {{ $googleKeys->where('enabled', 1)->count() }} مفاتيح نشطة
                    </span>
                </div>
                <p style="font-size:12px;color:#64748B;margin:0 0 12px;line-height:1.4;">
                    نفق الصوت التفاعلي ثنائي الاتجاه عبر WebSockets (24kHz HD PCM Audio).
                </p>
            </div>
            <div style="font-size:11.5px;color:#3B82F6;font-weight:600;">
                الموديل: {{ $googleActiveModel }}
            </div>
        </div>

        <!-- Fish Audio Card -->
        <div class="ontrack-provider-card" style="border-right:4px solid #10B981;">
            <div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <strong style="font-size:15px;color:#0F172A;display:flex;align-items:center;gap:6px;">
                        <span>🎙️</span> Fish Audio
                    </strong>
                    <span style="background:#F0FDF4;color:#16A34A;font-size:11px;font-weight:700;padding:2px 8px;border-radius:6px;">
                        {{ $fishKeys->where('enabled', 1)->count() }} مفاتيح نشطة
                    </span>
                </div>
                <p style="font-size:12px;color:#64748B;margin:0 0 12px;line-height:1.4;">
                    أصوات عربية فائقة الفخامة واستنساخ صوتي سريع (Zero-Shot Voice Cloning).
                </p>
            </div>
            <div style="font-size:11.5px;color:#10B981;font-weight:600;">
                الموديل: {{ $fishActiveModel }}
            </div>
        </div>

        <!-- NVIDIA NIM / Riva Card -->
        <div class="ontrack-provider-card" style="border-right:4px solid #76B900;">
            <div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <strong style="font-size:15px;color:#0F172A;display:flex;align-items:center;gap:6px;">
                        <span>⚡</span> NVIDIA Cloud
                    </strong>
                    <span style="background:#F7FEE7;color:#65A30D;font-size:11px;font-weight:700;padding:2px 8px;border-radius:6px;">
                        {{ $nvidiaKeys->where('enabled', 1)->count() }} مفاتيح نشطة
                    </span>
                </div>
                <p style="font-size:12px;color:#64748B;margin:0 0 12px;line-height:1.4;">
                    التعرف الفوري على الكلام ومعالجة الحوار فائقة السرعة (Riva ASR & LLM).
                </p>
            </div>
            <div style="font-size:11.5px;color:#65A30D;font-weight:600;">
                الموديل: {{ $nvidiaActiveModel }}
            </div>
        </div>

        <!-- Custom Providers Card -->
        <div class="ontrack-provider-card" style="border-right:4px solid #8B5CF6;">
            <div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <strong style="font-size:15px;color:#0F172A;display:flex;align-items:center;gap:6px;">
                        <span>🌐</span> مزود مخصص (Custom)
                    </strong>
                    <span style="background:#F5F3FF;color:#7C3AED;font-size:11px;font-weight:700;padding:2px 8px;border-radius:6px;">
                        {{ $customKeys->where('enabled', 1)->count() }} مفاتيح نشطة
                    </span>
                </div>
                <p style="font-size:12px;color:#64748B;margin:0 0 12px;line-height:1.4;">
                    دعم أي نقطة نهاية خارجية مخصصة (OpenAI, DeepSeek, Groq, vLLM).
                </p>
            </div>
            <div style="font-size:11.5px;color:#7C3AED;font-weight:600;">
                نقاط نهاية مخصصة (BYOK)
            </div>
        </div>
    </div>

    <!-- 3. Provider Filter Pills & Search -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div style="display:flex;align-items:center;gap:8px;overflow-x:auto;">
            <button type="button" class="ontrack-tab-filter-btn active" onclick="filterProviderTable('all', this)">الكل ({{ count($relayEntries) }})</button>
            <button type="button" class="ontrack-tab-filter-btn" onclick="filterProviderTable('google', this)">Google Gemini ({{ count($googleKeys) }})</button>
            <button type="button" class="ontrack-tab-filter-btn" onclick="filterProviderTable('fish', this)">Fish Audio ({{ count($fishKeys) }})</button>
            <button type="button" class="ontrack-tab-filter-btn" onclick="filterProviderTable('nvidia', this)">NVIDIA ({{ count($nvidiaKeys) }})</button>
            <button type="button" class="ontrack-tab-filter-btn" onclick="filterProviderTable('custom', this)">مزود مخصص ({{ count($customKeys) }})</button>
        </div>

        <div style="position:relative;">
            <input type="text" placeholder="البحث في المفاتيح أو الموديلات..." onkeyup="searchProviderKeys(this.value)" style="padding:7px 12px 7px 30px;font-size:12.5px;border:1px solid #CBD5E1;border-radius:8px;outline:none;background:#FFF;width:240px;">
            <span style="position:absolute;left:10px;top:8px;color:#94A3B8;font-size:13px;">🔍</span>
        </div>
    </div>

    <!-- 4. Main Keys Management Table -->
    <div style="overflow-x:auto;">
        <table class="ontrack-key-table" id="keysTable">
            <thead>
                <tr>
                    <th style="width:200px;">المزود والهوية</th>
                    <th style="width:240px;">الموديل ونقطة النهاية</th>
                    <th style="width:190px;">قيمة المفتاح (Secret Key)</th>
                    <th style="width:110px;text-align:center;">الأولوية (Failover)</th>
                    <th style="width:130px;text-align:center;">الحالة والاتصال</th>
                    <th style="width:180px;text-align:center;">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($relayEntries as $entry)
                    @php
                        $eId = $entry['id'];
                        $driver = $entry['driver'] ?? 'google_live';
                        $isGoogle = in_array($driver, ['google_live', 'gemini_live']);
                        $isFish = ($driver === 'fish_audio');
                        $isNvidia = ($driver === 'nvidia');
                        $isCustom = (!$isGoogle && !$isFish && !$isNvidia);

                        $providerCat = 'custom';
                        if ($isGoogle) $providerCat = 'google';
                        elseif ($isFish) $providerCat = 'fish';
                        elseif ($isNvidia) $providerCat = 'nvidia';

                        $isEnabled = ((int) ($entry['enabled'] ?? 1) === 1);
                        $status = $entry['last_status'] ?? 'ready';
                    @endphp
                    <tr class="key-row" data-provider="{{ $providerCat }}" data-label="{{ strtolower($entry['label'] ?? '') }}" data-model="{{ strtolower($entry['model'] ?? '') }}">
                        <!-- Provider & Identity -->
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:34px;height:34px;border-radius:8px;display:grid;place-items:center;font-size:16px;flex-shrink:0;background:{{ $isGoogle ? '#EFF6FF' : ($isFish ? '#F0FDF4' : ($isNvidia ? '#F7FEE7' : '#F5F3FF')) }};">
                                    {{ $isGoogle ? '✨' : ($isFish ? '🎙️' : ($isNvidia ? '⚡' : '🌐')) }}
                                </div>
                                <div style="min-width:0;">
                                    <strong style="font-size:13.5px;color:#0F172A;display:block;">{{ $entry['label'] }}</strong>
                                    <span style="font-size:11px;color:#64748B;">
                                        {{ $isGoogle ? 'Google Gemini Live' : ($isFish ? 'Fish Audio TTS' : ($isNvidia ? 'NVIDIA NIM' : 'Custom Endpoint')) }}
                                    </span>
                                </div>
                            </div>
                        </td>

                        <!-- Model & Endpoint -->
                        <td>
                            <div style="font-family:monospace;font-size:12px;color:#0F172A;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:230px;" title="{{ $entry['model'] }}">
                                {{ $entry['model'] ?: $googleActiveModel }}
                            </div>
                            @if (!empty($entry['endpoint']))
                                <div style="font-size:11px;color:#64748B;direction:ltr;text-align:right;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:230px;" title="{{ $entry['endpoint'] }}">
                                    {{ $entry['endpoint'] }}
                                </div>
                            @else
                                <div style="font-size:11px;color:#94A3B8;">الرابط السحابي الرسمي الافتراضي</div>
                            @endif
                        </td>

                        <!-- Masked Secret with Reveal & Copy -->
                        <td>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <span style="font-family:monospace;font-size:12px;background:#F1F5F9;padding:3px 8px;border-radius:6px;color:#334155;border:1px solid #E2E8F0;" id="secretMask-{{ $eId }}">
                                    {{ $entry['secret_masked'] ?? '••••••••' }}
                                </span>
                                <button type="button" onclick="revealKeySecret({{ $eId }})" class="btn btn-sm btn-icon" style="background:#FFF;border:1px solid #E2E8F0;border-radius:6px;padding:3px 7px;cursor:pointer;color:#64748B;" title="إظهار المفتاح">
                                    👁️
                                </button>
                            </div>
                        </td>

                        <!-- Failover Priority -->
                        <td style="text-align:center;">
                            <span style="background:{{ (int)($entry['priority'] ?? 10) === 1 ? '#FEF3C7' : '#F1F5F9' }};color:{{ (int)($entry['priority'] ?? 10) === 1 ? '#D97706' : '#475569' }};padding:3px 9px;border-radius:12px;font-size:11.5px;font-weight:700;">
                                #{{ $entry['priority'] ?? 10 }} {{ (int)($entry['priority'] ?? 10) === 1 ? '(الأساسي)' : '(احتياطي)' }}
                            </span>
                        </td>

                        <!-- Status & Health -->
                        <td style="text-align:center;">
                            <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
                                <div style="display:inline-flex;align-items:center;gap:5px;">
                                    <span style="width:8px;height:8px;border-radius:50%;background:{{ $isEnabled ? '#10B981' : '#94A3B8' }};"></span>
                                    <span style="font-size:12px;font-weight:600;color:{{ $isEnabled ? '#0F172A' : '#94A3B8' }};">
                                        {{ $isEnabled ? 'مفعل' : 'معطل' }}
                                    </span>
                                </div>
                                <span id="pingBadge-{{ $eId }}" style="font-size:10.5px;color:#64748B;">
                                    {{ $status === 'ok' ? '✓ تم الفحص' : ($status === 'error' ? '⚠️ خطأ' : 'جاهز') }}
                                </span>
                            </div>
                        </td>

                        <!-- Actions -->
                        <td style="text-align:center;">
                            <div style="display:inline-flex;align-items:center;gap:6px;">
                                <!-- Ping Test -->
                                <button type="button" onclick="testSingleKey({{ $eId }}, this)" class="btn btn-sm" style="background:#FFF;border:1px solid #E2E8F0;border-radius:6px;padding:4px 8px;font-size:11px;font-weight:600;cursor:pointer;color:#0F172A;" title="اختبار الاتصال">
                                    ⚡ فحص
                                </button>

                                <!-- Edit -->
                                <button type="button" onclick='openKeyModal("edit", @json($entry))' class="btn btn-sm" style="background:#FFF;border:1px solid #E2E8F0;border-radius:6px;padding:4px 8px;font-size:11px;font-weight:600;cursor:pointer;color:#0F172A;" title="تعديل">
                                    ✏️ تعديل
                                </button>

                                <!-- Toggle Enable/Disable -->
                                <form method="POST" action="{{ route('enterprise.action') }}" style="display:inline;margin:0;">
                                    @csrf
                                    <input type="hidden" name="action" value="toggle_relay_entry">
                                    <input type="hidden" name="relay_id" value="{{ $eId }}">
                                    <button type="submit" class="btn btn-sm btn-icon" style="background:#FFF;border:1px solid #E2E8F0;border-radius:6px;padding:4px 7px;cursor:pointer;color:{{ $isEnabled ? '#DC2626' : '#16A34A' }};" title="{{ $isEnabled ? 'تعطيل المفتاح' : 'تفعيل المفتاح' }}">
                                        {{ $isEnabled ? '⏸' : '▶' }}
                                    </button>
                                </form>

                                <!-- Delete -->
                                <form method="POST" action="{{ route('enterprise.action') }}" style="display:inline;margin:0;" onsubmit="return confirm('هل أنت متأكد من حذف هذا المفتاح نهائياً؟')">
                                    @csrf
                                    <input type="hidden" name="action" value="delete_relay_entry">
                                    <input type="hidden" name="relay_id" value="{{ $eId }}">
                                    <button type="submit" class="btn btn-sm btn-icon" style="background:#FFF;border:1px solid #E2E8F0;border-radius:6px;padding:4px 7px;cursor:pointer;color:#DC2626;" title="حذف المفتاح">
                                        🗑️
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;padding:50px 20px;color:#64748B;">
                            <div style="font-size:40px;margin-bottom:10px;">🔑</div>
                            <strong style="font-size:15px;color:#0F172A;display:block;margin-bottom:4px;">لا توجد مفاتيح API مسجلة بعد</strong>
                            <p style="font-size:12.5px;color:#94A3B8;margin:0 0 16px;">أضف مفاتيح Google Gemini Live أو Fish Audio أو NVIDIA لتشغيل الإيجنت ونظام الصوت.</p>
                            <button type="button" onclick="openKeyModal('add')" class="btn btn-sm" style="background:#0F172A;color:#FFF;border:none;border-radius:8px;padding:8px 18px;font-weight:700;cursor:pointer;">
                                + إضافة مفتاحك الأول الآن
                            </button>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- 5. ADD / EDIT KEY MODAL -->
<div id="providerKeyModal" class="ontrack-key-modal-backdrop" onclick="if(event.target===this)closeKeyModal()">
    <div class="ontrack-key-modal-box">
        <div style="padding:16px 20px;border-bottom:1px solid #F1F5F9;display:flex;align-items:center;justify-content:space-between;background:#FFFFFF;">
            <strong id="modalTitle" style="font-size:16px;color:#0F172A;">إضافة مفتاح مزود جديد</strong>
            <button type="button" onclick="closeKeyModal()" style="background:none;border:none;font-size:18px;color:#64748B;cursor:pointer;">✕</button>
        </div>

        <form method="POST" action="{{ route('enterprise.action') }}" id="keyActionForm" style="padding:20px;display:flex;flex-direction:column;gap:14px;background:#FFFFFF;margin:0;">
            @csrf
            <input type="hidden" name="action" id="formActionField" value="add_relay_entry">
            <input type="hidden" name="relay_id" id="formRelayIdField" value="0">
            <input type="hidden" name="scope" value="voice">

            <!-- Provider Selector -->
            <div>
                <label style="font-size:12.5px;font-weight:700;color:#0F172A;display:block;margin-bottom:6px;">
                    مزود الخدمة (Provider)
                </label>
                <select name="driver" id="inputDriver" onchange="onDriverChange(this.value)" required style="width:100%;padding:9px 12px;font-size:13px;border:1px solid #CBD5E1;border-radius:8px;background:#FFF;color:#0F172A;outline:none;">
                    <option value="google_live">Google Gemini Live (الصوت التفاعلي ثنائي الاتجاه)</option>
                    <option value="fish_audio">Fish Audio (الأصوات فائقة الفخامة TTS & Voice Cloning)</option>
                    <option value="nvidia">NVIDIA Cloud (التعرف على الكلام والمعالجة السريعة ASR & NIM)</option>
                    <option value="custom">مزود مخصص (OpenAI, DeepSeek, Groq, Custom API)</option>
                </select>
            </div>

            <!-- Key Label -->
            <div>
                <label style="font-size:12.5px;font-weight:700;color:#0F172A;display:block;margin-bottom:6px;">
                    اسم / تسمية المفتاح (Label)
                </label>
                <input type="text" name="label" id="inputLabel" placeholder="مثال: Google Live Primary Key أو Fish Audio Main" required style="width:100%;padding:9px 12px;font-size:13px;border:1px solid #CBD5E1;border-radius:8px;outline:none;background:#FFF;color:#0F172A;">
            </div>

            <!-- Model Field -->
            <div>
                <label style="font-size:12.5px;font-weight:700;color:#0F172A;display:block;margin-bottom:6px;">
                    معرف الموديل (Model ID)
                </label>
                <input type="text" name="model" id="inputModel" value="{{ $googleActiveModel }}" placeholder="معرف النموذج (مثال: gemini-3.8-live)" required style="width:100%;padding:9px 12px;font-size:13px;border:1px solid #CBD5E1;border-radius:8px;outline:none;background:#FFF;color:#0F172A;font-family:monospace;">
            </div>

            <!-- Custom Endpoint (Optional/Auto-filled) -->
            <div id="endpointGroup">
                <label style="font-size:12.5px;font-weight:700;color:#0F172A;display:block;margin-bottom:6px;">
                    نقطة النهاية (Endpoint URL)
                </label>
                <input type="text" name="endpoint" id="inputEndpoint" placeholder="اتركه فارغاً للاستخدام الافتراضي أو اكتب الرابط المخصص" style="width:100%;padding:9px 12px;font-size:13px;border:1px solid #CBD5E1;border-radius:8px;outline:none;background:#FFF;color:#0F172A;direction:ltr;">
            </div>

            <!-- Secret Key Input with Eye Toggle -->
            <div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                    <label style="font-size:12.5px;font-weight:700;color:#0F172A;">
                        مفتاح API السري (Secret Key)
                    </label>
                    <span id="secretHint" style="font-size:11px;color:#64748B;">سيتم تشفيره بنظام AES-256-GCM في Vault</span>
                </div>
                <div style="position:relative;">
                    <input type="password" name="secret" id="inputSecret" placeholder="أدخل المفتاح السري هنا (مثال: AIzaSy... أو fish_...)" style="width:100%;padding:9px 36px 9px 12px;font-size:13px;border:1px solid #CBD5E1;border-radius:8px;outline:none;background:#FFF;color:#0F172A;font-family:monospace;direction:ltr;">
                    <button type="button" onclick="toggleSecretVisibility('inputSecret', this)" style="position:absolute;right:10px;top:9px;background:none;border:none;cursor:pointer;font-size:14px;color:#64748B;">
                        👁️
                    </button>
                </div>
            </div>

            <!-- Priority & Enabled in 2 Columns -->
            <div style="display:grid;grid-template-columns: 1fr 1fr;gap:12px;">
                <div>
                    <label style="font-size:12.5px;font-weight:700;color:#0F172A;display:block;margin-bottom:6px;">
                        أولوية الدوران (Failover Priority)
                    </label>
                    <input type="number" name="priority" id="inputPriority" value="1" min="1" max="999" required style="width:100%;padding:8px 12px;font-size:13px;border:1px solid #CBD5E1;border-radius:8px;outline:none;background:#FFF;color:#0F172A;">
                    <span style="font-size:10.5px;color:#94A3B8;">1 = الأول، 2 = احتياطي أول...</span>
                </div>
                <div style="display:flex;flex-direction:column;justify-content:center;">
                    <label style="font-size:12.5px;font-weight:700;color:#0F172A;display:block;margin-bottom:6px;">
                        الحالة التشغيلية
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#0F172A;cursor:pointer;margin-top:6px;">
                        <input type="checkbox" name="enabled" id="inputEnabled" value="1" checked style="width:16px;height:16px;cursor:pointer;">
                        <span>تفعيل المفتاح فورياً</span>
                    </label>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;margin-top:8px;padding-top:14px;border-top:1px solid #F1F5F9;">
                <button type="button" onclick="closeKeyModal()" class="btn btn-sm btn-ghost" style="padding:8px 16px;border-radius:8px;border:1px solid #CBD5E1;font-weight:600;cursor:pointer;">
                    إلغاء
                </button>
                <button type="submit" id="btnSubmitKey" class="btn btn-sm" style="background:#0F172A;color:#FFFFFF;border:none;border-radius:8px;padding:8px 20px;font-weight:700;cursor:pointer;">
                    حفظ المفتاح المشفر
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 6. REVEAL SECRET MODAL -->
<div id="revealSecretModal" class="ontrack-key-modal-backdrop" onclick="if(event.target===this)closeRevealModal()">
    <div class="ontrack-key-modal-box" style="max-width:480px;">
        <div style="padding:16px 20px;border-bottom:1px solid #F1F5F9;display:flex;align-items:center;justify-content:space-between;">
            <strong style="font-size:15px;color:#0F172A;">قيمة المفتاح السري المشفر</strong>
            <button type="button" onclick="closeRevealModal()" style="background:none;border:none;font-size:18px;color:#64748B;cursor:pointer;">✕</button>
        </div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:14px;">
            <div style="font-size:12px;color:#64748B;">
                المفتاح الخاص بـ: <strong id="revealLabel" style="color:#0F172A;">-</strong>
            </div>
            <div style="position:relative;">
                <input type="text" id="revealedSecretInput" readonly style="width:100%;padding:10px 12px;font-size:13px;border:1px solid #CBD5E1;border-radius:8px;background:#F8FAFC;font-family:monospace;direction:ltr;outline:none;">
            </div>
            <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;">
                <button type="button" onclick="copyRevealedSecret()" id="btnCopySecret" class="btn btn-sm" style="background:#0F172A;color:#FFF;border-radius:8px;padding:8px 16px;border:none;cursor:pointer;font-weight:600;">
                    📋 نسخ المفتاح
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function filterProviderTable(cat, btn) {
    document.querySelectorAll('.ontrack-tab-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    document.querySelectorAll('#keysTable tbody .key-row').forEach(row => {
        if (cat === 'all' || row.getAttribute('data-provider') === cat) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function searchProviderKeys(term) {
    term = term.toLowerCase().trim();
    document.querySelectorAll('#keysTable tbody .key-row').forEach(row => {
        const label = row.getAttribute('data-label') || '';
        const model = row.getAttribute('data-model') || '';
        const text = row.innerText.toLowerCase();
        if (label.includes(term) || model.includes(term) || text.includes(term)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function onDriverChange(driver) {
    const inputModel = document.getElementById('inputModel');
    const inputEndpoint = document.getElementById('inputEndpoint');
    const inputLabel = document.getElementById('inputLabel');

    if (driver === 'google_live') {
        inputModel.value = '{{ $googleActiveModel }}';
        inputModel.placeholder = 'معرف النموذج (مثال: gemini-3.8-live)';
        inputEndpoint.value = '';
        inputEndpoint.placeholder = 'اتركه فارغاً للاستخدام السحابي الافتراضي (Google WebSockets)';
        if (!inputLabel.value || inputLabel.value.includes('Key') || inputLabel.value.includes('مفتاح')) {
            inputLabel.value = 'Google Gemini Live Key';
        }
    } else if (driver === 'fish_audio') {
        inputModel.value = '';
        inputModel.placeholder = 'اكتب معرف النموذج أو الصوت المخصص (Voice ID / Custom Model)';
        inputEndpoint.value = 'https://api.fish.audio/v1';
        if (!inputLabel.value || inputLabel.value.includes('Key') || inputLabel.value.includes('مفتاح')) {
            inputLabel.value = 'Fish Audio Key';
        }
    } else if (driver === 'nvidia') {
        inputModel.value = 'nvidia/parakeet-ctc-1.1b-asr';
        inputModel.placeholder = 'معرف النموذج (مثال: nvidia/parakeet-ctc-1.1b-asr)';
        inputEndpoint.value = 'https://integrate.api.nvidia.com/v1';
        if (!inputLabel.value || inputLabel.value.includes('Key') || inputLabel.value.includes('مفتاح')) {
            inputLabel.value = 'NVIDIA NIM Cloud Key';
        }
    } else {
        inputModel.value = '';
        inputModel.placeholder = 'مثال: deepseek-chat أو gpt-4o-mini';
        inputEndpoint.value = '';
        inputEndpoint.placeholder = 'مثال: https://api.deepseek.com/v1 أو https://api.groq.com/openai/v1';
        if (!inputLabel.value || inputLabel.value.includes('Key') || inputLabel.value.includes('مفتاح')) {
            inputLabel.value = 'Custom Provider Key';
        }
    }
}

function openKeyModal(mode, data = null) {
    const modal = document.getElementById('providerKeyModal');
    const title = document.getElementById('modalTitle');
    const formAction = document.getElementById('formActionField');
    const relayId = document.getElementById('formRelayIdField');
    const secretInput = document.getElementById('inputSecret');
    const secretHint = document.getElementById('secretHint');

    if (mode === 'edit' && data) {
        title.innerText = 'تعديل بيانات المفتاح: ' + (data.label || '');
        formAction.value = 'update_relay_entry';
        relayId.value = data.id || 0;

        document.getElementById('inputDriver').value = data.driver || 'google_live';
        document.getElementById('inputLabel').value = data.label || '';
        document.getElementById('inputModel').value = data.model || '';
        document.getElementById('inputEndpoint').value = data.endpoint || '';
        document.getElementById('inputPriority').value = data.priority || 1;
        document.getElementById('inputEnabled').checked = ((int)(data.enabled ?? 1) === 1);

        secretInput.value = '';
        secretInput.placeholder = 'اتركه فارغاً إذا كنت لا ترغب بتغيير المفتاح الحالي';
        secretInput.required = false;
        secretHint.innerText = 'اتركه فارغاً للاحتفاظ بالمفتاح المشفر الحالي';
    } else {
        title.innerText = 'إضافة مفتاح مزود جديد';
        formAction.value = 'add_relay_entry';
        relayId.value = '0';

        document.getElementById('keyActionForm').reset();
        document.getElementById('inputDriver').value = 'google_live';
        onDriverChange('google_live');

        secretInput.placeholder = 'أدخل المفتاح السري هنا...';
        secretInput.required = true;
        secretHint.innerText = 'سيتم تشفيره بنظام AES-256-GCM في Vault';
    }

    modal.style.display = 'flex';
}

function closeKeyModal() {
    document.getElementById('providerKeyModal').style.display = 'none';
}

function toggleSecretVisibility(inputId, btn) {
    const inp = document.getElementById(inputId);
    if (inp.type === 'password') {
        inp.type = 'text';
        btn.innerText = '🔒';
    } else {
        inp.type = 'password';
        btn.innerText = '👁️';
    }
}

async function revealKeySecret(relayId) {
    try {
        const formData = new FormData();
        formData.append('action', 'reveal_relay_secret');
        formData.append('relay_id', relayId);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        formData.append('_token', csrfToken);

        const res = await fetch('/console/action', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const data = await res.json();
        if (data.ok && data.secret) {
            document.getElementById('revealLabel').innerText = data.label || '';
            document.getElementById('revealedSecretInput').value = data.secret;
            document.getElementById('revealSecretModal').style.display = 'flex';
        } else {
            alert(data.error || 'تعذر فك تشفير المفتاح.');
        }
    } catch (e) {
        alert('حدث خطأ أثناء فك التشفير: ' + e.message);
    }
}

function closeRevealModal() {
    document.getElementById('revealSecretModal').style.display = 'none';
    document.getElementById('revealedSecretInput').value = '';
}

function copyRevealedSecret() {
    const inp = document.getElementById('revealedSecretInput');
    inp.select();
    navigator.clipboard.writeText(inp.value);
    const btn = document.getElementById('btnCopySecret');
    btn.innerText = '✓ تم النسخ!';
    setTimeout(() => btn.innerText = '📋 نسخ المفتاح', 2000);
}

async function testSingleKey(relayId, btn) {
    const originalText = btn.innerHTML;
    btn.innerHTML = '⏳ فحص...';
    btn.disabled = true;

    const badge = document.getElementById('pingBadge-' + relayId);
    if (badge) badge.innerText = 'جاري الفحص...';

    try {
        const formData = new FormData();
        formData.append('action', 'test_relay_entry');
        formData.append('relay_id', relayId);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        formData.append('_token', csrfToken);

        const res = await fetch('/console/action', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();

        if (data.ok) {
            if (badge) {
                badge.innerText = `✓ ${data.latency_ms || 0}ms`;
                badge.style.color = '#16A34A';
            }
            alert(data.message || 'الاتصال ناجح بالمزود!');
        } else {
            if (badge) {
                badge.innerText = '❌ فشل';
                badge.style.color = '#DC2626';
            }
            alert(data.error || data.message || 'فشل الاتصال بالمزود.');
        }
    } catch (e) {
        if (badge) badge.innerText = 'خطأ';
        alert('حدث خطأ أثناء الاتصال: ' + e.message);
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

async function testAllKeys() {
    const buttons = document.querySelectorAll('button[onclick*="testSingleKey"]');
    for (const b of buttons) {
        b.click();
        await new Promise(r => setTimeout(r, 600));
    }
}
</script>
