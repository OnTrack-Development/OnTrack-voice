# SaaS 1+2+3 — تقرير تكامل مرشح الاختبار
**تاريخ التقرير:** 2026-11-09
**الحالة:** MERGED ON ISOLATED BRANCH · OFFLINE CI PASS · HOST QA NOT RUN · NOT DEPLOYED
**فرع المصدر:** `fix/saas-integrated-phases123-20261109`

## نطاق الدمج
تم إنشاء Merge Commit **بين فرعي Phase 1 وStage 3 (المبني على Phase 2)** على فرع تكامل مستقل فقط. لم يجرِ Merge إلى `release/voice-v1-20261106` أو `main` أو نشر إلى `app.ontrackegy.com`.

- Phase 1: Token issuance guard, maintenance auth and private config, Omnira tester auth, signed Host-Link job guard، توافق Pilot adapter، حفظ ونشر سياسة الرد **5 ثوانٍ** من صفحة الـSaaS.
- Phase 2: tenant SIM contract probe، Flow policy reauthorization، KB bounded fetch، staff ACK compare-and-swap، عرض الاشتراك النشط الصحيح، توافق WhatsApp/WHMCS/Call Reports/Google keys.
- Phase 3: منصة الأدمن ترى وتتحكم في باقات الريسيلرز عبر action `assign_plan`، والريسيلر يرى باقته الخاصة وباقات السوق ورابط Checkout، دون تجاوز الدفع.
- **حل تداخل Views:** دمج نموذج الرد السريع من Phase 1 في `render_answering` مع الحفاظ على إضافات Plans وBilling من Phase 2/3. لم تُستبدل شاشة كاملة بنسخة قديمة.
- **اختبار Flow AI:** تم تصحيح فحص Test قديم كان يطلب `function showProposal(g)` بدل التوقيع الحالي `showProposal(g,baseline=null)`؛ أبقينا آلية Baseline وDraft collision safety الحقيقية وحميناها باختبارين إضافيين.

## دليل الاختبارات المجمعة — منفذة فعلاً

