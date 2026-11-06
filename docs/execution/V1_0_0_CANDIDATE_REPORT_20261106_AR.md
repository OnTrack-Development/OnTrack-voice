# تقرير تنفيذ مرشح OnTrack Voice v1.0.0 — 2026-11-06
## النسخ الاحتياطية
- `archive/old-version-main-20261106` نسخة ثابتة من `main`.
- `archive/old-version-active-20261106` نسخة ثابتة من `feat/voice-production-gap-20261103`.
- فرع تطوير النسخة الجديدة: `feat/voice-v1-minimal-console-20261106`; PR #60 **Draft** إلى فرع التطوير النشط، لا إلى الإنتاج.

## ما تغيّر بالكود
- `saas/console/_core/Bootstrap.php`: feature flag جديد في جدول منفصل دون حذف حسابات أو باقات.
- `saas/console/_core/Signup.php`: منع إصدار/اعتماد تسجيل ريسيلر عند إيقاف العرض.
- `saas/console/_core/Actions.php`: إجراء مع CSRF وRBAC وAudit لتفعيل/إخفاء الريسيلرز وحراسة إنشاء الحسابات والباقات.
- `saas/console/_core/Views.php`: مفتاح إظهار الريسيلرز، وإخفاء عناصرها من القوائم والحسابات والباقات وروابط التسجيل، وإخفاء قوائم بعض أدوات المرحلة التالية.
- `saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/MobileLinkActivity.kt`: فتح لوحة الموقع في المتصفح الخارجي (ACTION_VIEW).
- `saas/mobile/ota/ui.html` و`saas/mobile/audio-proof/overlay/app/src/main/assets/ontrack-ui.html`: واجهة هاتف مختصرة، wizard لأول ربط، حالة SIM/agent، تشغيل/إيقاف، صلاحيات، تحديثات؛ الاحتفاظ بمستقبل أوامر Native القديمة لتجنب انقطاع الوظائف. إزالة تعليقات HTML/JS/CSS العامة من هذه الملفات.
- `saas/mobile/ota/release.json`: مرشح Revision 48 ولم يُنشر.
- `docs/plan/V1_0_0_PHASE1_RELEASE_20261106_AR.md`: المواصفات ومعايير التسليم، الأجزاء المفتوحة.

## التحقق الفعلي
- تم رفع التغييرات إلى الفرع وفتح PR #60.
- GitHub Actions `42149822364` رجع FAILURE في **الأربع وظائف** PHP lint، Reseller، Mobile UI، Baseline. Connector يعيد `steps=[]` والـjob logs `BlobNotFound`؛ لا توجد خطوات/أوامر اختبار موثوقة في التقرير. **CI_PASS = لا؛ السبب غير مثبت؛ لا تنسبه للكود أو للبنية دون logs**.
- **BUILD APK: NOT RUN / NOT VERIFIED**.
- **HOST DEPLOY: NOT RUN**؛ لا إيصال من عامل الهوست.
- **DEVICE E2E: NOT RUN**؛ لم نغيّر pairing المالك.
- **Remote protected Audio Module: NOT IMPLEMENTED**؛ بقاء الشيفرة الحالية داخل التطبيق مؤقتًا يعني أن سر الصوت لم يحمَ بعد. إزالة تعليقات الواجهة ليست حل الحماية.

## ما بقي قبل إصدار v1.0.0
1. تشخيص CI بمخرجات قابلة للقراءة، ثم تصحيح الاختبارات وإعادة التشغيل.
2. اختبار خيار إخفاء الريسيلرز عبر SQLite/MySQL ودخول Platform Admin حقيقي، وتأكيد عدم تسرب من API أو روابط التسجيل.
3. اختبار Native build/التوقيع/OTA وبقاء pairing والمكالمات على Xiaomi، وإثبات أن فتح الموقع خارج WebView.
4. تثبيت wizard حقيقي على صلاحيات runtime لا pairing وحده، وتجربة أول تثبيت وتجربة جهاز مُفعل.
5. تنفيذ محرك الصوت منفصلًا بتحميل مصادق وموقع، واختبار reverse engineering، ثم صوت SIM Wi-Fi/4G، Hangup.
6. تنفيذ KB والـFlow الذكي وWHMCS/Laravel/Booking API والتقارير وفق خطة المرحلة الأولى.
7. استكمال جميع release gates: tests, host receipt, authenticated UI, actual Android calls, secure rollback.
**يُمنع دمج/نشر هذا المرشح قبل إغلاق الفحوص الحرجة.**

