@php
    $sessions = $data['sessions'] ?? [];
    $selectedPhone = request('phone', '');
    $activeSession = null;
    if ($selectedPhone !== '') {
        foreach ($sessions as $s) {
            if (($s['phone'] ?? '') === $selectedPhone) {
                $activeSession = $s;
                break;
            }
        }
    }
    if (!$activeSession && !empty($sessions) && request()->has('phone')) {
        $activeSession = $sessions[0];
    }
@endphp

<style>
/* 2/3-Column Responsive Dual-Contrast Messages Layout (Matching Screenshot 2) */
.ontrack-messages-layout {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 16px;
    height: calc(100vh - 160px);
    min-height: 650px;
}
@media (max-width: 820px) {
    .ontrack-messages-layout {
        grid-template-columns: 1fr;
        height: auto;
    }
}

.ontrack-msg-pane {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

.ontrack-filter-pill {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s;
    border: 1px solid #E2E8F0;
    background: #FFFFFF;
    color: #475569;
    white-space: nowrap;
}
.ontrack-filter-pill.active, .ontrack-filter-pill:hover {
    background: #0F172A;
    color: #FFFFFF;
    border-color: #0F172A;
}

.ontrack-chat-bubble-ai {
    background: #1E293B;
    color: #FFFFFF;
    padding: 10px 16px;
    border-radius: 14px 14px 14px 4px;
    font-size: 13.5px;
    line-height: 1.5;
    max-width: 80%;
}
.ontrack-chat-bubble-user {
    background: #F1F5F9;
    color: #0F172A;
    padding: 10px 16px;
    border-radius: 14px 14px 4px 14px;
    font-size: 13.5px;
    line-height: 1.5;
    max-width: 80%;
    margin-left: auto;
}
</style>

<div class="ontrack-messages-layout">
    <!-- 1. RIGHT PANE (in RTL): Conversation List & Filters (Matching Screenshot 2) -->
    <div class="ontrack-msg-pane">
        <!-- Header -->
        <div style="padding:14px 16px;border-bottom:1px solid #F1F5F9;display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                <strong style="font-size:16px;color:#0F172A;">الرسائل والمحادثات</strong>
            </div>
            <div style="display:flex;align-items:center;gap:6px;">
                <button type="button" class="btn btn-sm btn-icon" style="background:#FFF;border:1px solid #E2E8F0;color:#64748B;padding:4px 8px;border-radius:6px;cursor:pointer;" title="بحث">🔍</button>
                <button type="button" class="btn btn-sm btn-icon" style="background:#FFF;border:1px solid #E2E8F0;color:#64748B;padding:4px 8px;border-radius:6px;cursor:pointer;" title="تصفية">▼</button>
                <button type="button" class="btn btn-sm" style="background:#0F172A;color:#FFF;border-radius:6px;font-size:12px;font-weight:600;padding:4px 10px;border:none;cursor:pointer;" onclick="alert('بدء محادثة جديدة عبر WhatsApp Cloud API أو الودجت')">+ رسالة جديدة</button>
            </div>
        </div>

        <!-- Filter Row 1: All / Human (0) / AI Agents (0) -->
        <div style="padding:10px 14px 6px;display:flex;gap:6px;overflow-x:auto;">
            <a href="javascript:void(0)" onclick="filterMessagesChannel('all')" class="ontrack-filter-pill active" id="pill-all-agents">الكل</a>
            <a href="javascript:void(0)" onclick="filterMessagesChannel('human')" class="ontrack-filter-pill" id="pill-human">بشري ({{ collect($sessions)->where('is_muted_for_human', true)->count() }})</a>
            <a href="javascript:void(0)" onclick="filterMessagesChannel('ai')" class="ontrack-filter-pill" id="pill-ai">إيجنت AI ({{ collect($sessions)->where('is_muted_for_human', false)->count() }})</a>
        </div>

        <!-- Filter Row 2: All / Unread / WhatsApp / Widget -->
        <div style="padding:4px 14px 10px;display:flex;gap:6px;border-bottom:1px solid #F1F5F9;overflow-x:auto;">
            <a href="javascript:void(0)" class="ontrack-filter-pill active" style="font-size:11px;padding:3px 10px;">الكل</a>
            <a href="javascript:void(0)" class="ontrack-filter-pill" style="font-size:11px;padding:3px 10px;">غير مقروءة</a>
            <a href="javascript:void(0)" class="ontrack-filter-pill" style="font-size:11px;padding:3px 10px;display:inline-flex;align-items:center;gap:4px;">
                <span>💬</span> واتساب (WhatsApp)
            </a>
            <a href="javascript:void(0)" class="ontrack-filter-pill" style="font-size:11px;padding:3px 10px;display:inline-flex;align-items:center;gap:4px;">
                <span>🌐</span> ودجت الموقع (Widget)
            </a>
        </div>

        <!-- Conversations Stream or Clean Empty State (Screenshot 2) -->
        <div style="flex:1;overflow-y:auto;display:flex;flex-direction:column;">
            @forelse ($sessions as $s)
                @php
                    $sPhone = $s['phone'] ?? 'عميل محادثة';
                    $isSelected = $activeSession && (($activeSession['phone'] ?? '') === $sPhone);
                @endphp
                <a href="/?tab=messages&phone={{ urlencode($sPhone) }}" style="display:block;padding:12px 14px;border-bottom:1px solid #F1F5F9;background:{{ $isSelected ? '#F8FAFC' : '#FFFFFF' }};text-decoration:none;color:inherit;cursor:pointer;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                        <strong style="font-size:13px;color:#0F172A;" dir="ltr">{{ $sPhone }}</strong>
                        <span style="font-size:11px;color:#94A3B8;">{{ $s['updated_at'] ?? '' }}</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <span style="background:{{ ($s['driver'] ?? '') === 'meta' ? '#DCFCE7' : '#E0E7FF' }};color:{{ ($s['driver'] ?? '') === 'meta' ? '#15803D' : '#4338CA' }};font-size:10px;padding:1px 6px;border-radius:4px;font-weight:700;">
                            {{ ($s['driver'] ?? '') === 'meta' ? 'WhatsApp' : 'Widget' }}
                        </span>
                        <span style="font-size:12px;color:#64748B;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            {{ $s['last_message'] ?? 'جلسة محادثة نشطة' }}
                        </span>
                    </div>
                </a>
            @empty
                <!-- Exact Empty State from Screenshot 2 -->
                <div style="padding:60px 16px;text-align:center;color:#64748B;display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;">
                    <!-- Vector Inbox Tray Illustration -->
                    <div style="width:72px;height:72px;margin-bottom:16px;position:relative;">
                        <svg viewBox="0 0 64 64" fill="none" style="width:100%;height:100%;">
                            <!-- Document behind tray -->
                            <rect x="20" y="10" width="24" height="30" rx="3" fill="#E2E8F0" stroke="#CBD5E1" stroke-width="1.5"/>
                            <line x1="26" y1="18" x2="38" y2="18" stroke="#94A3B8" stroke-width="1.5" stroke-linecap="round"/>
                            <line x1="26" y1="24" x2="36" y2="24" stroke="#94A3B8" stroke-width="1.5" stroke-linecap="round"/>
                            <!-- Tray Body -->
                            <path d="M12 28L18 48H46L52 28H42L38 35H26L22 28H12Z" fill="#CBD5E1"/>
                            <path d="M12 28L18 48H46L52 28" stroke="#94A3B8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <!-- Little message dot bubble -->
                            <circle cx="44" cy="18" r="5" fill="#E2E8F0"/>
                            <circle cx="42" cy="18" r="1" fill="#94A3B8"/>
                            <circle cx="44" cy="18" r="1" fill="#94A3B8"/>
                            <circle cx="46" cy="18" r="1" fill="#94A3B8"/>
                        </svg>
                    </div>
                    <strong style="font-size:14px;color:#64748B;font-weight:600;display:block;">لا توجد محادثات حتى الآن</strong>
                </div>
            @endforelse
        </div>
    </div>

    <!-- 2. LEFT PANE (in RTL): Selected Conversation OR Large Empty State (Matching Screenshot 2) -->
    <div class="ontrack-msg-pane" style="display:flex;flex-direction:column;">
        @if ($activeSession)
            <!-- Active Chat Header -->
            <div style="padding:14px 18px;border-bottom:1px solid #E2E8F0;display:flex;align-items:center;justify-content:space-between;background:#FFFFFF;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:38px;height:38px;border-radius:50%;background:#10B981;color:#FFF;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;">
                        💬
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:15px;color:#0F172A;" dir="ltr">{{ $activeSession['phone'] ?? '' }}</div>
                        <div style="font-size:11.5px;color:#64748B;">متصل عبر <strong style="color:#10B981;">{{ ($activeSession['driver'] ?? '') === 'meta' ? 'WhatsApp Cloud API' : 'Live Website Widget' }}</strong></div>
                    </div>
                </div>

                <div style="display:flex;align-items:center;gap:8px;">
                    <form method="POST" action="{{ route('enterprise.action') }}" style="display:inline;margin:0;">
                        @csrf
                        <input type="hidden" name="action" value="takeover_conversation">
                        <input type="hidden" name="phone" value="{{ $activeSession['phone'] ?? '' }}">
                        <button type="submit" class="btn btn-sm" style="background:#DC2626;color:#FFF;border-radius:6px;font-size:12px;font-weight:600;padding:6px 12px;border:none;cursor:pointer;">
                            🚨 استلام بشري (Takeover)
                        </button>
                    </form>
                    <form method="POST" action="{{ route('enterprise.action') }}" style="display:inline;margin:0;">
                        @csrf
                        <input type="hidden" name="action" value="resume_ai_conversation">
                        <input type="hidden" name="phone" value="{{ $activeSession['phone'] ?? '' }}">
                        <button type="submit" class="btn btn-sm btn-ghost" style="border:1px solid #E2E8F0;font-size:11.5px;border-radius:6px;cursor:pointer;">🤖 تشغيل إيجنت AI</button>
                    </form>
                </div>
            </div>

            <!-- Chat Messages Stream -->
            <div style="flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:14px;background:#F8FAFC;">
                @if (!empty($activeSession['last_message']))
                    <div style="align-self:flex-start;max-width:80%;">
                        <div style="font-size:10px;font-weight:700;color:#94A3B8;text-align:right;margin-bottom:3px;text-transform:uppercase;">العميل</div>
                        <div class="ontrack-chat-bubble-user">
                            {{ $activeSession['last_message'] }}
                        </div>
                    </div>
                @else
                    <div style="text-align:center;padding:20px;color:#94A3B8;font-size:13px;">
                        المحادثة نشطة والذكاء الاصطناعي يستمع للرسائل الواردة.
                    </div>
                @endif
            </div>

            <!-- Chat Input Bar -->
            <form method="POST" action="{{ route('enterprise.action') }}" style="padding:14px 18px;border-top:1px solid #E2E8F0;background:#FFFFFF;display:flex;align-items:center;gap:10px;margin:0;">
                @csrf
                <input type="hidden" name="action" value="send_chat_message">
                <input type="hidden" name="phone" value="{{ $activeSession['phone'] ?? '' }}">
                <input type="text" id="manualMsgInput" name="message" placeholder="اكتب رداً يدوياً أو دع الذكاء الاصطناعي يستجيب..." required style="flex:1;background:#F8FAFC;border:1px solid #CBD5E1;border-radius:8px;padding:10px 14px;font-size:13px;color:#0F172A;outline:none;">
                <button type="submit" class="btn btn-sm" style="background:#0F172A;color:#FFF;border-radius:8px;padding:9px 18px;font-weight:600;border:none;cursor:pointer;">إرسال</button>
            </form>
        @else
            <!-- EXACT Pixel-Perfect Match to Screenshot 2 Right Pane -->
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;text-align:center;color:#64748B;padding:40px;">
                <!-- Vector Opened Envelope Illustration -->
                <div style="width:110px;height:110px;margin-bottom:20px;position:relative;">
                    <svg viewBox="0 0 100 100" fill="none" style="width:100%;height:100%;">
                        <!-- Motion sparkle line top right -->
                        <line x1="72" y1="20" x2="78" y2="14" stroke="#0F172A" stroke-width="2" stroke-linecap="round"/>
                        <line x1="78" y1="22" x2="84" y2="20" stroke="#0F172A" stroke-width="2" stroke-linecap="round"/>
                        <!-- Scribble motion loop left -->
                        <path d="M18 36 C14 30, 22 26, 26 32 C30 38, 22 42, 28 46" stroke="#0F172A" stroke-width="1.5" stroke-linecap="round" fill="none"/>
                        <!-- Envelope base -->
                        <rect x="22" y="34" width="56" height="38" rx="6" fill="#F1F5F9" stroke="#0F172A" stroke-width="2"/>
                        <!-- Paper coming out -->
                        <rect x="28" y="20" width="44" height="30" rx="3" fill="#FFFFFF" stroke="#0F172A" stroke-width="1.8"/>
                        <line x1="34" y1="28" x2="48" y2="28" stroke="#0F172A" stroke-width="2" stroke-linecap="round"/>
                        <!-- Envelope flap V -->
                        <path d="M23 40 L50 58 L77 40" stroke="#0F172A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>

                <strong style="font-size:18px;color:#0F172A;font-weight:700;margin-bottom:8px;">اختر محادثة</strong>
                <p style="font-size:13.5px;color:#64748B;max-width:340px;line-height:1.6;margin:0;">
                    اختر عميلاً من القائمة على اليمين لعرض الرسائل وبدء التواصل المباشر.
                </p>
            </div>
        @endif
    </div>
</div>

<script>
function filterMessagesChannel(type) {
    document.querySelectorAll('.ontrack-filter-pill').forEach(p => p.classList.remove('active'));
    if (type === 'human') {
        document.getElementById('pill-human').classList.add('active');
    } else if (type === 'ai') {
        document.getElementById('pill-ai').classList.add('active');
    } else {
        document.getElementById('pill-all-agents').classList.add('active');
    }
}
</script>
