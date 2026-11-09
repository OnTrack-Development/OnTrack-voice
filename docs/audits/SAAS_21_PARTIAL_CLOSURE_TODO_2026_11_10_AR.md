# OnTrack Voice SaaS — تنفيذ إغلاق 21 إصلاحًا جزئيًا (Code Closure Tracker)
**تاريخ البدء:** 2026-11-10
**قاعدة العمل:** `fix/saas-integrated-phases123-20261109` @ `56e2adc2b99ceb3c3bfc6804b835908107655634`
**فرع التنفيذ:** `fix/saas-21-closure-20261110`
**النطاق:** SaaS/Server/API فقط. **ممنوع تعديل Android/APK/OTA/PCM/SIM native**؛ اختبار الهاتف اليدوي مؤجل.
**حالة قبول المنتج:** غير معتمد إنتاجيًا؛ لا يساوي اجتياز الاختبارات الآلية نجاحًا على الهوست.
**شرط المستخدم:** تنفيذ جميع البنود تباعًا دون طلب موافقة لكل خطوة، وإرجاء مهام الـ10 غير المغلقة أصلًا إلى ما بعد إتمام بنود الـ21.

## تعليمات الحالة الصارمة
- `[ ]` لم يغلق برمجيًا في فرع التنفيذ؛ `[x]` كود واختبارات آلية مثبتة وروابط دليل؛ لا تستخدم `[x]` لوعود أو تغييرات غير مختبرة.
- لكل بند: `repro/risk → patch → tests → regression → commit SHA → evidence`.
- تحقق Host/Owner اليدوي المؤجل **بوابة مستقلة** ولا يُحوّل الكود المقبول تلقائيًا إلى Production Done.
- Preserve: الأدوار/الباقات الحالية، بيانات المستخدمين، API القديمة، مفاتيح مزودات الصوت، Flow وKB والصوت المنشور، SSO، صوت SIM الحالي و5s answer policy.
- لا تحذف بيانات أو تدور أسرار حية بدون خطة استرجاع؛ لا تشغيل اتصالات أو حملات/دفع فعلي في الاختبارات.

## الموجة A — SEC (7)
- [ ] **WEB-SEC-01**: أكمل خط حماية maintenance: تحقق هوية المتصل/رفض GET القديم/مسارات setup+diag، دعم تدوير digest ورفض secret القديم، اختبار Authorized/Admin؛ تدوير host secret الفعلي بعد اختبارات المالك.
- [ ] **WEB-SEC-02**: تشديد ephemeral Gemini mint demo/Tenant: session+CSRF+Origin+bounded per-scope quota+error contracts، واختبارات bypass/429.
- [ ] **WEB-SEC-03**: جرد live diagnostic/demo routes `test.php`/`dev_bridge.php` وOmnira؛ منع anonymous proxy/config write مع الحفاظ على authorized flows.
- [ ] **WEB-SEC-04**: Host-Link signed allowlist+nonce/replay+expiry بدون تشغيل agent/محو legacy.
- [ ] **WEB-SEC-05**: Login/SSO/API throttles/CSRF/role isolation، 401/403/429 وerror sanitization regression.
- [ ] **WEB-SEC-06**: Private config/Vault protections، atomic permissions/backup-safe decrypt compatibility من غير rotate مفاتيح فعلية.
- [ ] **WEB-SEC-07**: Structured audit/error log redaction للأسرار مع بقاء correlation IDs وسبب الفشل.

## الموجة B — CORE (7)
- [ ] **WEB-CORE-01**: Contract + scoped tenant/SIM bearer/heartbeat compatibility؛ إضافات SaaS فقط.
- [ ] **WEB-CORE-02**: Published Voice/Agent/5-second answer policy، draft-vs-published وpreserve existing voice/knowledge.
- [ ] **WEB-CORE-03**: Flow runtime/tool policy / idempotency / external verified-result safety.
- [ ] **WEB-CORE-04**: PDF/KB optional fallback + tenant isolation + bounded ingestion + Arabic/content conflicts.
- [ ] **WEB-CORE-06**: Legacy Pilot flat/nested adapters والتوافق دون إزالة أي مسار قائم.
- [ ] **WEB-CORE-07**: Call timeline/manager ACK once/human route explicit opt-in + reports integrity.
- [ ] **WEB-CORE-08**: Usage exact-once/no-answer excluded/atomic quota + active subscription render.

