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
        @if ($selectedTicket)
            <form method="POST" action="{{ route('enterprise.action') }}" style="margin:0;">
                @csrf
                <input type="hidden" name="action" value="scan_ticket_with_ai">
                <input type="hidden" name="ticket_id" value="{{ $selectedTicket->id ?? 1 }}">
                <button type="submit" class="btn btn-sm" style="background:#0F172A;color:#FFF;border-radius:8px;font-size:12px;padding:6px 14px;cursor:pointer;border:none;">
                    ✨ تشغيل فحص الـ AI الآن
                </button>
            </form>
        @endif
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
                <div style="padding:40px 16px;text-align:center;color:#64748B;">
                    <div style="font-size:36px;margin-bottom:10px;">🎫</div>
                    <strong style="font-size:14px;color:#0F172A;display:block;margin-bottom:4px;">لا توجد تذاكر معلقة حالياً</strong>
                    <p style="font-size:12px;color:#94A3B8;margin:0;">لم يتم فتح أي تذاكر دعم فني بعد.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Pane 2: Ticket Workspace & Multi-Agent Timeline -->
    <div class="card" style="padding:20px;overflow-y:auto;display:flex;flex-direction:column;gap:18px;">
        @if ($selectedTicket)
            <!-- Header -->
            <div style="border-bottom:1px solid var(--border,#E2E8F0);padding-bottom:14px;display:flex;justify-content:space-between;align-items:start;">
                <div>
                    <h2 style="margin:0 0 6px 0;font-size:17px;font-weight:800;">{{ $selectedTicket->subject }}</h2>
                    <div style="font-size:12px;color:var(--muted);display:flex;gap:12px;">
                        <span>المصدر: <strong>WHMCS LocalAPI</strong></span>
                        <span>القسم: <strong>{{ $selectedTicket->department ?? 'الدعم الفني' }}</strong></span>
                        <span>الأولوية: <strong>{{ $selectedTicket->priority ?? 'عادية' }}</strong></span>
                    </div>
                </div>
                <span class="pill pill-green" style="font-size:12px;font-weight:bold;">تذكرة نشطة</span>
            </div>

            <!-- Customer Message -->
            <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:12px;padding:16px;">
                <div style="font-size:12px;color:#64748B;margin-bottom:6px;font-weight:600;">رسالة العميل الواردة:</div>
                <p style="margin:0;font-size:13.5px;line-height:1.6;color:#1E293B;">
                    {{ $selectedTicket->latest_message ?? 'لا يوجد نص مرفق في الرسالة.' }}
                </p>
            </div>

            <!-- Proposed AI Draft Response Box -->
            @php
                $draft = $selectedTicket->drafts->first() ?? null;
            @endphp
            @if ($draft)
                <form method="POST" action="{{ route('enterprise.action') }}" style="background:#FFFFFF;border:2px solid #8B5CF6;border-radius:14px;padding:18px;box-shadow:0 8px 25px rgba(139,92,246,0.1);margin:0;">
                    @csrf
                    <input type="hidden" name="action" value="approve_ticket_draft">
                    <input type="hidden" name="ticket_id" value="{{ $selectedTicket->id }}">
                    <input type="hidden" name="draft_id" value="{{ $draft->id }}">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span style="background:#8B5CF6;color:#FFF;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:bold;">مسودة الرد المقترحة</span>
                            <span style="color:#64748B;font-size:12px;">جاهزة للاعتماد والنشر المباشر</span>
                        </div>
                        <span class="pill pill-green" style="font-size:11px;">Reviewer: Approved ✅</span>
                    </div>

                    <textarea name="approved_text" style="width:100%;height:140px;padding:12px;border:1px solid #CBD5E1;border-radius:10px;font-size:13px;line-height:1.6;outline:none;">{{ $draft->proposed_reply }}</textarea>

                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;">
                        <span style="font-size:12px;color:#64748B;">الرد سيُنشر باسم «OnTrack Support AI» على WHMCS</span>
                        <div style="display:flex;gap:8px;">
                            <button type="submit" class="btn btn-primary" style="background:#10B981;border-color:#10B981;font-weight:bold;padding:8px 22px;border:none;border-radius:8px;color:#FFF;cursor:pointer;">
                                ✓ اعتماد وإرسال الرد للعميل
                            </button>
                        </div>
                    </div>
                </form>
            @endif
        @else
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;text-align:center;color:#64748B;padding:40px;">
                <div style="font-size:52px;margin-bottom:14px;">🎫</div>
                <strong style="font-size:16px;color:#0F172A;margin-bottom:6px;">لا توجد تذاكر مفتوحة حالياً</strong>
                <p style="font-size:13px;color:#94A3B8;max-width:340px;line-height:1.6;margin:0;">
                    عند فتح أي تذكرة عبر إضافة ontrack_bridge في WHMCS أو عبر البريد، سيقوم محرك الذكاء الاصطناعي بتشخيص المشكلة وعرضها هنا للمراجعة والاعتماد.
                </p>
            </div>
        @endif
    </div>

    <!-- Pane 3: Client Diagnostic Facts -->
    <div class="card" style="padding:18px;display:flex;flex-direction:column;gap:16px;">
        @if ($selectedTicket)
            <h3 style="margin:0;font-size:15px;font-weight:700;">تشخيصات التذكرة والعميل</h3>
            <div style="font-size:13px;display:flex;flex-direction:column;gap:8px;">
                <div><span style="color:var(--muted);">العميل:</span> <strong>{{ $selectedTicket->client_name ?? 'عميل موثق' }}</strong></div>
                <div><span style="color:var(--muted);">حالة التذكرة:</span> <span class="pill pill-green" style="font-size:11px;">{{ $selectedTicket->status }}</span></div>
                <div><span style="color:var(--muted);">المعرف الخارجي:</span> <strong>#{{ $selectedTicket->external_ticket_id ?: $selectedTicket->id }}</strong></div>
            </div>
        @else
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;text-align:center;color:#94A3B8;padding:20px;">
                <div style="font-size:36px;margin-bottom:10px;">🛡️</div>
                <strong style="color:#0F172A;font-size:14px;margin-bottom:4px;">لم يتم تحديد تذكرة</strong>
                <p style="font-size:12px;margin:0;">اختر تذكرة من القائمة لعرض بيانات التشخيص والتحقق.</p>
            </div>
        @endif
    </div>

</div>
