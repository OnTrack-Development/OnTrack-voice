@php
    $leads = $data['leads'] ?? [];
@endphp

<div class="card-head" style="margin-bottom:16px;">
    <div>
        <h1 style="font-size:22px;margin:0 0 4px 0;font-weight:800;color:#0F172A;">العملاء المحتملون (Leads CRM)</h1>
        <p style="margin:0;font-size:13px;color:#64748B;">إدارة خط المبيعات والعملاء المحتملين القادمين تلقائياً من المكالمات والشات والواتساب</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <div style="display:flex;background:#F1F5F9;padding:3px;border-radius:8px;">
            <button type="button" class="btn btn-sm" id="btnViewList" onclick="toggleLeadView('list')" style="background:#FFF;color:#0F172A;font-weight:700;border-radius:6px;box-shadow:0 1px 2px rgba(0,0,0,0.05);font-size:12px;">قائمة (List)</button>
            <button type="button" class="btn btn-sm btn-ghost" id="btnViewKanban" onclick="toggleLeadView('kanban')" style="color:#64748B;font-size:12px;">لوحة (Kanban)</button>
        </div>
        <a href="{{ route('enterprise.leads.csv') }}" class="btn btn-sm btn-ghost" style="background:#FFF;border:1px solid #E2E8F0;color:#0F172A;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
            📊 تصدير CSV
        </a>
        <button type="button" class="btn btn-sm" onclick="openCreateLeadModal()" style="background:#0F172A;color:#FFFFFF;border:none;border-radius:8px;font-weight:600;padding:8px 16px;">
            + إضافة عميل محتمل
        </button>
    </div>
</div>

<!-- Top Metrics Counter Strip (Matching Zoie.ai Leads Pipeline) -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:14px;margin-bottom:20px;">
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size:12px;color:#64748B;font-weight:600;margin-bottom:6px;">Leads (إجمالي العملاء)</div>
        <div style="font-size:24px;font-weight:800;color:#0F172A;">{{ count($leads) }}</div>
        <div style="font-size:11.5px;color:#10B981;font-weight:600;margin-top:4px;">محدث لحظياً من قاعدة البيانات</div>
    </div>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size:12px;color:#64748B;font-weight:600;margin-bottom:6px;">Qualified (المؤهلون)</div>
        <div style="font-size:24px;font-weight:800;color:#3B82F6;">{{ collect($leads)->where('status', 'qualified')->count() }}</div>
        <div style="font-size:11.5px;color:#3B82F6;font-weight:600;margin-top:4px;">جاهز للتواصل وإغلاق الصفقة</div>
    </div>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size:12px;color:#64748B;font-weight:600;margin-bottom:6px;">Waiting for Human (بانتظار الموظف)</div>
        <div style="font-size:24px;font-weight:800;color:#F59E0B;">{{ collect($leads)->whereIn('status', ['waiting_for_human', 'new'])->count() }}</div>
        <div style="font-size:11.5px;color:#F59E0B;font-weight:600;margin-top:4px;">تتطلب تدخلاً سريعاً</div>
    </div>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size:12px;color:#64748B;font-weight:600;margin-bottom:6px;">Closed (تم الإغلاق بنجاح)</div>
        <div style="font-size:24px;font-weight:800;color:#10B981;">{{ collect($leads)->where('status', 'closed')->count() }}</div>
        <div style="font-size:11.5px;color:#10B981;font-weight:600;margin-top:4px;">مكتمل ومسجل</div>
    </div>
</div>

<!-- Tabs & Search Filter Strip -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
    <div style="display:flex;gap:6px;background:#F1F5F9;padding:3px;border-radius:8px;">
        <button type="button" class="btn btn-sm filter-tab active" onclick="filterLeads('all', this)" style="background:#FFF;color:#0F172A;font-weight:700;border-radius:6px;box-shadow:0 1px 2px rgba(0,0,0,0.05);font-size:12px;">الكل ({{ count($leads) }})</button>
        <button type="button" class="btn btn-sm btn-ghost filter-tab" onclick="filterLeads('qualified', this)" style="color:#64748B;font-size:12px;">مؤهل ({{ collect($leads)->where('status', 'qualified')->count() }})</button>
        <button type="button" class="btn btn-sm btn-ghost filter-tab" onclick="filterLeads('new', this)" style="color:#64748B;font-size:12px;">جديد ({{ collect($leads)->where('status', 'new')->count() }})</button>
        <button type="button" class="btn btn-sm btn-ghost filter-tab" onclick="filterLeads('waiting_for_human', this)" style="color:#64748B;font-size:12px;">بانتظار الرد ({{ collect($leads)->where('status', 'waiting_for_human')->count() }})</button>
        <button type="button" class="btn btn-sm btn-ghost filter-tab" onclick="filterLeads('closed', this)" style="color:#64748B;font-size:12px;">مغلق ({{ collect($leads)->where('status', 'closed')->count() }})</button>
    </div>
    <div style="display:flex;gap:8px;">
        <input type="text" id="leadSearchInput" onkeyup="searchLeads()" placeholder="بحث بالاسم أو الهاتف أو الشركة..." style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:8px;padding:6px 12px;font-size:12px;width:240px;outline:none;">
    </div>
