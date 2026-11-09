# OnTrack Voice — التقرير المُجمّع الأولي للتدقيق الصفري وخطة الإغلاق
**التاريخ:** 2026-11-09  
**الحالة:** PRELIMINARY / AUDIT ONLY / NOT A RELEASE CERTIFICATE  
**النطاق:** SaaS + Android + واجهات الصوت/الاتصال + API/DB/Billing/Tenancy + CI/Operations + Setup Wizard.  
**المستودع:** `OnTrack-Development/OnTrack-voice`  
**مرجع المقارنة الأحدث الذي جرت قراءته:** `release/voice-v1-20261106`.  
**مجلد مستبعد بقرار المالك:** `VS/` — لم يُفتح ولم يُراجع ولم يدخل نطاق النتائج أو الإجراءات.  
**قاعدة السلامة:** لا تُدرج أي كلمات مرور أو توكنات أو مفاتيح خاصة في هذا التقرير، ولا يُشغّل أي نشر أو مكالمات أو ترحيل بيانات ضمن مهمة التدقيق.

> **النتيجة التنفيذية:** توجد مخاطر أمنية ومشكلات اعتمادية وقيود تكامل تمنع وصف المنظومة بأنها جاهزة للإطلاق التجاري. يوجد **كود أحدث في فرع release** يعالج بعض الادعاءات القديمة الواردة في فحوص `main`؛ لذلك **لا يصح جمع أرقام التقارير جمعًا حسابيًا، ولا إعلان كل الملاحظات ما زالت مفتوحة**. يجب إغلاق الثغرات عالية الخطورة أولًا، ثم إثبات مسار مكالمة E2E لكل SIM ولكل نوع شبكة، ثم اختبار الباقات والربط والفوترة فعليًا. هذه نسخة أولية مُجمَّعة، وليست إعلانًا بأن أي إصلاح تم.

---

## منهج الإثبات والمصادر

### مصادر الإدخال
1. `Rep1.md` — تدقيق هندسي وأمني مفصل لنسخة `main` محلية، به 15 مجموعة ملاحظات عالية/حرجة ومتوسطة، جرد للمكونات، ومخطط Wizard، وأربع مراحل إصلاح.
2. `تقرير فحص مشروع OnTrack Voice — المشاكل والتوصيات.md` — تدقيق ثابت بتاريخ 9 نوفمبر، أحصى 22 مشكلة (4 حرجة، 6 عالية، 9 متوسطة، 3 منخفضة)، ونبّه أنه **لم يشغّل اختبارات أو الموقع الحي أو هاتفًا فعليًا**.
3. نص «خطة الفحص الشامل الصفري + مخطط معالج التصاريح» المقدم من المالك — **خطة عمل** وليست نتائج فحص نهائية، وتُلزم بتقسيم التقرير لخمسة أقسام وDoD، وتستبعد `VS/`.
4. قراءة مباشرة **جزئية** لكود GitHub الخاص على `release/voice-v1-20261106` بتاريخ هذا التقرير، تشمل: `AGENTS.md`، `README.md`، `CLAUDE.md`، تسليم `docs/plan/V1_0_0_SITE_MEMORY_HANDOFF_20261106_AR.md`، TODO الموقع، `api/live_session.php`، `api/setup_demo.php`، `api/diag.php`، `omnira_api_tester.php`، `UPLOAD_TO_ROOT.txt` (وجود الملف فقط؛ لم يُنسخ السر)، `ops/host-link/worker.py`، `saas/console/index.php`، `saas/console/.htaccess`، `saas/console/_core/DevicePairing.php`، `TenantVoiceSession.php`، `PublishedFlow.php`، `TenantCampaignQueue.php`، `saas/console/device/v1/index.php`، `saas/mobile/server/session.php`، `saas/mobile/ota/ui.html`، `MobileLinkActivity.kt`، `MobileLinkService.kt`، `MobileLiveCall.kt`، `LinkPcm.kt`، `CallEventOutbox.kt`، `TenantDeviceEnrollment.kt`، `prepare.py`، وملفات CI محددة.

### مستويات الثقة (إلزامية عند قراءة سجل العيوب)
- **[V] مُشاهَد في مصدر release الحالي:** الكود/المسار قُرئ مباشرة؛ **لا يعني** وجود دليل استغلال حي أو نجاح/فشل هاتف.
- **[R] من تقرير خارجي/لقطة main أقدم:** له مسار/سطر في مصدر التقرير، ولكن **لا يُحكم بأنه عيب حالي** قبل مراجعة الفرع التنفيذي والهوست.
- **[P] موجود جزئيًا:** مكوّن حقيقي في فرع release، لكن إغلاق السلوك المطلوب لم يثبت E2E.
- **[H] سلوك الهاتف:** موصوف في ملاحظات المالك/المستندات، ولم يُعَد اختبار جهاز فعلي في هذه المهمة.
- **[X] ادعاء تاريخي تجاوزه أو ناقضه كود أحدث:** لا يُفتح كعيب جديد بنفس الصيغة.
- **UNVERIFIED:** لم تُجرَ تجربة مستقلة أو ليس لدينا دليل مباشر من الهوست/الهاتف. **لا تُحوّل إلى FAIL أو PASS بلا دليل.**

**قيد أساسي:** لا مقارنة diff شاملة لكل commits، ولا قراءة حية للهوست، ولا تشغيل PHP/Kotlin/CI، ولا مكالمة أو اختبار استغلال أو فحص تاريخ Git في هذه المهمة. مسارات/أرقام الأسطر التي تحمل الوسم [R] من تقارير التدقيق الأصلية، وقد تختلف أو تختفي من الفرع الحالي. كل ادعاء من [V] يعتمد على القراءة المباشرة للملف المذكور.

---

# 1) جرد الحقائق الكودية (Fact-Based Inventory)

