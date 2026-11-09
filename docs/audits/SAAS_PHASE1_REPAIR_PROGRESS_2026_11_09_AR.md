# SaaS — Phase 1: سجل التنفيذ الأولي (9 نوفمبر 2026)

**الحالة:** CODE CHANGED / TESTS NOT VERIFIED / NOT DEPLOYED  
**فرع التنفيذ:** `fix/saas-phase1-security-nonregression-20261109`  
**فرع الأصل:** `release/voice-v1-20261106`  
**المراجعة:** Draft PR #79، لا Merge أو نشر.

## ما نُفذ فعلاً في الكود
- **WEB-SEC-02 (PARTIAL):** إضافة `api/LiveSessionSecurity.php` لتوليد CSRF مرتبط بجلسة المتصفح، فحص Origin/Sec-Fetch-Site، وحدود معدل عامة ولكل عنوان IP بحفظ متزامن خارج Webroot. `api/live_session.php` يستخدمها قبل إنشاء توكن Gemini. هذه آلية **حماية ديمو متصفح عامة**؛ ليست بديلاً عن SaaS Tenant auth ولا تُحوّل الديمو إلى حساب مدفوع.
- **Browser compatibility:** `index.php` يعرض nonce، ويمنع التخزين المؤقت، ويرفع إصدار `assets/app.js?v=064`؛ JavaScript يرسل الرمز مع طلب Demo/Outbound. صوت/Voice config لم تتغير؛ `ONTRACK_LIVE_LOCK_MODEL=1` اختيارية ومعطلة افتراضيًا لحين فحص Google E2E.
- **Legacy compatibility:** `saas/mobile/server/session.php` يحتفظ بالمسار القديم بعد `mobile_require_device()` ويعرّف ثابت internal-only عند fallback. `saas/mobile/patch_live_session.py` يظل مرتبطًا بنفس Anchors ويجب أن يجتاز Compat test.
- **Older diagnostic/browser test compatibility:** تهيئة `tools/check_mobile_live.py` للحصول على Session/Nonce من الصفحة الأصلية، وتحديث `tests/browser.test.mjs` ليفصل رفض الطلب غير المصرّح 403 عن فحص Mission مع CSRF الذي يرجع 400.
- **WEB-SEC-01 (PARTIAL فقط):** تعتيم Token من `UPLOAD_TO_ROOT.txt` وإضافة HTTP deny للملف في `.htaccess`. **هذا لا يدور التوكن على الهوست ولا يمحو تاريخ Git ولا يقفل `dev_bridge.php` إن كان موجودًا على الهوست**.
- **No regression tests:** `tools/test_live_demo_security.php` + workflow `.github/workflows/saas-phase1-live-demo-security-ci.yml` للاختبارات المعزولة، PHP syntax، JavaScript syntax، local HTTP دون Gemini، ومطابقة Legacy adapter.

## حاجات لم تُنفذ أو لم تُثبت
- لم يُختبر PHP/HTTP/Browser E2E فعليًا من هذه الجلسة، ولم يثبت CI PASS على التعديلات. تشغيلات PR backend المسجلة أعطت **failure** وتفاصيل logs غير متاحة عبر API عند المراجعة؛ يجب التشخيص **قبل أي merge**.
- لم يجرِ إرسال request حقيقي إلى Google أو اختبار صوت أو مكالمة؛ هذا جزء منفصل.
- لم يُنشر أي تعديل إلى `app.ontrackegy.com`، ولم يتغير Android/OTA/APK أو توقيعه، ولم تُمس قاعدة SaaS أو المفاتيح/ربط الشريحة.
- لم تُحل بعد أدوات الصيانة العامة `api/setup_demo.php` و`api/diag.php` ودوران الأسرار؛ يلزم Host check وSafe rotation مع تأكيد استمرار وظيفة الإدارة المشروعة.
- حماية `ONTRACK_LIVE_LOCK_MODEL` لم تُفعَّل؛ لا إعلان اكتمال قيد مزود Live قبل توافق جلسة حقيقية.