**[GitHub Actions #42558225733](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42558225733) — SUCCESS (آخر مصفوفة موسعة).**

شمل التشغيل:
- PHP syntax لجميع `saas/console` ومداخل الـAPI وJavaScript syntax + Python compile.
- Session-CSRF/Origin/Rate limit لـGemini Live وLocal HTTP بدون طلب فعلي إلى Google؛ رفض غير المصرح وحفاظ Contract الموبايل.
- Auth للصيانة وCSRF لأداة Omnira، تحقق توقيع Ed25519 لوظائف Host-Link دون تنفيذها.
- 43 اختبار SQLite معزولًا **بالإضافة إلى اختبار MySQL 8.0 مستقل حقيقي**: Agent/Flow/KB، PDF حقيقي عبر Poppler المثبت على Runner، Tenant/SIM/Device Pairing، تقارير المكالمات والموظف، Google key failover بمزود Mock، اشتراكات/Checkout وكاشير، SSO، WHMCS/WhatsApp وDNC.
- UI static: Modal/AJAX، Graph Routing، Floating AI Flow وFullscreen، وQR.
- **توسعة الاعتماد:** Auth login throttling، إخفاء وإظهار Reseller، تسجيل العملاء والتحقق من البريد وإعادة كلمة المرور، White-label/Domain isolation، Voice OpenAPI، Protected Engine signing، Device bootstrap، مفاتيح Assistant، سيناريوهات Agent، Dynamic Agent Studio وDNC Actions.
- **SSO HTTP على localhost:** تسجيل دخول فعلي بجلسة Cookie وCSRF وتوقيع HMAC، رفض Replay وعزل الحسابات؛ `VOICE_SSO_HTTP_PASS`. لا استدعاء WHMCS خارجي.
- أكد `SAAS_INTEGRATED_ISOLATED_MATRIX_PASS suite_count=43`، `FLOATING_FLOW_CHAT_AND_FULLSCREEN_CANVAS_PASS`، `SAAS_INTEGRATED_NO_NATIVE_APK_OTA_DEPLOY_PASS`.
- جميعها **CI Offline**؛ ليس لها سلطة إثبات الصوت الحقيقي أو صلاحية مفاتيح Google أو مرور الدفع على الاستضافة.

## فحص MySQL 8 الحقيقي على بيئة مؤقتة (ليس هوست الإنتاج)

- **[GitHub Actions MySQL #42559141038](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42559141038): SUCCESS.** فحص حقيقي عبر PDO_mysql وMySQL 8.0.46 على خدمة Docker مؤقتة، وليس SQLite أو محاكاة SQL فقط.
- **Bug فعلي مكتشف ومصلح:** ملف saas/console/database/schema.mysql.sql كان يحتوي events.external_id TEXT مع UNIQUE(integration_id,external_id) بدون طول Index، مما يعطل تثبيت قاعدة MySQL جديدة بخطأ SQLSTATE 42000 / 1170. أصبح العمود VARCHAR(190)، متوافقًا مع Validator الحالي في hooks/index.php الذي يحد event_id إلى 128 حرفًا، دون مسح صفوف أو تغيير جداول مثبتة.
- فحص MySQL يغطي تنفيذ migrations الكامل، Seed لحسابات معزولة، Tenant separation، التغيير الذري للاشتراكات، نشر Flow، ربط SIM تجريبي، البحث في Knowledge ومنع تسريب المستأجر، تقرير المكالمة واستهلاك متصل مرة واحدة، منع إشعار موظف رجعي، وعرض باقة العميل النشطة.
- تم التحقق من إضافة حدث Webhook بمعرّف 128 حرفًا في MySQL ورفض إعادة إرسال نفس المعرّف عبر Unique Index بخطأ 23000.
- **QA History:** أول تشغيل MySQL كشف خطأ Schema حقيقي، ثم أصلحنا Schema والـFixture واستمر اختبار Docker حتى PASS. لا بيانات من الاستضافة ولا اتصال Provider.
- **قيد النشر:** شهادة MySQL 8 الجديدة لا تثبت سلامة migrations على قاعدة MySQL حيّة ببيانات، ولا أن Backup/Restore جرى على الهوست. يلزم Host preflight على نسخة معزولة من البيانات الحالية قبل التحديث.

## نتيجة حقيقية للواجهة والنماذج — Chromium + HTTP + database

- [GitHub Actions #42564618431 — SUCCESS](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42564618431): تشغيل Google Chrome الحقيقي على Ubuntu CI مع PHP 8.3 وSQLite معزولة؛ **237 قياس شاشة** من 320px حتى 1440px على تسجيل ودخول عام وإداري وريسيلر وعميل، ودعم RTL والموبايل، Theme persist، Drawer، وAJAX navigation — **0 فشل**.
- **لم نكتفِ بالعرض:** من متصفح جلسة مصادق بها وPOST حقيقي مع CSRF، قام Admin بتغيير باقة ريسيلر من 1 إلى 2؛ ظهر التغيير في حساب الريسيلر؛ ومنع الطلب من حساب ريسيلر لتغيير باقة ريسيلر آخر.
- حساب العميل نشر عبر النموذج الفعلي `update_answer_policy_quick` الرد التلقائي بعد 5 ثوانٍ؛ [فحص Persisted DB](https://github.com/OnTrack-Development/OnTrack-voice/blob/fix/saas-integrated-phases123-20261109/tools/check_saas_roles_form_post_http.php) أثبت وجود الإعداد في `published_agent_flow`، وعدم مسح الصوت أو التعليمات أو المعرفة أو تمديد باقة الريسيلر.
- البنية: `tools/seed_responsive_ui.php` محمي بعزل CI؛ `tools/test_saas_roles_form_post_http.py` لا يستخدم إلا `http://127.0.0.1:18773`؛ `tools/check_saas_roles_form_post_http.php` يراجع قاعدة البيانات بعد POST. لم يعد الاختبار مقصورًا على Windows self-hosted runner؛ لم تُفتح صلاحيات seed على الـHost.
- **النطاق:** نجاح Browser على CI **لا يثبت** أي حفظ على Host الحقيقي أو مكالمة 5 ثوانٍ على Oppo/Xiaomi أو جودة صوت الهاتف؛ تؤكد هذه الفحوص صحة مسار SaaS في الإصدار المرشح فقط.

## إصلاحات UX المكتشفة أثناء اختبار وظيفي فعلي (WEB-UX-01/06/08)

- **Root cause / Missing tabs:** الدالة `nav_entries()` كانت تُسقط بالفعل `campaigns` و`whatsapp` و`voices` و`staff` من قائمة العملاء، و`voices` من الريسيلر، و`voices` و`whatsapp_admin` من الأدمن. كذلك كان `flows` قابلًا للعرض في Controller وغير قابل للوصول بسبب غياب تبويب الملاحة. إذا طلب المستخدم مسارًا مخفيًا كان Controller يعيده صامتًا إلى `overview`، ولذلك كانت قياسات الصفحة العامة تمر رغم تعطل الزر.
- **Source fix:** استعادة صفحات العمل الموجودة أصلًا بأدوارها الملائمة، وإظهار `flows` للعميل بدون تغيير RBAC أو الاشتراكات أو واجهات API/الهاتف.
- **Bug فعلي في Staff:** `render_staff` كان يمرر أرقام التواصل ومهلة التحويل الصحيحة كـ`int` إلى `select_html(..., string $selected)` تحت `strict_types=1`، فتعطّل عرض صفحة الموظفين للعملاء ذوي Agents. تصحيح helper ليقبل `string|int`، وتحويل المدخلات النصية، وإصلاح إغلاقات HTML Forms غير المتوازنة، دون تغيير حالة الإشعارات أو أرقام الموظفين.
- **Regressions أقوى:** بدل الاكتفاء بوجود محتوى Main، صار Chromium يُثبت أن رابط Sidebar المطلوب هو المحدد فعلًا وأن صفحة القسم لا تحتوي `تعذر عرض البيانات`.
- **Chrome حقيقي:** [Actions #42565765700 — SUCCESS](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42565765700) بعد استعادة القائمة وإصلاح Strict Types: 237 قياسًا و0 مشاكل، وتأكد من وصول جميع التابات بحسب الدور.
- **HTTP+SQLite فعلي:** [Actions #42565839935 — SUCCESS](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42565839935). اختبر POST من User إلى إضافة حملة Draft، تحديث حالة الحملة إلى Paused، رفض Running دون موافقة، رفض Contact بدون Consent، حفظ DNC Opt-out، وعدم إنشاء أي مكالمة صادرة. أكد أيضًا أن إعداد Staff/Human Handoff يفتح في PHP 8.3 بأكثر من Agent.
- **Manager routing through real forms:** [Actions #42566212892 — SUCCESS](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42566212892): حفظ رقم مدير اختباري غير قابل للاتصال الحقيقي، ثم اختيار المسؤول وحفظ مهلة 20 ثانية وحالة `enabled=0` عبر CSRF-authenticated POST؛ فحص SQLite بعد الطلب أثبت سلامة `human_escalation_routes` وأن جدول `human_escalation_jobs` فارغ، لذا **لم يُنشأ اتصال أو طلب هاتف**.
- **لا يستنتج من الاختبارات:** حملات تلقائية تعمل على شريحة، أو موظف يستلم مكالمة ملخصة فعليًا، أو صوت حي على Oppo، أو WhatsApp/Meta real token؛ هذه بوابات Host/Phone مفتوحة.

## بوابات قبول قبل أي نشر / اختبار على النسخة الحية

| البوابة | ما يجب فحصه | الحالة |
|---|---|---|
| 1. Host snapshot | نسخ DB وVault/config، والتحقق من Restore على عزل، وتسجيل Source SHA وHost SHA وRollback جاهز | **[ ]** |
| 2. Maintenance secret | تثبيت digest خارج Webroot، تغيير السر المتسرب وإلغاء القديم، اختبار Legit Admin Tool قبل أي غلق | **[ ]** |
| 3. Auth/Tenants | Admin/Reseller/Customer، CSRF/SSO وLogin بالبريد/الباسورد في التجارب دون فرض OTP، وعدم كشف بيانات عميل آخر | **[ ]** |
| 4. Plans/Checkout | باقات الريسيلر Admin+Owner، اشتراك العميل الحالي، رسوم التجاوز، Kashier sandbox Webhook/Manual references | **[ ]** |
| 5. Device/SIM | Phone bearer/heartbeat/published revision + عدم فك الشريحة أو تغيير token/agent/voice بغير طلب صريح | **[ ]** |
| 6. Answer delay | حفظ ونشر 5 ثوانٍ على إيجنت تجريبي ومقارنة المسودة/المنشور؛ قياس الرد الفعلي على الجهاز | **[ ]** |
| 7. Voice continuity | اختبار مكالمة واردة وصادرة بالصوت من الطرفين على Wi-Fi والداتا، جودة الميوت والمقاطعات، والتقرير | **[ ]** |
| 8. Knowledge/Flow | PDF على هوست حقيقي، Knowledge اختيارية، API verified read/write consent، Flow tool provider sandbox | **[ ]** |
| 9. WhatsApp/WHMCS | تشغيل Webhook signed/routing/tenant scope دون إرسال مالي أو تسويقي غير متفق عليه | **[ ]** |
| 10. Rollback | مقارنة SHA، تنفيذ بروفة استعادة منفصلة، ثم اختبار موقع + هاتف بعد أي نشر مصرح | **[ ]** |

## محظورات عمل التحديث
- لم يُعدل Android/PCM/APK/OTA أو توقيع التطبيق؛ لا مساس بـ`VS/` لأنه محذوف وفق تأكيد المالك.
- لا تفعيل Host-Link الموقوف أو حملات خارجية/مدير تلقائي من اختبار.
- لا تغيير لقيم الباقات، مفاتيح مزودات AI، ربط SIM، أو بيانات مستخدمين.
- **ممنوع إعلان `DONE` لأي من مهام الخطة الـ31 بمجرد مرور CI.** الكود اجتاز بوابة الاختبارات المجمعة، لكن Host/Phone E2E لم يتم.

## قرار الإصدار
**HOLD / NO DEPLOY** حتى إغلاق بوابات Host/SIM الفعلية، وبناء تقرير مقارنة ما قبل/بعد وRollback. لا يمكن تنفيذ تلك البوابات من GitHub source وحده؛ يلزم بيئة استضافة وجهاز تجريبي مملوكان للمستخدم.