| الطبقة | الموجود المتحقق أو المذكور | الوضع الصحيح للتقرير |
|---|---|---|
| ديمو الصوت الجذري | `api/live_session.php` وواجهة JS وGemini Live auth_tokens | **[V]** مسار ديمو مستقل لا يجوز افتراض أنه API الصوت المؤسسي المصادق. |
| لوحة SaaS الحالية | `saas/console/` PHP/PDO/SQLite أو MySQL؛ حسابات ومواقع وإيجنتات وباقات وRBAC وواجهات أجهزة | **[V]** موجودة وتطورت عن لقطات main القديمة، لكنها ليست دليل تشغيل تجاري كامل. |
| طبقة ربط المستأجر والجهاز | `DevicePairing.php`، `TenantDeviceEnrollment.kt`، `device/v1/index.php`، نشر إعدادات الإيجنت | **[V/P]** الاقتران ذي الست أرقام وToken لكل جهاز ومسار voice_session موجودان؛ إثبات المكالمة الفعلية لكل SIM مستقل. |
| مسار Pilot القديم | `saas/mobile/server/` وملفات JSON وBearer مشترك تاريخيًا | **[V/R]** ملفات التوافق باقية؛ مسار Tenant أحدث يمنع تشغيل Pilot في وضع Tenant وفق `MobileLinkService.kt:130-141`. يلزم إغلاق الاعتماد القديم بأمان. |
| Flow Builder | محرر عقد + `PublishedFlow.php` + `TenantVoiceSession.php` | **[V/P]** توجد نسخة منشورة وتوليد توجيه من الرسم؛ هذا **ليس محرك تنفيذ حتمي** للعمليات الخارجية (CRM/booking/handoff)؛ الكود نفسه يصرّح بذلك. |
| المكالمات والتقارير | Android `MobileLiveCall.kt` و`CallEventOutbox.kt` مع طبقات Tenant call evidence | **[V/P]** تسجيل أحداث ومعالجة صوت موجودان؛ جودة الصوت والرد الحقيقي وتسليم المكالمة والحملات تتطلب اختبار جهاز.
| الباقات والتحصيل | منطق Console + اختبارات منشورة في التسليمات، مع مكونات Kashier/Laravel تاريخية | **[P/R]** بعض العيوب القديمة قد تكون أصلحت؛ لا يوجد في هذا التدقيق Settlement حقيقي مؤكد ولا دليل E2E شامل للحصص. |
| Laravel | شجرة `saas/app` ووحدات أخرى بحسب الفحوص، لكن الأرشيف لم يتضمن كامل bootstrapping | **[R]** لا يُعتبر Runtime حيًا لمجرد وجود Models/Migrations/Tests؛ حدِّد المعمارية المعتمدة قبل الاعتماد عليه. |
| OTA / APK | UI OTA وNative APK بتوقيع وشهادة وهوية موروثة وCI | **[V/P]** البناء/التوقيع/النشر ثلاث بوابات مختلفة عن قبول SIM-Audio؛ مخاطر هوية debug لا تزال ظاهرة في Workflows محددة. |
| عمليات النشر | `ops/host-link/worker.py` وWorkflows ونص توثيق SSH مباشر | **[V/P]** مصدر العامل غير محمي بتوقيع job؛ **مذكرة التسليم تفيد أن Host-Link موقوف بعلامة STOP**. لا يُفترض أنه جارٍ الآن. |
| حالة المنتج | مرحلة تجربة داخلية وبطاقات Demo حسب `AGENTS.md` | **[V]** ليس هناك عملاء مدفوعون حقيقيون وفق قرار المالك؛ لا يجوز تفسير نجاح الـCI على أنه إتمام إطلاق تجاري. |

**تعارضات لا يجوز طمسها:** تقرير `main` الأول يقول إن الهاتف غير مرتبط بالـConsole وإن `tenantMenu` غير مدعوم في Rev29؛ بينما فرع release يحتوي `TenantDeviceEnrollment.kt` و`device/v1/index.php` وشاشة `ui.html` لربط SIM 1/2. كما أن التقرير القديم يقول إن دخول Console يُبطّأ بعداد Session فقط؛ أما `saas/console/index.php:111-140` الحالي فيستدعي `auth_login_throttled` المخزنة مركزيًا أيضًا. تقرير CI القديم يذكر checkout SHA مثبتًا، بينما `voice-saas-publish.yml` الجاري قراءته يستخدم checkout على ref المهمة، لا ذلك SHA الصلب. **هذه مسائل [X] أو تحتاج تحقق إنتاج، وليست عيوبًا جارية محسومة.**

---

# 2) سجل الثغرات والأخطاء الموحَّد (Deduplicated Findings Register)

**ترتيب P0/P1/P2/P3 يعكس أولوية التعامل، لا إثبات حدوث استغلال.** العيوب المتشابهة بين التقريرين دُمجت في سطر واحد مع حفظ مرجع المصدر.

## A — الأمن وسلسلة التوريد (Security / Supply Chain)

