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
    if (!$activeSession && !empty($sessions)) {
        $activeSession = $sessions[0];
    }
@endphp

<style>
.zoie-messages-layout {
    display: grid;
    grid-template-columns: 310px 1fr 300px;
    gap: 16px;
    height: calc(100vh - 150px);
    min-height: 650px;
}
.zoie-msg-pane {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}
.zoie-filter-capsule {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s;
    border: 1px solid #E2E8F0;
    background: #FFFFFF;
    color: #475569;
}
.zoie-filter-capsule.active, .zoie-filter-capsule:hover {
    background: #0F172A;
    color: #FFFFFF;
    border-color: #0F172A;
}
.zoie-chat-bubble-ai {
    background: #1E293B;
    color: #FFFFFF;
    padding: 12px 16px;
    border-radius: 14px 14px 14px 2px;
    font-size: 13.5px;
    line-height: 1.6;
    max-width: 82%;
}
.zoie-chat-bubble-user {
    background: #F1F5F9;
    color: #0F172A;
    padding: 12px 16px;
    border-radius: 14px 14px 2px 14px;
    font-size: 13.5px;
    line-height: 1.6;
    max-width: 82%;
    margin-right: auto;
}
</style>

<div class="zoie-messages-layout">
    <!-- 1. LEFT PANE: Conversation List -->
    <div class="zoie-msg-pane">
        <div style="padding:14px 16px;border-bottom:1px solid #F1F5F9;display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                <strong style="font-size:16px;color:#0F172A;">Messages</strong>
            </div>
            <span class="pill" style="font-size:11px;">{{ count($sessions) }}</span>
        </div>

        <!-- Filter Row 1: Agent vs Human -->
        <div style="padding:8px 12px;display:flex;gap:6px;border-bottom:1px solid #F1F5F9;">
            <a href="javascript:void(0)" class="zoie-filter-capsule active">All</a>
            <a href="javascript:void(0)" class="zoie-filter-capsule">Human ({{ collect($sessions)->where('is_muted_for_human', true)->count() }})</a>
            <a href="javascript:void(0)" class="zoie-filter-capsule">AI Agents ({{ collect($sessions)->where('is_muted_for_human', false)->count() }})</a>
        </div>

        <!-- Filter Row 2: Channel Sub-filters -->
        <div style="padding:8px 12px;display:flex;gap:6px;border-bottom:1px solid #F1F5F9;overflow-x:auto;">
            <a href="javascript:void(0)" class="zoie-filter-capsule active" style="font-size:11px;padding:4px 10px;">All</a>
            <a href="javascript:void(0)" class="zoie-filter-capsule" style="font-size:11px;padding:4px 10px;">Unread</a>
            <a href="javascript:void(0)" class="zoie-filter-capsule" style="font-size:11px;padding:4px 10px;">WhatsApp</a>
            <a href="javascript:void(0)" class="zoie-filter-capsule" style="font-size:11px;padding:4px 10px;">Widget</a>
        </div>

        <!-- Conversations List -->
        <div style="flex:1;overflow-y:auto;">
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
                        <span style="background:{{ ($s['driver'] ?? '') === 'meta' ? '#DCFCE7' : '#E0E7FF' }};color:{{ ($s['driver'] ?? '') === 'meta' ? '#15803D' : '#4338CA' }};font-size:10.5px;padding:1px 6px;border-radius:4px;font-weight:600;">
                            {{ ($s['driver'] ?? '') === 'meta' ? 'WhatsApp' : 'Widget' }}
                        </span>
                        <span style="font-size:12px;color:#64748B;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            {{ $s['last_message'] ?? 'جلسة محادثة نشطة' }}
                        </span>
                    </div>
                </a>
            @empty
                <div style="padding:40px 16px;text-align:center;color:#64748B;">
                    <div style="font-size:36px;margin-bottom:10px;">💬</div>
                    <strong style="font-size:14px;color:#0F172A;display:block;margin-bottom:4px;">No conversations yet</strong>
                    <p style="font-size:12px;color:#94A3B8;margin:0;">لا توجد محادثات نشطة حالياً عبر واتساب أو الويدجت.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- 2. CENTER PANE: Active Chat or Empty State -->
    <div class="zoie-msg-pane" style="display:flex;flex-direction:column;">
        @if ($activeSession)
            <!-- Chat Header -->
            <div style="padding:12px 18px;border-bottom:1px solid #E2E8F0;display:flex;align-items:center;justify-content:space-between;background:#FFFFFF;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:50%;background:#10B981;color:#FFF;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;">
                        💬
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:14px;color:#0F172A;" dir="ltr">{{ $activeSession['phone'] ?? '' }}</div>
                        <div style="font-size:11.5px;color:#64748B;">متصل عبر <strong style="color:#10B981;">{{ ($activeSession['driver'] ?? '') === 'meta' ? 'WhatsApp Cloud API' : 'Live Website Widget' }}</strong></div>
                    </div>
                </div>

                <div style="display:flex;align-items:center;gap:8px;">
                    <form method="POST" action="{{ route('enterprise.action') }}" style="display:inline;margin:0;">
                        @csrf
                        <input type="hidden" name="action" value="takeover_conversation">
                        <input type="hidden" name="phone" value="{{ $activeSession['phone'] ?? '' }}">
                        <button type="submit" class="btn btn-sm" style="background:#DC2626;color:#FFF;border-radius:8px;font-size:12px;font-weight:600;padding:6px 12px;border:none;cursor:pointer;">
                            🚨 Takeover (استلام بشري)
                        </button>
                    </form>
                    <form method="POST" action="{{ route('enterprise.action') }}" style="display:inline;margin:0;">
                        @csrf
                        <input type="hidden" name="action" value="resume_ai_conversation">
                        <input type="hidden" name="phone" value="{{ $activeSession['phone'] ?? '' }}">
                        <button type="submit" class="btn btn-sm btn-ghost" style="border:1px solid #E2E8F0;font-size:11.5px;cursor:pointer;">🤖 تشغيل AI</button>
                    </form>
                </div>
            </div>

            <!-- Chat Thread -->
            <div style="flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:14px;background:#F8FAFC;">
                @if (!empty($activeSession['last_message']))
                    <div style="align-self:flex-end;max-width:80%;">
                        <div style="font-size:11px;color:#64748B;text-align:left;margin-bottom:3px;">العميل</div>
                        <div class="zoie-chat-bubble-user">
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
                <input type="text" id="manualMsgInput" name="message" placeholder="اكتب رداً يدوياً أو دع الذكاء الاصطناعي يستجيب..." required style="flex:1;background:#F8FAFC;border:1px solid #CBD5E1;border-radius:10px;padding:10px 14px;font-size:13px;color:#0F172A;outline:none;">
                <button type="submit" class="btn btn-sm" style="background:#0F172A;color:#FFF;border-radius:8px;padding:8px 16px;font-weight:600;border:none;cursor:pointer;">إرسال</button>
            </form>
        @else
            <!-- Matching Screenshot 2 exactly -->
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;text-align:center;color:#64748B;padding:40px;">
                <div style="width:72px;height:72px;border-radius:50%;background:#F1F5F9;display:flex;align-items:center;justify-content:center;font-size:32px;margin-bottom:16px;">
                    ✉️
                </div>
                <strong style="font-size:16px;color:#0F172A;margin-bottom:6px;">Select a conversation</strong>
                <p style="font-size:13px;color:#94A3B8;max-width:320px;line-height:1.6;margin:0;">
                    Choose a lead from the list on the left to view their messages and start engaging.
                </p>
            </div>
        @endif
    </div>

    <!-- 3. RIGHT PANE: Contact Info / Privacy Shield -->
    <div class="zoie-msg-pane" style="padding:20px;">
        @if ($activeSession)
            <div style="text-align:center;margin-bottom:20px;">
                <div style="width:56px;height:56px;border-radius:50%;background:#0F172A;color:#FFF;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;margin:0 auto 10px;">
                    💬
                </div>
                <strong style="font-size:15px;color:#0F172A;" dir="ltr">{{ $activeSession['phone'] ?? '' }}</strong>
                <div style="font-size:12px;color:#10B981;font-weight:600;margin-top:2px;">
                    {{ !empty($activeSession['is_verified']) ? '✓ Verified 2FA Account' : 'Active Channel' }}
                </div>
            </div>

            <div style="font-size:12.5px;color:#475569;display:flex;flex-direction:column;gap:10px;">
                <div>
                    <span style="color:#94A3B8;">معرف العميل (Client ID):</span>
                    <strong style="float:left;color:#0F172A;">{{ $activeSession['client_id'] ?? 'غير مربوط' }}</strong>
                </div>
                <div>
                    <span style="color:#94A3B8;">بروتوكول القناة:</span>
                    <strong style="float:left;color:#0F172A;">{{ strtoupper($activeSession['driver'] ?? 'WIDGET') }}</strong>
                </div>
                <div>
                    <span style="color:#94A3B8;">حالة الدرع:</span>
                    <span class="badge" style="background:#F0FDF4;color:#15803D;font-size:11px;float:left;padding:2px 8px;border-radius:6px;font-weight:600;">Zero-Knowledge Protected</span>
                </div>
            </div>

            <div style="margin-top:24px;">
                <a href="/?tab=leads" class="btn" style="width:100%;background:#0F172A;color:#FFF;border-radius:8px;font-size:12.5px;text-align:center;text-decoration:none;display:block;padding:8px 0;box-sizing:border-box;">
                    + ربط بملف Lead في CRM
                </a>
            </div>
        @else
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;text-align:center;color:#94A3B8;padding:20px;">
                <div style="font-size:36px;margin-bottom:10px;">👤</div>
                <strong style="color:#0F172A;font-size:14px;margin-bottom:4px;">لم يتم تحديد محادثة</strong>
                <p style="font-size:12px;margin:0;">اختر محادثة لعرض تفاصيل العميل وحالة درع الخصوصية.</p>
            </div>
        @endif
    </div>
</div>
