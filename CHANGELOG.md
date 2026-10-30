## Android0.1.7 — fix local WebView request interception

Serve the exact current virtual HTTPS main document as200 from verified local bytes; block all other requests. Replace data-based loading, retain CSP and per-render bridge nonce. Native diagnostic/recovery buttons remain available on rendering failure. Add Android API34 emulator smoke test for actual rendering/native init before packaging. No reset of pairing or credentials.

## Android0.1.6 — signed UI OTA bootstrap

Download and verify local HTML/CSS/JS UI bundles, preserve offline baseline and previous bundle, reject incompatible/tampered/replayed updates, defer activation while a known call is active. Existing signed APK identity and settings retained. Native updates still require APK installation. Host OTA publication goes through existing serialized worker. Phone401 remains open.

## Android0.1.5 — install permission cleanup

Remove inherited notification listener, notification grants and UI; keep debugging notices visible. Final APK permission guard added. Play Protect acceptance and live handset call pending. Host deployment report now confirms success.

# سجل تغييرات المشروع

## 2026-10-30 — First owner-reported far-end audio

- Owner heard a beep and reports audio reaching the other call participant on Redmi Note12 Pro5G.
- Recorded partial device observation; exact APK/route/direction, speech content/digital isolation, remote capture and live AI remain pending.
- Preserve working phone setup. No new APK, host changes or claimed permission repair.

## 2026-10-30 — Android audio-proof APK 0.1.0

- Implemented Arabic one-call proof screen, local WAV/MP3/M4A import, inbound/outbound test controls and authenticated cancellation over pinned embedded-ADB upstream.
- GitHub Actions run41082229515 passed import/daemon tests, debug APK assembly and signature verification. Downloaded artifact SHA/provenance/manifest matched.
- APK is available as private build artifact; target handset installation/audio and continuous AI/campaign integration remain pending. Host PHP untouched.

## 2026-10-30 — Software-only SIM research

- Recorded explicit exclusions: Shizuku, Accessibility, customer hardware purchases and new carrier contracts/subscriptions.
- Added39primary-source register, pinned code review, Arabic research/proof plan and reproducible cost assumptions under docs/research/2026-10-30/.
- Embedded ADB/shell is a research candidate only; target-device duplex/outbound and unattended first-unlock recovery remain unverified.
- Updated current prompt, requirements, decisions, memory and acceptance gates; no application/host/phone changes or calls.

## 2026-10-30 — تطبيق الموبايل يدير دورة المكالمات دون Accessibility

- تسجيل قرار المالك باستقبال حزمة الحملة من الداشبورد وإدارة الاتصال والإنهاء والفاصل والرقم التالي في التطبيق.
- إضافة عقد التطبيق ومزامنة الطابور والتعافي وحدود الصلاحيات، وتحديث البرومبت والميموري والقبول.
- مراجعة Android الرسمية: التحكم بالمكالمة لا يثبت التقاط/حقن صوت SIM؛ لا APK منفذ أو تجربة صوت ناجحة ضمن هذا التحديث.

## 2026-10-29 — تجهيز مساحة SaaS وميموري GitHub قبل التنفيذ

- إنشاء /home/appontrack/public_html/saas فارغًا عبر الربط الحالي؛ بصمات29ملفًا من المصدر بقيت كما هي، ولا توثيق نُشر على الهوست.
- إضافة docs/execution/MASTER_EXECUTION_PROMPT_AR.md وميموري وقضايا وسجلات إجراءات ومحاولات واختبارات وcheckpoint وقوالب، خارج saas/.
- تثبيت تصحيح المالك: الميموري والبرومبت والسجلات علىGitHubفقط؛saasللمشروع.
- إضافة متطلب السوشيال وTelegram بعقود رسمية وقدرات منفصلة؛ لا حساب خارجي متصل في هذه المرحلة.
- لم يبدأ Laravel bootstrap أوDB/migration أوتثبيتdependencies أوتشغيلالتطبيق.

## 2026-10-29 — توحيد مرجع المشروع في الريبو الخاص