| ID | الخطورة / الثقة | الدليل (مسار:سطر أو دالة) | التشخيص المُجمَّع | المطلوب قبل إغلاقه |
|---|---|---|---|---|
| SEC-01 | **P0 / [V+R]** | `UPLOAD_TO_ROOT.txt` (موجود بالفرع)؛ التقرير الثاني S-01 يحيل إلى سطر 8 ومطابقة `TOKEN_HASH` | تقرير التدقيق يذكر توكن صيانة فعليًا ضمن الريبو، وربما الهوست، يتيح تهيئة مفاتيح/مسارات صيانة. لم ننقل قيمة التوكن ولم نتحقق من تطابقها في هذه المهمة. | تدوير الأسرار المحتملة، إزالة الملف من النشر، منع عرضه عبر HTTP، فحص access logs والتاريخ، ثم إثبات إبطال القديم. |
| SEC-02 | **P0 / [V]** | `api/live_session.php:7-19,110-176` | POST للديمو يولّد Gemini `auth_tokens` ولا يظهر في الملف فحص مصادقة/Origin/Rate Limit؛ `tokenBody` يحتوي `uses` ووقت الصلاحية فقط، ويُرجع `system_instruction` للعميل. خطر استهلاك تكاليف وإساءة الاستخدام. | مصادقة/حدود طلبات واستحقاق، فصل ديمو عن Tenant، فرض constraints مدعومة من المزود، وعدم إعادة Prompt/تفاصيل الخطأ الخام. |
| SEC-03 | **P0 / [V]** | `api/setup_demo.php:14`، `api/diag.php:37` | أدوات صيانة وتشخيص باقية وتقبل أسرارًا عبر `GET`؛ احتمال تسريب عبر سجل URL. | إزالة الوصول العام ووقف التعامل مع GET للأسرار ونقل المفاتيح خارج Webroot، مع اختبار 403/404 من الخارج. |
| SEC-04 | **P0 / [V] مع حالة تشغيل UNVERIFIED** | `ops/host-link/worker.py:96-145,258`، التقرير الثاني S-02 | `validate_job` يتحقق من البنية/الوقت/المجلد و`allow_exec` لكنه لا يثبت توقيع مستقل للوظيفة قبل تنفيذ `argv`؛ **العامل مُبلغ أنه موقوف** في مستند التسليم بتاريخ 7 نوفمبر. | التأكد من STOP وعدم إعادة تشغيله؛ إن أعيد استخدامه: توقيع Ed25519 والتحقق محليًا + allowlist للأوامر + صلاحيات فرع محدودة. |
| SEC-05 | **P0 / [V]** | `.github/workflows/mobile-ui-ota.yml:27-39` و`android-audio-proof.yml:68-115` | استخدام هوية `~/.android/debug.keystore` و`androiddebugkey` وCache في عمليات توقيع. تبديلها دون خطة قد يمنع تحديث الأجهزة القائمة. | Release signer محفوظ في Secrets/بيئة محمية، خطة ترحيل توافقية أو إعادة تثبيت مدروسة، إثبات سلسلة توقيع OTA/APK، وعدم مسح هوية أجهزة المالك عشوائيًا. |
| SEC-06 | **P1 / [V+R]** | `omnira_api_tester.php`؛ التقريران عند tester | أداة اختبار خارج نطاق المنتج باقية في جذر الريبو؛ تقرير الفحص يصف حماية ضعيفة وإرسالًا عبر Proxy. وجودها على الهوست الحالي غير مختبر. | حذف/حظر من المسار العام والتحقق HTTP، وحصر الاختبارات داخل CLI/بيئة مصادقة. |
| SEC-07 | **P1 / [R]** | `saas/mobile/server/auth.sha256`، `common.php`، `index.php` | سر Legacy واحد لجهاز/إدارة وتجزئة ثابتة؛ احتمال تخمين Offline وعدم القدرة على إبطال جهاز بمفرده. | عزله وإلغاؤه فقط بعد فحص جميع العملاء التجريبيين وترحيل ربط الجهاز الفردي؛ منع الانقطاع المفاجئ. |
| SEC-08 | **P1 / [X+P]** | `saas/console/index.php:111-140` | ادعاء «الحد عبر Session فقط» صحيح في لقطة قديمة لكنه لا يصف الكود الحالي الذي يستدعي `auth_login_throttled`. | **إعادة اختبار** 9 محاولات بلا Cookies ومن IPs متعددة؛ لا نسميه ثغرة حالية قبل الفحص. |
| SEC-09 | **P1 / [R]** | التقرير الثاني S-07، `docs/evidence/screenshots/03-other-agent-ssh-command.png`، `ops/host-link/results/` | احتمال انكشاف أسماء خوادم/أوامر SSH ومعلومات بنية نشر ضمن ملفات الأدلة، لا يلزم إعادة نشر تفاصيلها هنا. | حذف الصور/النتائج الحساسة، قصر الصلاحيات، والتحقق من `StrictHostKeyChecking` وسياسات الـRunner. |
| SEC-10 | **P1 / [R]** | التقرير الثاني C-04، Workflows SSH/Host-Link/Bridge | تعدد قنوات النشر مع Runner ذاتي قد يسمح بتغيير حي خارج بوابة فحص واحدة. مذكرة أحدث تقول إن Host-Link متوقف. | Inventory لكل قناة + قناة نشر واحدة محكومة، RBAC وموافقة بيئة وتقرير Receipt مع rollback. |
| SEC-11 | **P1 / [R]** | `saas/console/index.php`، `Bootstrap.php`؛ التقرير الثاني S-09/S-10 | تسريب أخطاء داخلية، مفتاح Vault على نفس حساب الملفات، fallback إلى temp، وكلمات مرور أولية تظهر برسالة Flash. لم نثبت التفاصيل كلها على الفرع الحالي. | فحص مصدر فعلي + اختبارات رفض/رسائل خطأ وعدم فقد Vault key عند إعادة التشغيل. |
| SEC-12 | **P1 / [R]** | `saas/console/api/v1/index.php`، مسارات Widget و`lead.create` | نقص Rate Limit على مسارات غير تفاعلية/عامة وفق التقارير؛ خطر Spam/DB abuse. | اختبار Rate Limit لكل IP/token/account، Idempotency للأحداث، واختبار العزل. |

## B — الهاتف والصوت والـSaaS والاعتمادية

