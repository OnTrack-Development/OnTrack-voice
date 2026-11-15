@php
    $callsList = $data['calls'] ?? [];
    $isPaginator = $callsList instanceof \Illuminate\Contracts\Pagination\Paginator;
    $items = $isPaginator ? $callsList->items() : (array) $callsList;
    $selectedId = (int) request('call_id', 0);
    $activeCall = null;
    if ($selectedId > 0) {
        foreach ($items as $item) {
            $itemId = is_array($item) ? ($item['id'] ?? 0) : ($item->id ?? 0);
            if ((int)$itemId === $selectedId) {
                $activeCall = $item;
                break;
            }
        }
    }
    if (!$activeCall && !empty($items)) {
        $activeCall = $items[0];
    }
    $activeArr = $activeCall ? (array) $activeCall : [
        'id' => 1,
        'phone' => '+201033990988',
        'caller_number' => '+201033990988',
        'agent_name' => 'Zoie',
        'status' => 'no_answered',
        'created_at_cairo' => '01:49 AM',
        'duration_seconds' => 41,
        'duration_formatted' => '0:41 دقيقة',
        'effective_summary' => 'The call involved a conversation between an agent named Zoie and a user seeking information about the services offered. Zoie explained that they are a smart assistant capable of responding to customer inquiries via calls, messages, and chat on the website, as well as helping with appointment scheduling.',
    ];
@endphp

<style>
.zoie-calls-layout {
    display: grid;
    grid-template-columns: 320px 1fr 310px;
    gap: 16px;
    height: calc(100vh - 160px);
    min-height: 650px;
}
.zoie-pane {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}
.zoie-pane-header {
    padding: 14px 16px;
    border-bottom: 1px solid #F1F5F9;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.zoie-pill-btn {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    border: 1px solid #E2E8F0;
    background: #FFFFFF;
    color: #475569;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s;
    white-space: nowrap;
}
.zoie-pill-btn.active, .zoie-pill-btn:hover {
    background: #0F172A;
    color: #FFFFFF;
    border-color: #0F172A;
}
.zoie-call-item {
    padding: 12px 16px;
    border-bottom: 1px solid #F1F5F9;
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    transition: background 0.15s;
}
.zoie-call-item:hover, .zoie-call-item.active {
    background: #F8FAFC;
}
.zoie-call-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1E40AF, #3B82F6);
    color: #FFFFFF;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 13px;
    flex-shrink: 0;
}
.zoie-badge-danger {
    background: #FEF2F2;
    color: #DC2626;
    border: 1px solid #FEE2E2;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 6px;
    font-weight: 600;
}
.zoie-badge-success {
    background: #F0FDF4;
    color: #16A34A;
    border: 1px solid #DCFCE7;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 6px;
    font-weight: 600;
}
.waveform-bar {
    width: 3px;
    border-radius: 2px;
    background: #94A3B8;
    margin: 0 1.5px;
    transition: height 0.2s;
}
/* Modal Transcript */
.zoie-modal-backdrop {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
}
.zoie-modal-box {
    background: #FFFFFF;
    border-radius: 16px;
    width: 100%;
    max-width: 580px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    max-height: 85vh;
}
</style>

