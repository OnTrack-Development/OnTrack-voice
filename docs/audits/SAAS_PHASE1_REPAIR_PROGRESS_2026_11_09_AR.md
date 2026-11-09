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
