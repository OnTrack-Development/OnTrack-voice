# OnTrack Voice — سجل تنفيذ المرحلة الثانية للـSaaS
**تاريخ التحديث:** 2026-11-09  
**الحالة:** CODED PARTIAL · TESTS NOT VERIFIED · NOT DEPLOYED  
**المرحلة:** SaaS-2: Tenancy / Device Contract / Flow Runtime / Knowledge / Integrations / Reports / Metering / Provider Failover  
**الفرع:** `fix/saas-phase2-tenant-flow-compat-20261109` — مشتق من `release/voice-v1-20261106` **بشكل مستقل عن إصلاحات المرحلة الأولى غير المعتمدة في PR #79**.

> **شرط المالك الملزم:** لا إزالة لأي ميزة/زر/أوبشن مثبت العمل؛ لا تغيير SIM/Agent/voice/Provider keys أو PIN، ولا حذف/إعادة ضبط بيانات أو خطط/حصص أو ربط هاتف، ولا تغيير Android/PCM/APK/OTA، ولا تشغيل حملات أو مكالمات حقيقية من اختبار.

## ما نُفذ فعليًا في كود المرحلة الثانية

| رقم الخطة | الملف | ما تم | حالة القبول |
|---|---|---|---|
| **WEB-CORE-01** | `saas/console/_core/TenantDeviceContract.php`, `saas/console/device/v1/index.php` | إضافة `contract_probe` اختيارية **لجهاز مربوط مصادق عليه فقط**. تُرجع Tenant/SIM/Agent/Published revision ومعلومات دعم واجهات السيرفر بدون Token، ولا تُغير Heartbeat أو ربط أو Agent أو أي سياسة. تُعيد قراءة الربط الحالي وتمنع Revoked/Cross-tenant. **لا تعتبر صوت الهاتف فاشلًا أو ناجحًا؛ هي لا تختبر Carrier audio.** العمليات الحالية `heartbeat` و`published_flow` و`voice_session` كما هي. | **[x] كود فقط / [ ] E2E** |
| **WEB-CORE-03** | `saas/console/_core/VoiceFlowRuntime.php` | فحص Policy/Connector عند استخدام عقدة `tool/crm_lookup/crm_write/schedule`، حتى لو كانت مربوطة سابقًا. العقدة ذات سياسة مُلغاة أو غير صالحة أو قراءة/كتابة غير مناسبة **تتوقف بدون فعل خارجي**. إصلاح الحالة: أداة نجحت في آخر مسار تُنهيه بـ`completed` بدل `blocked`. حافظنا على الموافقة الصريحة وسجل الـTrace ونسخة Agent المنشورة. | **[x] كود فقط / [ ] Provider E2E** |
| **WEB-CORE-04** | `saas/console/_core/VoiceKnowledgeWeb.php` | تنزيل HTML/TXT الخارجي أصبح محدودًا **أثناء البث** بحد 180KB؛ الطلب المفرط يفشل بدل تحميل ذاكرة كبيرة ثم رفضه لاحقًا. تظل موافقة المالك والفهرسة العامة/الخاصة والصلاحيات كما هي. لا تغيير في ملفات المعرفة أو PDF/Docx المرفوعة. | **[x] كود فقط / [ ] PDF وHost QA** |
| **WEB-CORE-02/05/06/07/08/09** | لا تغيير لواجهات التشغيل القائمة | أُبقيت مسارات Agent، Fallback، WHMCS/Laravel/REST، Reports، Billing، Google Failover كما كانت على فرع الأساس. أُضيف تشغيل اختبارات Offline موجودة لتغطيتها ضمن CI (فور توفر runner). **لا يجوز اعتبارها انتهت** دون E2E. | **[ ] إغلاق شامل** |

### اختبارات عدم التراجع التي تمت إضافتها (لم تثبت PASS)
- `tools/test_saas_phase2_flow_safety.php`: End-of-flow successful tool result، revoked/unverified connector، منع تحول `crm_lookup` إلى كتابة دون موافقة جديدة، منع Replay، وحفظ terminal status، بمزود وهمي فقط.
- `tools/test_saas_phase2_contract_probe.php`: التحقق من الربط والـSIM والـTenant والـFlow المنشور، ومنع cross-site/slot وrevoked، وعدم تغيير token/last_seen أو ادعاء نجاح صوت فعلي.
- `tools/test_saas_phase2_knowledge_safety.php`: التنزيل المقيد أثناء النقل، استرجاع Arabic aliases، التعامل مع تعارض Facts، وعمل Agent بدون KB.
- `.github/workflows/saas-phase2-core-regression.yml`: PHP 8.3 + اختبارات جديدة واختبارات موجودة للـFlow والـTool Gateway وDevicePairing وCall Reports وKB/PDF وGoogle failover واشتراكات الباقات في **قواعد اختبار معزولة**؛ لا deploy ولا مكالمات ولا إرسال WhatsApp.

### بنود الإغلاق المتبقية — مرحلتنا الثانية (9 مهام)
- [ ] **CORE-01:** تثبيت HTTP Contract/ACK على الجهاز الحقيقي وتجربة حسابين وشريحتين، والتحقق من نسخة الهاتف ونفس رقم Agent Revision.
- [ ] **CORE-02:** صوت Agent/Policy/KB لكل SIM واحتفاظ المكالمة الحالية بـSnapshot، مع اختبار 5 ثوانٍ.
- [ ] **CORE-03:** السيناريو التنفيذي كاملًا من واجهة ووظائف API حقيقية، أدوات Booking/CRM مع Provider sandbox، مراجعة بشرية قبل الكتابة.
- [ ] **CORE-04:** PDF عبر الهوست + معالجة أخطاء الفهرسة العربية وحالات KB فارغة وتعارضات المصدر وانتهاء الصلاحية.
- [ ] **CORE-05:** E2E لـWHMCS/Laravel/REST/WhatsApp مع توقيع Webhook ونتائج IDs حقيقية ومنع double-write.
- [ ] **CORE-06:** مراجعة تبعية مسارات Pilot القديمة على آخر APK وقياس الاستدعاءات قبل تعطيل أي endpoint (لا حذف).
- [ ] **CORE-07:** Evidence/Transcript/Call IDs/Handoff/10sec staff call على SIM حقيقية وموافقة مالك؛ عدم ادعاء نقل موظف قبل ACK.
- [ ] **CORE-08:** اختبار Usage Ledger مع Answered/No answer وQuota وOverage/Renewal وباقات الريسيلر وعدم خصم مكرر.
- [ ] **CORE-09:** Failover Google على 429/503/Timeout مع cooldown 15 دقيقة، مراعاة نفس الـTenant/Agent/Context؛ اختبار مزود حقيقي.

### بوابة أمان مشتركة
1. **إصلاحات المرحلة الأولى في PR #79 لم تُدمج أو تُنشر**. لا تفترض أنها موجودة في Phase 2؛ دمج الإصلاحات لاحقًا يحتاج Conflict review وRegression full.
2. **علامة [x] تعني كود مرفوع فقط**، لا تعني PASS أو DONE. جميع البنود التسعة **غير معتمدة نهائيًا** حتى الاختبار الفعلي.
3. لا Merge/Deploy، ولا دفع أو SMS/WhatsApp أو اتصال حقيقي من الاختبارات. قبل النشر: Host Backup/Receipt/rollback وQA مع صلاحية واضحة.
4. لا تلمس `VS/`: أكّد المالك حذفه ولا يدخل نطاق خطة SaaS.