</div>

<!-- 1. Table View -->
<div id="leadsListView" style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:14px;overflow-x:auto;box-shadow:0 1px 3px rgba(0,0,0,0.04);margin-bottom:20px;">
    <table style="width:100%;border-collapse:collapse;text-align:right;font-size:13px;">
        <thead>
            <tr style="background:#F8FAFC;border-bottom:1px solid #E2E8F0;color:#64748B;font-size:11.5px;font-weight:700;">
                <th style="padding:12px 18px;">العميل / جهة الاتصال</th>
                <th style="padding:12px 16px;">المصدر</th>
                <th style="padding:12px 16px;">الشركة / الملاحظات</th>
                <th style="padding:12px 16px;">القيمة</th>
                <th style="padding:12px 16px;">الحالة</th>
                <th style="padding:12px 16px;">تاريخ الإضافة</th>
                <th style="padding:12px 18px;text-align:center;">إجراءات</th>
            </tr>
        </thead>
        <tbody id="leadsTableBody">
            @forelse ($leads as $lead)
                @php
                    $lArr = (array) $lead;
                    $st = $lArr['status'] ?? 'new';
                @endphp
                <tr class="lead-row" data-status="{{ $st }}" style="border-bottom:1px solid #F1F5F9;">
                    <td style="padding:14px 18px;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:34px;height:34px;border-radius:50%;background:#F1F5F9;display:flex;align-items:center;justify-content:center;font-weight:700;color:#0F172A;font-size:12px;">
                                {{ mb_substr($lArr['name'] ?? 'U', 0, 2) }}
                            </div>
                            <div>
                                <div class="lead-name" style="font-weight:700;color:#0F172A;font-size:13.5px;">{{ $lArr['name'] }}</div>
                                <div class="lead-phone" style="font-size:11.5px;color:#64748B;" dir="ltr">{{ $lArr['phone'] }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="padding:14px 16px;color:#475569;">
                        <span style="background:#F1F5F9;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;">
                            {{ $lArr['source'] ?? 'manual' }}
                        </span>
                    </td>
                    <td style="padding:14px 16px;color:#0F172A;font-weight:600;">
                        {{ $lArr['company'] ?: ($lArr['notes'] ?: '—') }}
                    </td>
                    <td style="padding:14px 16px;color:#0F172A;font-weight:700;font-family:monospace;">
                        {{ $lArr['value'] ? '$'.number_format($lArr['value'], 0) : '—' }}
                    </td>
                    <td style="padding:14px 16px;">
                        <form method="POST" action="{{ route('enterprise.action') }}" style="margin:0;">
                            @csrf
                            <input type="hidden" name="action" value="update_lead_status">
                            <input type="hidden" name="lead_id" value="{{ $lArr['id'] }}">
                            <select name="status" onchange="this.form.submit()" style="padding:4px 8px;border-radius:6px;border:1px solid #E2E8F0;font-size:11.5px;font-weight:700;cursor:pointer;background:{{ $st === 'qualified' ? '#F0FDF4' : ($st === 'closed' ? '#EFF6FF' : ($st === 'waiting_for_human' ? '#FFFBEB' : '#F8FAFC')) }};color:{{ $st === 'qualified' ? '#16A34A' : ($st === 'closed' ? '#2563EB' : ($st === 'waiting_for_human' ? '#D97706' : '#64748B')) }};">
                                <option value="new" {{ $st === 'new' ? 'selected' : '' }}>جديد (New)</option>
                                <option value="qualified" {{ $st === 'qualified' ? 'selected' : '' }}>مؤهل (Qualified)</option>
                                <option value="waiting_for_human" {{ $st === 'waiting_for_human' ? 'selected' : '' }}>بانتظار الرد (Waiting)</option>
                                <option value="closed" {{ $st === 'closed' ? 'selected' : '' }}>مغلق (Closed)</option>
                            </select>
                        </form>
                    </td>
                    <td style="padding:14px 16px;color:#94A3B8;font-size:11.5px;">
                        {{ isset($lArr['created_at']) ? date('Y-m-d H:i', strtotime($lArr['created_at'])) : 'اليوم' }}
                    </td>
                    <td style="padding:14px 18px;text-align:center;">
                        <div style="display:flex;gap:6px;justify-content:center;align-items:center;">
                            <a href="tel:{{ $lArr['phone'] }}" class="btn btn-sm btn-icon" style="background:#F1F5F9;border:none;border-radius:6px;text-decoration:none;padding:4px 8px;" title="اتصال هاتفي">📞</a>
                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $lArr['phone']) }}" target="_blank" class="btn btn-sm btn-icon" style="background:#F1F5F9;border:none;border-radius:6px;text-decoration:none;padding:4px 8px;" title="محادثة واتساب">💬</a>
                            <form method="POST" action="{{ route('enterprise.action') }}" style="margin:0;display:inline;" onsubmit="return confirm('هل أنت متأكد من حذف العميل؟');">
                                @csrf
                                <input type="hidden" name="action" value="delete_lead">
                                <input type="hidden" name="lead_id" value="{{ $lArr['id'] }}">
                                <button type="submit" class="btn btn-sm btn-icon" style="background:#FEE2E2;color:#EF4444;border:none;border-radius:6px;padding:4px 8px;cursor:pointer;" title="حذف">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="padding:32px;text-align:center;color:#64748B;">
                        لا يوجد عملاء مسجلون حالياً. استخدم زر "+ إضافة عميل محتمل" لإضافة أول عميل.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<!-- 2. Kanban Board View (Hidden by default) -->