| ID | الخطورة / الثقة | الدليل | الحالة الدقيقة والخلل | شرط الإغلاق |
|---|---|---|---|---|
| MOB-01 | **P0 وظيفي / [V+H]** | `MobileLinkService.kt`، `MobileLiveCall.kt`، `CLAUDE.md:25-40` | تشغيل Carrier/SIM ونجاح Wi-Fi موصوفان في أدلة المالك؛ LTE تأخير/انعدام صوت في نسخ سابقة. مسار CaptureDaemon والميوت وPCM مختلف حسب الجهاز. لا يجوز القول «لم يعمل الصوت مطلقًا» ولا «E2E PASS» بلا تسجيل حديث. | مكالمة واردة وصادرة حقيقية على Wi-Fi و4G مع إثبات الطرفين/زمن أول رد/كتم/استلام بشري/نهاية سليمة. |
| MOB-02 | **P1 / [R/P]** | `MobileLinkService.kt`، `MobileLiveCall.kt`؛ التقرير الأول البنود 6 و13 | الخلط القديم بين `OFFHOOK` و«العميل رد» قد يفتح AI أثناء الرنين. تغير كود Android الحالي بشكل كبير؛ حالة العيب الحالية غير مثبتة. | اختبار `STATE_DIALING` مقابل `STATE_ACTIVE` و«لم يرد/مشغول/رفض» على الجهاز، دون تسجيل مكالمة مكتملة خطأ. |
| MOB-03 | **P1 / [V/P]** | `DevicePairing.php:10-57,130-145`، `TenantDeviceEnrollment.kt:60-125`، `device/v1/index.php` | ربط Tenant لكل SIM موجود في الكود الحديث؛ الربط الفعلي للصوت/اختيار شريحة outgoing والتحويل بين حسابين يحتاج E2E. | SIM1 وSIM2 مختلفتان، كل واحدة بهوية Tenant/Agent صحيحة، ولا تسريب Contacts/Calls/Knowledge. |
| MOB-04 | **P1 / [V]** | `CallEventOutbox.kt:41,73,94,118,235` | ما زال `check(pending.length()<1000)` يُلقي استثناء عند الامتلاء، و`singleOrNull()` قد يفشل الربط عند تعدد مكالمات متقاربة. | تخزين SQLite/Room متين، سياسة ضغط/Retry آمنة، ولا توقف للحلقة أو فقد هوية المتصل في اختبار انقطاع الشبكة. |
| MOB-05 | **P1 / [V+R]** | `MobileLinkActivity.kt` قرب `setWifiFreeMode`، `prepare.py`، `LivePcm.java` في تقرير 1 | محرك الصوت معتمد جزئيًا على ADB/CaptureDaemon؛ وضع «بدون Wi-Fi» ينبه صراحة إلى منفذ 5555. قابلية الوصول تعتمد على الشبكة/الجهاز ويجب ألا تكون افتراضية. | حصره في مسار تشخيص متقدم ومراقبة إغلاق المنفذ، عدم ترك Debug مفتوح، ودليل صوت بديل إن كان ممكنًا على الأجهزة المدعومة. |
| MOB-06 | **P1 / [V]** | `LinkPcm.kt:7-66` | التحويل الحالي يُكرر عينات الإخراج ويستخدم تجميعًا/اختزالًا للإدخال؛ جودة مكافحة aliasing والتحويل العالي لم تثبت بمقاييس. | اختبار قياسات Resampler (SINAD/aliasing/latency) ومقارنة تسجيل GSM حقيقي قبل/بعد مع عدم تغيير channel MIX الموثق. |
| MOB-07 | **P1 / [V/P]** | `TenantVoiceSession.php:1-55` و`PublishedFlow.php` | Flow المنشور يتحول إلى إرشادات route؛ التعليق والكود يمنعان اعتبار CRM/booking/handoff عمليات خارجية نُفذت بمجرد الكلام. العيب الأصلي «Flow مجرد JSON دون أثر» تجاوزه كود جزئي، لكن Runtime حتمي للأدوات لم يثبت. | State tracker + handlers فعلية + رد API موثق + إعادة اختبار Ask/Condition/Handoff/Booking وتقييد Tenant. |
| MOB-08 | **P1 / [V+R]** | `TenantCampaignQueue.php:122-138` مقابل `saas/mobile/server/pilot_workspace.php:253-307` من تقرير 1 | طابور Tenant الحالي يعالج انتهاء Lease ويتجنب إعادة الاتصال الأعمى؛ مشكلة «Leased للأبد» قد تخص Pilot القديم فقط. | اختبار crash/restart Offline وبقاء الحملات الصحيحة دون duplicate dial؛ رصد أي عميل ما زال يستدعي Pilot. |
| MOB-09 | **P1 / [P]** | `saas/console/device/v1/index.php:33-143`، `TenantVoiceSession.php`، `saas/mobile/server/session.php:9-21` | توجد طبقتا جلسة: Tenant موثقة وPilot/Demo قديم؛ خطر خلط البروتوكول والإعدادات إن دخل التطبيق المسار الخطأ، لا يعني أن الربط Tenant صفر. | طلب Session من الشريحة الموثقة يطبق الصوت/البرومبت المنشور، ويلفظ جلسة الغير؛ اختبارات Legacy regression من دون مسار مفتوح. |
| MOB-10 | **P1 / [P/R]** | `saas/console` و`Actions.php` و`OrderClaim.php` و`UsageLedger.php`، تقرير 1 بند 5 | منح اشتراك دون تحقق مالي أو توقف `paid_pending_claim` أو Worker وهمي ملاحظات خطيرة في فرع قديم؛ الفرع الحالي أكبر وبه اختبارات وأسعار وتغيرات، لذلك الحالة **تحتاج تدقيق مسار مالية نهائي**. | تجربة دفع sandbox مع Webhook موقّع + Claim لهوية المستخدم + Metering بعد المكالمة + منع الازدواج/التجاوز دون تفعيل مجاني عارض. |
| MOB-11 | **P1 / [P/R]** | `saas/console/`، `VoiceCallEvidence.php`، تقرير 1 `UsageLedger.php` | ملخصات وتقارير مكالمات وأدلة نصية موجودة جزئيًا؛ التزامن/التسجيل/الاستهلاك الفعلي على الجوال لكل Tenant غير مُثبت. | لكل Call ID: الاتجاه، SIM، أوقات، transcript، summary، تكلفة/حصة، disposition، مصدر الدليل، retry idempotency. |
| MOB-12 | **P2 / [V]** | `saas/console/.htaccess:18` و`index.php:12` | `Permissions-Policy` يعلن `microphone=()` للـConsole؛ يتعارض مع اختبار Agent عبر ميكروفون الويب إن كانت الصفحة تريد هذه الميزة. | السماح المقيّد فقط لصفحات اختبار الصوت وبموافقة المستخدم، مع اختبار Browser E2E. |
| MOB-13 | **P2 / [R]** | `pilot_import_file.php`، `call_ledger.php`؛ تقرير 1 بند 15 | استيراد CSV/XLSX بأعمدة ثابتة وقارئ سجل ملفات خطي في Pilot القديم؛ قد لا يكون مسار Tenant الحالي. | Column mapping وتحليل عناوين/تنسيقات، سجل DB مفهرس، وE2E Import→Campaign. |
| MOB-14 | **P2 / [R]** | `MobileLinkService.kt` (تقرير 1 بند 12) | Spam إشعارات كل 800ms وHTTP من حلقة المراقبة ظاهرتان في لقطة أقدم؛ الكود الحديث تغير ولم نتحقق من هذه السطور بعينها. | Perf test بطارية/Notification count وWatchdog أثناء مكالمة 30 دقيقة وخمول 24 ساعة. |
| MOB-15 | **P2 / [R/P]** | `prepare.py` وAndroid Manifest الناتج، `MobileLinkActivity.kt` | تقارير تصاريح Foreground Service والاتصال تخص builds متفاوتة؛ ملف upstream Manifest يُركّب وقت CI، فلا يصح الحسم بقراءة patch وحده. | استخراج **Merged Manifest من APK المبني** على API 31–36، اختبارات Background/Locked Screen وRuntime permissions. |
| MOB-16 | **P2 / [R/P]** | `Views.php`، `Actions.php`، مسارات reseller DNS/TLS بحسب التقرير الأول | White-label domains وDNS/TLS وتفعيل العلامة قد تبقى PARTIAL؛ لا توجد شهادة DNS حي في المواد المدخلة. | TXT proof ثم إصدار/تجديد TLS والتحقق من Host-routing وعزل تسجيل الدخول والمحتوى. |
| MOB-17 | **P2 / [R]** | `WhatsappWebhookController.php` والتقرير الثاني Q-02 | Dedup يعتمد على أول رسالة في Laravel بحسب الأرشيف؛ قد يسقط status/read أو payload متعدد، لكن Laravel المزعوم غير runtime معتمد هنا. | تحديد المسار الحي أولًا، اختبارات webhook multi-entry/status/idempotency/duplicate/out-of-order. |
| MOB-18 | **P2 / [R]** | `lib/EdgeTtsClient.php:63` و`assets/app.js:467` وفق تقرير 1 | Regex سرعة Edge ومُعالج صوت Web من نوع `createScriptProcessor` مسائل جودة/توافق قد تخص ديمو ثانوي لا صوت SIM الجاري. | اختبارات نقطة الاستخدام قبل إصلاحها؛ عدم تعديل قناة الصوت الأساسية عرضيًا. |
| MOB-19 | **P2 / [R/P]** | `docs/plan`، `version.json`، `status.php`؛ التقرير الثاني Q-03/Q-05/Q-06 | تشتت نسخ root/reference/Laravel ونسخ إصدار متضاربة ومكتبات ميتة؛ لم يثبت أنها كلها محملة في الإنتاج الحالي. | جرد مسارات Runtime الفعلية، مصدر واحد للإصدار، حذف آمن للأرشيفات/المكتبات غير المستخدمة بعد اختبارات Regression. |
| MOB-20 | **P2 / [X+P]** | `saas/mobile/ota/ui.html` الحالي به `v1Pair`/`v1Pair2`/`v1Setup`/`v1Test` | مشكلة OTA Rev29 القديمة «زر الربط غير موجود/tenantMenu مكسور» ليست وصفًا كافيًا للواجهة الحالية. يوجد Wizard مختصر وعناصر ربط، لكن نجاح نقرات OEM وE2E غير مثبت. | Browser Bridge command contract + نسخة OTA فعلية على الهاتف + فحص كل زر ووظيفته. |

