@php
    $tickets = $data['tickets'] ?? [];
    $selectedTicket = $tickets[0] ?? null;
@endphp

<div class="card-head" style="margin-bottom:14px">
    <div>
        <h1 style="font-size:20px;margin:0;font-weight:700">تذاكر الدعم ومحرك الوكلاء المتعددين (Emails & Tickets)</h1>
        <p class="card-sub">معالجة تذاكر WHMCS والبريد بذكاء اصطناعي متعدد الوكلاء (Dispatcher -> Specialists -> Reviewer)</p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <form method="POST" action="{{ route('enterprise.action') }}" style="margin:0;">
            @csrf
            <input type="hidden" name="action" value="scan_ticket_with_ai">
            <input type="hidden" name="ticket_id" value="{{ $selectedTicket->id ?? 1 }}">
            <button type="submit" class="btn btn-sm" style="background:#0F172A;color:#FFF;border-radius:8px;font-size:12px;padding:6px 14px;cursor:pointer;border:none;">
                ✨ تشغيل فحص الـ AI الآن
            </button>
        </form>
        <span class="pill pill-green">نواة Multi-Agent: نشطة ✅</span>
    </div>
</div>

<!-- 3-Pane Tickets View -->
<div style="display:grid;grid-template-columns:320px 1fr 280px;gap:16px;height:calc(100vh - 210px);min-height:550px;">
    
    <!-- Pane 1: Tickets List -->
    <div class="card" style="padding:0;overflow:hidden;display:flex;flex-direction:column;">
        <div style="padding:14px;border-bottom:1px solid var(--border,#E2E8F0);background:var(--bg-sub,#F8FAFC);">
            <input type="text" placeholder="بحث برقم التذكرة أو العنوان..." style="width:100%;padding:8px 12px;border:1px solid var(--border,#CBD5E1);border-radius:8px;font-size:12px;outline:none;">
        </div>
        <div style="flex:1;overflow-y:auto;padding:8px;">
            @forelse ($tickets as $idx => $t)
                <div class="ticket-item {{ $idx === 0 ? 'active' : '' }}" style="padding:12px;border-radius:10px;cursor:pointer;margin-bottom:6px;background:{{ $idx === 0 ? 'rgba(99,102,241,0.08)' : 'transparent' }};border:1px solid {{ $idx === 0 ? 'rgba(99,102,241,0.3)' : 'transparent' }};">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span class="pill" style="font-size:11px;padding:2px 8px;background:#1E293B;color:#FFF;">#{{ $t->external_ticket_id ?: $t->id }}</span>
                        <span class="pill {{ $t->status === 'open' ? 'pill-amber' : 'pill-green' }}" style="font-size:10px;">{{ $t->status }}</span>
                    </div>
                    <strong style="font-size:13px;display:block;margin:4px 0;color:var(--text-main);">{{ $t->subject }}</strong>
                    <div style="font-size:11.5px;color:var(--muted);display:flex;justify-content:space-between;">
                        <span>{{ $t->client_name ?: 'عميل WHMCS' }}</span>
                        <span>{{ $t->department ?: 'الدعم الفني' }}</span>
                    </div>
                </div>
            @empty
                <div style="padding:20px;text-align:center;color:var(--muted);">لا توجد تذاكر معلقة حالياً.</div>
            @endforelse
        </div>
    </div>

    <!-- Pane 2: Ticket Workspace & Multi-Agent Timeline -->
    <div class="card" style="padding:20px;overflow-y:auto;display:flex;flex-direction:column;gap:18px;">
        
        <!-- Header -->
        <div style="border-bottom:1px solid var(--border,#E2E8F0);padding-bottom:14px;display:flex;justify-content:space-between;align-items:start;">
            <div>
                <h2 style="margin:0 0 6px 0;font-size:17px;font-weight:800;">{{ $selectedTicket->subject ?? 'تذكرة رقم #98452 — عطل خطأ 500 في موقع ووردبريس' }}</h2>
                <div style="font-size:12px;color:var(--muted);display:flex;gap:12px;">
                    <span>المصدر: <strong>WHMCS LocalAPI</strong></span>
                    <span>القسم: <strong>الدعم الفني</strong></span>
                    <span>الأولوية: <strong style="color:#EF4444;">عالية</strong></span>
                </div>
            </div>
            <span class="pill pill-green" style="font-size:12px;font-weight:bold;">مسودة الذكاء الاصطناعي جاهزة</span>
        </div>

        <!-- Customer Message -->
        <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:12px;padding:16px;">
            <div style="font-size:12px;color:#64748B;margin-bottom:6px;font-weight:600;">رسالة العميل الواردة:</div>
            <p style="margin:0;font-size:13.5px;line-height:1.6;color:#1E293B;">
                {{ $selectedTicket->latest_message ?? 'الموقع بتاعي واقف وبيجيب 500 Internal Server Error بعد ما رفعت إضافة جديدة في ووردبريس، وعايز أعرف هل استهلاك الذاكرة في cPanel هو السبب؟' }}
            </p>
        </div>

        <!-- AttachmentVision Badge -->
        <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:12px;">
            <div style="font-size:20px;">🖼️</div>
            <div style="font-size:12.5px;color:#166534;">
                <strong>نتائج فحص مرفق الصورة (AttachmentVision):</strong>
                <div>رصد خطأ PHP Fatal: Allowed memory size exhausted في سطر 244 بمجلد wp-content/plugins.</div>
            </div>
        </div>

        <!-- Multi-Agent Consultation Timeline -->
        <div style="background:#0F172A;color:#FFFFFF;border-radius:14px;padding:18px;">
            <div style="font-size:13px;font-weight:700;margin-bottom:12px;display:flex;justify-content:space-between;">
                <span>🧠 مسار الاستشارات الذاتية (Multi-Specialist Orchestration)</span>
                <span class="mono" style="color:#38BDF8;font-size:11px;">زمن المعالجة: 284ms</span>
            </div>

            <div style="display:flex;flex-direction:column;gap:10px;font-size:12px;">
                <div style="display:flex;align-items:center;gap:10px;background:rgba(255,255,255,0.06);padding:8px 12px;border-radius:8px;">
                    <span style="color:#34D399;">✓</span>
                    <strong>Dispatcher Agent:</strong> تم الفرز كتذكرة دعم فني ووردبريس + تشخيص سيرفر. تم استدعاء [server-agent] و [wordpress-agent].
                </div>
                <div style="display:flex;align-items:center;gap:10px;background:rgba(255,255,255,0.06);padding:8px 12px;border-radius:8px;">
                    <span style="color:#34D399;">✓</span>
                    <strong>Server Specialist:</strong> فحص cPanel UAPI: استهلاك الذاكرة 256MB وذاكرة الحساب 512MB. يلزم رفع memory_limit إلى 512M في wp-config.php.
                </div>
                <div style="display:flex;align-items:center;gap:10px;background:rgba(255,255,255,0.06);padding:8px 12px;border-radius:8px;">
                    <span style="color:#34D399;">🛡️</span>
                    <strong>Reviewer Verdict:</strong> تم تدقيق الرد بنسبة ثقة 99%. تم حجب مسارات السيرفر الحساسة ومنع الادعاء بأي تكلفة إضافية.
                </div>
            </div>
        </div>

        <!-- Proposed AI Draft Response Box -->
        <form method="POST" action="{{ route('enterprise.action') }}" style="background:#FFFFFF;border:2px solid #8B5CF6;border-radius:14px;padding:18px;box-shadow:0 8px 25px rgba(139,92,246,0.1);margin:0;">
            @csrf
            <input type="hidden" name="action" value="approve_ticket_draft">
            <input type="hidden" name="ticket_id" value="{{ $selectedTicket->id ?? 1 }}">
            <input type="hidden" name="draft_id" value="1">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="background:#8B5CF6;color:#FFF;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:bold;">مسودة الرد المقترحة</span>
                    <span style="color:#64748B;font-size:12px;">جاهزة للاعتماد والنشر المباشر</span>
                </div>
                <span class="pill pill-green" style="font-size:11px;">Reviewer: Approved ✅</span>
            </div>

            <textarea name="approved_text" style="width:100%;height:140px;padding:12px;border:1px solid #CBD5E1;border-radius:10px;font-size:13px;line-height:1.6;outline:none;">أهلاً بك أستاذنا العزيز،