<div id="leadsKanbanView" style="display:none;grid-template-columns:repeat(4, 1fr);gap:14px;margin-bottom:20px;">
    @foreach ([
        'new' => ['title' => 'جديد (New)', 'color' => '#64748B', 'bg' => '#F8FAFC'],
        'qualified' => ['title' => 'مؤهل (Qualified)', 'color' => '#16A34A', 'bg' => '#F0FDF4'],
        'waiting_for_human' => ['title' => 'بانتظار الرد (Waiting)', 'color' => '#D97706', 'bg' => '#FFFBEB'],
        'closed' => ['title' => 'مغلق (Closed)', 'color' => '#2563EB', 'bg' => '#EFF6FF'],
    ] as $colKey => $colMeta)
        <div style="background:{{ $colMeta['bg'] }};border:1px solid #E2E8F0;border-radius:12px;padding:14px;display:flex;flex-direction:column;gap:10px;min-height:350px;">
            <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #E2E8F0;padding-bottom:8px;">
                <strong style="font-size:13px;color:{{ $colMeta['color'] }};">{{ $colMeta['title'] }}</strong>
                <span style="font-size:11px;font-weight:700;background:#FFFFFF;padding:1px 6px;border-radius:10px;color:#0F172A;">
                    {{ collect($leads)->where('status', $colKey)->count() }}
                </span>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;overflow-y:auto;">
                @foreach (collect($leads)->where('status', $colKey) as $kLead)
                    @php $kArr = (array) $kLead; @endphp
                    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:8px;padding:10px;box-shadow:0 1px 2px rgba(0,0,0,0.03);">
                        <strong style="font-size:12.5px;color:#0F172A;display:block;">{{ $kArr['name'] }}</strong>
                        <div style="font-size:11px;color:#64748B;" dir="ltr">{{ $kArr['phone'] }}</div>
                        @if(!empty($kArr['company']))
                            <div style="font-size:11px;color:#334155;margin-top:4px;">🏢 {{ $kArr['company'] }}</div>
                        @endif
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;border-top:1px solid #F1F5F9;padding-top:6px;">
                            <span style="font-size:11px;font-weight:700;color:#0F172A;">{{ $kArr['value'] ? '$'.number_format($kArr['value'], 0) : '' }}</span>
                            <div style="display:flex;gap:4px;">
                                <a href="tel:{{ $kArr['phone'] }}" style="text-decoration:none;font-size:11px;">📞</a>
                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $kArr['phone']) }}" target="_blank" style="text-decoration:none;font-size:11px;">💬</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

