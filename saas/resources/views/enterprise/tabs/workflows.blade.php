@php
    $workflows = $data['workflows'] ?? [];
    $activeFlow = $workflows[0] ?? null;
@endphp

<div class="card-head" style="margin-bottom:16px;">
    <div>
        <h1 style="font-size:22px;margin:0 0 4px 0;font-weight:800;color:#0F172A;">التدفقات ومحرك الأتمتة (Workflows & Automations)</h1>
        <p style="margin:0;font-size:13px;color:#64748B;">بناء تدفقات العمل الآلية بعد المكالمات والمحادثات وحجز المواعيد مع ميزة البناء التلقائي بالذكاء الاصطناعي</p>
    </div>
</div>

<!-- Build with AI Banner Box -->
<div style="background:linear-gradient(135deg, #0F172A, #1E1B4B);color:#FFFFFF;border-radius:14px;padding:22px;margin-bottom:20px;box-shadow:0 4px 20px rgba(15,23,42,0.15);">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
        <span style="font-size:20px;">✨</span>
        <strong style="font-size:16px;">ابنِ تدفقاتك باللغة الطبيعية (Build with AI)</strong>
    </div>
    <p style="font-size:13px;color:#94A3B8;margin:0 0 14px;max-width:700px;line-height:1.5;">
        اكتب الفكرة التي تريدها بأي لغة وسيقوم المساعد بتحويلها إلى Trigger و Conditions و Actions فوراً دون أي برمجة.
    </p>
    <form method="POST" action="{{ route('enterprise.action') }}" style="display:flex;gap:10px;margin:0;">
        @csrf
        <input type="hidden" name="action" value="ai_build_workflow">
        <input type="text" name="prompt" placeholder="مثال: لو العميل اتكلم أكتر من دقيقة وكان مهتم بسيرفر VPS، ابعتله رسالة واتساب فيها لينك الحجز وأضفه كـ Lead مؤهل..." required style="flex:1;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.15);color:#FFF;padding:10px 16px;border-radius:10px;font-size:13px;outline:none;">
        <button type="submit" class="btn btn-sm" style="background:linear-gradient(135deg, #8B5CF6, #06B6D4);color:#FFF;border:none;border-radius:10px;padding:10px 20px;font-weight:700;cursor:pointer;">
            توليد التدفق 🪄
        </button>
    </form>
</div>

@if ($activeFlow)
<!-- Visual Flow Preview -->
<div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:14px;padding:24px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <strong style="font-size:15px;color:#0F172A;">مخطط التدفق: «{{ $activeFlow['title'] ?? 'تدفق أتمتة' }}»</strong>
        <span class="pill pill-green">نشط ويعمل لحظياً ✅</span>
    </div>

    <!-- Nodes Pipeline -->
    <div style="display:flex;align-items:center;gap:16px;overflow-x:auto;padding:14px 6px;">
        <!-- Node 1: Trigger -->
        <div style="background:#F8FAFC;border:2px solid #3B82F6;border-radius:12px;padding:16px;width:220px;flex-shrink:0;">
            <div style="font-size:11px;font-weight:800;color:#2563EB;margin-bottom:4px;">TRIGGER (المُحفز)</div>
            <strong style="font-size:13px;color:#0F172A;display:block;margin-bottom:4px;">{{ $activeFlow['trigger'] ?? 'Trigger Event' }}</strong>
            <div style="font-size:11.5px;color:#64748B;">شرط الانطلاق الأولي</div>
        </div>

        <div style="font-size:20px;color:#94A3B8;">→</div>

        <!-- Node 2: Action -->
        <div style="background:#F8FAFC;border:2px solid #10B981;border-radius:12px;padding:16px;width:220px;flex-shrink:0;">
            <div style="font-size:11px;font-weight:800;color:#059669;margin-bottom:4px;">ACTION (الإجراء الآلي)</div>
            <strong style="font-size:13px;color:#0F172A;display:block;margin-bottom:4px;">{{ $activeFlow['action'] ?? 'Automation Action' }}</strong>
            <div style="font-size:11.5px;color:#64748B;">تنفيذ الإجراء فور تحقق الشرط</div>
        </div>
    </div>
</div>
@endif

<!-- Active Automations List -->
<div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
    <div style="padding:16px 20px;border-bottom:1px solid #E2E8F0;display:flex;justify-content:space-between;align-items:center;">
        <strong style="font-size:15px;color:#0F172A;">قواعد الأتمتة المفعلة (Active Automation Rules)</strong>
        <span style="font-size:12px;color:#64748B;">{{ count($workflows) }} قواعد نشطة</span>
    </div>

    <table style="width:100%;border-collapse:collapse;text-align:right;font-size:13px;">
        <thead>
            <tr style="background:#F8FAFC;border-bottom:1px solid #E2E8F0;color:#64748B;font-size:12px;">
                <th style="padding:12px 18px;">اسم التدفق</th>
                <th style="padding:12px 16px;">المحفز (Trigger)</th>
                <th style="padding:12px 16px;">الإجراءات (Actions)</th>
                <th style="padding:12px 16px;">مرات التنفيذ</th>
                <th style="padding:12px 16px;">الحالة</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($workflows as $wf)
                <tr style="border-bottom:1px solid #F1F5F9;">
                    <td style="padding:14px 18px;font-weight:700;color:#0F172A;">{{ $wf['title'] ?? 'تدفق عمل' }}</td>
                    <td style="padding:14px 16px;">{{ $wf['trigger'] ?? '' }}</td>
                    <td style="padding:14px 16px;">{{ $wf['action'] ?? '' }}</td>
                    <td style="padding:14px 16px;font-family:monospace;">{{ $wf['executions'] ?? 0 }} مرة</td>
                    <td style="padding:14px 16px;"><span class="pill pill-green" style="font-size:11px;">{{ $wf['status'] ?? 'active' }}</span></td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="padding:36px;text-align:center;color:#64748B;">
                        لا توجد قواعد أتمتة منشأة بعد. اكتب فكرتك في الحقل أعلاه لتوليد أول تدفق عمل ذكي فوراً!
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