### قرارات وظروف مؤثرة ليست أخطاءً بحد ذاتها
- **2FA:** قرار المالك بتاريخ 4 نوفمبر هو تعطيل OTP/2FA أثناء **التجربة الداخلية فقط**؛ لذلك «غياب فرض 2FA» ليس عيبًا تنفيذيًا لهذا الطور، لكنه **بوابة إطلاق تجاري مستقبلية**. لا يُعاد فرضها الآن دون أمر جديد.
- **تقرير «الصوت لم يعمل مطلقًا»:** يتعارض مع أدلة تشغيل هاتف المالك و`CLAUDE.md:25-40`؛ الصحيح حاليًا أن نتيجة إعادة الاختبار المستقل على جهاز فعلي في هذه المهمة **UNVERIFIED**، مع وجود مشكلات LTE/PCM تاريخية.
- **رفع كود ≠ نشر:** `voice-saas-publish.yml` على branch release الجاري قراءته لا يحتوي checkout SHA الصلب المذكور في تقرير `main`، لكن وحده لا يثبت أن المضيف الحالي يشغل نفس SHA؛ يُحسم بـ Host Receipt/Hash فقط.
- **الـSetup Wizard:** يوجد في الواجهة الحالية مسار إعداد/اختبار؛ الخطة المطلوبة هي **استكمال فاحص صلاحيات أصلي وحقيقي**، لا الادعاء بأن كل خطواته غائبة.

---

# 3) النواقص الأساسية لإطلاق المنتج (Functional Gaps / Acceptance)

| المسار | المطلوب بدقة | دليل القبول المفقود حاليًا |
|---|---|---|
| جلسة الصوت المؤسسية | Tenant + SIM + Agent + Provider key + Published voice/instruction/knowledge/Flow | Voice session scoped تُسمع فيها تعليمات مختلفة لموقعين، ومنع الوصول المتبادل؛ لا استخدام Root Demo المفتوح. |
| تحكم المكالمة | حالة اتصال حقيقية: ringing/dialing/active/human/ended، auto-answer مضبوط، mute/route صادق | لوج + صوت من الطرفين لمكالمات واردة/صادرة وعلى Wi-Fi وLTE. |
| ربط الشريحة | QR/6 digits، SIM1/SIM2، إعادة ربط/revoke، اختيار رقم الصادر، واختلاف الحسابين | جهاز Dual-SIM فعلي، هويات منعزلة، شريحة الصحيحة واتصال مصدّق. |
| Flow Runtime | Execute/Condition/Ask/Collect/CRM/Handoff مع ردود أدوات موثقة | تتابع State لكل عقدة وTool Trace، منع الادعاء بإتمام حجز بلا تأكيد API. |
| قاعدة المعرفة | فهرسة/بحث وعزل Tenant ومراجع استعمال، لا حاجة لتعطيل الإيجنت إن كانت KB فارغة | سؤال من ملف/KB ثم سؤال بلا KB، والأجوبة لا تتسرب لمستأجر آخر. |
| الباقات/الاشتراكات | Eligibility، checkout، settlement، claim، renewal، quotas، reseller entitlements، تجاوز مضبوط | رحلة عميل جديدة مع دفع تجريبي، تعديل باقة، انتهائها، خصم المكالمات، إيقاف تجاوز بدون إذن. |
| التسجيل/التقارير | Call events + source IDs + transcript/summary/recording عند توافره + Staff escalation | لكل مكالمة تقرير تدقيقي موحد يشمل المصدر والحالة وعدم احتساب no-answer كـ completed. |
| الجودة على الشبكة | لا بطء مُتراكم على الداتا، recovery للصوت والـProvider cooldown | قياسات أول صوت وTurn latency وPacket loss وReconnect وNetwork switch. |
| التشغيل والنشر | Signed releases، هوية signer مدعومة، قناة نشر واحدة، Host receipts، rollback | SHA مطابق من اختبار حتى host حتى apk؛ توقيع موثق؛ rollback جرّب بالفعل. |
| السلامة التشغيلية | منع استغلال public endpoints، التحكم في مفاتيح الـAPI، DNC، rate limits | اختبارات محاولات متعددة وNo-Cookie brute force، وعزل أسرار ورفض طلب بلا صلاحية. |

