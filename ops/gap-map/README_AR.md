> **LIVE MAP DEPLOY CONFIRMED — 2026-11-10:** [Architecture Map](https://app.ontrackegy.com/architecture-map/) منشورة ومحمية بالـPIN؛ [GitHub Actions #42739520075](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42739520075) نجح عليه JOB النشر على BEDIR، إيصال `GAP_MAP_DIRECT_SSH_PUBLISHED_AND_PIN_HTTP_VERIFIED` لكود `b407c690fc35` مع 200 صفحة PIN، 401 API مجهول، و200 API بمفتاح جلسة PIN، وSHA مطابق. هذا **إثبات نشر الماب فقط**، لا اكتمال إصلاحات SaaS ولا الصوت. بعد أول نشر **احتفظ بالـPIN hash الموجود على الهوست**، والتغيير يكون من Actions Secret فقط؛ لا ترجع إلى PIN مكشوف بتاريخ Git. رابط التطبيق الرئيسي `app.ontrackegy.com`؛ لم تُنفذ إعادة هيكلة المسارات `GAP-7`.

# OnTrack Voice — Architecture Gap Map v2

**Reference first:** [Current Handoff](../../docs/audits/ARCHITECTURE_GAP_MAP_CURRENT_HANDOFF_AR.md) · [21-task SaaS repair To-Do](../../docs/audits/SAAS_21_PARTIAL_CLOSURE_TODO_2026_11_10_AR.md) · [Direct SSH permission file](../../docs/ops/ONTRACK_DIRECT_SSH_DEPLOY_ACCESS_20261107_AR.md).

## الملفات
- `status.seed.json`: 8 فجوات و18 مكوّنًا وحالات 21+10 مهمة. source-data وليس قياس host.
- `web/index.php` و`web/style.css` و`web/app.js`: واجهة RTL، بحث، Nodes، روابط، حالة Simulator، زر Owner Review.
- `web/_auth.php`: PIN ست أرقام من **private hash خارج git**، CSRF، session مؤقت، rate limits.
- `web/status.php`: قراءة JSON تحت نفس جلسة PIN، وضم تقييم المالك من `reviews.json`.
- `web/verify.php`: Confirm/Revoke تأكيد المالك مع `basis_hash`؛ تغير البصمة يفرض إعادة تأكيد.
- `tools/build_gap_map_snapshot.py`: source scanner يقرأ git tracked files، يضيف per-gap SHA256 fingerprint، ويكشف تكرار المسارات والـworkflow count. **لا يعتمد GitHub commit alone لإبطال التأكيد**.
- `tools/run_gap_map_simulator.py`: تشغيل 8 PHP/SQLite test fixtures معزولة في GitHub hosted CI فقط (لا calls، لا live charges).
- `tools/test_gap_map_handoff.py`: guard لاستمرارية روابط AGENTS/CLAUDE/README/Checkpoint/permissions وتطابق 21/10 و8 GAPs.
- `tools/publish_gap_map_direct_ssh.py`: نشر معزول للماب فقط عبر Windows runner SSH، مع تحقق HTTPS/CSRF/PIN/403/401 وSHA/backup.

## حالة النشر (2026-11-10)
- **CODE + CI VERIFIED**: [Actions SaaS 21 Regression](https://github.com/OnTrack-Development/OnTrack-voice/actions/workflows/saas-21-closure-regression.yml) ينتج Artifact باسم `SaaS-Architecture-Gap-Map` بعد تنفيذ simulator وscanner.
- **HOST UNVERIFIED**: لا تفترض أن `https://app.ontrackegy.com/architecture-map/` يعمل بدون إيصال نشر؛ الـpin لا يُوضع في الكود أو اللوجات.
- Workflow `saas-21-closure-regression.yml` يحتوي Job مستقل `publish-gap-map` **لا يعمل** إلا عند ضبط repo variable `ONTRACK_GAP_MAP_DEPLOY_ENABLED=true`، وrepo Actions secret `ONTRACK_GAP_MAP_PIN` (6 digits) من القناة الموثوقة؛ لا تكتب القيمة في `workflow_dispatch` inputs أو ملفات Git. لا ننشئ سرًا نيابة عن المالك بدون tool مرخص.
- Windows self-hosted Runner `BEDIR`, Direct SSH 7195 من ملف الصلاحيات؛ Host-Link **STOP**، لا APK ولا Android ولا تعديل Console runtime.
- Dest web: `/home/appontrack/public_html/architecture-map/`؛ private: `/home/appontrack/.ontrack-gap-map/` مع `pin.hash`, `status.json`, `reviews.json`.
- عند نجاح نشر فعلّي، تحقق: anonymous `status.php=401`، صفحة PIN 200، قبول جلسة PIN 200، `status.json.commit` يساوي SHA المنشور، وعدم ظهور credentials. إذا فشل SHA/HTTP، **لا تُعلن النشر نجاحًا**.

## قواعد الحالة والربط
- كل Gap له `id`, `area=saas/mobile/shared`, `modules`, `links`, `files`, `basis_hash`, `source_probe`, `verification`.
- `confirmed_by_owner` ≠ `complete`. عند تغيّر ملفات المصدر المُؤثِّرة تظهر `recheck_required`، وتبقى مراجعة المالك محفوظة بتاريخها.
- 21 بنود SaaS: `PARTIAL/CODE_COMPLETE/HOST_VERIFIED/USER_VERIFIED/COMPLETE` تبع evidence، 10 مؤجلة. CI 100% لا يساوي إنتاج 100%.
- `VS/` محذوف نهائيًا، لا تعيده. `GAP-7` canonical URL مؤجل، `GAP-8` صوت غير مقاس فعليًا، مطلوب تجنب Web Login وجميع hops غير الضرورية مع حماية Device Bearer/DNC/Consent/Quota.
- بعد كل إصلاح: تحديث To-Do + seed/status snapshot + دليل GitHub + ملف Handoff. لو 3 بنود أُغلقت كوديًا بمصفوفة قبول ناجحة، انقل 3 فقط إلى `code_complete` ولا ترفع عداد الجميع.

## Rule-based automatic findings
`tools/build_gap_map_snapshot.py` adds `findings` on every source push with file evidence and linked GAP, without reading credential contents. SRC-001 (Laravel/Console), SRC-002 (API sprawl), SRC-003 (tracked hash filename), SRC-004 (diagnostic routes), SRC-005 (legacy login subpath), SRC-006 (Google auth request in voice session), SRC-007 (VS regression, should remain absent). All have `runtime_verified=false`; they are **review candidates**, not falsely verified service outages. UI has a separate clickable area. The last verified host deployment is [42739520075](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42739520075) with SHA `b407c690fc35`; subsequent deployments should preserve the host-private PIN verifier.

## SaaS wave 2026-11-10 — status in map
`WEB-CORE-06`, `WEB-CORE-08`, `WEB-UX-01` تظل **awaiting_code_gap_closure** ولكن لها `patch_evidence`, `patch_summary`, `next_action` في `status.seed.json`. الماب تبرز تحديثات كود Pilot flat compatibility وUsage call-start carryover وUI invalid-tab 404؛ **SaaS code was NOT deployed**. Snapshot/CI على HEAD التالي يعرض الوضع الفعلي، وعمليات النشر الخاصة بالماب لا تُغلق تذاكر SaaS تلقائيًا. الاختبارات اليدوية مؤجلة بطلب المالك.

## Latest Code-Wave Evidence 2026-11-10
[49 suites + Map-only deploy #42744338630](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42744338630) PASS at SHA `0461878788c6`: 103 view actions, 21 nav tabs, exact-once usage and flat Pilot legacy safety. Map `/architecture-map/` SSH/host private SHA verified, PIN preserved; all three CORE-06/CORE-08/UX-01 remain PARTIAL, not fully accepted SaaS features.
