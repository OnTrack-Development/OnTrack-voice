<div class="card-head" style="margin-bottom:16px;">
    <div>
        <h1 style="font-size:22px;margin:0 0 4px 0;font-weight:800;color:#0F172A;">التقارير والتحليلات الشاملة (Analytics & Reports)</h1>
        <p style="margin:0;font-size:13px;color:#64748B;">تحليلات دقيقة لأداء المكالمات، استجابة الذكاء الاصطناعي، معدلات التحويل، ورضا العملاء عبر كافة القنوات</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <div style="display:flex;gap:6px;background:#F1F5F9;padding:3px;border-radius:8px;">
            <button type="button" class="btn btn-sm btn-ghost" style="color:#64748B;font-size:12px;">اليوم</button>
            <button type="button" class="btn btn-sm" style="background:#FFF;color:#0F172A;font-weight:700;border-radius:6px;box-shadow:0 1px 2px rgba(0,0,0,0.05);font-size:12px;">آخر 7 أيام</button>
            <button type="button" class="btn btn-sm btn-ghost" style="color:#64748B;font-size:12px;">هذا الشهر</button>
        </div>
        <button type="button" class="btn btn-sm btn-ghost" onclick="alert('تصدير التقرير بتنسيق PDF')" style="background:#FFF;border:1px solid #E2E8F0;color:#0F172A;font-weight:600;">
            📄 تصدير PDF
        </button>
    </div>
</div>

<!-- 5 Top KPI Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:14px;margin-bottom:20px;">
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size:12px;color:#64748B;font-weight:600;margin-bottom:6px;">إجمالي المكالمات والتفاعلات</div>
        <div style="font-size:24px;font-weight:800;color:#0F172A;">348</div>
        <div style="font-size:11.5px;color:#10B981;font-weight:600;margin-top:4px;">↑ +18% مقارنة بالأسبوع الماضي</div>
    </div>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size:12px;color:#64748B;font-weight:600;margin-bottom:6px;">نسبة الرد والاستجابة</div>
        <div style="font-size:24px;font-weight:800;color:#10B981;">94.2%</div>
        <div style="font-size:11.5px;color:#64748B;margin-top:4px;">متوسط زمن الرد: 0.8 ثانية</div>
    </div>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size:12px;color:#64748B;font-weight:600;margin-bottom:6px;">الحل الذاتي (Containment Rate)</div>
        <div style="font-size:24px;font-weight:800;color:#3B82F6;">88.6%</div>
        <div style="font-size:11.5px;color:#3B82F6;font-weight:600;margin-top:4px;">دون الحاجة لتدخل بشري</div>
    </div>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size:12px;color:#64748B;font-weight:600;margin-bottom:6px;">تقييم رضا العملاء (CSAT)</div>
        <div style="font-size:24px;font-weight:800;color:#0F172A;">★ 4.8 / 5</div>
        <div style="font-size:11.5px;color:#F59E0B;font-weight:600;margin-top:4px;">بناءً على 210 تقييماً</div>
    </div>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="font-size:12px;color:#64748B;font-weight:600;margin-bottom:6px;">مواعيد وصفقات محققة</div>
        <div style="font-size:24px;font-weight:800;color:#8B5CF6;">24</div>
        <div style="font-size:11.5px;color:#8B5CF6;font-weight:600;margin-top:4px;">قيمة الصفقات: $14,200</div>
    </div>
</div>

<div class="grid-2" style="grid-template-columns: 1fr 1fr;gap:18px;margin-bottom:20px;">
    <!-- Channel Breakdown -->
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:14px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <strong style="font-size:15px;color:#0F172A;display:block;margin-bottom:16px;">توزيع حجم التفاعلات عبر القنوات (Omnichannel Distribution)</strong>
        <div style="display:flex;flex-direction:column;gap:14px;">
            <div>
                <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:6px;">
                    <span style="font-weight:700;color:#0F172A;">📞 المكالمات الصوتية (SIM Voice)</span>
                    <span style="font-weight:700;color:#2563EB;">62% (216 مكالمة)</span>
                </div>
                <div style="height:8px;background:#F1F5F9;border-radius:4px;overflow:hidden;">
                    <div style="width:62%;height:100%;background:#2563EB;border-radius:4px;"></div>
                </div>
            </div>
            <div>
                <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:6px;">
                    <span style="font-weight:700;color:#0F172A;">💬 محادثات واتساب (WhatsApp)</span>
                    <span style="font-weight:700;color:#10B981;">24% (83 محادثة)</span>
                </div>
                <div style="height:8px;background:#F1F5F9;border-radius:4px;overflow:hidden;">
                    <div style="width:24%;height:100%;background:#10B981;border-radius:4px;"></div>
                </div>
            </div>
            <div>
                <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:6px;">
                    <span style="font-weight:700;color:#0F172A;">🌐 ودجت الموقع والشات الصوتي (Widget)</span>
                    <span style="font-weight:700;color:#8B5CF6;">10% (35 تفاعل)</span>
                </div>
                <div style="height:8px;background:#F1F5F9;border-radius:4px;overflow:hidden;">
                    <div style="width:10%;height:100%;background:#8B5CF6;border-radius:4px;"></div>
                </div>
            </div>
            <div>
                <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:6px;">
                    <span style="font-weight:700;color:#0F172A;">🎫 تذاكر WHMCS والبريد</span>
                    <span style="font-weight:700;color:#F59E0B;">4% (14 تذكرة)</span>
                </div>
                <div style="height:8px;background:#F1F5F9;border-radius:4px;overflow:hidden;">
                    <div style="width:4%;height:100%;background:#F59E0B;border-radius:4px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Agent Leaderboard -->
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:14px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <strong style="font-size:15px;color:#0F172A;display:block;margin-bottom:16px;">لوحة تصدر الإيجنت الذكي (AI Agent Leaderboard)</strong>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <div style="border:1px solid #E2E8F0;border-radius:10px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;background:#F8FAFC;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg, #8B5CF6, #3B82F6);color:#FFF;display:flex;align-items:center;justify-content:center;font-weight:700;">Z</div>
                    <div>
                        <strong style="font-size:13px;color:#0F172A;">OnTrack AI (وكيل المبيعات والاستشارات)</strong>
                        <div style="font-size:11.5px;color:#64748B;">184 مكالمة · نسبة إغلاق 24%</div>
                    </div>
                </div>
                <div style="text-align:left;">
                    <div style="font-weight:800;font-size:13px;color:#10B981;">★ 4.9</div>
                    <div style="font-size:11px;color:#94A3B8;">زمن رد 0.7s</div>
                </div>
            </div>

            <div style="border:1px solid #E2E8F0;border-radius:10px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;background:#FFFFFF;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg, #10B981, #059669);color:#FFF;display:flex;align-items:center;justify-content:center;font-weight:700;">S</div>
                    <div>
                        <strong style="font-size:13px;color:#0F172A;">سارة (الدعم الفني السريع)</strong>
                        <div style="font-size:11.5px;color:#64748B;">122 مكالمة · حل ذاتي 92%</div>
                    </div>
                </div>
                <div style="text-align:left;">
                    <div style="font-weight:800;font-size:13px;color:#10B981;">★ 4.7</div>
                    <div style="font-size:11px;color:#94A3B8;">زمن رد 0.8s</div>
                </div>
            </div>
        </div>
    </div>
</div>