@php
    $leads = $data['leads'] ?? [];
    if (empty($leads)) {
        $leads = [
            [
                'id' => 1,
                'name' => 'أحمد محمود',
                'phone' => '+201012345678',
                'email' => 'ahmed.m@example.com',
                'source' => 'مكالمة هاتفية (SIM)',
                'interest' => 'سيرفر VPS سحابي',
                'status' => 'qualified',
                'status_label' => 'مؤهل (Qualified)',
                'agent_name' => 'Zoie',
                'value' => '$1,200',
                'created_at' => 'اليوم 01:45 م',
            ],
            [
                'id' => 2,
                'name' => 'سارة إبراهيم',
                'phone' => '+201122334455',
                'email' => 'sara@domain.eg',
                'source' => 'واتساب (WhatsApp)',
                'interest' => 'استضافة ووردبريس مدارة',
                'status' => 'contacted',
                'status_label' => 'تم التواصل',
                'agent_name' => 'Zoie',
                'value' => '$450',
                'created_at' => 'أمس 04:20 م',
            ],
            [
                'id' => 3,
                'name' => 'شركة النيل للبرمجيات',
                'phone' => '+201288776655',
                'email' => 'info@nilesoft.com',
                'source' => 'ودجت الموقع (Widget)',
                'interest' => 'خادم مخصص Dedicated',
                'status' => 'converted',
                'status_label' => 'تم الإغلاق (Closed)',
                'agent_name' => 'Zoie',
                'value' => '$3,800',
                'created_at' => '13 نوفمبر',
            ],
            [
                'id' => 4,
                'name' => 'محمد كمال',
                'phone' => '+201500112233',
                'email' => 'm.kamal@cloud.io',
                'source' => 'تذكرة WHMCS',
                'interest' => 'ترقية ترخيص cPanel',
                'status' => 'qualified',
                'status_label' => 'مؤهل (Qualified)',
                'agent_name' => 'Zoie',
                'value' => '$850',
                'created_at' => '12 نوفمبر',
            ],
        ];
    }
@endphp

<div class="card-head" style="margin-bottom:16px;">
    <div>
        <h1 style="font-size:22px;margin:0 0 4px 0;font-weight:800;color:#0F172A;">العملاء المحتملون (Leads CRM)</h1>
        <p style="margin:0;font-size:13px;color:#64748B;">إدارة خط المبيعات والعملاء المحتملين القادمين تلقائياً من المكالمات والشات والواتساب</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <button type="button" class="btn btn-sm btn-ghost" onclick="alert('تصدير بيانات العملاء إلى Excel')" style="background:#FFF;border:1px solid #E2E8F0;color:#0F172A;font-weight:600;">
            📊 تصدير Excel
        </button>
        <button type="button" class="btn btn-sm" onclick="openCreateLeadModal()" style="background:#0F172A;color:#FFFFFF;border:none;border-radius:8px;font-weight:600;padding:8px 16px;">
            + إضافة عميل محتمل
        </button>
    </div>
</div>

<!-- Top Metrics Counter Strip (Matching Zoie.ai Leads Pipeline) -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:14px;margin-bottom:20px;">
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size:12px;color:#64748B;font-weight:600;margin-bottom:6px;">Leads (إجمالي العملاء)</div>
        <div style="font-size:24px;font-weight:800;color:#0F172A;">{{ count($leads) + 20 }}</div>
        <div style="font-size:11.5px;color:#10B981;font-weight:600;margin-top:4px;">↑ +14% هذا الأسبوع</div>
    </div>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size:12px;color:#64748B;font-weight:600;margin-bottom:6px;">Appointments (المواعيد المحجوزة)</div>
        <div style="font-size:24px;font-weight:800;color:#0F172A;">8</div>
        <div style="font-size:11.5px;color:#3B82F6;font-weight:600;margin-top:4px;">حجزها الإيجنت تلقائياً</div>
    </div>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size:12px;color:#64748B;font-weight:600;margin-bottom:6px;">No-shows (لم يحضروا)</div>
        <div style="font-size:24px;font-weight:800;color:#EF4444;">1</div>
        <div style="font-size:11.5px;color:#64748B;margin-top:4px;">مجدول لإعادة التواصل</div>
    </div>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size:12px;color:#64748B;font-weight:600;margin-bottom:6px;">Closed Won (تم الإغلاق)</div>
        <div style="font-size:24px;font-weight:800;color:#10B981;">5</div>
        <div style="font-size:11.5px;color:#10B981;font-weight:600;margin-top:4px;">معدل تحويل 21%</div>
    </div>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size:12px;color:#64748B;font-weight:600;margin-bottom:6px;">Revenue Pipeline (قيمة الصفقات)</div>
        <div style="font-size:24px;font-weight:800;color:#0F172A;">$12,450</div>
        <div style="font-size:11.5px;color:#8B5CF6;font-weight:600;margin-top:4px;">متوقع إغلاقه هذا الشهر</div>
    </div>