## الموجة C — UX (5)
- [ ] **WEB-UX-01**: ALL role-appropriate navigation/actions functional (no silent overview fallback), RTL/mobile/errors, authenticated POST persistence.
- [ ] **WEB-UX-02**: Admin/Reseller/Customer plans transitions + owner-only access + no unverified paid grants.
- [ ] **WEB-UX-03**: Signed Kashier settlement/reverse/refund/claim idempotency, paid term continuity; manual channels fenced off until verified.
- [ ] **WEB-UX-06**: Staff/RBAC/role-scoped invitation/manager routing + strict-types render.
- [ ] **WEB-UX-08**: Campaign draft/import/mapping/consent/DNC/pause/resume guard and zero outbound call in CI.

## الموجة D — REL (2)
- [ ] **WEB-REL-01**: SaaS PHP/JS/Python/HTTP/Browser regression matrix, controlled log of passing/failing suites.
- [ ] **WEB-REL-03**: MySQL8 migrations fresh+existing-schema additive safety, replay/tenant/financial integrity.

## العشرة المؤجلة بقرار المستخدم — لا تبدأ قبل إغلاق الـ21
`WEB-CORE-05`, `WEB-CORE-09`, `WEB-UX-04`, `WEB-UX-05`, `WEB-UX-07`, `WEB-REL-02`, `WEB-REL-04`, `WEB-REL-05`, `WEB-REL-06`, `WEB-REL-07`.

## بوابة التحقق اليدوي المؤجلة (خارج إغلاق الكود)
Host backup/restore; maintenance secret rotation/revoke; production MySQL snapshot+dry-run; authorized Admin/Reseller/Customer walkthrough; Kashier sandbox/reconcile; signed Meta/WHMCS live config; real provider; phone/SIM sound and answer delay; SHA/rollback/publish receipt. تبقى `UNVERIFIED` حتى إثباتها. **لا ادعاء 100% production** دونها.

## سجل التنفيذ
| التاريخ | البند | الملفات/النتيجة | الاختبارات | Commit |
|---|---|---|---|---|
| 2026-11-10 | Baseline | أنشئ فرعًا مستقلًا وحافظ على release وAndroid كما هما | Pending | Pending |


