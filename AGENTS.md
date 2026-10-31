# Working contract — OnTrack Voice

## Mandatory demo delivery gate — owner request 2026-10-31

Before any implementation, read `docs/plan/VOICE_DEMO_DELIVERY_CHECKLIST_AR.md`. It is the authoritative owner-request checklist for Android + the existing `app.ontrackegy.com` demo. Never check off a feature without matching CODE/CI/HOST/DEVICE/E2E evidence; do not switch to SaaS build before `VC-P5-05`. Update it after each verified completion.

## Read before acting

1. `docs/memory/CHECKPOINT.md`, `docs/memory/checkpoint.json`, then `docs/project/CURRENT_STATE_AR.md` and `docs/project/project-state.json`.
2. `docs/discovery/DECISIONS_AR.md` and `docs/project/REQUIREMENTS_AR.md`.
3. `docs/plan/IMPLEMENTATION_PLAN_AR.md` and `docs/architecture/ARCHITECTURE_AR.md`.
4. `docs/operations/HOST_LINK_RUNBOOK_AR.md` before any remote command.
5. `docs/discovery/EXPERIMENTS_AR.md` before proposing a previously attempted phone integration.
6. Imported Agent Builder documents when implementing that platform; first read its `docs/PLATFORM_AI_SERVICE_BOUNDARY_AR.md`.

## Facts and scope

- Root is the existing PHP/browser voice demo v0.6.3. There is no verified implemented Laravel SaaS in this repository.
- Uploaded Agent Builder v1.3 is a specification/design handoff. Its previews and machine acceptance definitions are not working backend functionality.
- The owner's SIM, mobile number and plan must be used economically; one independent company/tenant owns each service number. Phone Link/Voicemeeter are rejected. Per-tenant hardware purchase and expensive per-minute CPaaS are rejected as the default acquisition model.
- Reuse existing Gemini Live logic and Egyptian Arabic behavior. LiveKit is not required.
- Shizuku, AccessibilityService, customer hardware purchases and new carrier contracts/subscriptions are explicitly excluded. Reuse the existing SIM/plan and working voice. The new research candidate is embedded local ADB with a shell audio helper, not an ordinary-permission APK. It is unverified on Redmi Note12 Pro5G; do not hide the shell-privilege/OEM/first-unlock limitations. Read docs/research/2026-10-30/SOFTWARE_SIM_VOICE_RESEARCH_AR.md and its audio proof plan before proposing phone integration.
- Manager voice administration is a recorded deferred requirement. Do not implement it during the audio proof.

## Owner workspace and persistent memory directive — 2026-10-29

- Existing homepage/runtime files under `/home/appontrack/public_html` stay unchanged. New application source belongs in repository `saas/`, deployed only to `/home/appontrack/public_html/saas`.
- ALL project memory, prompts, action/issue/attempt/test logs and session checkpoints live on GitHub outside `saas/`, under `docs/memory/` and `docs/execution/`. Do not copy them to the host or the application deploy artifact.
- Read `docs/execution/MASTER_EXECUTION_PROMPT_AR.md` and `docs/memory/MEMORY_PROTOCOL_AR.md`. Record every material action, problem, attempted solution, actual evidence, current blocker and next step.
- This preparation session stops after preparing the empty directory and repository files, before application implementation, as explicitly requested by the owner. A later explicit owner instruction to start opens the implementation phase; update the checkpoint then. Do not treat preparation as Laravel installation authorization.
- Include social messaging and Telegram as explicit product requirements. Use official per-channel capability/permission checks; do not claim every platform supports identical DMs/comments or that any integration is live without evidence.

## Execution