</div>

<!-- Filters and View Toggle Bar -->
<div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <input type="text" placeholder="بحث بالاسم أو الهاتف أو الإيميل..." style="background:#F8FAFC;border:1px solid #CBD5E1;border-radius:8px;padding:7px 14px;font-size:12.5px;width:240px;color:#0F172A;outline:none;">
        <div style="display:flex;gap:6px;">
            <button type="button" class="btn btn-sm" style="background:#0F172A;color:#FFF;border-radius:20px;font-size:11.5px;padding:4px 12px;">الكل</button>
            <button type="button" class="btn btn-sm btn-ghost" style="border:1px solid #E2E8F0;border-radius:20px;font-size:11.5px;padding:4px 12px;color:#475569;">مؤهل (Qualified)</button>
            <button type="button" class="btn btn-sm btn-ghost" style="border:1px solid #E2E8F0;border-radius:20px;font-size:11.5px;padding:4px 12px;color:#475569;">تم التواصل</button>
            <button type="button" class="btn btn-sm btn-ghost" style="border:1px solid #E2E8F0;border-radius:20px;font-size:11.5px;padding:4px 12px;color:#475569;">تم الإغلاق</button>
        </div>
    </div>
    <div style="display:flex;gap:6px;background:#F1F5F9;padding:3px;border-radius:8px;">
        <button type="button" class="btn btn-sm" style="background:#FFF;color:#0F172A;border-radius:6px;box-shadow:0 1px 2px rgba(0,0,0,0.05);font-size:12px;font-weight:600;">☰ قائمة</button>
        <button type="button" class="btn btn-sm btn-ghost" onclick="alert('عرض كانبان قريباً')" style="color:#64748B;border-radius:6px;font-size:12px;">☷ كانبان</button>
    </div>
</div>