## شروط قبول الدمج والنشر (يمنع تجاوزه)
1. نجاح `php -l` و`php tools/test_live_demo_security.php` و`node --check assets/app.js` و`python -m py_compile tools/check_mobile_live.py saas/mobile/patch_live_session.py`.
2. تحقق Browser E2E للـDemo والـOutbound والأوامر القائمة + تحقق البوابة عبر `403` و`400` و`429`، مع اختبار Token Google في بيئة مقيّدة قبل الإنتاج.
3. تسجيل Baseline وسلامة Admin/Reseller/Customer، ودخول البريد وكلمة المرور بلا OTP تجريبي، وإثبات عدم تغيير أي API مستخدم من الهاتف.
4. Host isolated QA + نسخة رجوع + SHA مطابق، ثم نشر مصرح فقط. لا يُغلق أي P0 بالاعتماد على Static code وحده.

**قاعدة حفظ الوظائف:** أي Regression في زر/مسار/صوت سبق نجاحه يمنع الدمج؛ أصلحه أو ارجع التعديل قبل النشر.

---

## دفعة التنفيذ الإضافية: أمان الصيانة والتكامل وسياسة الرد

**مهم:** الكود موجود على فرع الإصلاح فقط. لم يُنشر على الهوست، ولم تُجرَ اختبارات حية، ولا يجوز اعتباره DONE.

| البند | حالة الكود | الدليل الدقيق / ما تبقى |
|---|---|---|
| WEB-SEC-01 | **CODED PARTIAL** | `api/MaintenanceAuth.php` يقرأ SHA-256 من Private Host أو Env؛ `setup_demo.php` و`diag.php` يرفضان توكنات GET، و`tools/provision_maintenance_token.php` CLI-only. **لا تدوير فعلي على الهوست ولا محو Git history**. |
| WEB-SEC-02 | **CODED PARTIAL** | حارس CSRF/Origin/Atomic quotas للديمو وOutbound؛ `liveConnectConstraints` خلف علم معطل افتراضيًا حتى اختبار Google الحقيقي، ولا توجد شهادة Host/Browser E2E. |
| WEB-SEC-03 | **CODED PARTIAL** | `omnira_api_tester.php` لا يقبل المستخدم المجهول؛ Basic Auth لمشغّل الصيانة + CSRF، مع الحفاظ على Basic/Stream/Tools/Models. `test.php` لم يُلغَ ولا يوفر API proxy. وصول `dev_bridge.php` على الهوست يحتاج فحصًا مستقلًا. |
| WEB-SEC-04 | **CODED PARTIAL** | `ops/host-link/worker.py` يتطلب Ed25519 signature لكل `exec` قبل تشغيله، مع أداة توقيع محلية واختبار تزوير؛ **Host-Link يظل STOP** ولا تأكيد تثبيت public verifier أو تشغيل أي مهمة. |
| WEB-SEC-05 | **CODED PARTIAL** | إضافة `ApiWriteGuard.php` للحد من طلبات `lead.create` الموثقة حسب API key/tenant، مع بقاء Auth/CSRF/SSO كما كانت؛ يجب اختبار الأحمال ومسارات التكامل الحية. |
| WEB-SEC-06 | **CODED PARTIAL** | `Bootstrap.php` يرفض قاعدة إنتاج عابرة في `/tmp` عند غياب private home، و`demo_config.php` يفضّل مخزنًا خاصًا مع fallback للتهيئة القديمة؛ لم يُختبر Restore/Vault encryption على الهوست. |
| WEB-SEC-07 | **PARTIAL** | أخطاء Google الخام لم تعد ترجع للمتصفح في مسار Root token؛ بقية Logger/PII/Call tracing تحتاج Audit مستقل وتغطية اختبارات. |
| WEB-CORE-01/06 | **CODED PARTIAL** | `saas/mobile/server/session.php` يحافظ على Legacy authenticated fallback في layout مسطح أو متداخل، دون أي تعديل Android. Client/Host E2E ما زال UNVERIFIED. |
| WEB-CORE-02 / WEB-UX-01 | **CODED PARTIAL** | إضافة حفظ/نشر مباشر لتأخير الرد 0–30 ثانية وAuto-answer **لكل إيجنت** في صفحة إعدادات الرد، مع الحفاظ على جميع بقية Policy وFlow وVoice وKB وSnapshot المنشور لحين قرار المستخدم. اختبار Fixture `test_saas_quick_answer_policy.php` مكتوب **ولم يثبت تشغيله**. |