تم فحص سجلات الخادم لخطأ 500 وتبين أن المشكلة سببها وصول استهلاك ذاكرة PHP إلى الحد الأقصى (Allowed memory size exhausted) بسبب الإضافة الحديثة.

لحظر هذا العطل فوراً يمكنك زيادة حد الذاكرة عبر إضافة السطر التالي في ملف wp-config.php:
define('WP_MEMORY_LIMIT', '512M');

كما قمنا بفحص السيرفر عبر cPanel وتأكدنا من سلامة باقي الخدمات والمساحة. إذا كنت ترغب في أن نقوم بهذا التعديل نيابة عنك يسعدنا تأكيد ذلك وسنقوم به فوراً.</textarea>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;">
                <span style="font-size:12px;color:#64748B;">الرد سيُنشر باسم «OnTrack Support AI» على WHMCS</span>
                <div style="display:flex;gap:8px;">
                    <button type="submit" class="btn btn-primary" style="background:#10B981;border-color:#10B981;font-weight:bold;padding:8px 22px;border:none;border-radius:8px;color:#FFF;cursor:pointer;">
                        ✓ اعتماد وإرسال الرد للعميل
                    </button>
                </div>
            </div>
        </form>

    </div>

    <!-- Pane 3: Client Diagnostic Facts -->
    <div class="card" style="padding:18px;display:flex;flex-direction:column;gap:16px;">
        <h3 style="margin:0;font-size:15px;font-weight:700;">تشخيصات الخادم والخدمة</h3>

        <div style="font-size:13px;display:flex;flex-direction:column;gap:8px;">
            <div><span style="color:var(--muted);">النطاق:</span> <strong>[DOMAIN_1] (مُعمّى 🔒)</strong></div>
            <div><span style="color:var(--muted);">حالة الاستضافة:</span> <span class="pill pill-green" style="font-size:11px;">Active</span></div>
            <div><span style="color:var(--muted);">الباقة:</span> <strong>Business Cloud Hosting</strong></div>
            <div><span style="color:var(--muted);">إصدار PHP:</span> <strong>8.2 (ea-php82)</strong></div>
            <div><span style="color:var(--muted);">استهلاك المعالج:</span> <strong>0.38 (طبيعي)</strong></div>
            <div><span style="color:var(--muted);">استهلاك الديسك:</span> <strong>14% (86GB متبقي)</strong></div>
        </div>

        <div style="border-top:1px solid var(--border,#E2E8F0);padding-top:14px;">
            <h4 style="margin:0 0 10px 0;font-size:13px;">أدوات التشخيص عن بُعد:</h4>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <button class="btn btn-sm" style="width:100%;justify-content:center;">🔄 فحص cPanel UAPI لحظي</button>
                <button class="btn btn-sm" style="width:100%;justify-content:center;">📋 سحب آخر 10 أسطر Error Log</button>
            </div>
        </div>
    </div>

</div>