---

# 4) المخطط التنفيذي لمعالج Setup/Permissions Wizard

## 4.1 آلة الحالات المقترحة
هذا **مخطط للتنفيذ** وليس ادعاءً بأنه مكتمل حاليًا. يُدمج مع شاشة `v1Wizard` الموجودة، ولا يُدخل المستخدم غير التقني في إعدادات ADB افتراضيًا.

```text
S0   DETECT_DEVICE          → Android API / OEM / SIM count / app build / manifest capability
S1   INSTALL_GUIDANCE       → مصدر APK المعتمد + SHA256 + شرح موافقة Android
S2   NOTIFICATIONS          → POST_NOTIFICATIONS على Android 13+ إن لزم
S3   PHONE_PERMISSIONS      → READ_PHONE_STATE / CALL_PHONE / ANSWER_PHONE_CALLS حسب الوظيفة والإصدار
S4   MIC_PERMISSION         → RECORD_AUDIO مع شرح واضح وحدود Android أثناء مكالمة SIM
S5   CALL_ROLE              → اختبار ROLE_DIALER / InCallService إن كان المسار مدعومًا ومطلوبًا
S6   OVERLAY                → SYSTEM_ALERT_WINDOW + فحص canDrawOverlays()
S7   BATTERY                → battery unrestricted + auto-launch، مع fallback قياسي
S8   OEM_CHECK              → MIUI/HyperOS / OneUI / ColorOS / Huawei-specific hints
S9   TENANT_AND_SIM_PAIRING → كود 6 أرقام/QR؛ ربط SIM 1 أو SIM 2 بحساب وإيجنت منشور
S10  SELF_TEST              → API readiness + foreground/overlay + Local PCM + اختبار مكالمة بموافقة المالك
DONE → READY_VERIFIED فقط عند اجتياز الشروط الفعلية المعلنة
```

**تمييز مهم:** S1 لا يُعطّل Play Protect ولا يلتف عليه برمجياً؛ يعرض إرشادات التثبيت والتحذيرات الحقيقية، ويحترم قرار نظام Android. الحصول على `ROLE_DIALER` أو `RECORD_AUDIO` **لا يضمن وحده** حق الوصول لصوت الطرف الآخر في مكالمة خلوية؛ إثبات الصوت الفعلي يعتمد على الجهاز/نسخة Android/طريقة الالتقاط المسموحة ويجب أن يظهر حالة فشل صريحة.

## 4.2 Checker لحظي وانتقالات رفض التصاريح
- لكل خطوة: `NOT_ASKED` / `GRANTED` / `RATIONALE_NEEDED` / `DENIED_PERMANENTLY` / `OS_UNAVAILABLE` / `HARDWARE_UNSUPPORTED` / `TEST_FAILED`.
- عند `onResume` وعند نتيجة طلب التصريح (Activity Result API) يُنفَّذ `evaluateAndAdvance()` ثم عرض السبب الفعلي للخطوة الحالية، لا مؤشّر أخضر ثابت.
- `shouldShowRequestPermissionRationale=false` **لا يكفي وحده** للحكم برفض دائم؛ اربطه بتاريخ محاولات الإذن ورقم API مع مسار فتح `ACTION_APPLICATION_DETAILS_SETTINGS`.
- إذا عاد المستخدم من الإعدادات، يعاد الفحص تلقائيًا من Android؛ ولا تُعتبر العودة نجاحًا قبل قراءة الصلاحية.
- OEM deep-links **اختيارية وغير موثوقة**: افحص `resolveActivity` وتدرج إلى صفحة التطبيق القياسية إذا غابت.
- لا تُطلب التصاريح الاختيارية (Contacts/Call Log) قبل حاجتها، ولا يُفعّل رقم هاتف/اتصال حقيقي بلا موافقة صريحة.

## 4.3 دليل الشركات المصنّعة
| OEM | الفحص المطلوب | fallback |
|---|---|---|
| Xiaomi / Redmi / POCO | Autostart + عرض النوافذ في الخلفية + Battery No Restrictions؛ خطوات Developer/ADB في التشخيص فقط | صفحة App Info القياسية |
| Samsung / One UI | Battery Unrestricted + Never Sleeping Apps + استمرار Foreground | App Info + دليل مصوّر |
| Oppo / Realme / OnePlus | Auto-launch + Allow background activity + نافذة فوق التطبيقات؛ اختبارات Screen-off | App Info مع أسماء الإعدادات حسب الإصدار |
| Huawei | إدارة التشغيل الخلفي وقيود النظام حسب build الفعلي | App Info وتعليمات عامة دون افتراض deep-link ثابت |
| Android آخر | Runtime permissions + Role + Overlay + Battery دون وصفات OEM | Settings القياسية |

## 4.4 تقرير الجاهزية المرسل للـSaaS
`readiness_report` يقتصر على `installation_id` و`binding_id` و`sim_slot` ونسخة Android/التطبيق ونتيجة كل فحص ووقت إجرائه و`test_run_id`؛ يُرفع عبر Endpoint محمي بتوكن الجهاز. لا يُرفع الرقم الكامل دون حاجة تشغيلية ولا أي توكنات أو مفاتيح. **حالة الحساب لا تتغير لمجرد فتح Wizard**: التفريق بين `UI_READY` و`API_READY` و`CARRIER_CALL_E2E_VERIFIED`، وإبقاء الحالة `UNVERIFIED` عند غياب إثبات SIM.

---

# 5) خطة التنفيذ بأربع مراحل ومعايير الإغلاق DoD

> هذه **Backlog فقط**؛ جميع الخانات غير مؤشرة لأن مهمة اليوم لا تُعدّل الكود ولا تشغّل الاختبارات أو النشر. يعاد تقييم كل [R] ضد أحدث كود قبل التعديل. لا يتم حذف مسارات Legacy قبل اختبار عدم اعتماد النسخة المثبتة عليها.

## Phase 1 — Blockers & Security

