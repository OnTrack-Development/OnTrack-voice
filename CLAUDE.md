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

## OnTrack Voice — current project-specific rules (2026-11-04)

- **Latest owner authentication override (2026-11-04):** Website login uses account email + password ONLY, including platform admins, resellers, customers and signed-SSO entry. Never require Google Authenticator/TOTP/6-digit 2FA enrollment or code on `app.ontrackegy.com/saas/console`. This supersedes earlier Q02 mandatory-admin-2FA wording. Keep CSRF, password validation, session rotation, login throttling, SSO signed assertion/consent and role checks. **Do not remove SIM pairing QR, API tokens or first-time email ownership verification; those are NOT website login 2FA.** Source of truth: `saas/console/index.php`, `_core/Views.php`, `_core/Signup.php`, `tools/test_admin_mfa.php`.

- **Owner stage:** internal/demo development and owner-controlled live tests only. No real paying customer accounts. Production infrastructure use is for verified internal testing, not proof of commercial release.
- **Approved requirements:** [Q01–Q24 owner-approved decisions](docs/discovery/OWNER_APPROVED_MASTER_DECISIONS_2026_11_01_AR.md) are authoritative. Older documents calling these requirements OPEN or awaiting permission are obsolete. For remaining genuine gaps, check [master repair roadmap](docs/plan/MASTER_REPAIR_ROADMAP_2026_11_03_AR.md) against actual source and evidence.
- **Read the actual implementation first:** Android tenant service, overlay and PCM in saas/mobile/audio-proof/overlay; version/OTA in saas/mobile/ota; account-scoped console and published Flow in saas/console; remote worker in ops/host-link. Preserve existing customer/SIM binding, secrets, auth, signing and source provenance.
- **Call-path regression:** the signed Android 0.3.19 / native code 42 APK and UI OTA revision 41 had verified host receipts on 2026-11-04. The owner's phone screenshot showed connected carrier call but no proven AI takeover or microphone mute, with misleading "you took over" overlay. Therefore **device call audio, auto-answer, mute and MIX remain FAIL/UNVERIFIED**. The 0.3.20 call-state/PCM candidate was merged by PR #38 into feat/voice-production-gap-20261103 (merge 7f80ef17); source-contract CI initially failed due an obsolete readiness assertion and is being corrected. Android Kotlin/R8/signature, host deployment and physical device E2E must be checked independently before PASS.
- **2026-11-04 LTE audio regression:** owner confirmed SIM calls ARE auto-answered on mobile data, but audio is inaudible, while Wi-Fi audio works. This is not an incoming-call detection failure. In MobileLinkService.kt, failed Google Live/PCM startup previously latched answeredByAgent, preventing retry after brief VoLTE/data interruption. Fix under review in Android 0.3.22 / code45: bounded, internet-validated reconnection for the same call, never reissue shell answer; observable PCM input, generated Google audio, and bytes forwarded to call shell separately in MobileLiveCall.kt and floating overlay. **The VoLTE modem routing of injected audio is NOT proven by socket writes or CI**; a real device test must distinguish network suspension from capture/uplink routing, and the existing Wi-Fi path must not regress.\n- **Mobile safety invariant:** carrier OFFHOOK is not proof of live AI PCM; a transport failure, unpaired SIM, ambiguous dual SIM or disabled agent must not be represented as explicit human takeover. Only a real user handoff action can record that event. Keep SIM-specific authorization and do not mix tenant audio. Native floating overlay changes require signed APK (not HTML-only OTA).
- **Evidence gates:** treat Android unit tests, Kotlin compile/R8, APK signature, signed UI receipt, native APK host receipt and owner-device SIM-call E2E as distinct outcomes. Never label the last PASS using the previous five alone.
- **Deployment scope:** inspect the current host path/allowlist rather than relying on historic paths. Do not place private working docs, credentials or GitHub job logs in public_html. Do not restart owner-cancelled hourly autonomous development schedules.
- **Recovery discipline:** retain exact failing CI step, source SHA, test output and next action; retry only on a diagnosed fix. Continue from current repository head and existing authorized connection instead of requesting repeat API secrets.

## Mandatory final report for every development task

Give **task / changed paths / tests (command + PASS, FAIL, or NOT RUN) / regression evidence / commit SHA + branch / Build status / Deploy + host receipt / physical E2E result / remaining blockers**. An unresolved blocker is a reported partial result, never DONE.
