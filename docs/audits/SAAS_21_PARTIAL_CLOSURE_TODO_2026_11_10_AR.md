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
