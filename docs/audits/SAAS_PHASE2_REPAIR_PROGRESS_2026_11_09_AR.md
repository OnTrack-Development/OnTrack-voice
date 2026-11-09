# OnTrack Voice — سجل تنفيذ المرحلة الثانية للـSaaS
**تاريخ التحديث:** 2026-11-09
**الحالة:** CODED PARTIAL · GITHUB CI OFFLINE PASS · HOST/DEVICE E2E NOT VERIFIED · NOT DEPLOYED
**المرحلة:** SaaS-2: Tenancy / Device Contract / Flow Runtime / Knowledge / Integrations / Reports / Metering / Provider Failover
**الفرع:** `fix/saas-phase2-tenant-flow-compat-20261109` — مشتق من `release/voice-v1-20261106` **بشكل مستقل عن إصلاحات المرحلة الأولى غير المعتمدة في PR #79**.

> **شرط المالك الملزم:** لا إزالة لأي ميزة/زر/أوبشن مثبت العمل؛ لا تغيير SIM/Agent/voice/Provider keys أو PIN، ولا حذف/إعادة ضبط بيانات أو خطط/حصص أو ربط هاتف، ولا تغيير Android/PCM/APK/OTA، ولا تشغيل حملات أو مكالمات حقيقية من اختبار.

## ما نُفذ فعليًا في كود المرحلة الثانية

| رقم الخطة | الملف | ما تم | حالة القبول |
|---|---|---|---|
| **WEB-CORE-01** | `saas/console/_core/TenantDeviceContract.php`, `saas/console/device/v1/index.php` | إضافة `contract_probe` اختيارية **لجهاز مربوط مصادق عليه فقط**. تُرجع Tenant/SIM/Agent/Published revision ومعلومات دعم واجهات السيرفر بدون Token، ولا تُغير Heartbeat أو ربط أو Agent أو أي سياسة. تُعيد قراءة الربط الحالي وتمنع Revoked/Cross-tenant. **لا تعتبر صوت الهاتف فاشلًا أو ناجحًا؛ هي لا تختبر Carrier audio.** العمليات الحالية `heartbeat` و`published_flow` و`voice_session` كما هي. | **[x] كود + CI Offline PASS / [ ] Host/Device E2E** |
| **WEB-CORE-03** | `saas/console/_core/VoiceFlowRuntime.php` | فحص Policy/Connector عند استخدام عقدة `tool/crm_lookup/crm_write/schedule`، حتى لو كانت مربوطة سابقًا. العقدة ذات سياسة مُلغاة أو غير صالحة أو قراءة/كتابة غير مناسبة **تتوقف بدون فعل خارجي**. إصلاح الحالة: أداة نجحت في آخر مسار تُنهيه بـ`completed` بدل `blocked`. حافظنا على الموافقة الصريحة وسجل الـTrace ونسخة Agent المنشورة. | **[x] كود فقط / [ ] Provider E2E** |
| **WEB-CORE-04** | `saas/console/_core/VoiceKnowledgeWeb.php` | تنزيل HTML/TXT الخارجي أصبح محدودًا **أثناء البث** بحد 180KB؛ الطلب المفرط يفشل بدل تحميل ذاكرة كبيرة ثم رفضه لاحقًا. تظل موافقة المالك والفهرسة العامة/الخاصة والصلاحيات كما هي. لا تغيير في ملفات المعرفة أو PDF/Docx المرفوعة. | **[x] كود فقط / [ ] PDF وHost QA** |
| **WEB-CORE-07/08** | `HumanEscalations.php`, `Views.php` | منع ACK وهمي في إشعار الموظف، وعرض الاشتراك الحالي الصحيح بدل أحدث سجل غير نشط، مع اختبارات CI موسعة. | **[x] كود + CI / [ ] Host/SIM/Payment E2E** |
| **WEB-CORE-02/05/06/09** | واجهات قائمة بلا تغيير في هذه الدفعة | بقيت Agent / WHMCS/Laravel / Pilot / Google failover قائمة ومغطاة باختبارات offline؛ لا تدعي تكاملًا حقيقيًا بلا E2E. | **[ ] إغلاق شامل** |

### اختبارات عدم التراجع التي نُفذت بنجاح (Offline CI فقط)
- `tools/test_saas_phase2_flow_safety.php`: End-of-flow successful tool result، revoked/unverified connector، منع تحول `crm_lookup` إلى كتابة دون موافقة جديدة، منع Replay، وحفظ terminal status، بمزود وهمي فقط.
- `tools/test_saas_phase2_contract_probe.php`: التحقق من الربط والـSIM والـTenant والـFlow المنشور، ومنع cross-site/slot وrevoked، وعدم تغيير token/last_seen أو ادعاء نجاح صوت فعلي.
- `tools/test_saas_phase2_knowledge_safety.php`: التنزيل المقيد أثناء النقل، استرجاع Arabic aliases، التعامل مع تعارض Facts، وعمل Agent بدون KB.
- `.github/workflows/saas-phase2-core-regression.yml`: PHP 8.3 + اختبارات جديدة واختبارات موجودة للـFlow والـTool Gateway وDevicePairing وCall Reports وKB/PDF وGoogle failover واشتراكات الباقات في **قواعد اختبار معزولة**؛ لا deploy ولا مكالمات ولا إرسال WhatsApp. **تم إثبات PASS في GitHub Actions، وليس عبر هوست الإنتاج أو هاتف فعلي.**