<!-- Modal: + Create New Lead (Connected to Live MySQL) -->
<div id="createLeadModal" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.45);backdrop-filter:blur(4px);z-index:9999;display:none;align-items:center;justify-content:center;">
    <div style="background:#FFFFFF;border-radius:16px;width:100%;max-width:500px;box-shadow:0 20px 40px rgba(0,0,0,0.15);overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid #E2E8F0;display:flex;align-items:center;justify-content:space-between;">
            <strong style="font-size:16px;color:#0F172A;">إضافة عميل محتمل جديد (New Lead)</strong>
            <button type="button" onclick="closeCreateLeadModal()" style="background:none;border:none;font-size:18px;color:#64748B;cursor:pointer;">✕</button>
        </div>
        <form method="POST" action="{{ route('enterprise.action') }}">
            @csrf
            <input type="hidden" name="action" value="create_lead">
            <div style="padding:20px;display:flex;flex-direction:column;gap:14px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">الاسم الكامل *</label>
                    <input type="text" name="name" placeholder="مثال: م. أحمد السعيد" required style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">رقم الهاتف *</label>
                        <input type="tel" name="phone" placeholder="+2010xxxxxxxx" dir="ltr" required style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">البريد الإلكتروني</label>
                        <input type="email" name="email" placeholder="client@domain.com" style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">الشركة / الخدمة</label>
                        <input type="text" name="company" placeholder="مثال: سيرفر VPS أو اسم الشركة" style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">القيمة المتوقعة ($)</label>
                        <input type="number" name="value" placeholder="500" style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">المصدر</label>
                        <select name="source" style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
                            <option value="manual">إدخال يدوي</option>
                            <option value="voice_call">مكالمة هاتفية</option>
                            <option value="whatsapp">واتساب</option>
                            <option value="website_widget">ودجت الموقع</option>
                            <option value="whmcs">WHMCS</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">الحالة الأولية</label>
                        <select name="status" style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;">
                            <option value="new">جديد (New)</option>
                            <option value="qualified" selected>مؤهل (Qualified)</option>
                            <option value="waiting_for_human">بانتظار الرد (Waiting)</option>
                            <option value="closed">مغلق (Closed)</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;">ملاحظات إضافية</label>
                    <textarea name="notes" rows="2" placeholder="أي تفاصيل خاصة باحتياجات العميل..." style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:13px;outline:none;"></textarea>
                </div>
            </div>
            <div style="padding:14px 20px;border-top:1px solid #E2E8F0;display:flex;justify-content:flex-end;gap:10px;background:#F8FAFC;">
                <button type="button" class="btn btn-sm btn-ghost" onclick="closeCreateLeadModal()">إلغاء</button>
                <button type="submit" class="btn btn-sm btn-primary" style="background:#0F172A;color:#FFF;border:none;padding:8px 18px;border-radius:8px;font-weight:600;">حفظ العميل في قاعدة البيانات</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateLeadModal() {
    document.getElementById('createLeadModal').style.display = 'flex';
}
function closeCreateLeadModal() {
    document.getElementById('createLeadModal').style.display = 'none';
}
function toggleLeadView(mode) {
    if (mode === 'kanban') {
        document.getElementById('leadsListView').style.display = 'none';
        document.getElementById('leadsKanbanView').style.display = 'grid';
        document.getElementById('btnViewKanban').style.background = '#FFF';
        document.getElementById('btnViewKanban').style.color = '#0F172A';
        document.getElementById('btnViewKanban').style.fontWeight = '700';
        document.getElementById('btnViewList').style.background = 'none';
        document.getElementById('btnViewList').style.color = '#64748B';
    } else {
        document.getElementById('leadsListView').style.display = 'block';
        document.getElementById('leadsKanbanView').style.display = 'none';
        document.getElementById('btnViewList').style.background = '#FFF';
        document.getElementById('btnViewList').style.color = '#0F172A';
        document.getElementById('btnViewList').style.fontWeight = '700';
        document.getElementById('btnViewKanban').style.background = 'none';
        document.getElementById('btnViewKanban').style.color = '#64748B';
    }
}
function filterLeads(status, btn) {
    document.querySelectorAll('.filter-tab').forEach(b => {
        b.style.background = 'none';
        b.style.color = '#64748B';
    });
    btn.style.background = '#FFF';
    btn.style.color = '#0F172A';
    btn.style.fontWeight = '700';

    const rows = document.querySelectorAll('.lead-row');
    rows.forEach(r => {
        if (status === 'all' || r.getAttribute('data-status') === status) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });
}
function searchLeads() {
    const val = document.getElementById('leadSearchInput').value.toLowerCase();
    const rows = document.querySelectorAll('.lead-row');
    rows.forEach(r => {
        const text = r.innerText.toLowerCase();
        r.style.display = text.includes(val) ? '' : 'none';
    });
}
</script>
