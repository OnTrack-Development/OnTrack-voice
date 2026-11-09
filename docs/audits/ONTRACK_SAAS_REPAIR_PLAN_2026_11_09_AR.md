# OnTrack Voice — خطة إصلاح الـSaaS فقط مع ضمان عدم تعطيل الميزات القائمة
**التاريخ:** 2026-11-09 | **آخر تحديث متابعة:** 2026-11-09 | **النوع:** خطة إصلاح SaaS مستقلة | **الحالة:** IN PROGRESS — CODE PATCHES ON BRANCH / NO VERIFIED RELEASE
**المستودع:** `OnTrack-Development/OnTrack-voice`
**قاعدة المقارنة:** `release/voice-v1-20261106` عند إعداد التقرير المجمّع؛ قبل كل مهمة يُقرأ HEAD الفعلي وHost Receipt بدل الاعتماد على تاريخ التقرير.
**المرجع الأساسي:** [التقرير المجمّع](ONTRACK_CONSOLIDATED_ZERO_ASSUMPTION_AUDIT_2026_11_09_AR.md).
**النطاق المسموح في هذه الخطة:** لوحة الـSaaS، API، قواعد البيانات، تكاملات الموقع، الباقات، الـBilling، الـTenancy، الـVoice session server-side، الاختبارات والتشغيل المرتبطين بالموقع.
**خارج النطاق:** تغيير Android/PCM/SIM/APK/OTA أو توقيع الجوال؛ **مجلد `VS/` اتشال من المشروع طبقًا لتأكيد المالك، وبالتالي لم يعد بندًا مطلوبًا ولا هدفًا للفحص/الإصلاح**؛ النشر ليس جزءًا من تحديث الخطة.

> ## شرط مالك المشروع الحاكم — NON-REGRESSION / PRESERVE WORKING FEATURES
> **ممنوع إزالة أو تعطيل أو إخفاء أو إعادة ضبط أو استبدال أي ميزة أو خيار أو مسار تم تشغيله والتأكد منه، حتى لو وُجد في كود Legacy أو بدا غير مستخدم.** الإصلاح يكون إضافيًا ومتوافقًا إلى الخلف (additive/backward compatible) مع اختبارات إثبات. عند تعارض الحماية الأمنية مع استمرار وظيفة سليمة، **أغلق مسار الاستغلال فقط** وأبقِ الوظيفة المشروعة عبر توثيق/حدود صلاحية/ترحيل آمن. لا تُحذف نقطة API أو حقل DB أو خيار واجهة أو Provider أو Subscription أو Token مستخدم لمجرد أن بديلًا أحدث موجود.

---

## متابعة تنفيذ خطة الـSaaS — العلامات الرسمية