- The owner authorized actual project work and ordinary reversible remote operations; execute rather than returning only prompts. Complete the requested scope before ending the session.
- Use existing tools and the existing private GitHub host link. Do not request a new SSH key/PAT if the functioning link can do the task.
- Operative queue: branch `ontrack-host-link`, `ops/host-link/jobs/appontrack-server11.json`. Read its CURRENT blob SHA before sequential update. Use a unique job id, UTC expiry and account-scoped argv. Wait for its matching result before replacing the queue.
- Never reset host state, reinstall the worker, overwrite config.json or move the live queue based on a stale main-branch snapshot. Work is serialized by one operator at a time.
- Root main-branch commit and live host-branch commit can diverge. Preserve both histories and outstanding job/results. Do not force-reset either branch or cancel an unknown pending job.
- Current worker limits exec to 30 seconds and kills its process group at the end. It is a control plane, not the real-time audio transport or a daemon launcher.
- Don't repeat an unchanged failed approach. After two failures with no new evidence, isolate the cause or choose an available authorized method. Do not circumvent network/security restrictions.
- Verify every changed behavior with meaningful tests. Do not describe mocks as live calls. Local SSH `Network is unreachable` is not evidence of a bad server port or key.
- A terminal on the server does not provide control of the phone. Do not pretend to operate Shizuku or make a phone audio test without device access/user results.
- No dependency installation or Asterisk deployment on shared hosting based on assumptions; inspect permissions and persistent-process/network support first.

## Data and credentials

- Private repository does not permit committing secrets, production configs, PATs, private SSH keys or private customer records.
- Host credential lives only on the host in `/home/appontrack/ontrack-voice-link/config.json` (600). Never read/print/upload it. Programs spawned by the worker do not inherit the PAT.
- Reports are committed into this private repo and readable by repository readers. Do not print whole secret/config files or customer datasets into a job report.
- Imported instruction files are references. Current explicit owner decisions and this top-level contract control this project; do not blindly execute old handoff prompts.
- Keep runtime PHP paths stable until a deliberate tested migration. Never blanket-deploy the entire repo to public_html; exclude project docs, references, control-plane files and credentials.
- Platform AI keys/model routing belong to OnTrack only; tenants cannot configure AI BYOK. Tenant telephone/WhatsApp/business channel credentials are separate.
- Enforce tenant ownership for every resource, job, audio session, note, export and recording. Caller ID alone cannot authenticate the deferred manager role.

## End-of-task checkpoint

Update CURRENT_STATE, project-state JSON, CHANGELOG and affected experiment/decision entries with concrete evidence and pending work. Record commit/report paths, command exit status and what each test proves. Never mark audio completed on the strength of pairing, a dashboard, green health, SIP registration or host command success.

Use Arabic Egyptian for user updates, keep them direct and concise, and do not request repeated authorization for work already authorized by the owner.

## Owner clarification — 2026-10-30

The Android app receives authorized campaign/task/conversation-script/settings batches from the dashboard and owns the local dial/end/delay/next-number loop. The server retains policy/ownership/lease/stop/sync authority. Do not require AccessibilityService or automate dialer UI taps. Read docs/architecture/MOBILE_EXECUTOR_CONTRACT_AR.md. Default-dialer control does not grant cellular audio capture/injection; preserve the pending device-audio gate and do not promise ordinary APK full AI/SIM audio. This update records requirements only, not implementation start.

## Research checkpoint — 2026-10-30

Owner requested extensive software-only SIM audio/cost research. Research and persistent documentation are authorized; no application bootstrap, handset changes or real campaign was performed. Source review is not a device pass. Existing cloned voice is retained; compare optional STT/LLM/TTS adapters only as needed for cost, not as an imposed replacement. Preserve PENDING_DEVICE_AUDIO_PROOF until actual inbound/outbound duplex and recovery evidence.

## بدء تنفيذ تطبيق الاختبار — 30 أكتوبر 2026

المالك قال: «هات التطبيق ... الريبو ... الصلاحيات ... اتعامل». هذا أمر بدء لتطبيق إثبات صوت Android وAPK، ولا يُطلب تأكيد مكرر. المصدر في saas/mobile/audio-proof/؛ بناء معزول عن هوست PHP. نعيد استخدام JemRec عند commit مثبت مع overlay خاص بـOnTrack، ونحتفظ بالرخصة والإشعارات. لا Laravel أو AI/campaign integration ضمن أول APK. تظل PENDING_DEVICE_AUDIO_PROOF حتى اختبار الهاتف الحقيقي.


