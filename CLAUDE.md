<!-- ONTRACK_MANDATORY_AGENT_RULES_START -->
# تعليمات المالك الملزمة لكل Coding Agent — اقرأ قبل أي تعديل

**ابدأ دائمًا من [README.md](README.md)، فقسم «تعليمات إلزامية لتطوير المشاريع باستخدام AI Coding Agents» هو النص الكامل المعتمد بتاريخ 2026-11-04.** ثم اقرأ [AGENTS.md](AGENTS.md) وراجع الكود الحقيقي والسجلات وأحدث القرارات المؤكدة. هذه القاعدة تسبق الملاحظات التاريخية المتعارضة في بقية الملف، وتُستخدم معيارًا في أي مشروع OnTrack يُعمل عليه لاحقًا.

## Execution contract / Definition of Done

1. **Specify:** حوّل الطلب إلى سلوك مطلوب ومعايير قبول قابلة للاختبار، من دون إعادة السؤال أو اختراع متطلبات خارج النطاق.
2. **Diagnose:** اقرأ الملفات المتأثرة والسجلات؛ تتبّع المسار وقارنه بآخر سلوك مؤكد. عند تعارض التوثيق مع الكود والأدلة، صحّح التوثيق لا الحقيقة.
3. **Implement:** عدّل المصدر الحقيقي ضمن الصلاحيات، وعلّق فقط بجوار القرارات المعمارية أو منطق الحماية غير البديهي.
4. **Evaluate:** أضف/حدّث Evals واختبارات النجاح والفشل والحالات الطرفية والانحدار؛ شغّلها فعلًا، ثم افحص الأخطاء والتحذيرات والسجلات.
5. **Repair:** أصلح العطل المكتشف وأعد تشغيل الاختبارات حتى PASS أو عائق حقيقي موثق. لا تكلف المالك بفرز أخطاء يمكن اكتشافها في CI.
6. **Build / Deploy:** عند طلب النشر، ابنِ الإصدار وتحقق من توقيعه، ثم انشر عبر قنوات المشروع المرخّصة وتحقق من إيصالات الهوست والبصمات.
7. **Real E2E:** لا تخلط بين نجاح Unit/CI أو APK ونجاح جهاز فعلي. المكالمة الحقيقية والرد وكتم الميكروفون وصوت MIX لا تُعد PASS إلا بدليل تجربة هاتف حقيقية.
8. **Fan-out/Fan-in:** نفّذ التحقيقات المستقلة بالتوازي حين تتوفر أدوات آمنة؛ نسّق التغييرات وتحقق من النتيجة الموحدة، واستخدم الموارد الأقل تكلفة عند كفايتها.
9. **Context / Fallback:** حمّل فقط ما يلزم، احتفظ بآخر حالة مؤكدة قبل أي ضغط سياق، وأعد محاولات مزود AI المحدودة بأمان مع البديل المعتمد إن وجد؛ لا تدّع عملًا مستمرًا بعد انقطاع الأدوات.
10. **Report:** قدّم حالة كل معيار بوضوح PASS / FAIL / UNVERIFIED، ونتائج الاختبارات، وCommit/Push، وBuild/Deploy، ودليل Host/Device E2E، والعوائق المتبقية. لا تعلن DONE إذا بقي بند مطلوب غير مثبت.

**Safety:** حافظ على أسرار الحسابات، عزل العملاء والشريحتين، وعدم استبدال هوية SIM أو تخطي التحقق؛ لا تنفذ حذفًا أو تغيير إنتاجيًا خطرًا خارج التفويض. **لا تعاود تشغيل جدولة التطوير كل ساعة التي أوقفها المالك.** لا تصنع توثيقًا مكررًا: README.md للعقد الكامل، CLAUDE.md للقاعدة التنفيذية والقرارات المعمارية المختصرة، والكود مصدر الحقيقة.

<!-- ONTRACK_MANDATORY_AGENT_RULES_END -->

> **Current execution source of truth (2026-11-03):** [Master Repair Roadmap](docs/plan/MASTER_REPAIR_ROADMAP_2026_11_03_AR.md) + [machine gap backlog](docs/plan/MASTER_REPAIR_BACKLOG_2026_11_03.json), reflecting all approved owner Q01–Q24, updated root deployment, failed CI gate, and remaining real device/billing checks. Do NOT restart the owner-stopped hourly development schedule. Older paths and OPEN Q references below are historical.

# OnTrack Voice — session entry

> **Authoritative 2026-11-01 owner contract:** [Approved master decisions](docs/discovery/OWNER_APPROVED_MASTER_DECISIONS_2026_11_01_AR.md). Q01–Q24 are answered, Q07/Q13 delegated options resolved. This overrides earlier pending-approval notes. Before production features, read the checkpoint and show evidence for CI, host, auth UI, device E2E separately. The signed hook payment path intentionally stages an unclaimed paid order, not identity by email; verified account claim/SSO is still required before activation.\n\n
> **Read first:** [Owner decisions + pending questions (2026-11-01)](docs/discovery/OWNER_DECISIONS_AND_OPEN_QUESTIONS_2026_11_01_AR.md). Newer owner-approved decisions override contradictory legacy status notes. The Admin login works for the owner; separate assistant authenticated E2E verification remains open.\n\n
Read [AGENTS.md](AGENTS.md), then [CURRENT_STATE_AR.md](docs/project/CURRENT_STATE_AR.md), before changing anything.

This repository includes existing PHP voice demo code, a private GitHub host-control worker, and imported Agent Builder specifications. It is not a completed telephone SaaS. Continue from the verified checkpoint and preserve working host connectivity.

Authoritative decisions: [DECISIONS_AR.md](docs/discovery/DECISIONS_AR.md). Current implementation roadmap: [IMPLEMENTATION_PLAN_AR.md](docs/plan/IMPLEMENTATION_PLAN_AR.md). Phone audio remains unverified; do not rebuild pairing/UI and present that as call success.

Workspace directive: application code only in `saas/`; host target `/home/appontrack/public_html/saas`. Memory/logs/prompts stay on GitHub under `docs/`, never in the host application. Read `docs/memory/CHECKPOINT.md` and `docs/execution/MASTER_EXECUTION_PROMPT_AR.md`; current scope is preparation before the owner starts implementation.