- إضافة نقطة دخول README وAGENTS/CLAUDE، ومتطلبات المنتج وقرارات المالك وتفاصيل المناقشة والخطة الحالية والمعمارية وحدود الإثبات.
- حفظ handoff Agent Builder كاملًا، ونسخة voicecall منقحة، وخطةv1.1تاريخية، ولقطات الإعداد، وجرد كل ملف وبصماته والاستثناءات.
- توثيق رفض Phone Link/Voicemeeter وشراء Gateway لكل عميل، وموقف Android/Shizuku غير المثبت وضرورة اختبار الجهاز.
- حفظ مصدر قناة التحكم وrunbook ونتائج الاختبارات والتقارير الحية؛ الفرع التشغيلي يبقى ontrack-host-link.
- توضيح أن تنظيم المرجع لا ينشر تعديلات على الموقع ولا يثبت مكالمة SIM.

## 2026-10-29 — التحكم في الهوست ثبت عمليًا

- تركيب العامل بواسطة المستخدم؛ bootstrap-probe بدأ `2026-10-28T23:42:06Z`.
- مهمة exec من هذه الجلسة نفذها Cron: verify-terminal-20261028234301727، success/exit0،1032ms.
- ثبتت كتابة/قراءة ملف وPHP8.1.34 وapp0.6.3 وCronمرةواحدة. تقريرها commit3f33123bc6f9f525dc9cecc4604858a4014fabeb.

## 2026-10-29 — إنشاء قناة GitHub البديلة

- main baseline:92795829331d686871b30d17be0d7cb91ee50b93.
- Worker/installer/tests:08363de8125d454e5df3180cab86f0113c947ba6؛11اختبارًا محليًا ناجحًا.
- Checksum-pinned bootstrap:7886478da65ae4d140a295dae70cedd8c0cc9bfa.
- اختيار GitHub pull worker بعد Network is unreachable في SSH المحلي، دون تغيير إعدادات SSH السيرفر.

## تاريخ المناقشة قبل ذلك

voicecallv0.6.3متصفح/PHP، handoffAgentBuilderv1.3مواصفات، خطةVoicev1.1كانت تقترحGateway/Asterisk/Node ثم تغير معيار الاختيار الاقتصادي. تاريخ التنفيذ السابق لـOnTrackAIPhone غير معاد اختباره في هذه الجلسة. التفاصيل في سجل المناقشة والتجارب.


## أحدث اختبار وإصلاح — 30 أكتوبر2026

المالك أكد أن الطرف البعيد سمع ملف الكلام كاملًا بوضوح، لكن قائمة التسجيلات فارغة والتقرير المحفوظ يظهر فارغًا عند رفعه. سماعه الشخصي للطرف البعيد لا يثبت التقاط التطبيق. وجدنا أن Main.stopRecording في المصدر المثبت يحذف تسجيلات outcome.test عمدًا؛ فات تعديل ذلك في نسخة OnTrack الأولى.

تحديث0.1.2 يحتفظ بالتسجيل كاملًا، ويسلّمه لمسار الحفظ المعتاد، ويضيف زر جمع التسجيل بعد نهاية المكالمة مع عدد الملفات ونتائج الحفظ. تصدير JSON يأخذ لقطة قبل فتح منتقي الملفات، ولا يتجاهل callback بسبب busy، ويقرأ الملف بعد الكتابة للتحقق من البايتات. السبب الدقيق لملف المالك الفارغ غير مثبت، ولا نفترض أنه نفس هذه الاحتمالات. بناء CI واختبار الهاتف مطلوبان. لا تمسح بيانات الإعداد العامل.



## Current integration handoff — 2026-10-30

Owner explicitly requested direct phone/site AI integration without a separate recording-test gate.0.1.4 fixes our wrong slash in the Google Live RPC URL; actual setupComplete now accepted using the existing site token/model.0.1.3 must not be delivered for linking. Read docs/mobile/MOBILE_SITE_LINK_AR.md and current CHECKPOINT. Build run41122903309 and queued host deployment mobile-link-deploy-1793390868859 are separate gates. Do not overwrite the live host queue, read its credential config or request a new PAT. Settings are not deployed until matching result/HTTP checks. No live AI/SIM duplex claim yet.