## متابعة فعلية بعد بدء التنفيذ — 2026-11-10
**الحالة:** كود مرفوع على نفس الفرع؛ آخر فحص الفرع [SaaS 21 CI #42721145188](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42721145188) **SUCCESS**. [PR #84 — DRAFT / NO DEPLOY](https://github.com/OnTrack-Development/OnTrack-voice/pull/84).

> **تمييز حاسم:** السطور التالية تسجّل **إصلاحات كود واختبارات آلية تمت فعليًا**، وليست تصريحًا بإغلاق البند الأعلى بالكامل أو تنفيذ اختبار المستخدم المؤجل. لا تبدّل الـ21 علامة رئيسية إلى [x] دون إثبات جميع معايير القبول الكودية والمراجعة النهائية.

### إصلاحات فعلية جديدة ذات اختبار آلي
- [x] **SEC-01** تأمين ملف digest الخاص بالصيانة (permissions/symlinks)، مع اختبار تدوير تجريبي يرفض التوكن القديم؛ **تدوير هوست حقيقي مؤجل**. `api/MaintenanceAuth.php` + `tools/test_saas_security_guards.php`.
- [x] **SEC-02** حصص إصدار Gemini ephemeral tokens ذرّية per-session/per-IP/global، مع اختبار تغيير IP لنفس الجلسة. `api/LiveSessionSecurity.php` + `tools/test_live_demo_security.php`.
- [x] **SEC-03** حظر redirects مع credential وفرض HTTPS وحد 32KB في Diagnostics، وحماية `test.php` بمصادقة المشرف، والتأكد من عدم وجود `dev_bridge.php` في Webroot النشط؛ الأرشيف المرجعي لم يُعدل. `api/diag.php` + `api/DiagnosticsSafety.php`.
- [x] **SEC-04** كود Host-Link الموقوف يقبل فقط أمر exec موقّع *ومدرج كـargv كامل* في allowlist محلية صريحة؛ test forged/changed argv بدون تنفيذ أو نشر. `ops/host-link/worker.py` + `tools/test_host_link_signed_jobs.py`.
- [x] **SEC-05** حد طلب `lead.create` عند القراءة وقبل استهلاك ذاكرة PHP، مع استبقاء auth/rate-limit القائم. `saas/console/api/v1/index.php`.
- [x] **SEC-06** منع سباق إنشاء مفتاح Vault والتبديل الصامت، التحقق 0600 وعدم قبول symlink أو key تالف، AES-GCM roundtrip. `Bootstrap.php` + `tools/test_saas_vault_integrity.php`.
- [x] **SEC-07** إخفاء الأسرار في audit وtool traces وprovider diagnostic errors مع الاحتفاظ بـcall/account IDs وأسباب HTTP. `Bootstrap.php` و`VoiceToolGateway.php`.
- [x] **CORE-01** إصلاح MySQL affected rows=0 عند تكرار heartbeat في نفس الثانية؛ إثبات MySQL8 حقيقي مع إصلاح الربط ومنع revocation الكاذب. `DevicePairing.php`.
- [x] **CORE-07** سجل أحداث المكالمات immutable حتى عند duplicate INSERT المتزامن، ورفض تغيير event_id/content. `VoiceCallEvidence.php` + timeline test.
- [x] **UX-02** تسلسل تغييرات باقات الريسيلر/العملاء على MySQL بقفل حساب InnoDB لتفادي اشتراكين نشطين متوازيين. `SubscriptionPlans.php`.
- [x] **UX-03** رفض refund HMAC صحيح لكنه يعيد نفس transaction بإجمالي مختلف؛ الاختبارات تتأكد من تضارب المبلغ وعدم منح اشتراك إضافي. `KashierWebhook.php` + `test_verified_checkout_settlement.php`.
- [x] **UX-06** منع دعوة موظف عبر استدعاء خدمة من مستخدم ليس مالك/أدمن الحساب المستهدف، وتسلسل التعيينات مقابل staff quotas، واختبار SQLite/MySQL8. `StaffInvitations.php`.
- [x] **UX-08** منع ازدواج جهات الاتصال في حملة، حد 2000، رفض stale cancelled campaign re-arm، وتزامن claims وإنهاء محاولات SIM. `TenantCampaignQueue.php` + tenant workspace tests.
- [x] **REL-01** workflow معزول يختبر 45 PHP suites إضافة إلى PHP/JS/Python security، مع مصفوفة مستقلة للـChromium الحقيقي (237 قياسًا)، authenticated POST/DB وMySQL8؛ لا نشر.
- [x] **REL-03** تشغيل migrations وbilling وtenant وFlow وstaff وheartbeat ضد MySQL8 حقيقي معزول (وليس قاعدة الإنتاج). [MySQL8 #42720792937](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42720792937).

### بنود لها كود موجود في فرع الأساس ولم يثبت إغلاق جميع متطلباتها بعد
- [ ] **CORE-02** اختبار نشر السياسات/الصوت على بيئة قبول مستأجر حقيقية بعد اجتياز API/Browser.
- [ ] **CORE-03** إثبات موصلات Tool/Provider Sandbox عبر التنفيذ الفعلي المصرح دون نتائج وهمية.
- [ ] **CORE-04** فهرسة PDF ومصادر عربية/TTL/التعارض على بيئة هوست مطابقة.
- [ ] **CORE-06** توافق مسارات Pilot مع سجل الطلبات الفعلية وعدم إزالة Legacy قبل صفر استخدام.
- [ ] **CORE-08** موازنة Usage/Quota ومدة الاشتراك على بيانات حية مستنسخة مع فحص double-charge.
- [ ] **UX-01** تدقيق كل زر ونموذج على الهوست، مع استمرار نجاح 237 قياس Chromium وعمليات POST على CI.

**عدّ التقدم الكودي في هذه الدفعة:** 15 بندًا من الـ21 أُضيفت له حمايات/اختبارات فعلية جديدة على هذا الفرع؛ 6 تعتمد حاليًا على عمل فرع الأساس واختباراته، وتحتاج إغلاقًا أوسع. **21/21 اعتماد نهائي = غير مُدّعى**. عشر المهام الأخرى مؤجلة حسب أمر المالك. لا تمس Native أو Production حتى إقرار مستقل.

### أدلة CI على الفرع
- [SaaS full code matrix](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42721145188) — SUCCESS.
- [Real MySQL8 InnoDB](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42720792937) — SUCCESS.
- [Chromium roles and responsive](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42720552924) — SUCCESS.
- [Authenticated HTTP forms & persisted database](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42720552933) — SUCCESS.

**Host/user manual E2E:** مؤجل بطلب المالك؛ لا يُكتب `Production Ready` أو `100%` قبل تحقق مستقل، ولا تغيير للـAndroid/APK/OTA أو النشر الحي.