<!-- Leads Data Table -->
<div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
    <table style="width:100%;border-collapse:collapse;text-align:right;font-size:13px;">
        <thead>
            <tr style="background:#F8FAFC;border-bottom:1px solid #E2E8F0;color:#64748B;font-weight:600;font-size:12px;">
                <th style="padding:12px 18px;">الاسم وبيانات الاتصال</th>
                <th style="padding:12px 16px;">المصدر</th>
                <th style="padding:12px 16px;">الاهتمام / الخدمة</th>
                <th style="padding:12px 16px;">قيمة الصفقة</th>
                <th style="padding:12px 16px;">الحالة</th>
                <th style="padding:12px 16px;">الإيجنت المسؤول</th>
                <th style="padding:12px 16px;">التاريخ</th>
                <th style="padding:12px 18px;text-align:center;">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($leads as $l)
                @php $lArr = (array) $l; @endphp
                <tr style="border-bottom:1px solid #F1F5F9;transition:background 0.15s;" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='#FFF'">
                    <td style="padding:14px 18px;">
                        <div style="font-weight:700;color:#0F172A;">{{ $lArr['name'] ?: 'عميل محتمل جديد' }}</div>
                        <div style="font-size:11.5px;color:#64748B;margin-top:2px;" dir="ltr">{{ $lArr['phone'] ?: '—' }} · {{ $lArr['email'] ?? '' }}</div>
                    </td>
                    <td style="padding:14px 16px;">
                        <span style="background:#F1F5F9;color:#334155;padding:3px 8px;border-radius:6px;font-size:11px;font-weight:600;">
                            {{ $lArr['source'] ?? 'مكالمة هاتفية' }}
                        </span>
                    </td>
                    <td style="padding:14px 16px;color:#0F172A;font-weight:600;">
                        {{ $lArr['interest'] ?? 'استضافة سحابية' }}
                    </td>
                    <td style="padding:14px 16px;color:#0F172A;font-weight:700;font-family:monospace;">
                        {{ $lArr['value'] ?? '$500' }}
                    </td>
                    <td style="padding:14px 16px;">
                        @php
                            $st = $lArr['status'] ?? 'qualified';
                            $bg = '#F0FDF4'; $fg = '#16A34A';
                            if ($st === 'contacted') { $bg = '#EFF6FF'; $fg = '#2563EB'; }
                            if ($st === 'converted') { $bg = '#FAF5FF'; $fg = '#7C3AED'; }
                        @endphp
                        <span style="background:{{ $bg }};color:{{ $fg }};padding:3px 10px;border-radius:12px;font-size:11.5px;font-weight:700;">
                            {{ $lArr['status_label'] ?? 'مؤهل' }}
                        </span>
                    </td>
                    <td style="padding:14px 16px;">
                        <span style="color:#8B5CF6;font-weight:600;font-size:12px;">✨ {{ $lArr['agent_name'] ?? 'Zoie' }}</span>
                    </td>
                    <td style="padding:14px 16px;color:#94A3B8;font-size:11.5px;">
                        {{ $lArr['created_at'] ?? 'اليوم' }}
                    </td>
                    <td style="padding:14px 18px;text-align:center;">
                        <div style="display:flex;gap:6px;justify-content:center;">
                            <button type="button" class="btn btn-sm btn-icon" style="background:#F1F5F9;border:none;border-radius:6px;" title="اتصال هاتفي" onclick="alert('بدء اتصال عبر شريحة SIM')">📞</button>
                            <button type="button" class="btn btn-sm btn-icon" style="background:#F1F5F9;border:none;border-radius:6px;" title="محادثة واتساب" onclick="alert('فتح محادثة واتساب')">💬</button>
                            <button type="button" class="btn btn-sm btn-icon" style="background:#F1F5F9;border:none;border-radius:6px;" title="عرض الملف الكامل" onclick="alert('عرض ملف العميل الكامل')">👁️</button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<!-- Modal: + Create New Lead -->
<div id="createLeadModal" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.45);backdrop-filter:blur(4px);z-index:9999;display:none;align-items:center;justify-content:center;">
    <div style="background:#FFFFFF;border-radius:16px;width:100%;max-width:500px;box-shadow:0 20px 40px rgba(0,0,0,0.15);overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid #E2E8F0;display:flex;align-items:center;justify-content:space-between;">
            <strong style="font-size:16px;color:#0F172A;">إضافة عميل محتمل جديد (New Lead)</strong>
            <button type="button" onclick="closeCreateLeadModal()" style="background:none;border:none;font-size:18px;color:#64748B;cursor:pointer;">✕</button>
        </div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:14px;">
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">الاسم الكامل</label>
                <input type="text" placeholder="مثال: م. أحمد السعيد" style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">رقم الهاتف</label>
                <input type="text" placeholder="+2010xxxxxxxx" dir="ltr" style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">الخدمة / الاهتمام</label>
                <input type="text" placeholder="مثال: استضافة VPS أو Dedicated" style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
            </div>
            <div style="display:flex;gap:12px;">
                <div style="flex:1;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">المصدر</label>
                    <select style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
                        <option>مكالمة هاتفية</option>
                        <option>واتساب</option>
                        <option>ودجت الموقع</option>
                        <option>يدوي</option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">قيمة الصفقة المقدرة</label>
                    <input type="text" placeholder="$500" style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
                </div>
            </div>
        </div>
        <div style="padding:14px 20px;border-top:1px solid #E2E8F0;display:flex;justify-content:flex-end;gap:10px;background:#F8FAFC;">
            <button type="button" class="btn btn-sm btn-ghost" onclick="closeCreateLeadModal()">إلغاء</button>
            <button type="button" class="btn btn-sm btn-primary" onclick="alert('تمت إضافة العميل بنجاح!'); closeCreateLeadModal();" style="background:#0F172A;border:none;">حفظ العميل</button>
        </div>
    </div>
</div>

<script>
function openCreateLeadModal() {
    document.getElementById('createLeadModal').style.display = 'flex';
}
function closeCreateLeadModal() {
    document.getElementById('createLeadModal').style.display = 'none';
}
</script>