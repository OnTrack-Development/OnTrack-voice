@php
    $sessions = $data['sessions'] ?? [];
    $selectedSession = $sessions[0] ?? null;
@endphp

<div class="card-head" style="margin-bottom:14px">
    <div>
        <h1 style="font-size:20px;margin:0;font-weight:700">صندوق المحادثات الموحد (Omnichannel Messages)</h1>
        <p class="card-sub">إدارة رسائل WhatsApp الرسمي (Meta) و WhatsApp المكتب (QR) وشات الويب مع الأزرار التفاعلية</p>
    </div>
    <div style="display:flex;gap:8px;">
        <span class="pill pill-green">Meta Official: متصل ✅</span>
        <span class="pill pill-green">Baileys QR: نشط ✅</span>
    </div>
</div>

<!-- 3-Pane Omnichannel Messages View -->
<div style="display:grid;grid-template-columns:300px 1fr 280px;gap:16px;height:calc(100vh - 210px);min-height:550px;">
    
    <!-- Pane 1: Conversations List -->
    <div class="card" style="padding:0;overflow:hidden;display:flex;flex-direction:column;">
        <div style="padding:14px;border-bottom:1px solid var(--border,#E2E8F0);background:var(--bg-sub,#F8FAFC);">
            <input type="text" placeholder="بحث في المحادثات..." style="width:100%;padding:8px 12px;border:1px solid var(--border,#CBD5E1);border-radius:8px;font-size:12px;outline:none;">
        </div>
        <div style="flex:1;overflow-y:auto;padding:8px;">
            @forelse ($sessions as $idx => $s)
                <div class="msg-thread-item {{ $idx === 0 ? 'active' : '' }}" style="padding:12px;border-radius:10px;cursor:pointer;margin-bottom:6px;background:{{ $idx === 0 ? 'rgba(99,102,241,0.08)' : 'transparent' }};border:1px solid {{ $idx === 0 ? 'rgba(99,102,241,0.3)' : 'transparent' }};">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <strong style="font-size:13px;" dir="ltr">{{ $s['phone'] ?? 'زائر شات الويب' }}</strong>
                        <span class="mono" style="font-size:10.5px;color:var(--muted,#94A3B8);">منذ قليل</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                        <span class="pill" style="font-size:10px;padding:2px 6px;background:{{ ($s['driver'] ?? '') === 'meta' ? '#065F46' : '#1E3A8A' }};color:#FFF;">
                            {{ ($s['driver'] ?? '') === 'meta' ? 'WhatsApp Meta' : 'WhatsApp QR' }}
                        </span>
                        @if(!empty($s['is_verified']))
                            <span class="pill pill-green" style="font-size:10px;padding:2px 6px;">2FA موثق ✓</span>
                        @endif
                    </div>
                    <div style="font-size:12px;color:var(--muted,#64748B);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        {{ ($s['state'] ?? '') === 'wa_ticket_create' ? '📋 في انتظار مراجعة مسودة التذكرة' : 'آخر رد: شكراً لتواصلك معنا...' }}
                    </div>
                </div>
            @empty
                <div style="padding:20px;text-align:center;color:var(--muted);">لا توجد محادثات مسجلة بعد.</div>
            @endforelse
        </div>
    </div>

    <!-- Pane 2: Message Thread & Interactive Buttons -->
    <div class="card" style="padding:0;overflow:hidden;display:flex;flex-direction:column;">
        <div style="padding:14px 20px;border-bottom:1px solid var(--border,#E2E8F0);background:var(--bg-sub,#F8FAFC);display:flex;justify-content:space-between;align-items:center;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;border-radius:50%;background:#8B5CF6;color:#FFF;display:flex;align-items:center;justify-content:center;font-weight:bold;">WA</div>
                <div>
                    <strong style="font-size:14px;" dir="ltr">{{ $selectedSession['phone'] ?? '+201012345678' }}</strong>
                    <div style="font-size:11px;color:var(--muted);">الحالة: {{ ($selectedSession['state'] ?? 'idle') === 'idle' ? 'محادثة عادية (الذكاء الاصطناعي نشط)' : 'مسار جمع واعتماد التذكرة' }}</div>
                </div>
            </div>
            <button class="btn btn-sm" onclick="alert('تم تفعيل الصمت للإيجنت لمدة 60 دقيقة. يمكنك التحدث مع العميل بحرية.')" style="background:#EF4444;color:#FFF;border:none;">
                🚨 استلام المحادثة (Mute AI)
            </button>
        </div>

        <!-- Chat Stream -->
        <div style="flex:1;padding:20px;overflow-y:auto;display:flex;flex-direction:column;gap:14px;background:#F8FAFC;">
            <div style="align-self:flex-end;background:#2563EB;color:#FFF;padding:10px 16px;border-radius:14px;max-width:75%;font-size:13px;">
                السلام عليكم، عايز أعرف باقات الاستضافة المشتركة وسيرفرات الـ VPS المتاحة حالياً عندكم.
            </div>

            <div style="align-self:flex-start;background:#FFFFFF;border:1px solid #E2E8F0;color:#1E293B;padding:14px;border-radius:14px;max-width:85%;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);">
                أهلاً بك! نوفر باقات استضافة سحابية متقدمة تبدأ من 150 جنيه/شهرياً، وباقات VPS مدعومة بلوحة cPanel. يمكنك مراجعة التفاصيل أو فتح تذكرة عبر الخيارات أدناه:
                
                <!-- Rendered Dynamic Interactive Buttons -->
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;padding-top:10px;border-top:1px dashed #CBD5E1;">
                    <button class="btn btn-sm" style="background:#F1F5F9;border:1px solid #CBD5E1;color:#1E293B;border-radius:20px;font-size:12px;">🌐 باقات الاستضافة</button>
                    <button class="btn btn-sm" style="background:#F1F5F9;border:1px solid #CBD5E1;color:#1E293B;border-radius:20px;font-size:12px;">⚡ باقات VPS</button>
                    <button class="btn btn-sm" style="background:#F1F5F9;border:1px solid #CBD5E1;color:#1E293B;border-radius:20px;font-size:12px;">🎫 فتح تذكرة دعم</button>
                    <button class="btn btn-sm" style="background:#F1F5F9;border:1px solid #CBD5E1;color:#1E293B;border-radius:20px;font-size:12px;">👤 تحويل لموظف</button>
                </div>
            </div>

            <div style="align-self:flex-end;background:#2563EB;color:#FFF;padding:10px 16px;border-radius:14px;max-width:75%;font-size:13px;">
                ممكن مسودة تذكرة بطلب استشارة لسيرفر VPS بمواصفات 4 Core و 8GB RAM؟
            </div>

            <div style="align-self:flex-start;background:#FFFFFF;border:1px solid #E2E8F0;color:#1E293B;padding:14px;border-radius:14px;max-width:85%;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);">
                📋 <strong>مسودة التذكرة (لم تُنشأ بعد):</strong><br>
                📌 <strong>العنوان:</strong> طلب استشارة وتجهيز سيرفر VPS 4 Core 8GB RAM<br>
                📝 <strong>الوصف:</strong> استفسار العميل عن أسعار وتوافر باقة VPS بمواصفات 4 Core و 8GB RAM مع إعداد cPanel.<br><br>
                يرجى مراجعة التفاصيل واعتمادها:
                
                <!-- State Action Buttons -->
                <div style="display:flex;gap:8px;margin-top:10px;">
                    <button class="btn btn-sm" style="background:#10B981;color:#FFF;border:none;border-radius:20px;font-size:12px;">اعتمدها ✅</button>
                    <button class="btn btn-sm" style="background:#E2E8F0;color:#1E293B;border:none;border-radius:20px;font-size:12px;">تعديل الوصف ✏️</button>
                    <button class="btn btn-sm" style="background:#EF4444;color:#FFF;border:none;border-radius:20px;font-size:12px;">إلغاء الطلب ❌</button>
                </div>
            </div>
        </div>

        <div style="padding:12px 16px;border-top:1px solid var(--border,#E2E8F0);background:#FFF;display:flex;gap:10px;">
            <input type="text" placeholder="اكتب رداً يدوي للعميل هنا..." style="flex:1;padding:10px 14px;border:1px solid #CBD5E1;border-radius:10px;outline:none;font-size:13px;">
            <button class="btn btn-primary" style="padding:0 20px;">إرسال</button>
        </div>
    </div>

    <!-- Pane 3: Client Facts & Actions -->
    <div class="card" style="padding:18px;display:flex;flex-direction:column;gap:16px;">
        <h3 style="margin:0;font-size:15px;font-weight:700;">بيانات العميل (CRM Facts)</h3>
        
        <div style="font-size:13px;display:flex;flex-direction:column;gap:8px;">
            <div><span style="color:var(--muted);">الرقم:</span> <strong dir="ltr">{{ $selectedSession['phone'] ?? '+201012345678' }}</strong></div>
            <div><span style="color:var(--muted);">معرف WHMCS:</span> <strong>#{{ $selectedSession['client_id'] ?? '1042' }}</strong></div>
            <div><span style="color:var(--muted);">حالة التوثيق:</span> <span class="pill pill-green" style="font-size:11px;">موثق 2FA ✅</span></div>
            <div><span style="color:var(--muted);">الخدمات النشطة:</span> <strong>2 سيرفر VPS + 1 استضافة</strong></div>
            <div><span style="color:var(--muted);">الفواتير المستحقة:</span> <span style="color:#10B981;font-weight:bold;">0.00 جنيه (سداد كامل)</span></div>
        </div>

        <div style="border-top:1px solid var(--border,#E2E8F0);padding-top:14px;">
            <h4 style="margin:0 0 10px 0;font-size:13px;">إجراءات سريعة:</h4>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <button class="btn btn-sm" style="width:100%;justify-content:center;">🔗 إرسال رابط تأكيد 2FA جديد</button>
                <button class="btn btn-sm btn-primary" style="width:100%;justify-content:center;">🎫 تحويل لتذكرة رسمية</button>
            </div>
        </div>
    </div>

</div>