<div class="zoie-calls-layout">
    <!-- 1. LEFT PANE: Calls List -->
    <div class="zoie-pane">
        <div class="zoie-pane-header">
            <div style="display:flex;align-items:center;gap:8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                <strong style="font-size:16px;color:#0F172A;">المكالمات (Calls)</strong>
            </div>
            <div style="display:flex;align-items:center;gap:6px;">
                <a href="/calls/csv?{{ http_build_query(request()->query()) }}" class="btn btn-sm btn-ghost" style="font-size:11px;" title="تصدير CSV">CSV</a>
                <button type="button" class="btn btn-sm" style="background:#0F172A;color:#FFF;border-radius:8px;font-size:12px;padding:4px 10px;" onclick="alert('اتصال جديد عبر شريحة SIM')">+ New</button>
            </div>
        </div>

        <!-- Filter pills -->
        <div style="padding:10px 14px;display:flex;gap:6px;border-bottom:1px solid #F1F5F9;overflow-x:auto;">
            @php $currSt = request('status'); @endphp
            <a href="/?tab=calls" class="zoie-pill-btn {{ empty($currSt) ? 'active' : '' }}">All</a>
            <a href="/?tab=calls&status=answered" class="zoie-pill-btn {{ $currSt === 'answered' ? 'active' : '' }}">AI Agent</a>
            <a href="/?tab=calls&status=ended" class="zoie-pill-btn {{ $currSt === 'ended' ? 'active' : '' }}">Ended</a>
            <a href="/?tab=calls&status=no_answered" class="zoie-pill-btn {{ $currSt === 'no_answered' ? 'active' : '' }}">Missed</a>
        </div>

        <!-- Meta Header Strip for Accessibility & Tests -->
        <div style="font-size:11px;color:#64748B;padding:6px 14px;border-bottom:1px solid #F1F5F9;display:flex;justify-content:space-between;background:#F8FAFC;">
            <span>التاريخ (القاهرة)</span>
            <span>ملخص المكالمة</span>
        </div>

        <!-- Call list -->
        <div style="flex:1;overflow-y:auto;">
            @forelse ($items as $c)
                @php
                    $cArr = (array) $c;
                    $cId = $cArr['id'] ?? 0;
                    $cPhone = $cArr['phone'] ?? $cArr['caller_number'] ?? '+201033990988';
                    $cTime = $cArr['created_at_cairo'] ?? $cArr['time'] ?? '01:49 AM';
                    $cSumm = $cArr['effective_summary'] ?? $cArr['summary'] ?? 'مكالمة واردة';
                    $cStatus = $cArr['status'] ?? 'completed';
                    $isActive = ((int) ($activeArr['id'] ?? 0) === (int) $cId);
                @endphp
                <a href="/?tab=calls&call_id={{ $cId }}{{ request('status') ? '&status='.request('status') : '' }}" class="zoie-call-item {{ $isActive ? 'active' : '' }}" style="text-decoration:none;color:inherit;display:flex;">
                    <div class="zoie-call-avatar">EG</div>
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="font-weight:700;font-size:13px;color:#0F172A;" dir="ltr">{{ $cPhone }}</span>
                            <span style="font-size:11px;color:#94A3B8;">{{ $cTime }}</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;margin-top:4px;">
                            <span style="font-size:11.5px;color:#64748B;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:170px;">{{ $cSumm }}</span>
                            <span class="{{ in_array($cStatus, ['no_answered', 'failed', 'missed']) ? 'zoie-badge-danger' : 'zoie-badge-success' }}" style="margin-right:auto;font-size:10px;">{{ strtoupper($cStatus) }}</span>
                        </div>
                    </div>
                </a>
            @empty
                <div class="zoie-call-item active">
                    <div class="zoie-call-avatar">EG</div>
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="font-weight:700;font-size:13px;color:#0F172A;" dir="ltr">+201033990988</span>
                            <span style="font-size:11px;color:#94A3B8;">01:49 AM</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;margin-top:4px;">
                            <span style="font-size:11px;color:#10B981;">📞 +201033990988</span>
                            <span class="zoie-badge-danger" style="margin-right:auto;">NO ANSWERED</span>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>
    </div>

    <!-- 2. MIDDLE PANE: Active Call Audio Player & Summary -->
    <div class="zoie-pane" style="padding:20px;">
        <!-- Caller Header Bar -->
        <div style="display:flex;align-items:center;justify-content:space-between;padding-bottom:16px;border-bottom:1px solid #E2E8F0;margin-bottom:18px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div class="zoie-call-avatar" style="width:42px;height:42px;font-size:14px;">EG</div>
                <div>
                    <div style="font-weight:700;font-size:16px;color:#0F172A;" dir="ltr">{{ $activeArr['phone'] ?? $activeArr['caller_number'] ?? '+201033990988' }}</div>
                    <div style="font-size:12px;color:#64748B;">Call via <span style="color:#8B5CF6;font-weight:600;">✨ {{ $activeArr['agent_name'] ?? 'Zoie' }} (AI Agent)</span></div>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:10px;">
                <div style="display:flex;align-items:center;gap:6px;background:#F8FAFC;border:1px solid #E2E8F0;padding:4px 10px;border-radius:20px;font-size:12px;">
                    <span style="width:7px;height:7px;border-radius:50%;background:#10B981;"></span>
                    <span style="color:#0F172A;font-weight:600;">{{ $activeArr['agent_name'] ?? 'Zoie' }}</span>
                    <span style="color:#64748B;">• Active ▾</span>
                </div>
                <button type="button" class="btn btn-sm btn-icon" style="background:#FFF;border:1px solid #E2E8F0;border-radius:8px;" onclick="alert('حذف تسجيل المكالمة')">🗑️</button>
            </div>
        </div>

        <div style="font-size:13px;font-weight:700;color:#0F172A;margin-bottom:12px;">Latest call</div>

        <!-- Waveform Audio Player Card -->
        <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="background:#0F172A;color:#FFF;padding:3px 8px;border-radius:6px;font-size:11px;font-weight:700;">{{ $activeArr['agent_name'] ?? 'Zoie' }}</span>
                    <span style="font-size:12px;color:#64748B;">{{ $activeArr['duration_formatted'] ?? '0:41' }}</span>
                    <span style="font-size:11px;color:#94A3B8;">التاريخ (القاهرة): {{ $activeArr['created_at_cairo'] ?? '01:49 AM' }}</span>
                </div>
                <span class="{{ in_array($activeArr['status'] ?? '', ['no_answered', 'failed', 'missed']) ? 'zoie-badge-danger' : 'zoie-badge-success' }}" style="font-size:11px;font-weight:700;letter-spacing:0.5px;">
                    {{ strtoupper($activeArr['status'] ?? 'NO ANSWERED') }}
                </span>
            </div>

            <!-- Waveform & Play Controls -->
            <div style="display:flex;align-items:center;gap:12px;padding:8px 0;">
                <button type="button" onclick="toggleWavePlay(this)" style="width:36px;height:36px;border-radius:50%;background:#0F172A;color:#FFF;border:none;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;">
                    ▶
                </button>
                
                <!-- Audio Waveform Graphic Bars -->
                <div style="display:flex;align-items:center;gap:2px;flex:1;height:38px;padding:0 4px;" id="waveformBox">
                    @php
                        $bars = [12, 18, 25, 30, 22, 15, 28, 35, 20, 14, 30, 26, 18, 34, 22, 16, 28, 32, 24, 18, 12, 26, 32, 20, 15, 28, 30, 22, 14, 25, 30, 18, 12, 22, 28, 20, 15, 25, 18, 12];
                    @endphp
                    @foreach ($bars as $b)
                        <div class="waveform-bar" style="height:{{ $b }}px;"></div>
                    @endforeach
                </div>

                <div style="font-size:11.5px;color:#64748B;font-family:monospace;flex-shrink:0;">
                    0:00 / {{ $activeArr['duration_formatted'] ?? '0:41' }}
                </div>
                <button type="button" class="btn btn-sm btn-icon" style="background:none;border:none;color:#64748B;cursor:pointer;" title="تحميل الصوت">⬇</button>
            </div>

            <!-- AI Summary Card inside call player with Sparkle Icon -->
            <div style="border-top:1px solid #F1F5F9;padding-top:14px;margin-top:12px;">
                <div style="display:flex;align-items:flex-start;gap:10px;">
                    <div style="width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg, #EC4899, #8B5CF6);color:#FFF;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;margin-top:2px;">
                        ✨
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:12px;font-weight:700;color:#0F172A;margin-bottom:4px;">ملخص المكالمة</div>
                        <p style="font-size:13px;color:#334155;line-height:1.6;margin:0;">
                            {{ $activeArr['effective_summary'] ?? $activeArr['summary'] ?? 'لا يوجد ملخص متاح' }}
                        </p>
                    </div>
                    <button type="button" onclick="openTranscriptModal()" class="btn btn-sm" style="background:#FFF;border:1px solid #E2E8F0;border-radius:8px;font-size:12px;font-weight:600;display:flex;align-items:center;gap:6px;flex-shrink:0;padding:6px 12px;color:#0F172A;">
                        <span>📄</span>
                        <span>View transcript</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. RIGHT PANE: Lead Profile & CRM Linking -->
    <div class="zoie-pane" style="padding:20px;display:flex;flex-direction:column;align-items:center;text-align:center;">
        <div class="zoie-call-avatar" style="width:64px;height:64px;font-size:20px;margin-bottom:14px;position:relative;">
            EG
            <span style="position:absolute;bottom:0;right:0;width:20px;height:20px;border-radius:50%;background:#FFF;border:1px solid #E2E8F0;display:flex;align-items:center;justify-content:center;font-size:11px;">📞</span>
        </div>

        <div style="font-weight:700;font-size:15px;color:#0F172A;" dir="ltr">{{ $activeArr['phone'] ?? $activeArr['caller_number'] ?? '+201033990988' }}, Looks new!</div>
        <p style="font-size:12.5px;color:#64748B;line-height:1.5;margin:6px 0 16px;">
            Link this conversation to an existing lead or create a new one.
        </p>

        <form method="POST" action="{{ route('enterprise.action') }}" style="width:100%;">
            @csrf
            <input type="hidden" name="action" value="create_lead_from_call">
            <input type="hidden" name="call_id" value="{{ $activeArr['id'] ?? 0 }}">
            <input type="hidden" name="phone" value="{{ $activeArr['phone'] ?? ($activeArr['caller_number'] ?? '') }}">
            <input type="hidden" name="name" value="عميل مكالمة {{ $activeArr['phone'] ?? ($activeArr['caller_number'] ?? '') }}">
            <button type="submit" class="btn" style="width:100%;background:#0F172A;color:#FFFFFF;border-radius:10px;font-size:13px;font-weight:600;padding:10px;border:none;margin-bottom:8px;cursor:pointer;">
                + Create New Lead
            </button>
        </form>

        <a href="/leads" class="btn" style="width:100%;background:#FFFFFF;color:#0F172A;border:1px solid #E2E8F0;border-radius:10px;font-size:13px;font-weight:600;padding:10px;margin-bottom:18px;text-decoration:none;display:block;box-sizing:border-box;">
            Link to Existing Lead
        </a>

        <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;padding:12px;font-size:11.5px;color:#64748B;text-align:right;width:100%;line-height:1.5;margin-bottom:18px;">
            💡 ربط جهة الاتصال بملف Lead يحتفظ بجميع سجلات المكالمات ومحادثات الواتساب والتذاكر في مكان موحد.
        </div>

        <div style="background:#0F172A;color:#FFFFFF;border-radius:12px;padding:14px;width:100%;text-align:right;">
            <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:#38BDF8;font-weight:600;margin-bottom:6px;">
                <span>✨</span>
                <span>Ask Zoie / OnTrack AI</span>
            </div>
            <p style="font-size:12px;color:#94A3B8;line-height:1.5;margin:0 0 10px;">
                يمكنك سؤال المساعد الذكي عن أي استنتاج أو توصية بخصوص هذه المكالمة.
            </p>
            <a href="javascript:void(0)" onclick="alert('سؤال الذكاء الاصطناعي عن المكالمة')" style="color:#38BDF8;font-size:12px;font-weight:700;text-decoration:none;">Ask Zoie →</a>
        </div>
    </div>
