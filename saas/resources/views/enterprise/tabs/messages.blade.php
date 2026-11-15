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
.zoie-dyn-btn {
    background: #F8FAFC;
    border: 1px solid #CBD5E1;
    color: #0F172A;
    padding: 6px 12px;
    border-radius: 18px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
}
.zoie-dyn-btn:hover {
    background: #0F172A;
    color: #FFFFFF;
    border-color: #0F172A;
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
            <button type="button" class="btn btn-sm" style="background:#0F172A;color:#FFF;border-radius:8px;font-size:12px;padding:4px 10px;">+ New</button>
        </div>

        <!-- Filter Row 1: Agent vs Human -->
        <div style="padding:8px 12px;display:flex;gap:6px;border-bottom:1px solid #F1F5F9;">
            <a href="javascript:void(0)" class="zoie-filter-capsule active">All</a>
            <a href="javascript:void(0)" class="zoie-filter-capsule">Human (0)</a>
            <a href="javascript:void(0)" class="zoie-filter-capsule">AI Agents (3)</a>
        </div>

        <!-- Filter Row 2: Channel Sub-filters -->
        <div style="padding:8px 12px;display:flex;gap:6px;border-bottom:1px solid #F1F5F9;overflow-x:auto;">
            <a href="javascript:void(0)" class="zoie-filter-capsule active" style="font-size:11px;padding:4px 10px;">All</a>
            <a href="javascript:void(0)" class="zoie-filter-capsule" style="font-size:11px;padding:4px 10px;">Unread</a>
            <a href="javascript:void(0)" class="zoie-filter-capsule" style="font-size:11px;padding:4px 10px;">WhatsApp</a>
            <a href="javascript:void(0)" class="zoie-filter-capsule" style="font-size:11px;padding:4px 10px;">Website Widget</a>
        </div>

        <!-- Conversations List -->
        <div style="flex:1;overflow-y:auto;">
            <div style="padding:12px 14px;border-bottom:1px solid #F1F5F9;background:#F8FAFC;cursor:pointer;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                    <strong style="font-size:13px;color:#0F172A;" dir="ltr">+201012345678</strong>
                    <span style="font-size:11px;color:#94A3B8;">02:15 م</span>
                </div>
                <div style="display:flex;align-items:center;gap:6px;">
                    <span style="background:#DCFCE7;color:#15803D;font-size:10.5px;padding:1px 6px;border-radius:4px;font-weight:600;">WhatsApp</span>
                    <span style="font-size:12px;color:#64748B;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">أهلاً، أحتاج ترقية سيرفر cPanel...</span>
                </div>
            </div>

            <div style="padding:12px 14px;border-bottom:1px solid #F1F5F9;cursor:pointer;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                    <strong style="font-size:13px;color:#0F172A;">عميل الودجت #44</strong>
                    <span style="font-size:11px;color:#94A3B8;">11:30 ص</span>
                </div>
                <div style="display:flex;align-items:center;gap:6px;">
                    <span style="background:#E0E7FF;color:#4338CA;font-size:10.5px;padding:1px 6px;border-radius:4px;font-weight:600;">Widget</span>
                    <span style="font-size:12px;color:#64748B;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">ما هي أسعار الباقات السحابية؟</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. CENTER PANE: Active Chat & Dynamic Buttons -->
    <div class="zoie-msg-pane" style="display:flex;flex-direction:column;">
        <!-- Chat Header -->
        <div style="padding:12px 18px;border-bottom:1px solid #E2E8F0;display:flex;align-items:center;justify-content:space-between;background:#FFFFFF;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;border-radius:50%;background:#10B981;color:#FFF;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;">
                    💬
                </div>
                <div>
                    <div style="font-weight:700;font-size:14px;color:#0F172A;" dir="ltr">+201012345678</div>
                    <div style="font-size:11.5px;color:#64748B;">متصل عبر <strong style="color:#10B981;">WhatsApp Official Cloud API</strong></div>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:8px;">
                <form method="POST" action="{{ route('enterprise.action') }}" style="display:inline;margin:0;">
                    @csrf
                    <input type="hidden" name="action" value="takeover_conversation">
                    <input type="hidden" name="phone" value="+201012345678">
                    <button type="submit" class="btn btn-sm" style="background:#DC2626;color:#FFF;border-radius:8px;font-size:12px;font-weight:600;padding:6px 12px;border:none;cursor:pointer;">
                        🚨 Takeover (استلام بشري)
                    </button>
                </form>
                <form method="POST" action="{{ route('enterprise.action') }}" style="display:inline;margin:0;">
                    @csrf
                    <input type="hidden" name="action" value="resume_ai_conversation">
                    <input type="hidden" name="phone" value="+201012345678">
                    <button type="submit" class="btn btn-sm btn-ghost" style="border:1px solid #E2E8F0;font-size:11.5px;cursor:pointer;">🤖 تشغيل AI</button>
                </form>
            </div>
        </div>

        <!-- Chat Thread -->
        <div style="flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:14px;background:#F8FAFC;">
            <!-- Customer message -->
            <div style="align-self:flex-end;max-width:80%;">
                <div style="font-size:11px;color:#64748B;text-align:left;margin-bottom:3px;">العميل • 02:14 م</div>
                <div class="zoie-chat-bubble-user">
                    السلام عليكم، عندي مشكلة في السيرفر ومحتاج أفتح تذكرة دعم فني بخصوص زيادة مساحة الرام.
                </div>
            </div>

            <!-- AI response with Dynamic Buttons -->
            <div style="align-self:flex-start;max-width:80%;">
                <div style="font-size:11px;color:#64748B;margin-bottom:3px;">سارة (OnTrack AI) • 02:15 م</div>
                <div class="zoie-chat-bubble-ai">
                    وعليكم السلام ورحمة الله! جهزت لك مسودة التذكرة التالية:<br><br>
                    📌 <strong>الموضوع:</strong> طلب ترقية ذاكرة RAM للسيرفر السحابي<br>
                    🏢 <strong>القسم:</strong> الدعم الفني وإدارة السيرفرات<br>
                    ⚡ <strong>الأولوية:</strong> متوسطة<br><br>
                    هل تعتمد فتح التذكرة الآن أم ترغب في تعديل الوصف؟
                </div>

                <!-- Dynamic Action Buttons Pills -->
                <div style="display:flex;gap:6px;margin-top:10px;flex-wrap:wrap;">
                    <form method="POST" action="{{ route('enterprise.action') }}" style="display:inline;margin:0;">
                        @csrf
                        <input type="hidden" name="action" value="approve_ticket_draft">
                        <input type="hidden" name="ticket_id" value="1">
                        <input type="hidden" name="draft_id" value="1">
                        <button type="submit" class="zoie-dyn-btn" style="background:#10B981;color:#FFF;border-color:#10B981;cursor:pointer;">
                            ✅ اعتمدها
                        </button>
                    </form>
                    <button type="button" class="zoie-dyn-btn" onclick="document.getElementById('manualMsgInput').focus()">
                        ✏️ تعديل الوصف
                    </button>
                </div>
            </div>
        </div>

        <!-- Chat Input Bar -->
        <form method="POST" action="{{ route('enterprise.action') }}" style="padding:14px 18px;border-top:1px solid #E2E8F0;background:#FFFFFF;display:flex;align-items:center;gap:10px;margin:0;">
            @csrf
            <input type="hidden" name="action" value="send_chat_message">
            <input type="hidden" name="phone" value="+201012345678">
            <input type="text" id="manualMsgInput" name="message" placeholder="اكتب رداً يدوياً أو دع الذكاء الاصطناعي يستجيب..." required style="flex:1;background:#F8FAFC;border:1px solid #CBD5E1;border-radius:10px;padding:10px 14px;font-size:13px;color:#0F172A;outline:none;">
            <button type="submit" class="btn btn-sm" style="background:#0F172A;color:#FFF;border-radius:8px;padding:8px 16px;font-weight:600;border:none;cursor:pointer;">إرسال</button>
        </form>
    </div>

    <!-- 3. RIGHT PANE: Contact & Verification Context -->
    <div class="zoie-msg-pane" style="padding:18px;">
        <div style="text-align:center;padding-bottom:14px;border-bottom:1px solid #F1F5F9;margin-bottom:14px;">
            <div style="width:52px;height:52px;border-radius:50%;background:#F1F5F9;display:flex;align-items:center;justify-content:center;font-size:20px;margin:0 auto 8px;">
                👤
            </div>
            <strong style="font-size:14px;color:#0F172A;" dir="ltr">+201012345678</strong>
            <div style="font-size:12px;color:#10B981;font-weight:600;margin-top:2px;">✓ Verified 2FA Account</div>
        </div>

        <div style="font-size:12.5px;color:#475569;display:flex;flex-direction:column;gap:10px;">
            <div>
                <span style="color:#94A3B8;">رقم العميل في WHMCS:</span>
                <strong style="float:left;color:#0F172A;">#1042</strong>
            </div>
            <div>
                <span style="color:#94A3B8;">النطاق المسجل:</span>
                <strong style="float:left;color:#0F172A;" dir="ltr">myserver.eg</strong>
            </div>
            <div>
                <span style="color:#94A3B8;">الباقة الحالية:</span>
                <strong style="float:left;color:#0F172A;">Cloud VPS 4GB</strong>
            </div>
            <div>
                <span style="color:#94A3B8;">حالة الدرع:</span>
                <span class="badge" style="background:#F0FDF4;color:#15803D;font-size:11px;float:left;padding:2px 8px;border-radius:6px;font-weight:600;">Zero-Knowledge Tokenized</span>
            </div>
        </div>

        <div style="margin-top:20px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;padding:12px;">
            <strong style="font-size:12px;color:#0F172A;">إجراءات القناة:</strong>
            <div style="display:flex;flex-direction:column;gap:6px;margin-top:8px;">
                <button type="button" class="btn btn-sm btn-ghost" style="border:1px solid #E2E8F0;font-size:11.5px;justify-content:flex-start;" onclick="alert('تم إرسال كود تحقق 2FA جديد للعميل')">
                    🔑 إعادة إرسال كود 2FA
                </button>
                <button type="button" class="btn btn-sm btn-ghost" style="border:1px solid #E2E8F0;font-size:11.5px;justify-content:flex-start;" onclick="alert('تم تحويل المحادثة إلى مكالمة صوتية')">
                    📞 تحويل لمكالمة صوتية
                </button>
            </div>
        </div>
    </div>
</div>