**مصدر التنفيذ:** [فرع إصلاح الـSaaS](https://github.com/OnTrack-Development/OnTrack-voice/tree/fix/saas-phase1-security-nonregression-20261109) · [PR #79 (Draft)](https://github.com/OnTrack-Development/OnTrack-voice/pull/79) · [سجل الدفعة](https://github.com/OnTrack-Development/OnTrack-voice/blob/fix/saas-phase1-security-nonregression-20261109/docs/audits/SAAS_PHASE1_REPAIR_PROGRESS_2026_11_09_AR.md).

**معنى العلامات:** `[x]` = **خطوة تعديل كود مكتوبة ومرفوعة للمراجعة فقط**؛ `[ ]` = لم تكتمل خطوة التنفيذ/التحقق. **العلامة `[x]` هنا لا تساوي DONE للمهمة الشاملة أو نشرًا**. في الجداول أدناه، 🟡 = تعديل جزئي مع شروط قبول ناقصة، و⬜ = ليس لدينا إغلاق موثق. لا نعتبر أي بند `DONE` حتى Test + Regression + Host/Owner evidence + Receipt.

### ✅ خطوات كود أُنجزت ورُفعت على فرع الإصلاح (ليس إنتاجًا)

- [x] **WEB-SEC-01 / جزئي:** تعتيم توكن الصيانة في النسخة الحالية من دليل الرفع، منع تنزيل الدليل بـ `.htaccess`، وإضافة مصادقة `X-Maintenance-Token` مع تخزين digest خاص، ورفض أسرار `GET` في `setup_demo.php` و`diag.php`.
- [x] **WEB-SEC-02 / جزئي:** إضافة Session-CSRF وOrigin check وAtomic quota لـ `api/live_session.php`، وتحديث Demo/Outbound والتشخيص القديم ليرسل الـnonce.
- [x] **WEB-SEC-03 / جزئي:** إضافة Basic Auth وCSRF لأداة اختبار Omnira مع الحفاظ على أزرار Basic/Stream/Tools/Models الأربعة.
- [x] **WEB-SEC-04 / جزئي:** إضافة فحص Ed25519 لوظائف `exec` في Host-Link وأداة توقيع واختبار رفض العبث في الكود **دون تشغيل الوكيل**.
- [x] **WEB-SEC-05 / جزئي:** إضافة Rate Limit لعملية `lead.create` حسب المستأجر ومفتاح الـAPI من غير تغيير الـRead APIs.
- [x] **WEB-SEC-06 / جزئي:** منع fallback لقاعدة SaaS العامة على `/tmp` في PHP-FPM؛ دعم حفظ تهيئة Gemini الجديدة خارج Webroot مع إبقاء التهيئة القديمة قابلة للقراءة.
- [x] **WEB-SEC-07 / جزئي:** حجب تفاصيل أخطاء مزود Gemini الخام عن مستدعي Root Demo.
- [x] **WEB-CORE-01/06 / جزئي:** تحسين `saas/mobile/server/session.php` للمحافظة على Legacy authorized fallback في المسارين Flat/Nested.
- [x] **WEB-CORE-02 / WEB-UX-01 / جزئي:** إضافة حفظ ونشر إعداد الرد الآلي وتأخير **0–30 ثانية** من شاشة إعدادات الرد لكل إيجنت، مع إبقاء صوت الإيجنت والـFlow والـKnowledge والسياسات الأخرى.
- [x] إضافة ملفات اختبارات PHP/JS/Python وWorkflow معزول لهذه الدفعة (**كتابة الاختبارات فقط؛ ليس إثبات نجاحها**).
- [x] إزالة `VS/` من نطاق خطة الـSaaS بعد تأكيد المالك أنه **حُذف من المشروع** (ليس مهمة إصلاح).

### ✅ تحديث التنفيذ الفعلي — المرحلة الثانية (SaaS-2)

**فرع العمل المستقل:** [Phase 2](https://github.com/OnTrack-Development/OnTrack-voice/tree/fix/saas-phase2-tenant-flow-compat-20261109) · [سجل الإصلاحات](https://github.com/OnTrack-Development/OnTrack-voice/blob/fix/saas-phase2-tenant-flow-compat-20261109/docs/audits/SAAS_PHASE2_REPAIR_PROGRESS_2026_11_09_AR.md). الفرع من `release/voice-v1-20261106` وليس من Phase 1 غير المعتمدة، لمنع إدخال تغييرات اختبارية غير موثقة إلى المسار الصوتي.

- [x] **WEB-CORE-01 / جزئي:** إضافة `contract_probe` قراءة فقط، وبمصادقة توكن جهاز Tenant المرتبط؛ تأكيد Account/Site/SIM/Published Agent revision وعدم إرجاع مفاتيح أو تغيير حالة الربط. **لا اختبار Carrier Audio من هذه العملية**.
- [x] **WEB-CORE-03 / جزئي:** إعادة فحص صلاحيات Connector/Tool عند تنفيذ عقد Flow، وإصلاح اعتبار أداة ناجحة في نهاية المسار `completed` بدل `blocked`؛ لا كتابة خارجية من غير الموافقة الصريحة.
- [x] **WEB-CORE-04 / جزئي:** تحديد تنزيل مصادر المعرفة HTTPS أثناء النقل (180KB) لمنع استهلاك الذاكرة غير المحدود؛ لا تعديل في الملفات/المعرفة المنشورة.
- [x] إضافة اختبارات معزولة وتغطية Regression للـFlow وKB وSIM contract وGoogle Failover وCall Reports وMetering وBilling؛ **تم تشغيلها ونجحت Offline على GitHub Actions — Run #42555119910** (لا يعني نجاح على الهوست أو الهاتف).
- [ ] اختبار Android/Browser/Host الحي لكل بند من CORE-01..09، بما فيه WHMCS/PDF/Flow Tools/Voices/Call Reports/Google Key Failover؛ لا إغلاق نهائي.
- [x] نجاح بوابة اختبارات المرحلة الثانية Offline على [GitHub Actions #42555119910](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42555119910) بعد إصلاح إخفاقات Workflow وWhitespace.
- [ ] **ما زال مطلوبًا:** Host/Physical SIM/Provider E2E واختبار الحفاظ على كل خاصية مؤكدة؛ لا Merge ولا Deploy قبلها.

**المجموع بعد هذه الدفعة:** 31 مهمة عليا؛ **0 DONE بمعايير DoD**، **15 لديها تغييرات كود جزئية، منها 5 بنود Phase 2 اجتازت اختبارات Offline**، 16 لا يوجد لها إغلاق كودي موثق داخل هذين الفرعين. كل مربع [x] هو *جزء مكتوب من المهمة* وليس نجاحًا على السيرفر أو الهاتف.

### ✅ مرحلة SaaS-2 — إغلاق أجزاء إضافية من كود التقارير والفوترة

- [x] **WEB-CORE-07 / Partial Code + CI:** التحقق من ACK فعلي عند تحديث حالة طلب الاتصال بالمدير، وحماية إعادة الإرسال والتزامن، وعدم إعادة فتح اتصال انتهى. نجح `tools/test_human_escalation.php` في CI.
- [x] **WEB-CORE-08 / Partial Code + CI:** إصلاح اختيار الباقة النشطة الظاهرة للعميل بدل أحدث اشتراك تاريخي/مستقبلي. نجح `tools/test_saas_phase2_billing_current_subscription.php` في CI.
- [x] **Regression أوسع:** 11 اختبارًا إضافيًا لـTenant/Calls/Reports/Handoff/Checkout/DNC/WhatsApp/WHMCS/Google Keys، وبدون اتصال حي أو تعديل بيانات إنتاج. [GitHub Actions #42556153166 — SUCCESS](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42556153166).
- [ ] **لا يزال CORE-07/08 غير DONE على الهوست:** مكالمات فعلية، مراجعة الموظف المستهدف، وتأكيدات الاشتراكات والدفع والحصص على البيئة الحقيقية بلا تغيير حسابات أو باقات.

**الحالة العددية الجديدة:** 31 مهمة عامة؛ **0 DONE بمعايير Host/Device DoD**، **15 مهمة لها خطوات كود جزئية** (منها 5 مهام CORE-01/03/04/07/08 اجتازت اختبارات Offline)، و16 مهمة بلا إغلاق كودي ضمن هذين الفرعين.

### ⛔ شروط الإغلاق التي ما زالت مفتوحة

- [ ] **WEB-SEC-01:** تدوير السر المتسرب على الهوست، إبطال النسخة القديمة، التحقق من Git History و`dev_bridge.php` وسجل الوصول.
- [ ] **WEB-SEC-02/03/04/05/06/07:** CI PASS وHTTP/Browser/Host probes، اختبار أدوات الصيانة المصرحة، Vault decrypt/restore وفحص Logs، والتأكد من حالة STOP الفعلية.
- [ ] **WEB-CORE-01..09:** إثبات عقد Android/SaaS والـTenant/SIM والصوت وProvider/Flow/KB/Integrations/Call Evidence/Billing على اختبارات معزولة ثم E2E.
- [ ] **WEB-UX-01..08:** فحص كامل للأزرار والباقات والريسيلرز والدفع والدومينات والصلاحيات والمايك والحملات؛ نجاح تغيير تأخير الرد بالكود لا يثبت باقي الواجهة.
- [ ] **WEB-REL-01..07:** تشغيل الاختبارات، سجل Host SHA ونسخة رجوع، توافق Android الموثق، نشر متحكم فيه بعد قبول منع التراجع.
- [ ] **لا Merge ولا Deploy:** PR #79 ما زال Draft وغير مدموج؛ لا إثبات نشر أو اختبارات حية. تشغيلات GitHub Actions التي جرى فحصها كانت `failure` وليست `PASS`.

**العدّ الحالي:** **31 مهمة عليا** = 7 أمن + 9 تكامل + 8 واجهات/تجارة + 7 جودة ونشر. **المكتمل بمقياس DoD = 0/31**؛ **15 مهمة بها عمل كودي جزئي/مقدم**، و**16 مهمة ليس لها إغلاق ضمن هذين الفرعين**. وجود وظائف قديمة تعمل لا يُحوَّل تلقائيًا إلى علامة إغلاق لهذه الخطة.

**شرط المالك:** الحفاظ على أي ميزة أو خيار تم تأكيد عمله؛ ممنوع كسر صوت أو ربط SIM أو تسجيل الدخول أو بيانات الباقات أو التوقيع نتيجة إصلاح الـSaaS. لا فرض 2FA أثناء التجارب.

---

## 0. بروتوكول الحماية الإلزامي قبل أي مهمة

### 0.1 جرد «ما يعمل» وعقد السلوك
- [ ] استخرج **source SHA** للفرع المعني، و**host SHA/Receipt** الفعلي، وآخر Browser/CI E2E، وقائمة Feature Flags والـAPI routes وDB migrations؛ لا تفترض تطابق الفرع مع الهوست.
- [ ] التقط Baseline لسلوك Admin/Reseller/Customer، وLogin/Password Reset/SSO، والتسجيل، White-label، الباقات والاشتراكات، Agent Studio/Flow Builder/Knowledge، الإيجنتات والأصوات، API keys والمزودات، Campaigns/Contacts، Calls/Reports، إعدادات المكالمات (بما فيها **5 ثوانٍ** إن كان مفعّلًا)، الربط بالشريحة، WhatsApp/WHMCS/Laravel، الحجز والتذاكر، Billing، UI العربي/RTL، Dark/Light، Responsive.
- [ ] صنّف كل ميزة `OWNER_CONFIRMED` أو `HOST_VERIFIED` أو `CODED_CI_PASS` أو `PARTIAL` أو `UNVERIFIED`؛ لا ترفع قيمة التأكيد من دون دليل. حافظ خصوصًا على وظائف الباقات/Flows/الأصوات/Knowledge/SSO/المكالمات التي سبق اختبارها.
- [ ] خذ Backup قابل للاستعادة لقاعدة البيانات والملفات والإعدادات والمفاتيح المشفرة **من دون تصدير الأسرار إلى Git**؛ اختبر Restore معزولًا. سجّل حد الرجوع وقائمة ملفات النشر.
- [ ] ثبّت عقد إصدار Client/API سابق يدعم الهاتف المثبت: لا تغيير صامت في `device/v1` أو payloads أو Bearer scheme أو عمليات الـPilot المطلوبة قبل Compat tests، ولا تغيير Prefix أو Domain مستخدم.
- [ ] كل مهمة تُنفذ في فرع/commit مستقل مع diff محدود وFeature Flag افتراضي يُبقي السلوك السابق، ثم Unit/Contract/Browser + Host QA معزول؛ لا Merge/Deploy إن هبط أي اختبار حماية.

### 0.2 قائمة «ممنوع المساس» مع حق الإصلاح الآمن
1. **دخول الموقع خلال التجارب** بريد + كلمة مرور فقط لجميع الأدوار؛ **لا إعادة إلزام 2FA/OTP/TOTP** حتى قرار إطلاق تجاري جديد. أبقِ CSRF/session rotation/login throttling/signed SSO/email verification وSIM pairing مستقلين.
2. **الحسابات/الريسيلرز/الدومينات**: لا حذف عملاء Demo أو Subscriptions أو Plans أو ريسيلرز أو إعدادات White-label، ولا إلغاء تحكم الأدمن؛ عزل المستأجرين دائم.
3. **مفاتيح Providers**: Google Live/IO/Fish إن كانت مفعلة، ومجموعات Omnira A/B/NVIDIA/Custom للـAssistant كما هي وفق الصلاحيات؛ لا تغيير صوت أو Agent أو Provider افتراضي/تكاليف أو قائمة الأصوات دون اختبار وتغيير مقصود. لا تفترض أن مزود Text يملك Voice API.
4. **الإيجنتات والسيناريوهات**: الحفاظ على النص المنشور، Draft، Flow JSON، Knowledge versions، Policies، واختيار صوت/Agent لكل SIM؛ لا تحوّل المسودات إلى نشر تلقائي، ولا تعطل النشر الحالي.
5. **ربط الشريحة**: Token/device bindings الحالية، سياسات pairing ذات الستة أرقام، dual-SIM scope، Agent revision، وحق العميل في تشغيل/إيقاف الإيجنت؛ لا Unpair أو Rotate Tokens لمجرد إصلاح صفحة الويب.
6. **التكاملات والفواتير**: لا تصفير quotas أو تعديل plan price أو إسناد اشتراك أو إرسال إشعارات مالية/واتساب أثناء اختبارات بلا fixture وموافقة؛ لا ترسل حملات فعلية.
7. **الإصدارات**: أي Publish لموقع فقط يجب أن يثبت `signed_mobile_unchanged=true` ونسخ مؤشر native/OTA قبل وبعد؛ لا إرسال تحديث APK أو تشغيل Host-Link الموقوف.
8. **الأمان**: لا يُستخدم مبدأ «عدم التعطيل» لإبقاء endpoint مفتوح للاستغلال؛ وفر بديلًا مصادقًا أو حظرًا انتقائيًا قبل غلق الوصول غير الموثق.

**بوابة قبول موحدة لكل بند:** `CODE_READ → REPRO_OR_RISK_PROOF → PATCH_MINIMAL → TEST_NEW + REGRESSION_ALL → HOST_ISOLATED_QA → (AUTHORIZED DEPLOY ONLY) → HTTP/Browser/Owner E2E → RECEIPT → DONE`. إن نقص أي دليل: `PARTIAL/UNVERIFIED` لا `DONE`.

---

## Phase SaaS-1 — إغلاق الثغرات دون كسر API أو الديمو

| ID | الحالة الحالية | المهمة الدقيقة | المصدر/المسار | تنفيذ متوافق إلى الخلف + اختبار الإغلاق |
|---|---|---|---|---|
| WEB-SEC-01 (P0) | 🟡 كود حماية مُضاف؛ تدوير الأسرار واختبار الهوست باقي | تدقيق توكن الصيانة المتسرب وحماية التهيئة | `UPLOAD_TO_ROOT.txt`، `api/setup_demo.php`، `api/diag.php`، private config | احصر التعرض والـcallers؛ انقل الوظيفة الإدارية إلى مسار داخلي مصادق، ثم دوّر التوكنات المتأثرة فقط بخطة rollback؛ اختبار `old token denied` و`authorized maintenance still works`؛ لا نشر قيمة السر. |
| WEB-SEC-02 (P0) | 🟡 كود حماية مُضاف؛ Live/CI E2E باقي | حماية توليد Gemini ephemeral tokens | `api/live_session.php:7-19,110-176`، `TenantVoiceSession.php` | فصل ديمو المستخدم المخول عن Tenant SIM session؛ مصادقة + per-IP/user/tenant quota/rate limit وconstraints المدعومة فعليًا؛ الحفاظ على تجربة الديمو الشرعية؛ `401/429` لغير المخول، مكالمة مخولة تعمل، لا Prompt/مفاتيح خام في الأخطاء. |
| WEB-SEC-03 (P0) | 🟡 الكود مؤمَّن جزئيًا؛ فحص Host/Bridge باقي | حماية أدوات الديمو/الفحص المنشورة | `omnira_api_tester.php`، `test.php`، `dev_bridge.php` حيث يوجد فعليًا | تحقق من Host access أولًا؛ انقل أدوات التشخيص إلى CLI أو Admin محمي؛ امنع proxy/setup عمومي؛ حافظ على فحوص Admin مصرح بها؛ HTTP external 403/404 مع اختبار admin-path pass. |
| WEB-SEC-04 (P0/P1) | 🟡 تحقق Ed25519 بالكود؛ حالة الهوست/STOP غير متحققة | فصل التحكم في هوست GitHub job عن منطق الموقع | `ops/host-link/worker.py`، النشر/SSH pipelines | أثبت حالة STOP أولًا، لا تعاود التشغيل؛ إن استخدم مستقبلًا: signed jobs + allowlist + replay protection؛ لا تغيّر قناة النشر النشطة/SSH دون تحقق مماثل وrollback. |
| WEB-SEC-05 (P1) | 🟡 حدود `lead.create` بالكود؛ Login/API E2E باقي | ضمان التوثيق ومنع brute force وتسرب الأخطاء | `saas/console/index.php:111-140`، `Bootstrap.php`، `api/v1/index.php`، Widget/hooks | اختبار أن DB login throttle الحالي يعمل بدل إعادة كتابته؛ إضافة missing endpoint throttles + bounded errors + CSRF/HMAC وscope؛ Browser Login/SSO/RBAC لا يتغير، no-cookie lock 429. |
| WEB-SEC-06 (P1) | 🟡 خصوصية تخزين محسَّنة؛ Vault/Restore باقي | حماية Vault والنسخ الاحتياطية | `saas/console/_core/Bootstrap.php`، أدوات Admin | صلاحيات ملفات صارمة ومفتاح دائم خارج webroot ونسخ احتياطي مشفر؛ لا تستبدل Vault key على بيانات حالية؛ decrypt pre/post ومفاتيح متعددة واسترجاع backup بنجاح. |
| WEB-SEC-07 (P1) | 🟡 أخطاء ديمو مُنقّحة؛ تدقيق Logs الكامل باقي | تقوية السجل والـAudit بدون تسريب | loggers/`VoiceCallEvidence.php`/`Actions.php` | Logs تحتوي operation/correlation/tenant/call IDs دون secrets وPII غير لازمة؛ رضا مالك المشروع عن تشخيص الأجهزة في التطوير؛ اختبار masking للأسرار دون إخفاء سبب العطل. |

**DoD Phase-1:** لا endpoint عام ينشئ تكلفة Gemini/يغير إعدادًا حساسًا بلا صلاحية؛ لا توقف لأي جلسة ديمو مخولة، أو دخول/SSO/ربط قائم؛ اختبارات مسبقة ولاحقة وتقرير Host حقيقي بعد نشر مصرح فقط.

---

## Phase SaaS-2 — الربط والتشغيل المؤسسي والفصل بين المستأجرين

| ID | الحالة الحالية | المهمة الدقيقة | المصدر/المسار | DoD مع حظر التراجع |
|---|---|---|---|---|
| WEB-CORE-01 | 🟡 كود + CI Offline PASS لـContract Probe؛ Phone/SIM E2E باقي | فحص/تثبيت Contract بين Phone وSaaS | `saas/console/device/v1/index.php`، `DevicePairing.php`، `TenantVoiceSession.php`، API legacy | versioned contracts + response fields stable، Token per binding/SIM/revoke/heartbeat؛ ثبت عمل الاقتران الحالي، لا تبدّل Device ID أو SIM mapping ولا تمنع العميل القديم قبل version gate. |
| WEB-CORE-02 | 🟡 إعدادات الرد ونشرها بالكود؛ Voice/SIM E2E باقي | اختيار Agent وVoice والـPublished Policy لكل شريحة | `DevicePairing.php`، `PublishedFlow.php`، `Actions.php`، `VoiceCatalog.php` | تغيير الإيجنت من Console يظهر في heartbeat/session الجديدة؛ المكالمة الجارية تحتفظ بالـsnapshot؛ صوت/لهجة/answer_delay والبروفايل الحالي لا يُستبدلون بإعداد Demo. |
| WEB-CORE-03 | 🟡 كود + CI Offline PASS لسلامة Flow/Tool؛ Provider E2E باقي | استكمال Flow Runtime والأدوات الحقيقية | `TenantVoiceSession.php`، `PublishedFlow.php`، `FlowAi*.php`، `VoiceToolGateway.php` | Preserve draft/published separation، تطويع Ask/Condition/Collect/Handoff، فقط verified tools تكتب/تحجز، replay/idempotency، لا نطق «تم الحجز» بلا provider result، اختبارات real provider sandbox. |
| WEB-CORE-04 | 🟡 كود + CI Offline PASS (بما فيه PDF Extract)؛ Host E2E باقي | Knowledge indexing/PDF/API facts | `VoiceKnowledgeSearch.php`، `VoiceOpenApi.php`، knowledge services | الحفاظ على المصادر والنسخ وowner review/priority/Tenant scope؛ KB اختيارية ولا تمنع Agent إن كانت فارغة؛ PDF حقيقي على Host + Arabic ranking + handling conflicts/TTL + no cross-tenant leak. |
| WEB-CORE-05 | ⬜ لا إغلاق موثّق ضمن خطة الإصلاح | موصلات WHMCS/Laravel/REST/WhatsApp | `VoiceToolGateway.php`، `VoiceOpenApi.php`، webhook handlers | لا تزيل العمليات الموجودة؛ Validate schema/signature/identity/tenant/caller permission؛ Booking/Ticket/Status callbacks with external IDs ورفض duplicate/replay؛ E2E على Sandbox قبل إنتاج. |
| WEB-CORE-06 | 🟡 Legacy fallback بالكود؛ ترحيل/Host E2E باقي | إسعاف تباين Pilot وTenant دون كسر النسخ المثبتة | `saas/mobile/server/session.php`، `device/v1/index.php`، routing | سجّل استعمال المسار الفعلي، أنشئ adapter/redirect موثق إن لزم؛ لا تحذف Pilot حتى صفر استدعاءات مثبت لفترة قبول ومع نجاح Client compatibility matrix. |
| WEB-CORE-07 | 🟡 كود + CI Offline PASS لإشعارات الموظف؛ هاتف/Host E2E باقي | تقارير المكالمات وملخصها والمساعدة البشرية | `VoiceCallEvidence.php`، `TenantWorkspace.php`، Calls/Reports APIs | Audit timeline immutable، Caller/Agent speaker، Agent tool references، حالات human requested/acknowledged؛ إبلاغ المدير بعد 10s **فقط إذا كان الإجراء مفعّلًا ومصرحًا**؛ لا حجز/اتصال خارجي وهمي. |
| WEB-CORE-08 | 🟡 كود + CI Offline PASS لعرض الباقة الحالية؛ Billing/Payment E2E باقي | قاعدة الاستهلاك والأرصدة والمكالمات | `SubscriptionPlans.php`، `UsageLedger`/usage tables، `TenantCampaignQueue.php` | no-answer لا يُحسب متصلًا، quota atomic/idempotent، انقطاع الشبكة لا يكرر الخصم، لا reset لبيانات الاشتراكات، DNC لا يُعطّل. |
| WEB-CORE-09 | ⬜ لا إغلاق موثّق ضمن خطة الإصلاح | اختيار Assistant/Voice keys والـFailover | `AiRelay.php`، `GoogleVoiceFailover.php`، provider profiles | احتفظ بكل المجموعات/المفاتيح المفعلة وبحدود الباقات الحالية؛ 429/503/timeout cooldown ~900s حسب السياسة، نفس Tenant/SIM/Context؛ لا تبديل إلى مفتاح غير مخول؛ E2E صوتي ما زال مطلوبًا. |

**DoD Phase-2:** ربط شريحتين وحسابين بمعرفات/صلاحيات منفصلة، Agent published instructions الصحيحة، Call report/Tool ID/Quota متوافق مع المصدر؛ كل واجهة واختبار عمل سابق يحتفظ بنتيجته.

---

## Phase SaaS-3 — تجربة المستخدم والباقات والريسيلرز والفوترة

| ID | الحالة الحالية | المهمة | ملفاتها المرجعية | معايير القبول |
|---|---|---|---|---|
| WEB-UX-01 | 🟡 شاشة ضبط الرد عولجت بالكود؛ باقي الأزرار غير مُعتمدة | تدقيق كل زر ونموذج ورابط ومفتاح Toggle (وظائف فعلية لا شكلية) | `Views.php`، `Actions.php`، `assets/js/app.js`، `flow-graph.js` | لكل Action: click → CSRF/RBAC → DB/state → refresh → feedback → error path؛ لا زر dead؛ Mobile/Desktop RTL/Light/Dark محفوظ. |
| WEB-UX-02 | ⬜ لا إغلاق موثّق ضمن خطة الإصلاح | الباقات والإدارة والاشتراكات للريسيلر والعميل | `SubscriptionPlans.php`، `Views.php`، `Actions.php` | Admin يعدّل باقة Reseller، Reseller يختار/يعدل ضمن صلاحياته، الترقية/التخفيض/التجديد واضحة؛ preserve assigned plans & ledgers؛ لا اشتراك مدفوع تلقائي بسبب إنشاء الحساب إلا إذا Trial مصرح. |
| WEB-UX-03 | ⬜ لا إغلاق موثّق ضمن خطة الإصلاح | End-to-end Billing/Kashier/manual/Wallet/Claim حسب القنوات المفعلة | Billing callbacks/`OrderClaim.php`/Outbox worker | Gateway webhook signature+amount+currency+invoice unique، paid_pending_claim قابل للربط الآمن، لا settlement وهمي؛ Refund/Retry/Expired tests؛ أي بوابة غير مفعلة لا تُعطل الحالية. |
| WEB-UX-04 | ⬜ لا إغلاق موثّق ضمن خطة الإصلاح | استكمال تسجيل/استعادة كلمة المرور دون MFA تجريبية | `Signup.php`، `index.php`، reset/email | Login البريد/كلمة المرور فقط، Reset token expiry، SSO signatures، Admin/Reseller/Customer، رسائل بريد حقيقية، عدم إزالة التسجيل حسب إعدادات Owner. |
| WEB-UX-05 | ⬜ لا إغلاق موثّق ضمن خطة الإصلاح | ريسيلرز وWhite-label domains | domain worker + `Views.php` + Host config | TXT ownership ثم DNS/TLS/Host routing فعلي؛ Reseller branded Login/Register/Logo، عزل تام، لا تغيير Domain قائم حتى ينجح البديل. |
| WEB-UX-06 | ⬜ لا إغلاق موثّق ضمن خطة الإصلاح | صلاحيات الدعم وإدارة الموظفين | RBAC/`StaffInvitations.php`/support grants | Admin/Reseller/Staff صلاحيات دقيقة بمدة موحدة، audit، اختبارات Privilege Escalation، وعدم تعطيل دخول الدعم المصرح. |
| WEB-UX-07 | ⬜ لا إغلاق موثّق ضمن خطة الإصلاح | اختبار الصوت على المتصفح إن كانت ميزة مفعلة | `saas/console/.htaccess`، `index.php`، Agent testing | `microphone=()` قد يحظر Test mic؛ اسمح للمسار الموثق الضروري فقط وبموافقة browser، ولا تفتح camera أو embed origins غير لازمة؛ اختبر Web Demo/Console على browsers مدعومة. |
| WEB-UX-08 | ⬜ لا إغلاق موثّق ضمن خطة الإصلاح | خطط الحملات/جهات الاتصال/DNC والإشعارات | Campaign/Contacts/`TenantCampaignQueue.php` | Preview/Import/Mapping/Pause/Resume/Cancel/Arm وConsent؛ لا Calls صادرة غير مقصودة، DNC enforced، إرسال notifications idempotent، لا خلط قاعدة قديمة وجديدة. |

**DoD Phase-3:** اختبارات مستخدم حقيقية على دور Admin + Reseller + Customer + Demo/Trial/Expired، واجهات وعناصر محفوظة، لا تغير في Defaults أو Permissions أو Account State دون فعل مصرح.

---

## Phase SaaS-4 — جودة الإنتاج، CI/CD، ونقل آمن للإصدارات

- [ ] **WEB-REL-01:** Run جميع `tools/test_*` المرتبطة بـAuth/SSO/Tenants/Flow/KB/Billing/Voice/API، PHP 8.2/8.3 compatibility، JS lint، Browser role journeys؛ اختبارات smoke حية بعد تصريح نشر. احتفظ بحالة PASS/FAIL لكل suite **ولا تخلط Site CI مع SIM E2E**.
- [ ] **WEB-REL-02:** طبّق CI source SHA == Publish package SHA == Host receipt SHA وSnapshot DB schema؛ لا يعتمد النشر على SHA تاريخي إلا rollback صريح.
- [ ] **WEB-REL-03:** Migration additive/expand-contract: إضافة أعمدة/جداول أولًا، dual-read/write إن لزم، backfill batch قابل للاستئناف، تدقيق old/new rows، ثم cutover بموافقة؛ **ممنوع DROP/RENAME/إعادة seed على قاعدة حية** قبل تحقق مستقل.
- [ ] **WEB-REL-04:** Endpoint/API versioning + feature flags لكل تغيير حساس، مع دعم إصدارات Android الحالية وPilot؛ اختبارات `401/403/409/429/500` وحالات الشبكة وUndo.
- [ ] **WEB-REL-05:** خطوة نشر واحدة صغيرة على Staging/Host isolated QA؛ نسخة Restore/rollback، لوج منفصل، HTTP screenshots/API probes؛ **لا نشر موقع يولّد تغييرًا في Native/OTA**.
- [ ] **WEB-REL-06:** مراقبة زمن استجابة voice-session والموصلات، error budgets، provider/tenant/host anomalies، retry/failover دون retry عشوائي يكرر رسومًا أو عمليات.
- [ ] **WEB-REL-07:** اختبار مستقـل لمصفوفة التوافق مع Android دون تعديل كوده؛ يستلزم تأكيد فريق/مسار التطبيق أن كل payload/command ظل متوافقًا.

---

## مصفوفة اختبار منع التراجع — Mandatory SaaS Regression Gate

| حالة يجب أن تبقى تعمل | معيار الاختبار قبل وبعد |
|---|---|
| حسابات وأدوار Admin/Reseller/Customer | Login/Logout/Reset/SSO/Auth/CSRF/RBAC؛ لا OTP تجريبية ولا كسر جلسة مشروعة |
| التسجيل/التفعيل/دومينات الريسيلر | نفس الروابط والشعارات/الفورم ونطاقات الصلاحيات؛ Existing users preserved |
| إنشاء/تعديل/نشر Agent/Flow | نفس Voice/Provider/Instructions/Published revision، Draft isolation، إضافة/حذف/حفظ كما كان |
| Knowledge/Integrations | موجودة بنفس IDs وVersion/Priority/Access، Working connector responses محفوظة |
| الباقات/الاشتراكات/الدفع/الاستحقاقات | لا تغيير أرصدة/حدود/مدفوعات سابقة؛ all transitions atomic |
| مفاتيح Providers | لا Rotation غير مصرح؛ AES-GCM decrypt تعمل، Pool/scopes preserved |
| الأجهزة/الشريحتان/المكالمات | paired devices تظل paired؛ update/heartbeat/voice_session requests تستمر بنفس contract |
| الحملات/الجهات/DNC | لا اتصال جديد من الاختبار؛ قاعدة منع الاتصال محفوظة |
| Call reports/handoff/notifications | أحداث Call IDs ومصدرها وحالاتها لم تتغير، لا false human completion |
| واجهة الويب | RTL/Responsive/Dark-Light/Search/Filters/Buttons تعمل قبل وبعد |
| قواعد الأمان | no-cross-tenant، malformed/webhook spoof، origin/cost controls، no-cookie throttle |

**مقياس رفض الإصدار:** أي Broken feature سبق توثيق نجاحها = **RELEASE BLOCKER**، حتى لو نجح إصلاح ثغرة أو ظهرت نسخة واجهة أجمل. عند فشل Regression: rollback للكود/feature flag مع الإبقاء على DB المتوافق، تحليل السبب، ثم إعادة الاختبار. لا يُسمح بالادعاء أن «كل شيء شغال» اعتمادًا على CI فقط.

## تسليم كل مهمة (الحد الأدنى الإلزامي)
`Task ID | Before/After behavior | Source SHA | Affected routes/DB | Tests + exact outputs | Preserved features evidence | Security impact | Host receipt (if published) | Rollback | PASS/FAIL/UNVERIFIED`.

### الحالة الحالية بعد التحديث
**تم رفع تعديلات كود SaaS فعلية على فرع إصلاح مستقل، لكن لم تُدمج ولم تُنشر، ولم تثبت اختبارات CI/Host/E2E نجاح الإصلاحات.** لهذا لا تُوسم أي مهمة شاملة بـ `DONE` حتى تحقيق الـDoD المبين أعلاه.
**تأكيد المالك:** تم حذف `VS/`، ولا توجد مهمة SaaS متبقية تخصه.