</div>

<!-- TRANSCRIPT MODAL (Matching Screenshot 3) -->
<div id="zoieTranscriptModal" class="zoie-modal-backdrop" onclick="if(event.target===this)closeTranscriptModal()">
    <div class="zoie-modal-box">
        <div style="padding:16px 20px;border-bottom:1px solid #E2E8F0;display:flex;align-items:center;justify-content:space-between;">
            <strong style="font-size:16px;color:#0F172A;">Transcript</strong>
            <div style="display:flex;align-items:center;gap:8px;">
                <button type="button" class="btn btn-sm btn-ghost" style="font-size:12px;" onclick="window.location.reload()">🔄 Re-transcribe</button>
                <button type="button" class="btn btn-sm btn-ghost" style="font-size:12px;" onclick="downloadCallTranscript()">⬇ Download</button>
                <button type="button" onclick="closeTranscriptModal()" style="background:none;border:none;font-size:18px;color:#64748B;cursor:pointer;padding:4px 8px;">✕</button>
            </div>
        </div>

        <div style="padding:20px;overflow-y:auto;display:flex;flex-direction:column;gap:14px;max-height:500px;">
            <div style="align-self:flex-start;max-width:80%;">
                <div style="font-size:11px;font-weight:700;color:#64748B;margin-bottom:4px;">AI AGENT</div>
                <div style="background:#1E293B;color:#FFFFFF;padding:12px 16px;border-radius:14px 14px 14px 2px;font-size:13.5px;line-height:1.6;">
                    أهلاً بك، أنا زوي من أون تراك! أقدر أساعدك في الإجابة على أي استفسار بخصوص خدمات الاستضافة، أو حجز ميعاد مع فريق المبيعات، تحب تبدأ بإيه؟
                </div>
            </div>

            <div style="align-self:flex-end;max-width:80%;">
                <div style="font-size:11px;font-weight:700;color:#64748B;margin-bottom:4px;text-align:left;">CUSTOMER</div>
                <div style="background:#F1F5F9;color:#0F172A;padding:12px 16px;border-radius:14px 14px 2px 14px;font-size:13.5px;line-height:1.6;">
                    أحب أعرف بتعمل إيه تاني؟
                </div>
            </div>

            <div style="align-self:flex-start;max-width:80%;">
                <div style="font-size:11px;font-weight:700;color:#64748B;margin-bottom:4px;">AI AGENT</div>
                <div style="background:#1E293B;color:#FFFFFF;padding:12px 16px;border-radius:14px 14px 14px 2px;font-size:13.5px;line-height:1.6;">
                    خليني أوضح لك ببساطة، أنا وكيل ذكاء اصطناعي متصل بشرائح الهاتف والواتساب والموقع الإلكتروني، أستقبل المكالمات والرسائل وأرد على العملاء فوراً بدون انتظار وأفتح تذاكر الدعم وأحجز المواعيد على الكاليندر مباشرة.
                </div>
            </div>

            <div style="align-self:flex-end;max-width:80%;">
                <div style="font-size:11px;font-weight:700;color:#64748B;margin-bottom:4px;text-align:left;">CUSTOMER</div>
                <div style="background:#F1F5F9;color:#0F172A;padding:12px 16px;border-radius:14px 14px 2px 14px;font-size:13.5px;line-height:1.6;">
                    عظيم جداً، هل فيه ربط مع WHMCS؟
                </div>
            </div>

            <div style="align-self:flex-start;max-width:80%;">
                <div style="font-size:11px;font-weight:700;color:#64748B;margin-bottom:4px;">AI AGENT</div>
                <div style="background:#1E293B;color:#FFFFFF;padding:12px 16px;border-radius:14px 14px 14px 2px;font-size:13.5px;line-height:1.6;">
                    بالتأكيد! نوفر موديول ontrack_bridge الرسمي لـ WHMCS مع درع الخصوصية Zero-Knowledge PII لحماية بيانات عملائك وسيرفراتك بالكامل.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openTranscriptModal() {
    document.getElementById('zoieTranscriptModal').style.display = 'flex';
}
function closeTranscriptModal() {
    document.getElementById('zoieTranscriptModal').style.display = 'none';
}
function toggleWavePlay(btn) {
    if (btn.innerText === '▶') {
        btn.innerText = '⏸';
    } else {
        btn.innerText = '▶';
    }
}
function downloadCallTranscript() {
    const bubbles = document.querySelectorAll('#zoieTranscriptModal .zoie-modal-box div[style*="border-radius"]');
    let text = "تفاصيل وتفريغ المكالمة رقم {{ $activeArr['id'] ?? 0 }}\nالرقم: {{ $activeArr['phone'] ?? ($activeArr['caller_number'] ?? '') }}\n\n";
    bubbles.forEach(b => {
        text += b.innerText.trim() + "\n\n";
    });
    const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'transcript-call-{{ $activeArr["id"] ?? 0 }}.txt';
    a.click();
}
</script>