| تم؟ | المهمة | الملفات المستهدفة | DoD / دليل قبول |
|---|---|---|---|
| [ ] | P1-01 عزل/تدوير توكن الصيانة وكشف التعرض | `UPLOAD_TO_ROOT.txt`، `api/setup_demo.php`، `api/diag.php`، Host logs | لا سر في Git أو Webroot، القديم مرفوض، وكل المسارات الحساسة 403/404، وتقرير Redaction لا يعرض السر. |
| [ ] | P1-02 حماية إنشاء Gemini Ephemeral Token | `api/live_session.php` + Runtime tenant session | POST دون توثيق=401/403، rate-limit=429، constraints/allowed model/scopes متحققة، لا يُعاد `system_instruction` الخام بغير ضرورة. |
| [ ] | P1-03 إغلاق أدوات التشخيص العامة | `omnira_api_tester.php`، `test.php`، `dev_bridge.php` حيث يوجد على الهوست | اختبار HTTP خارجي ينفي إمكانية تشغيل Proxy/تغيير إعداد/تنفيذ كود؛ حفظ بديل CLI مقيد. |
| [ ] | P1-04 تجميد Host-Link غير الموقّع أو إصلاحه دون تشغيله | `ops/host-link/worker.py` وbranch/rules | STOP مُثبت على الهوست؛ لو أعيد: Ed25519 + replay prevention + allowlist واختبارات رفض job معدل. |
| [ ] | P1-05 ضبط توقيع APK وUI OTA | `mobile-ui-ota.yml`، `android-audio-proof.yml`، build signing scripts | key release في Secrets، لا signing secret في Cache قابل للقراءة، سيناريو تحديث قائم أو إعادة تثبيت معلن، مقارنة SHA/cert/OTA على الجهاز. |
| [ ] | P1-06 قفل الدخول والأسرار وواجهات API | `console/index.php`، `Bootstrap.php`، `api/v1`، `mobile/server` | no-cookie brute-force اختبار 429، Revocation لكل device، لا error details أو secret خارج scope. |
| [ ] | P1-07 حماية مسار ADB التشخيصي | `MobileLinkActivity.kt`، `CaptureDaemon`، `prepare.py` | منفذ 5555 لا يظل مكشوفًا دون اختيار واعٍ، ويغلق بعد الاختبار، ومنع تغيير المسار أثناء مكالمة. |

## Phase 2 — Core SaaS–Android Integration & Real Calls

| تم؟ | المهمة | الملفات المستهدفة | DoD |
|---|---|---|---|
| [ ] | P2-01 مصفوفة عقد Contract بين App/Console/Legacy | `device/v1/index.php`، `TenantDeviceEnrollment.kt`، `MobileLinkService.kt` | اختبارات كل أمر UI/Bridge/API، لا endpoint «شكلي»، هويات Tenant وتوكن الشريحة تتطابق. |
| [ ] | P2-02 إثبات حالة الرد للشبكتين والاتجاهين | `MobileLinkService.kt`، `MobileLiveCall.kt`، Telephony bridge | ringing ≠ answered؛ outgoing no-answer لا يحسب completed؛ تسجيل Call state مع تأكيد الصوت بعد الرد. |
| [ ] | P2-03 Dual SIM + اختيار شريحة الصادر | Android telephony/`DevicePairing.php` | SIM1/SIM2 مختلفتان، PhoneAccountHandle الصحيح، handoff لا ينقل حالة بين الحسابين. |
| [ ] | P2-04 إصلاح Outbox ومنع فقد الأحداث | `CallEventOutbox.kt` + Tenant call endpoints | انقطاع 30 دقيقة ثم recovery بلا crash/duplicate؛ >1000 حدث محفوظ أو مضغوط بأمان، matchingCall صحيح. |
| [ ] | P2-05 تحسين PCM/شبكة LTE | `LinkPcm.kt`، `MobileLiveCall.kt`، `CaptureDaemon` | Test vectors + قياسات jitter/latency، محادثة واضحة على 4G وWi-Fi مع مرجع قبل/بعد؛ لا قطع صوت الطرفين. |
| [ ] | P2-06 تأكيد Published Agent + Flow/tools/Knowledge | `TenantVoiceSession.php`، `PublishedFlow.php`، Runtime APIs | E2E لموقعين وإيجنتين، Talk/Ask/Condition/Handoff/Knowledge، عمليات API تقرّ فقط مع Result موثق. |
| [ ] | P2-07 إنهاء الميتَرنج والفوترة المتصلة | `Actions.php`، `OrderClaim.php`، gateway/webhooks/`usage_ledger` | دفع Sandbox→verified entitlement→مكالمة→خصم→انتهاء/تجديد؛ إثبات عدم تفعيل مجاني/عدم خصم مكرر. |
| [ ] | P2-08 إنهاء حملات Tenant والتقارير والتحويل للبشر | `TenantCampaignQueue.php`، `VoiceCallEvidence.php`، Android event path | Crash/lease expiry لا يحبس حملة، no-answer صادق، وحفظ transcript/summary/human request مطابق لكل Call ID. |

## Phase 3 — Setup Wizard & OEM Compatibility

| تم؟ | المهمة | الملفات المستهدفة | DoD |
|---|---|---|---|
| [ ] | P3-01 بناء State Machine S0–S10 مع حالات صريحة | `MobileLinkActivity.kt`، شاشة `v1Wizard`، checker native | أول تشغيل في جهاز نظيف يعرض الخطوات دون تدوير بين شاشات مبهمة. |
| [ ] | P3-02 اكتشاف Runtime permissions ودورة الرجوع | Kotlin checker + merged manifest | رفض مرة/رفض دائم/عودة من Settings يحدّث الحقيقة تلقائيًا. |
| [ ] | P3-03 فحص Role وTelephony/SIM من النظام | Android telecom + `TenantDeviceEnrollment.kt` | يكتشف التطبيق دعم ROLE_DIALER والشرائح الحقيقية، لا يمنح حالة Ready قبل اختبار OS. |
| [ ] | P3-04 دعم Xiaomi/Samsung/Oppo/Huawei | OEM routes/UI | صفحة الإعداد المناسبة تُفتح أو fallback دون crash؛ تصوير إثبات على أجهزة ممثلة. |
| [ ] | P3-05 Self-test آمن قبل تفعيل الجهاز | device readiness endpoint + Android | اختبار صلاحيات/Overlay/خدمة خلفية و60 ثانية قفل شاشة؛ مكالمة اختبار بموافقة صريحة؛ Ready/Unverified متمايزان. |
| [ ] | P3-06 مسار تنزيل/تحديث موثّق | releases UI + Android OTA | APK SHA256 ونسخة وشهادة واضحة، Android يطلب الموافقة، OTA UI لا يُخلط بتثبيت Native. |

## Phase 4 — Production Readiness & Independent Acceptance