### شهادة الاختبارات في GitHub Actions

- **Run موثق:** [SaaS Phase 2 — Actions #42555119910](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42555119910)؛ **Result: SUCCESS**، وجميع الخطوات المنفذة انتهت `success`.
- **PHP Syntax:** نجاح فحص ملفات الـCore وتعديلات Contract/Runtime/Knowledge.
- **Flow:** `SAAS_PHASE2_FLOW_NONREGRESSION_PASS`، `VOICE_FLOW_RUNTIME_STATES_AND_TRACES_PASS`، `VOICE_SITE_TOOL_GATEWAY_PASS`.
- **Tenant/SIM:** `SAAS_PHASE2_TENANT_DEVICE_CONTRACT_NONREGRESSION_PASS`، `SIM_AGENT_SWITCH_AND_CALL_REPORTS_PASS`، واختبار pairing المعزول.
- **Knowledge/PDF:** `SAAS_PHASE2_KNOWLEDGE_BOUNDED_FETCH_NONREGRESSION_PASS`، `SITE06_PDF_REAL_POPPLER_EXTRACTION_PASS` (Poppler مثبت على Runner الاختبار فقط)، `SITE06_PUBLIC_SCOPED_KNOWLEDGE_SEARCH_PASS`، `VOICE_KNOWLEDGE_WEB_AGENT_TTL_PASS`.
- **Billing/Provider:** `SITE05_ATOMIC_SUBSCRIPTION_AND_OWNER_ENTITLEMENTS_PASS`، `GOOGLE_LIVE_MIDCALL_FAILOVER_TEST_PASS` من مفاتيح/استجابات **Mock**؛ لم تُجرَ مكالمة فعلية أو أي عملية دفع حقيقية.
- **No Native/Deploy diff:** `SAAS_PHASE2_NO_NATIVE_OR_DEPLOY_CHANGES_CHECKED`.
- التشغيل الأول فشل في CI hygiene بسبب `HEAD~1`/Whitespace في ملف Markdown، وتم إصلاحهما وإعادة التشغيل حتى SUCCESS. هذا الفشل **ليس دليلًا على فشل الوظائف نفسها**.

**حدود شهادة CI:** جميع هذه الاختبارات على Runner/SQLite ومع mocks؛ لا تؤكد أداء مزود Google الفعلي أو وجود Poppler على الاستضافة الحالية أو جودة SIM audio. **ممنوع Merge/Deploy قبل Host QA والتوافق الفعلي والتأكد من أن ميزات المستخدم المؤكدة لم تتعطل**.

### دفعة إضافية — منع التراجع في الاتصال بالموظف والفوترة (Phase2 CORE-07/08)

- [x] **CORE-07 / كود + CI:** `HumanEscalations::he_ack` أصبح يتأكد أن تعديل حالة Job تم فعليًا مع حساب/موقع/جهاز/SIM وحالته السابقة. في سباق الطلبات المتزامنة لا يعلن نجاحًا وهميًا؛ إذا سبق حفظ نفس الحالة يرجع `duplicate=true`؛ ويمنع ACK قديمًا من إعادة فتح طلب اتصال مغلق.
- [x] **CORE-07 / اختبار:** `tools/test_human_escalation.php` يغطي تكرار `dial_started` و`call_ended_unverified` ومنع إعادة فتح الحالة النهائية، إضافة إلى فحص العزل ووقت الانتظار بعد المكالمة.
- [x] **CORE-08 / كود + CI:** إصلاح `render_billing`: اختيار الاشتراك **النشط حاليًا** بدل أحدث سجل تاريخي قد يكون `replaced` أو اشتراكًا يبدأ مستقبلًا. العرض أصبح متفقًا مع `voice_usage_summary` دون أي تعديل في الباقات/الفواتير/الأسعار.
- [x] **CORE-08 / اختبار:** `tools/test_saas_phase2_billing_current_subscription.php` يصنع سجل اشتراك تاريخي أحدث وتجديدًا مستقبليًا ويثبت بقاء اشتراك العميل النشط ظاهرًا، وعدم إعطاء حصة حين لا يوجد اشتراك صالح.
- [x] **مصفوفة CI موسعة:** `test_voice_saas_console.php` و`test_tenant_workspace.php` و`test_tenant_voice_transcript_archive.php` و`test_tenant_call_ai_summary.php` و`test_voice_call_timeline.php` و`test_human_escalation.php` و`test_voice_dnc_ledger.php` و`test_google_tenant_keys.php` و`test_console_checkout.php` و`test_whatsapp_inbox.php` و`test_whatsapp_whmcs_bridge.php` كلها على قواعد/مزودات Mock معزولة.
- [x] **تشغيل موثق جديد:** [GitHub Actions #42556153166](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42556153166) — `SUCCESS` لجميع خطوات PHP/Flow/Tenant/SIM/Knowledge/PDF/Handoff/Billing/Checkout/WhatsApp وNo-native-diff. هذه نتيجة CI فقط، ليست اعتماد هوست/هاتف.

**التحقق المتبقي:** لا يوجد إثبات لدائرة الاتصال الهاتفي فعليًا، أو مدير تلقّى المكالمة واستمع للملخص، أو فاتورة دُفعت على بوابة الإنتاج. هذه النقاط تبقى `[ ]` وفق DoD.

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
