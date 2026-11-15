# تقرير الفحص الأمني النهائي المعتمد - OnTrack AI Voice Assistant

> المصدر: سكل Cloudflare security-audit (المراحل الست كاملة). فحص ساكن فقط - بدون تنفيذ كود، بدون لمس credentials او سيرفرات حية.
> الهدف: d:/agent/OnTrack-voice-main. مجلد VS/ و VS-downloads مستبعدان الزاميا (قرار المالك 2026-11-08).
> الحالة: مكتمل ومعتمد. دمج نتايج 101 وكيل (0 اخطاء) + Adversarial Verify مستقل + فلترة مكرر.

## منهجية الفحص

- 5 وكلاء استطلاع (Recon) + 12 صياد امان + 4 صيادين اضافيين لفجوات Critic + 2 مراجع تغطية + 78 وكيل تحقق (refute/confirm) = 101 وكيل، 2.9M token، 0 اخطاء.
- كل مرشح تاكد عبر وكيلين مستقلين معاديين (refute + confirm)؛ الاغلبية تحدد الحكم النهائي: تاكيد+بدون رفض = confirmed، رفض+بدون تاكيد = rejected، غير ذلك = needs_validation.
- لا يوجد OS-enforced sandbox على النظام؛ كل النتايج ساكنة من الكود، والتحقق التنفيذي محول الى needs_validation.
- VS/ و VS-downloads خارج النطاق تماما (قرار مالك ملزم).

## ملخص الوضع الامني

مجموع الثغرات: 29 مؤكدة (confirmed: {'high': 8, 'medium': 13, 'low': 8})، 10 تحتاج تحقق (needs_validation)، 0 مرفوضة (rejected).

محاور الخطر الرئيسية: (1) توكن صيانة نصي مزروع في الريبو يفتح نقاط كتابة/تشخيص ومزامير Gemini؛ (2) اصدار توكنات Gemini بلا مصادقة وبدون rate limit (استنزاف حساب/تكلفة)؛ (3) سر جهاز مشترك وحيد (SHA-256) كاصل ثقة لكل المحمول؛ (4) تصعيد صلاحيات وضع الدعم بالكونسول؛ (5) تنفيذ اوامر اعتباطية عبر host-link exec (High).

## الثغرات المؤكدة (Confirmed)

