<div class="card-head" style="margin-bottom:16px;">
    <div>
        <h1 style="font-size:22px;margin:0 0 4px 0;font-weight:800;color:#0F172A;">النشاط والتعلم الذاتي المستمر (Activity & Self-Learning)</h1>
        <p style="margin:0;font-size:13px;color:#64748B;">سجل الأنشطة الحية، وتحليل الاستفسارات المتكررة لتدريب وتطوير قاعدة معرفة الإيجنت تلقائياً</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <button type="button" class="btn btn-sm btn-ghost" onclick="alert('تحديث سجل التعلم الآن')" style="background:#FFF;border:1px solid #E2E8F0;color:#0F172A;font-weight:600;">
            🔄 تحديث السجل
        </button>
    </div>
</div>

<!-- Continuous Self-Learning Proposed Entries -->
<div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:14px;padding:22px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
    <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #F1F5F9;padding-bottom:12px;margin-bottom:16px;">
        <div style="display:flex;align-items:center;gap:8px;">
            <span style="font-size:18px;">🧠</span>
            <strong style="font-size:15px;color:#0F172A;">معلومات ومقترحات استخلصها الذكاء الاصطناعي (Pending Knowledge Insights)</strong>
        </div>
        <span class="pill pill-green">2 مقترحات جديدة بانتظار الاعتماد</span>
    </div>

    <div style="display:flex;flex-direction:column;gap:12px;">
        <!-- Insight 1 -->
        <div style="border:1px solid #E2E8F0;border-radius:10px;padding:14px 16px;background:#F8FAFC;display:flex;align-items:center;justify-content:space-between;gap:16px;">
            <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <span style="background:#FAF5FF;color:#7C3AED;border:1px solid #E9D5FF;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;">سؤال متكرر (4 مرات اليوم)</span>
                    <strong style="font-size:13px;color:#0F172A;">«هل تدعم باقة Starter إصدارات Node.js و Python؟»</strong>
                </div>
                <div style="font-size:12px;color:#475569;line-height:1.5;">
                    مقترح الإجابة المستخلصة من كتالوج المنتجات: نعم، جميع باقات cPanel تدعم CloudLinux NodeJS Selector و Python Selector مع إمكانية تحديد الإصدار بنقرة واحدة.
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-shrink:0;">
                <button type="button" class="btn btn-sm btn-ghost" onclick="alert('تعديل الصياغة')" style="border:1px solid #CBD5E1;font-size:12px;">تعديل ✏️</button>
                <button type="button" class="btn btn-sm" onclick="alert('تمت إضافة المعلومة لقاعدة المعرفة بنجاح!')" style="background:#10B981;color:#FFF;border:none;border-radius:8px;font-weight:700;font-size:12px;padding:6px 14px;">
                    ✓ اعتماد في المعرفة
                </button>
            </div>
        </div>

        <!-- Insight 2 -->
        <div style="border:1px solid #E2E8F0;border-radius:10px;padding:14px 16px;background:#F8FAFC;display:flex;align-items:center;justify-content:space-between;gap:16px;">
            <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <span style="background:#EFF6FF;color:#2563EB;border:1px solid #DBEAFE;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;">طرق دفع جديدة</span>
                    <strong style="font-size:13px;color:#0F172A;">«العملاء يسألون عن الدفع عبر إنستاباي ومحافظ فودافون كاش مباشرة»</strong>
                </div>
                <div style="font-size:12px;color:#475569;line-height:1.5;">
                    مقترح التوضيح: تفعيل رابط مباشر مع بوابة Kashier ومحافظ الهاتف المصرية في سيناريو الرد التلقائي.
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-shrink:0;">
                <button type="button" class="btn btn-sm btn-ghost" onclick="alert('تعديل الصياغة')" style="border:1px solid #CBD5E1;font-size:12px;">تعديل ✏️</button>
                <button type="button" class="btn btn-sm" onclick="alert('تمت إضافة المعلومة لقاعدة المعرفة بنجاح!')" style="background:#10B981;color:#FFF;border:none;border-radius:8px;font-weight:700;font-size:12px;padding:6px 14px;">
                    ✓ اعتماد في المعرفة
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Real-Time Activity Audit Feed -->
<div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
    <div style="padding:16px 20px;border-bottom:1px solid #E2E8F0;display:flex;justify-content:space-between;align-items:center;">
        <strong style="font-size:15px;color:#0F172A;">سجل الأحداث المباشر (Audit Activity Log)</strong>
        <span style="font-size:12px;color:#64748B;">تحديث حي (Live)</span>
    </div>

    <table style="width:100%;border-collapse:collapse;text-align:right;font-size:13px;">
        <thead>
            <tr style="background:#F8FAFC;border-bottom:1px solid #E2E8F0;color:#64748B;font-size:12px;">
                <th style="padding:12px 18px;">الوقت</th>
                <th style="padding:12px 16px;">الحدث (Event)</th>
                <th style="padding:12px 16px;">القناة</th>
                <th style="padding:12px 16px;">الممثل (Actor)</th>
                <th style="padding:12px 18px;">التفاصيل</th>
            </tr>
        </thead>
        <tbody>
            <tr style="border-bottom:1px solid #F1F5F9;">
                <td style="padding:14px 18px;color:#94A3B8;font-size:12px;">منذ دقيقتين</td>
                <td style="padding:14px 16px;"><span class="pill pill-green" style="font-size:11px;">مكالمة مكتملة</span></td>
                <td style="padding:14px 16px;">SIM 1 (Android)</td>
                <td style="padding:14px 16px;color:#8B5CF6;font-weight:600;">✨ Zoie</td>
                <td style="padding:14px 18px;color:#0F172A;">الرد على استفسار العميل بخصوص مواعيد العمل والأسعار (المدة 41 ثانية)</td>
            </tr>
            <tr style="border-bottom:1px solid #F1F5F9;">
                <td style="padding:14px 18px;color:#94A3B8;font-size:12px;">منذ 15 دقيقة</td>
                <td style="padding:14px 16px;"><span class="pill" style="background:#EFF6FF;color:#2563EB;font-size:11px;">موعد جديد</span></td>
                <td style="padding:14px 16px;">WhatsApp API</td>
                <td style="padding:14px 16px;color:#8B5CF6;font-weight:600;">✨ Zoie</td>
                <td style="padding:14px 18px;color:#0F172A;">حجز موعد استشارة سحابية مبيعات VPS مع أحمد محمود (اليوم 11:00 ص)</td>
            </tr>
            <tr style="border-bottom:1px solid #F1F5F9;">
                <td style="padding:14px 18px;color:#94A3B8;font-size:12px;">منذ 35 دقيقة</td>
                <td style="padding:14px 16px;"><span class="pill" style="background:#FAF5FF;color:#7C3AED;font-size:11px;">تذكرة دعم</span></td>
                <td style="padding:14px 16px;">WHMCS Bridge</td>
                <td style="padding:14px 16px;color:#8B5CF6;font-weight:600;">🛡️ Multi-Agent</td>
                <td style="padding:14px 18px;color:#0F172A;">تشخيص خطأ 500 ووردبريس واعتماد مسودة الرد بنسبة ثقة 99%</td>
            </tr>
        </tbody>
    </table>
</div>