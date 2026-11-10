> **LIVE MAP DEPLOY CONFIRMED — 2026-11-10:** [Architecture Map](https://app.ontrackegy.com/architecture-map/) منشورة ومحمية بالـPIN؛ [GitHub Actions #42739520075](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42739520075) نجح عليه JOB النشر على BEDIR، إيصال `GAP_MAP_DIRECT_SSH_PUBLISHED_AND_PIN_HTTP_VERIFIED` لكود `b407c690fc35` مع 200 صفحة PIN، 401 API مجهول، و200 API بمفتاح جلسة PIN، وSHA مطابق. هذا **إثبات نشر الماب فقط**، لا اكتمال إصلاحات SaaS ولا الصوت. بعد أول نشر **احتفظ بالـPIN hash الموجود على الهوست**، والتغيير يكون من Actions Secret فقط؛ لا ترجع إلى PIN مكشوف بتاريخ Git. رابط التطبيق الرئيسي `app.ontrackegy.com`؛ لم تُنفذ إعادة هيكلة المسارات `GAP-7`.

# OnTrack Voice SaaS — تنفيذ إغلاق 21 إصلاحًا جزئيًا (Code Closure Tracker)
**تاريخ البدء:** 2026-11-10
**قاعدة العمل:** `fix/saas-integrated-phases123-20261109` @ `56e2adc2b99ceb3c3bfc6804b835908107655634`
**فرع التنفيذ:** `fix/saas-21-closure-20261110`
**النطاق:** SaaS/Server/API فقط. **ممنوع تعديل Android/APK/OTA/PCM/SIM native**؛ اختبار الهاتف اليدوي مؤجل.
**حالة قبول المنتج:** غير معتمد إنتاجيًا؛ لا يساوي اجتياز الاختبارات الآلية نجاحًا على الهوست.
**شرط المستخدم:** تنفيذ جميع البنود تباعًا دون طلب موافقة لكل خطوة، وإرجاء مهام الـ10 غير المغلقة أصلًا إلى ما بعد إتمام بنود الـ21.

**مرجع الشاتات الجديدة:** [ARCHITECTURE_GAP_MAP_CURRENT_HANDOFF_AR.md](ARCHITECTURE_GAP_MAP_CURRENT_HANDOFF_AR.md) — هذا الملف هو سجل المهام الذي **يجب تحديثه بعد كل إصلاح**، و`ops/gap-map/status.seed.json` هو سجل عرض الفجوات الحالي.

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
- [x] **CORE-02** قفل MySQL لتخصيص immutable agent Flow revisions عند Publish متزامن، مع اختبار إعادة النشر idempotent وبقاء Voice/Policy snapshot. `PublishedFlow.php` + real MySQL8.
- [x] **CORE-04** حماية إعادة فهرسة Knowledge على نفس المصدر من سباقات عمال MySQL/SQLite؛ لا يُكتب Index مكرر ولا يستبدل hash أحدث بآخر قديم. `VoiceKnowledgeSearch.php` + Arabic conflict/tenant/PDF search CI.
- [x] **CORE-03** إرجاء كتابة Trace لأحداث Flow إلى ما بعد نجاح step_version CAS؛ خسارة السباق لا تسجل زيارة/ردًا موهومًا. `VoiceFlowRuntime.php` + Flow runtime regression.
- [x] **CORE-01** إصلاح MySQL affected rows=0 عند تكرار heartbeat في نفس الثانية؛ إثبات MySQL8 حقيقي مع إصلاح الربط ومنع revocation الكاذب. `DevicePairing.php`.
- [x] **CORE-07** سجل أحداث المكالمات immutable حتى عند duplicate INSERT المتزامن، ورفض تغيير event_id/content. `VoiceCallEvidence.php` + timeline test.
- [x] **UX-02** تسلسل تغييرات باقات الريسيلر/العملاء على MySQL بقفل حساب InnoDB لتفادي اشتراكين نشطين متوازيين. `SubscriptionPlans.php`.
- [x] **UX-03** رفض refund HMAC صحيح لكنه يعيد نفس transaction بإجمالي مختلف؛ الاختبارات تتأكد من تضارب المبلغ وعدم منح اشتراك إضافي. `KashierWebhook.php` + `test_verified_checkout_settlement.php`.
- [x] **UX-06** منع دعوة موظف عبر استدعاء خدمة من مستخدم ليس مالك/أدمن الحساب المستهدف، وتسلسل التعيينات مقابل staff quotas، واختبار SQLite/MySQL8. `StaffInvitations.php`.
- [x] **UX-08** منع ازدواج جهات الاتصال في حملة، حد 2000، رفض stale cancelled campaign re-arm، وتزامن claims وإنهاء محاولات SIM. `TenantCampaignQueue.php` + tenant workspace tests.
- [x] **REL-01** workflow معزول يختبر 47 PHP suites إضافة إلى PHP/JS/Python security، مع مصفوفة مستقلة للـChromium الحقيقي (237 قياسًا)، authenticated POST/DB وMySQL8؛ لا نشر.
- [x] **REL-03** تشغيل migrations وbilling وtenant وFlow وstaff وheartbeat ضد MySQL8 حقيقي معزول (وليس قاعدة الإنتاج). [MySQL8 #42720792937](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42720792937).

### بنود لها كود موجود في فرع الأساس ولم يثبت إغلاق جميع متطلباتها بعد
- [ ] **CORE-06** توافق مسارات Pilot مع سجل الطلبات الفعلية وعدم إزالة Legacy قبل صفر استخدام.
- [ ] **CORE-08** موازنة Usage/Quota ومدة الاشتراك على بيانات حية مستنسخة مع فحص double-charge.
- [ ] **UX-01** تدقيق كل زر ونموذج على الهوست، مع استمرار نجاح 237 قياس Chromium وعمليات POST على CI.

**عدّ التقدم الكودي في هذه الدفعة:** 18 بندًا من الـ21 أُضيفت له حمايات/اختبارات فعلية جديدة على هذا الفرع؛ 3 تعتمد حاليًا على عمل فرع الأساس واختباراته، وتحتاج إغلاقًا أوسع. **21/21 اعتماد نهائي = غير مُدّعى**. عشر المهام الأخرى مؤجلة حسب أمر المالك. لا تمس Native أو Production حتى إقرار مستقل.

### أدلة CI على الفرع
- [SaaS full code matrix](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42721145188) — SUCCESS.
- [Real MySQL8 InnoDB](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42720792937) — SUCCESS.
- [Chromium roles and responsive](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42720552924) — SUCCESS.
- [Authenticated HTTP forms & persisted database](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42720552933) — SUCCESS.

**Host/user manual E2E:** مؤجل بطلب المالك؛ لا يُكتب `Production Ready` أو `100%` قبل تحقق مستقل، ولا تغيير للـAndroid/APK/OTA أو النشر الحي.

## GAP MAP v2 — مراجعة الخريطة القديمة وتوجيه مالك المشروع (2026-11-10)

**المرجع:** الملف المرسل من المالك `ontrack_voice.html`، يتضمن ست فجوات أصلية وخمس طبقات. هذه **فرضيات بنيوية قديمة** يجب تأكيدها مقابل HEAD الفعلي قبل تصنيفها Complete. المصدر المرجعي للـSaaS هو هذا الفرع، وليس snapshot "OnTrack-voice-main" المرسوم.

**VS/ خارج المشروع نهائيًا:** تأكد عدم وجود أي مسار يبدأ `VS/` في `main` و`release/voice-v1-20261106` وفرع `fix/saas-21-closure-20261110`. **لا يُعاد إنشاؤه أو ترحيله أو اختباره أو احتسابه GAP مفتوحة.** جزء GAP-3 الخاص بـVS obsolete؛ ازدواج واجهة Kotlin/OTA مش ضمن SaaS الحالية.

| Gap | حالة التحقق من الكود | التأثير / التقاطع | المطلوب بعد مراجعة وربط الأوديت |
|---|---|---|---|
| **GAP-1** Laravel/Console منفصلان | **CODE DIVERGENCE CONFIRMED؛ انتشار Laravel على Host غير مثبت**؛ Laravel Controllers/Models/26 migrations جنب Console Core وMySQL schema. ناشر الموقع الحالي يستهدف Console فقط؛ لا نستنتج Live split-brain دون host evidence. | SaaS أساسي P0؛ CORE-08, UX-02/03, REL-03 وwebhook/billing/tenant IDs. | inventory route→runtime→DB→owner، تكوين مصدر حقيقة وحيد لكل workflow، منع double-settlement وdouble-usage قبل أي migration. **لا حذف/إيقاف Runtime يستخدمه عميل**. |
| **GAP-2** 4 API domains / auth paths | **CONFIRMED IN SOURCE**: `saas/mobile/server/`, `console/device/v1`, root `api/`, Laravel `routes/web.php`؛ لا نفترض جميعها مستعملة فعليًا. | **SHARED SaaS↔MOBILE**؛ CORE-01/06/07/08، تحديث bearer/contract وحماية Pilot/Voice. | متتبع request path/method/auth/tenant/DB/consumer + adapter additive خلف مسارات موجودة؛ لا تعديل Android ولا كسر API قديمة. |
| **GAP-3** VS وواجهات OTA | **VS OBSOLETE / ZERO FILES**، Kotlin/OTA views غير موحّدة في snapshot؛ غير مؤكد وجود divergence فعلي حاليًا. | MOBILE ONLY، deferred. | إخراج VS من أي ماب جديدة تمامًا؛ تحديث المصدر الوحيد للـUI لاحقًا ضمن خطة تطبيق مستقلة. |
| **GAP-4** root vs `reference/voicecall-v0.6.3` | **DUPLICATE PATHS CONFIRMED، BYTE IDENTICAL غير مثبت**؛ snapshot غير release. | SHARED/MAINTAINABILITY؛ SEC-03/07، root voice deployment | audit deploy packaging / runtime references أولاً، منع نشر reference، لا حذف أرشيفات أثناء عمل الـ21. |
| **GAP-5** diagnostic routes & static hash | **SOURCE CONFIRMED**: `test.php`, `omnira_api_tester.php`, `api/diag.php` وtracked `saas/mobile/server/auth.sha256`; تعرّضها للعامة **غير مثبت**. وثيقة الصور قد تحتاج مراجعة بحذر. | **P0 SECURITY / SHARED**؛ SEC-01..07 وmobile auth. | تأكد عبر public probes بلا تسريب؛ اعتماد أسرار private خارج webroot مع rotate+migration آمن بدون كسر الأجهزة، وربط diagnostics بـoperator auth؛ لا تسجل أي أسرار في التقرير/الماب. |
| **GAP-6** متاهة Workflows | **STALE COUNT**: عدد Workflow YAML على فرع العمل **69 وليس 16**. | DevOps SHARED، mobile build خارج scope | audit actual triggers & publication overlap، عزل site-only وAndroid/no double publish، لا إلغاء workflows مثبتة دون مبرر. |
| **GAP-7 — NEW** login canonical host and hidden subpaths | **OWNER-OBSERVED, NOT YET VERIFIED WITH HOST**: بعد الدخول من نطاق التطبيق الرئيسي ينتقل المتصفح إلى `/saas/console` بدل إبقاء الدومين ومسار الدخول canonical. المالك ذكر `app.entrakegy.com`؛ ناشر الريبو يسمي `app.ontrackegy.com`. يُحدَّد FQDN الصحيح من إعدادات Host/DNS قبل أي تغيير، دون افتراض أن أحدهما صحيح. | **P1 UX/ROUTING / SHARED DEPENDENCIES**: Laravel/Console/SSO/white-label/session cookie/device URLs / webhooks. | **مؤجل بقرار المالك الآن:** سجل 302 Location chain، canonical base URL، rewrite/proxy، session+CSRF+SSO callback، hash/relative links، الحفاظ على `/saas/console` و`/mobile` وAPI aliases للأجهزة الحالية حتى traffic+compatibility صفر مخاطر. لا تغيير Routing ولا حذف فولدر حاليًا. |

### معمارية الماب الجديدة المتحركة
- [ ] إنشاء مجلد Host جديد **`/home/appontrack/public_html/architecture-map/`** للوحة عرض تفاعلية **محمية بـPIN من ستة أرقام وجلسة مؤقتة (ليس Login حساب SaaS)**. لا تُنشر فجوات الأمان أو بنية خاصة لعامة الويب؛ لا ملفات Secrets/SSH/Reports داخل Webroot.
- [ ] **Live dashboard data** في `/home/appontrack/.ontrack-gap-map/status.json` خارج Webroot، تزامن آمن من branch SHA وCI statuses؛ الصفحة تستعلم `status.php` كل ~20s وتعرض *آخر مزامنة حقيقية* ولا تختلق Streaming realtime.
- [ ] خريطة 3 مسارات: **SaaS** / **MOBILE (Observation-only)** / **SHARED INTERFACES** مع Connections، severity، source refs، وتاريخ التحديث وCommit SHA، 21 tasks، 10 deferred، GAP-7.
- [ ] إنشاء CI/host publish لـ**ملفات الماب وحدها** على الـWindows self-hosted runner عبر SSH موثق، دون نشر SaaS runtime، وبدون Android أو Host-Link القديم. Verify SHA وHTTP 401/200 auth gate.
- [ ] في كل push كودي على فرع الـ21: snapshot متجدد + smoke + نشر status في host؛ بعد أي CI gate تمييز passed/failed/queued، **لا مزامنة فعلية بين كل ثانية بدون backend**.
- [ ] لا تصنيف GAP أو Ticket DONE بمجرد عدد commits/الـCI؛ فصل CODE PATCHED / CI VERIFIED / HOST VERIFIED / USER E2E DEFERRED.

### الوصول والنشر المصرَّح
- وثيقة الصلاحيات الرسمية: `docs/ops/ONTRACK_DIRECT_SSH_DEPLOY_ACCESS_20261107_AR.md`.
- Windows self-hosted runner `BEDIR`, SSH direct 7195 بمفتاح خاص **داخل حساب Runner فقط**، username `appontrack`، Host-Link STOP. لا حفظ المفتاح أو عرضه. نطاق صلاحيات السيرفر لحساب appontrack فقط.
- **لا تستخدم Host-Link / queue_saas_console القديمة** ما دام STOP؛ workflow الماب لا يلمس APK/OTA/PCM/Android ولا يحاول نشر الموقع الأصلي.

## GAP-8 — Voice Critical Path / Latency (2026-11-10)
**التصنيف:** P0 Shared SaaS↔Mobile / قياس غير مكتمل، ليس إصلاحًا معتمدًا. أُضيفت الفجوة إلى `ops/gap-map/status.seed.json` مع اتصالات واضحة إلى `GAP-1/GAP-2/GAP-5/GAP-6`.

**قاعدة تصميم ملزمة:** الهاتف المرتبط بشريحة ضمن Tenant وSIM فعال يستخدم **Device Bearer Token** الحالي، وليس Web Login أو session cookie أو مرورًا عبر Dashboard، ولا تُطلب جلسة تسجيل دخول لكل مكالمة أو لكل قطعة صوت. لا يُسقط ذلك تحقق حالة الربط والإلغاء أو عزل المستأجر أو المتطلبات التي لا يمكن حذفها (DNC/consent وحصة الاتصال قبل الاتصال الصادر).

### تجربة وارد وصادر متوازية
- [ ] **مسار وارد:** trigger استقبال المكالمة → إثبات device binding المحلي/الخادم → published Agent snapshot المستقر → Live provider session → first audio → streaming → event ledger غير متزامن → post-call report. سجل بداية ونهاية كل خطوة بـ monotonic clock؛ قِس 3G/H+/4G/5G/Wi-Fi.
- [ ] **مسار صادر:** تحقق موافقة الحملة وDNC ومنع الأرقام والحصة قبل dial → binding → dial → session/stream → report. **لا bypass لبوابة consent/DNC أو لرفض الشريحة المسحوبة**.
- [ ] **SaaS audit:** `saas/console/device/v1/index.php` يقرأ Bearer ويستدعي `device_binding_authorize`، و`TenantVoiceSession.php` يصدر Gemini Live token بطلب إلى Google. المطلوب قياس الكلفة الزمنية الحقيقية للـSQL والخطوة الخارجية، وفصل الضروري منها عن المتكرر.
- [ ] **Fast-path:** prefetch/cached published snapshot وتهيئة provider connection قبل بداية الكلام حين يمكن، تحديث البروفايل بالخلفية، عدم إعادة KB index أو تحميل Dashboard أو OAuth/Login في loop الصوت، واستخدام idempotent async logs من دون فقد دليل الأحداث.
- [ ] **Observability:** p50/p95/p99 وtrace_id لكل hop؛ `answer_delay`, `first_audio`, `speech_end_to_first_response`, `device_auth_ms`, `provider_token_ms`, `provider_connect_ms`, `db_wait_ms`, `RTT`, `jitter`, `loss`, `reconnect_count`.
- [ ] **ميزانية قبول:** هدف المالك **≤2s** لتأخير التفاعل، وليس قيمة قياس حقيقية بعد. أي رقم 50ms×10 أو زمن مستنتج من الكود هو مثال فقط لا Evidence.
- [ ] **اختبارات آلية آمنة:** محاكاة slow-provider/timeout/retry على SaaS، Assertions لعدم Login في call handler، وقياس بدون أجهزة؛ تجارب المكالمات الفعلية ومقارنة جودة Data/Wi-Fi مؤجلة لمرحلة موبايل مستقلة.

### تقدم كود الماب — 10 نوفمبر 2026 (ليس نشرًا على الهوست)
- [x] مصدر واجهة RTL تفاعلية ضمن `ops/gap-map/web/index.php`, `style.css`, `app.js`: فصل SaaS/Mobile/Shared وخطوط GAP والعلاقات، العداد، PHP PIN/CSRF/session، زر «تم التحقق منها» مع private review ledger. **PHP/JS lint + regression PASS** [Run #42732197969](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42732197969).
- [x] مصدر **الكشف التلقائي** `tools/build_gap_map_snapshot.py`: fingerprint ملفات كل فجوة، عدد ملفات الـWorkflow الحالي، رصد tracked auth hash من غير قراءة قيمته للنشر، مقارنة `VS/`, إظهار `source_scan.runtime_checked=false`.
- [x] مصدر **Simulator CI** `tools/run_gap_map_simulator.py`: 8 اختبارات معزولة، PASS/FAIL وسبب الفشل/المكوّن، 4 مراحل خارجيّة NOT_TESTED/BLOCKED؛ **ليس مكالمة حية**.
- [x] Workflow رفع Artifact `SaaS-Architecture-Gap-Map` إلى Actions بعد نجاح اختبارات الـSaaS؛ نتيجة Snapshot مرتبطة بـGitHub SHA.
- [ ] **نشر الماب على Windows SSH host** والتحقق من PIN خاص/URL/HTTP/QA/SHA؛ **غير منجز**، لا تُعرض كصفحة عامة ناجحة.
- [ ] Auto-publish إلى المجلد الخاص بعد كل دفعة متحقق منها؛ حالياً CI Artifact فقط، وليس مزامنة Host مثبتة.

### طلبات الماب التفاعلية الإضافية
- [ ] PIN منفصل من 6 أرقام يحدده المالك في المحادثة ويُجهَّز على الهوست **دون إدراج الرمز كنص في الملفات أو الكوميتات**، **لا تحفظ PIN كنص في Git أو webroot**؛ استخدم private password hash + CSRF + rate limiting + session TTL. PIN الحالي اختاره المالك؛ يفضَّل تغييره قبل مشاركة رابط المراقبة.
- [ ] زر **تم التحقق منها** لكل GAP، يُحفظ مراجعة المالك + timestamp + source fingerprint في Host private ledger؛ عند تغيّر ملفات المصدر في Gap يعرض `recheck_required`، ولا يغيّر هذا تصنيف CI/Production إلى PASS تلقائيًا.
- [ ] قسم **Simulation Journey** يشغّل اختبارات PHP/DB/Browser المعزولة فقط ويعرض `PASS/FAIL/BLOCKED/NOT_TESTED`؛ تسجيل/استعادة كلمة مرور، ريسيلر وباقات، تغيير باقة، Agent/Flow/KB، محاكاة مكالمة وحظر DNC، وتصنيف فشل كل Step وملفه المسؤول. لا تنفّذ مكالمات حقيقية أو رسوم أو رسائل بريد حقيقية تلقائيًا.
- [ ] نشر ماب مستقلة `/architecture-map/` مع Live status من Snapshot ملف مُولّد من GitHub Actions، وتسجيل result بعد كل push، منفصلة تمامًا عن نشر Android/OTA أو إعادة تفعيل Host-Link.

## دفتر الحالة المدققة للمجموعة 21 — تحديث الشاتات المستقبلية

**قاعدة تحديث تلقائية/يدوية ملزمة:** لا تُحدّث العداد 18/3 من التوقع. بعد كل Source Commit، اربط في الجدول أعلاه وفـ`ops/gap-map/status.seed.json` `source_sha`, `test_run`, `result`, `remaining_acceptance`, `updated_at`. عند إصلاح 3 بنود مثلاً، فقط البنود التي اجتازت شروطها تصبح `CODE_COMPLETE` وتنتقل من PARTIAL إلى المكتمل الكودي؛ لا تلغِ فجوة مرتبطة بتغيير لم يُختبر. إن أخفق CI أو تغير المصدر، أعدها PARTIAL/BLOCKED واحتفظ بسجل التغييرات.

| مرحلة البند | التفسير | إمكانية وضع علامة [x] |
|---|---|---|
| OPEN | لم يُبدأ أو ليس له دليل | لا |
| PARTIAL | بعض الكود/الاختبارات اكتملت، وهناك شروط ناقصة | على الخطوات الجزئية المثبتة فقط |
| CODE_COMPLETE | كل التغييرات المطلوبة في الكود + Test matrix نجحت على نفس SHA | نعم على **إغلاق الكود** فقط؛ لا تعني نجاح الإنتاج |
| HOST_VERIFIED | إصلاح منشور بإيصال SHA وHTTP معزول | علامة النشر بعد الإثبات |
| USER_VERIFIED | تحقق المالك يدويًا في سيناريو حقيقي | علامة التحقق البشري بعد الإثبات |
| COMPLETE | كل معايير قبول هذا البند تحققت | نعم على **البند الرئيسي**، بدون مبالغة |

**آخر baseline موثق:** 18/21 عليها إصلاحات كود ولا يوجد إثبات إغلاق كامل بالمعيار أعلاه، CORE-06/CORE-08/UX-01 تحتاج مزيدًا من الكود؛ 10 مهام أخرى مؤجلة. الماب لم يثبت نشرها؛ وتأكيدات المالك لها سجل fingerprint مستقل. **هذه الأرقام أساس فقط ويجب إعادة فحص أحدث GitHub SHA والـCI بعد أي تنفيذ.**

### Rule-based Source Detection — إغلاق جزء من الأتمتة (10 نوفمبر 2026)
- [x] `tools/build_gap_map_snapshot.py` يضيف `findings[]` للـSnapshot تلقائيًا من كود HEAD الفعلي، مع `SRC-001..007`: Laravel/Console، تشتت APIs، ملف hash متتبع (دون قراءة قيمته)، diagnostics، legacy canonical URL، external Google Live token hop، وعودة `VS/` إن حدثت. النتائج تتضمن `evidence_paths`, `confidence`, `linked_gap`, `runtime_verified=false`، وتظهر في قسم منفصل بالماب.
- [x] واجهة الماب تعرض نتائج الكشف وتربطها بالفجوات الأصلية؛ لا تُحوّل Source Candidate تلقائيًا إلى "خطأ مؤكد" ولا إلى تذكرة مغلقة.
- [ ] تحليل أداء الصوت الفعلي والـDB/HTTP telemetry، واكتشاف الأخطاء الديناميكية على Host يحتاج Instrumentation مستقل؛ لا تُختلق قياسات أو تأكيدات.

## 2026-11-10 — موجة إصلاح متكاملة للـ21 (CORE-06 + CORE-08 + UX-01)
**نطاق التنفيذ:** SaaS/Console PHP والـCI فقط؛ Android/VS/OTA ممنوع، وGAP-7 Login redirect **مؤجل**. بعد كل push مُتحقق منه يُعاد بناء Snapshot وتُنشر **الماب فقط**، وليس نظام SaaS الإنتاجي. تم إنشاء Automation لاستكمال الخطة كل ساعة بتكرار فحصها ومتابعة مجموعة كاملة مع تحديث الملفات والأدلة.

| التذكرة | السبب المثبت | إصلاح كود مرفوع | اختبارات الإغلاق | الحالة |
|---|---|---|---|---|
| **WEB-CORE-06** | سياسة Pilot flat قد تُستبدل بالـdefaults بصمت عند فساد JSON، والتغييرات غير المعروفة يمكن أن تلوث الإعدادات | `saas/console/_core/MobilePilot.php`: رفض symlink/ملف فاسد، whitelist/validation للكتابة، حفظ حقول Legacy والتعامل مع revision string؛ `tools/test_saas_pilot_policy_compat.php` | CI isolated fixture + legacy traffic/device ACK عند السماح | **PARTIAL**؛ لا حذف لمسارات Pilot |
| **WEB-CORE-08** | سجل الاستهلاك كان مربوطًا بتوقيت وصول event بدل تاريخ المكالمة، وفي تغيير باقة بنفس الفترة قد يُعاد ضبط used؛ 3G out-of-order يؤدي لفوترة خاطئة | `BillingUsage.php`: charge only authenticated answered+ended, no demo/missed/no-answer, actual call start, replaced-plan history، quota carryover؛ `test_saas_usage_period_reconciliation.php` + MySQL8 fixture | SQLite exact-once/out-of-order/tenant، MySQL8 billing والتجديد، ثم production snapshot reconcile | **PARTIAL**؛ paid renewal/overage/live reconciliation ما زال مفتوحًا |
| **WEB-UX-01** | tab غير موجود كان يرجع Overview بـHTTP 200 كأنه يعمل؛ واستثناء render يعرض تفاصيل داخلية للمستخدم | `saas/console/index.php`: 404 للتاب غير المسموح، 503 برقم Incident للعرض الفاشل؛ `tools/test_saas_roles_form_post_http.py` | HTTP authenticated roles + 237 Chrome + all-action coverage + host/manual E2E | **PARTIAL**؛ باقي واجهات المستخدم لم تُعتمد |

**دليل الموجة الجاري:** آخر شيفرة `9a18a84c7384a69ba2211967b4c3008e56ecf306`، [SaaS 21 CI #42743726116](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42743726116) = **IN PROGRESS عند كتابة التوثيق**، [Integrated CI #42743734339](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42743734339) = **IN PROGRESS**. لا تُعلن PASS قبل تحقق النتيجة النهائية على HEAD. آخر [MySQL8 ناجح على patch سابق #42743633011](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42743633011)؛ 237 Chrome/HTTP قد نجحا على فروع أصغر. انضباط الحالة: ما زالت **18 code-patched + 3 partial**، و**0/21 اعتماد Host/User كامل**؛ هذه الموجة أضافت sub-fixes فعلية للثلاثة وليست 3/3 إغلاق شامل.

**ملاحظات قبول مهمة:** فصل Runtime Laravel/Console وauth.sha256 لم يتغيرا؛ توجيه الروابط /saas/console مؤجل؛ قياس ميلي ثانية حقيقي غير متاح، والماب يعرضه "غير مقاس". عند فشل CI أو عدم اكتمال نشر الماب حدّث الحالة بدل وضع ✅. ولا تنفذ أي مكالمات أو دفع حقيقي من السيميوليتر.

### تحقق ناجح للموجة وإيصال نشر الماب — 2026-11-10
- **SaaS 21 Code Matrix — PASS:** [Run #42744338630](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42744338630) على SHA `0461878788c63766ba42ec281900e58faf73ff52`: **49 PHP test suites** مع `SAAS_USAGE_PERIOD_EXACT_ONCE_NO_ANSWER_DEMO_REORDERED_CROSS_TENANT_PASS` و`SAAS_PILOT_FLAT_POLICY_BACKWARD_COMPATIBILITY_FAIL_CLOSED_PASS`، و**103 action forms / 21 navigation tabs** عبر `SAAS_NAV_AND_ACTION_CONTRACT_PASS`; Static gap scanner كشف 6 source findings.
- **Integrated regression — PASS:** [Run #42744344873](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42744344873) على نفس SHA. في أول نسخة توثيق ظهر خطأ trailing whitespace في `docs/memory/SESSION_HANDOFF_AR.md`؛ أصلحناه في Commit `0461878788c6` وأعدنا CI حتى نجح. لا نخفي الـfailure القديم.
- **MySQL8 / Chromium / Authenticated HTTP — PASS** بعد تغييرات الكود: [MySQL8 #42743633011](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42743633011) و[Chromium #42743633113](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42743633113) و[HTTP #42743632856](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42743632856)؛ تشغيلها كان على SHA `925c3b98db...` (وهو يتضمن `BillingUsage.php` المعدل، قبل إضافة اختبار محاكاة SQLite). واجهة UX الأصلية عُدلت قبل ذلك SHA؛ لا نساوي نتيجة على SHA قديم بإثبات أحدث Doc HEAD.
- **Map-only deploy — VERIFIED:** نفس [Run #42744338630](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42744338630) نجح على BEDIR بإيصال `GAP_MAP_EXISTING_PIN_PRESERVED_HOST_PRIVATE_SHA_VERIFIED` + `GAP_MAP_DIRECT_SSH_PUBLISHED sha=0461878788c6` على `https://app.ontrackegy.com/architecture-map/`. نشر الماب لا يعني نشر وظائف SaaS الجديدة للإنتاج.
- **حالة البنود الرئيسية:** `WEB-CORE-06` / `WEB-CORE-08` / `WEB-UX-01` **PARTIAL رغم نجاح كود المرحلة** لأن مراجعة تجارب Legacy الحية، دورات الدفع/الاسترداد والحصص، وفحص كل واجهة وسيناريو قبول المستخدم ما زالت خارج التغطية الحالية. **21/21 إنتاجي غير مثبت.** Automation كل ساعة تتابع المجموعة التالية وتتطلب CI وماب محدثة.