### الاختبارات التي أُضيفت ولا يجوز نسب نجاح إليها حتى تشغيلها
- `tools/test_live_demo_security.php`: Session CSRF + Origin + quota + Legacy+Outbound contract.
- `tools/test_saas_security_guards.php`: Header-only maintenance + Basic Auth + CSRF + Tenant API quotas + production path.
- `tools/test_host_link_signed_jobs.py`: Ed25519 signed jobs + tamper/unsigned rejection؛ بلا تنفيذ أوامر على السيرفر.
- `tools/test_saas_quick_answer_policy.php`: تأخير الرد 5 ثوانٍ، تغيير الرد التلقائي دون إسقاط باقي الخصائص، Preview Draft مقابل Publish.
- `.github/workflows/saas-phase1-live-demo-security-ci.yml`: PHP syntax، Local HTTP بلا Gemini، JS syntax، Python signature، PHP SQLite isolated fixture.

**فشل تشغيل Actions:** أحدث تشغيلات هذه الدفعة (ومنها `42551892658`) كانت `failure` قبل أي Steps ظاهرة، مع `runner_name` فارغ؛ **سبب إخفاق Provisioning/Runner لا يُعرف بعد**. لا يتم تحويله إلى إخفاق اختبار كود أو نجاح. غير موثّق PASS.

### ما لم يُغلق من الخطة الشاملة (SaaS فقط)
- **Phase 1:** إعداد أسرار الصيانة وتدويرها فعليًا، اختبار صفحة الصيانة والأدمن والـDemo والـAPI الحقيقي وVault Restore وسجلات HTTP، التحقق من `STOP` على الهوست، وانعدام تعرض Legacy Bridge.
- **Phase 2:** E2E للحسابات/الشريحتين وFlow Runtime مع Google/Tool Providers، PDF/Knowledge الحقيقي، WHMCS/Laravel/WhatsApp/REST، Call reports/usage ledger على Call IDs من جهاز، Billing reconciliation، failover أثناء مكالمة فعلية.
- **Phase 3:** إثبات كل زر/نموذج وPricing/Reseller/White-label DNS/TLS وRefund/Webhooks وBrowser Mic ودعم العملاء والحملات على إنتاج داخلي.
- **Phase 4:** تشغيل اختبارات CI/Browser/PHP، MySQL E2E، Host preflight وBackup/Restore وsha receipt، فحص rollback، Live SIM compatibility. **لا يجوز دمج أو نشر هذه الدفعة قبل إغلاق بواباتها.**

### منع التراجع
وفق تحديث المالك، مجلد `VS/` محذوف بالفعل ولم يعد ضمن نطاق العمل أو الخطة. لا تغييرات في Android/PCM/APK/OTA/SIM أو هوية التوقيع. لا يُفصل أي جهاز ولا يُعاد تعيين صوت/مفتاح/حصة/اشتراك. لا يُمكّن Host-Link الموقوف. خطط الويب والفوترة القديمة بقيت في المصدر دون استبدال.


## تحديث مرجعي — خطة SaaS المعلمة بالمنفذ والمتبقي
- [x] تحديث حالة البنود التفصيلية في [خطة الـSaaS](ONTRACK_SAAS_REPAIR_PLAN_2026_11_09_AR.md) مع وضع علامة على **خطوات الكود المكتوبة والمرفوعة** فقط.
- [x] إلغاء بند استبعاد `VS/` من الخطة لأن المالك أكد أنه محذوف بالفعل.
- [ ] اعتماد كل مهمة عليا حسب DoD: **0/31** مغلقة نهائيًا؛ **11/31** بها خطوات كود جزئية (غير معتمدة)، **20/31** دون إغلاق مثبت.
- [ ] إثبات CI/HTTP/Host/Regression PASS، تدوير سر الصيانة على الهوست، ودمج/نشر مع Receipt وRollback بعد التحقق.