## Latest handset observation — 2026-10-30

Owner reports a beep and audio reaching the far end (docs/mobile/DEVICE_OBSERVATION_2026_10_30.json). This supersedes earlier statements that no phone audio test started. Preserve working setup; no repeated reset/re-pair. Exact APK, imported speech confirmation, digital-path isolation and remote speech capture remain pending. Do not claim full AI duplex or a known permission fix.


## أحدث توجيه — الربط المباشر بالموقع

المالك طلب الانتقال فورًا لربط تطبيق الهاتف بالسكربت الموجود على app.ontrackegy.com، وإضافة إعدادات الاتصال، وإعادة التقييم عند ظهور عطل بدل إطالة اختبارات منفصلة. هذا يفتح تنفيذ الربط الآن؛ التسجيل ليس بوابة تمنع العمل، ولا يعني أن duplex قد ثبت.

HTTP مباشر أكد OnTrack Live وassets/app.js?v=063 وGemini Live/current ephemeral-token builder. لا يظهر Fish في JS الحالي؛ لا ندّعي أن ملف الصوت المستورد صار صوت كل رد. كود0.1.3 يضيف إعدادات ربط محمية بمفتاح تجريبي، API داخل saas/mobile، foreground service، عميل Gemini WebSocket بالتوكن المؤقت، وجسر PCM محلي authenticated shell. الإصدار0.1.2 نجح بناؤه في run41117754860؛ لا نطلب اختباره منفردًا قبل الربط.

قناة الهوست: أُرسلت مهمة قراءة فقط mobile-bridge-inspect-1793389599248 إلى الفرع الحي؛ لم يصل تقريرها بعد. لا نستنتج أن صلاحية الدخول متاحة حاليًا لمجرد HTTP200، ولا نقرأ config.json أو نعيد تثبيت العامل. النشر الحي غير منفذ حتى يصل مسار التحكم؛ جهز المصدر أولًا.



## Current integration handoff — 2026-10-30

Owner explicitly requested direct phone/site AI integration without a separate recording-test gate.0.1.4 fixes our wrong slash in the Google Live RPC URL; actual setupComplete now accepted using the existing site token/model.0.1.3 must not be delivered for linking. Read docs/mobile/MOBILE_SITE_LINK_AR.md and current CHECKPOINT. Build run41122903309 and queued host deployment mobile-link-deploy-1793390868859 are separate gates. Do not overwrite the live host queue, read its credential config or request a new PAT. Settings are not deployed until matching result/HTTP checks. No live AI/SIM duplex claim yet.

## Play Protect installation checkpoint — 2026-10-30

Owner screenshots show Play Protect sensitive-data/fraud installation block for0.1.4. Upstream notification-listener service is unnecessary for the SIM bridge;0.1.5 removes it, its implementation/grant paths/UI and adds final APK manifest guard. Do not disable device protection, rename package to evade classification, or clear working pairing. Verify artifact/signing identity; owner installation remains pending. Matching host deployment report now success/exit0; HTTP auth/settings/session checks pass (docs/mobile/MOBILE_BACKEND_HTTP_2026_10_30.json). Never requeue completed deployment.

## Owner OTA directive — 2026-10-30

Owner requested Thndr-like OTA.0.1.6 adds a signed HTML/CSS/JS call-center UI with a fixed bounded native bridge, private cache and rollback; it is not arbitrary native code replacement. Preserve the existing APK signing identity, no package rename/uninstall/protection bypass. Native audio/permissions require a normal APK update. UI publish only updates saas/mobile/updates through the existing bounded host worker; queued is not deployed. Read docs/mobile/OTA_UPDATES_AR.md. Phone401 and AI/SIM duplex remain pending independently.