| # | الشدة | العنوان | الحد (Boundary) |
|---|---|---|---|
| 1 | HIGH | Release gate pins a fixed historical run/branch and asserts a version lineage that contradicts  | publish gate |
| 2 | HIGH | Static maintenance token committed in repo gates live config rewrite and outbound diagnostic pr | web |
| 3 | HIGH | Plaintext maintenance token committed at web root and reused as the static gate for config-writ | repository/deploy artifact |
| 4 | HIGH | Support-mode session elevates the low-privilege 'support' role to 'owner' on any account (inclu | POST /saas/console/ -> act() after login+CSRF |
| 5 | HIGH | Static maintenance token committed in the repository authenticates a write-capable endpoint (ov | root-credential |
| 6 | HIGH | Committed plaintext maintenance token equals static auth for diag/setup_demo | repo root |
| 7 | HIGH | Static-token-gated endpoint overwrites server Gemini key and model config | api/setup_demo.php |
| 8 | HIGH | Host-link exec action is arbitrary command execution as appontrack | ops/host-link/worker.py |
| 9 | MEDIUM | Self-hosted owner runner performs SSH host deploy and writes a repo proof file with contents:wr | workflow job |
| 10 | MEDIUM | Unauthenticated, unthrottled Gemini ephemeral-token mint enables AI cost/quota abuse | browser |
| 11 | MEDIUM | omnira_api_tester.php is a committed third-party API-key pass-through proxy intended for deleti | web |
| 12 | MEDIUM | rotate_secret mints otv_ API keys with 64 hex chars that the api/v1 format gate (60 hex) reject | owner/admin console action, CSRF+RBAC-gated |
| 13 | MEDIUM | Webhook HMAC endpoint omits the HTTPS-enforcement that device/v1 enforces | saas/console |
| 14 | MEDIUM | A stale campaign lease permanently blocks every future campaign claim on the pilot executor | POST action=claim with device Bearer key invo |
| 15 | MEDIUM | Single shared SHA-256 secret is the sole root of trust for both the device API plane and the ad | mobile_authorized(): verifier for both device |
| 16 | MEDIUM | Final AndroidManifest.xml (exported-component closure) is not source-verifiable; it is generate | saas/mobile/audio-proof |
| 17 | MEDIUM | Unhandled job validation/processing errors crash the worker every Cron minute (deployment contr | tick() queue fetch |
| 18 | MEDIUM | Static-token-gated diagnostics proxy outbound requests and reflects responses | api/diag.php |
| 19 | MEDIUM | Leftover operator proxy file relays third-party API with weak header gate | omnira_api_tester.php |
| 20 | MEDIUM | Unauthenticated Gemini ephemeral-token mint with no rate limit | api/live_session.php |
| 21 | MEDIUM | Session cookie Secure flag depends on request-time HTTPS header | saas/console/_core/Bootstrap.php |
| 22 | LOW | reveal_secret / rotate_secret run unlimited password_verify with no throttle or lockout | act() POST dispatcher, case 'reveal_secret' |
| 23 | LOW | Vault master key is a single 32-byte file with no rotation, backup, or re-seal tooling | secret_key() |
| 24 | LOW | Standalone third-party API tester shipped in the public web root with only a spoofable header g | public HTTP POST |
| 25 | LOW | Pilot attempts/DNC stores grow without bound and hard-fail every campaign and ops read past a 3 | GET reads attempts before any try/catch |
| 26 | LOW | UI OTA host installer (deploy.py) does not verify the RSA signature; it trusts a caller-supplie | deploy(encoded, expected): UI OTA host instal |
| 27 | LOW | Unauthenticated ephemeral-token mint with no rate limit (Gemini quota abuse / availability) | endpoint |
| 28 | LOW | Unauthenticated status and env-probe debug pages disclose configuration | test.php |
| 29 | LOW | Console login throttle tracked in session, not per-IP | saas/console/index.php |

## تحتاج تحقق (Needs Validation)

| # | العنوان | المانع |
|---|---|---|
| 1 | Mobile device API enforces only the bearer secret, not application-layer TL | needs_validation: whether the live ingress forwards or terminates plaintext HTTP to / |
| 2 | Shared admin/device key digest is committed in the repository while the dep | Cannot read the value (secret discipline); cannot observe whether the live host diges |
| 3 | hooks/index.php (and api/v1) do not enforce HTTPS in-endpoint; transport se | needs_validation: whether the live deployment serves /saas/console/hooks or /saas/con |
| 4 | Suspend is not a uniform tenant revocation: API keys and integration tokens | Decisive validation requires execution or a deployment observation unavailable in thi |
| 5 | DNC ledger is account-scoped and append-only; survives contact delete and a | Decisive validation requires execution or a deployment observation unavailable in thi |
| 6 | No hard account/tenant deletion exists, so cascade-failure and orphan-resid | Decisive validation requires execution or a deployment observation unavailable in thi |
| 7 | omnira_api_tester.php gated only by a spoofable X-Requested-With header; fi | needs_validation: confirm whether omnira_api_tester.php is present under public_html  |
| 8 | Privileged local ADB/shell/PCM bridge and ADB-over-WiFi (port 5555) exposur | Upstream local-bridge classes not in checkout; cannot confirm bind address or auth fr |
| 9 | No cross-instance lease: two installed workers execute the same single job  | Deployment topology: confirm only ONE worker install/crontab exists on the appontrack |
| 10 | Single shared device key is sole mobile trust root; git-tracked digest, no  | needs_validation: confirm the live host's /home/appontrack/public_html/saas/mobile/se |

## ملاحظات تقوية (Hardening)

- راجعت التغطية (Critic) اوجد فجوات ادت لتشغيل 4 صيادين اضافيين؛ الفحص جزئي بالضرورة ولا يمثل كل الاحتمالات الممكنة.
- المصادق الرسمي validate-findings.cjs لا يعمل على ويندوز (يفرض O_NOFOLLOW/O_NONBLOCK)؛ تم التحقق من الـ schema بسكربت مكافئ بلغة بايثون.
- كل النتايج ساكنة؛ لم يتم تنفيذ اي كود للتارجت ولا اختبار اي سيرفر حي.

## تغطية الفحص

- 39 مرشحا مرشحا (مدمج من اصول التكرار) غطاها 101 وكيل عبر الـ 6 مراحل.
- خارج النطاق: VS/ و VS-downloads (قرار مالك).

*تقرير مشتق آليا من findings.json النهائي المعتمد.*