| تم؟ | المهمة | الملفات المستهدفة | DoD |
|---|---|---|---|
| [ ] | P4-01 اختبارات كاملة إلزامية للنشر | `.github/workflows/*` المعنية | SHA واحد مختبَر ومنشور، PHP lint/unit/contract Android tests، لا Build خضراء مع E2E غير متحقق وموسوم PASS. |
| [ ] | P4-02 Receipt/rollback وسلسلة التوقيع | Deploy scripts/host evidence | hash الملفات + HTTP + commit + وقت النشر + rollback محفوظ؛ منع قنوات متوازية غير مراقبة. |
| [ ] | P4-03 اختبار هواتف وشبكات حقيقي | Xiaomi + Oppo + هاتف Dual SIM | inbound/outbound، 4G/Wi-Fi، screen off، mute، human takeover، report، 10–20 مكالمة محاكاة مستقلة عن قدرة شريحة واحدة. |
| [ ] | P4-04 اختبار عزل واشتراكات كل الأدوار | Admin/Reseller/Customer/Devices | لا تسرب Account/SIM/Keys، Reseller billing/white-label، Trial/No plan/Expired/Overage، حفظ الأسعار والحصص. |
| [ ] | P4-05 التخلص من Legacy والتكرار بأمان | Root Demo/`mobile/server`/Laravel/reference | خريطة Runtime مثبتة، نقل البيانات والتوكنات، اختبارات توافق مثبتة قبل إزالة أي مسار. |
| [ ] | P4-06 تفعيل 2FA بوابة تجارية فقط | Auth policy/Owner launch checklist | **مؤجل عمدًا خلال التجارب**؛ عند الإطلاق التجاري بموافقة المالك: TOTP/Recovery/forced enrollment مع اختبارات. |
| [ ] | P4-07 شهادة قبول نهائية | docs/evidence/Acceptance Matrix | لكل معيار PASS/FAIL/UNVERIFIED + source SHA + CI + host receipt + owner-device phone evidence. لا إعلان Stable 1.0.0 قبل إغلاق الحرِج. |

---

## مصفوفة ربط التقارير بالملف الموحّد (Traceability)

| مصدر التقرير | البنود الأصلية | التغطية داخل التقرير الحالي |
|---|---|---|
| `Rep1.md` — البنود 1–4 | OTA mismatch، جلسات Gemini، أدوات صيانة، حماية تسجيل الدخول | MOB-20، SEC-02، SEC-03، SEC-07/08 |
| `Rep1.md` — البنود 5–8 | دفع/Claim، الرد الصادر/الشريحة، deadlock الحملات، ADB/PCM | MOB-10، MOB-02/03، MOB-08، MOB-05 |
| `Rep1.md` — البنود 9–12 | Tester، Debug signer، Permissions/FGS، Outbox/Notifications | SEC-06، SEC-05، MOB-15، MOB-04/14 |
| `Rep1.md` — البنود 13–15 | زمن فتح الجلسة/Prompt/Resampler، Console APIs/Permissions، import/Edge TTS | MOB-01/06/09، MOB-07/12، SEC-12، MOB-13/18 |
| `تقرير فحص...` — S-01..S-11 | أسرار، Worker، public token، rate-limit، infra، Vault، test endpoints | SEC-01..SEC-04، SEC-06..SEC-12 |
| `تقرير فحص...` — Q-01..Q-07 | Laravel، webhook، تعدد المسارات، migrations، versions، dead code، VS | جرد `Laravel`، MOB-17/19؛ **VS خارج النطاق** |
| `تقرير فحص...` — C-01..C-04 | signing/checkout/test gates/multiple deploy | SEC-05/10 + Phase 4 |
| نص `خطة الفحص الصفري` | الأقسام الخمسة + Wizard + DoD + استبعاد VS | بنية هذه الوثيقة: الأقسام 1–5، Phase 1–4، وأعلام الثقة |

---

## بروتوكول التحقق التالي (لا يُعد منفذًا)

1. تثبيت **Source SHA + Branch + Host SHA + APK/OTA SHA** لكل ادعاء؛ كتابة «قديم/جديد/غير موجود» عند تغيّر الملفات.
2. مراجعة معاكسة لكل P0/P1 من مختبِر ثانٍ: هل المسار قابل للوصول على **الهوست الحالي**؟ هل يوجد شرط حماية في ملف آخر؟ هل الاستغلال ممكّن حقًا أم مجرد كود غير مستخدم؟
3. إنشاء مصفوفة نتائج مستقلة: **Static code PASS/FAIL**، **Automated tests PASS/FAIL**، **HTTP/Host PASS/FAIL**، **Physical SIM E2E PASS/FAIL**. لا يُستبدل أي عمود بالآخر.
4. حفظ أدلة مقتضبة ومحمية من الأسرار، وعدم رفع ملفات التوكنات أو الـPrivate Keys أو رقم هاتف مالك الشريحة إلى الريبو.
5. إجراء الإصلاحات لاحقًا **فقط بتكليف صريح**، ببوابة تقييم قبل أي APK/OTA أو تعديل إعدادات عميل/جهاز.

### سجل العمل على هذا التقرير فقط
- [x] قراءة مصدرَي التدقيق المرفوعين والنص الثالث الخاص بخطة الفحص.
- [x] فحص عيّنة واسعة من ملفات فرع release المذكورة في قسم المصادر بواسطة GitHub.
- [x] استبعاد `VS/` تمامًا.
- [x] توحيد التكرارات وتمييز البنود القديمة أو المتعارضة عن البنود المشاهدة في المصدر الأحدث.
- [ ] اختبار PHP/Kotlin/CI — **NOT RUN**.
- [ ] اختبار حي HTTP/Host — **NOT RUN**.
- [ ] مكالمة SIM/PCM على جهاز مالك المشروع — **NOT RUN**.
- [ ] أي إصلاح/Build/Deploy/تغيير مفاتيح أو إعدادات — **NOT DONE**.

**الخلاصة:** هذا سجل أولي منظم لما يجب فحصه وإصلاحه ويعترف بالكود الحقيقي الذي أضيف بعد تقارير `main`. لا يُعامل التقرير دليلاً بأن كل عيب ما زال مفتوحًا، ولا بأنه أُغلق؛ الاستحقاق الوحيد للـDONE هو الاختبار المتخصص والدليل المستقل على النسخة الصحيحة.