## تحديث تنفيذ نفس اليوم — طلب بناء النسخة الموحّدة
- أُضيف `.github/workflows/voice-v1-build-only.yml` لبناء APK موقّع بالهوية الأصلية والتأكد من التوقيع والمحتويات **دون رفعه للهاتف أو استدعاء عامل النشر**. تشغيل `42154020662` انتهى بفشل مثبت في Python `UnicodeDecodeError` أثناء `prepare.py` على Windows (cp1252)، بعد نجاح checkout/JDK/SDK/signing identity. أُصلح إعداد الترميز عبر `PYTHONUTF8=1` و`PYTHONIOENCODING=utf-8` في workflow. إعادة المحاولة: https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42154272984؛ لم يؤكد APK بعد.
- `MobileLinkActivity.kt` يفتح SaaS بـ Android ACTION_VIEW للمتصفح الخارجي، ويرسل حالة READ_PHONE_STATE للواجهة. واجهتا `ui.html` متطابقتان، wizard يبقى ظاهرًا عند انعدام ربط SIM أو فقد إذن الهاتف، وأزرار التشغيل تستدعي bridge القائم.
- `TenantVoiceSession.php` أزيل منه وصف سيناريو تجريبي في تعليمات الصوت، وأضيف حد جملتين لكل دور والانتظار لرد العميل. لا إثبات بعد لتحسن LTE.
- `prepare.py` لم يعد يشحن قائمة تفاصيل مسارات التنفيذ في `ONTRACK-MODIFICATIONS.txt`، مع الحفاظ على ترخيص Apache-2.0 والإسناد الإلزامي.
- `Views.php` لا يعرض حسابات ريسيلر أو عملاء تابعين له في جدول Platform عند إيقاف خيار الريسيلرز؛ بياناتهم محفوظة.
- تحقق مباشر من مصدر الفرع (دون build): نص واجهة OTA متطابق مع embedded، Script HTML يمر في JavaScript parser، فتح الموقع الخارجي موصول في Native، وإذن الهاتف ظاهر في state والواجهة. **هذه فحوص ثابتة فقط وليست CI أو Build أو تجربة مكالمة**.
- لم تُنفّذ بعد Knowledge Base المطلوبة، العمليات الحية WHMCS/Laravel/Booking، Flow الذكي، Hangup E2E، ولا نقل الـshell audio engine إلى وحدة مصرح بتحميلها. APK الحالي المحتمل **لا يحقق شرط حماية المحرك**.
- التعديل الحالي لا يُدمج في release branch ولا ينشر في host؛ PR #60 يبقى Draft. CI Validation فشل قبل إظهار خطوات قابلة للقراءة، فلا تُعلن PASS.

## 2026-11-06 — Full Windows build triage and artifact-quota workaround
- Windows build `42154272984` completed source overlay, JDK21/Android36, shell-server D8, UI contract, Gradle unit/daemon tests, `assembleRelease`, signed `com.ontrack.voiceproof` versionCode 48, manifest verification. The **APK build and signature PASS**.
- Binary: `OnTrack-Voice-0.3.25.apk`, **11,136,631 bytes**, SHA-256 `a3c16a37d27cb61e4fc49744dbee4d76b08e608312d9b87540d34a3177943aa1`, original signing identity verified. These facts are from run logs; user-file download is NOT confirmed.
- Final job **FAILED solely because `actions/upload-artifact@v4` returned `Artifact storage quota has been hit`** after signing. This is not an Android compilation failure.
- An attempted preserve-and-upload job `42156492079` safely failed because the self-hosted workspace did not retain `.build/release` after cleanup. No stale/unknown APK was published.
- Fixed `tools/publish_v1_candidate_release.py` for immutable per-run signed candidate private GitHub prereleases; fixed `.github/workflows/voice-v1-build-only.yml` to request `contents:write` and upload via GitHub Releases (not Actions artifacts), no host publishing.
- Replacement full build: https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42156616707; **QUEUED when recorded**, must verify its signed asset and digest before sharing a download. Not an actual v1.0.0 release. Current device session/pairing untouched.
