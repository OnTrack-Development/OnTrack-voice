> **تحديث تنفيذ المنتج 2026-11-01 — PR #20:** اكتمل في الكود مسار `Draft → Test (محاكاة نصية مكتملة) → Publish (نسخة immutable) → Rollback` للإيجنت لكل حساب وموقع، مع واجهة عربية وSQLite/MySQL schema.v6 وعزل tenant واختبارات PHP/HTTP. CI على `99414328`: [SaaS #41346497610](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/41346497610) و[Flow #41346497630](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/41346497630) **SUCCESS**. لم يدمج أو ينشر؛ لا توجد مكالمة حية ولا ربط Gemini/SIM بالنسخة المنشورة. PR #19 Android يظل BLOCKED عند الجهاز/المراجعة؛ #9 SSO و#23 2FA مفتوحان. التالي مراجعة staging للـFlow، ثم ربط الشريحة بملف الإيجنت المنشور بعد اختبار Xiaomi، مع استمرار الباقات والدفع والتكاملات ضمن نطاق الإطلاق الكامل.\n\n> **تحديث المنتج 2026-11-01 — PR #19 / #20:** ربط Android لشريحتين بحسابين على PR #19 وصل إلى CI PASS لكنه **محجوب عن الدمج والنشر** حتى اختبار Xiaomi الفعلي ومراجعة أمنية. لاستغلال وقت انتظار الجهاز، PR #20 يضيف تنفيذًا نصيًا حتميًا لعقد Flow داخل واجهة Agent Builder مع فروع وأسئلة وعزل حسابات، وقد اجتاز PHP/SQLite + HTTP session/CSRF/tenant + JS syntax CI على SHA `920e21b2` (runs `41333279754` و`41333279662`)، لكنه **ليس تشغيل AI صوتي ولا Publish/Revision**؛ AUTH_UI على المتصفح الحقيقي غير مثبت. لا تُغلق مهمة Flow رقم 07 أو Device رقم 04 حتى تحقق أدلة الجهاز والصوت والنشر. راجع CHECKPOINT للـSHA والـCI والـreceipt.

# خطة التنفيذ الحالية — v1.2

> **أولوية التنفيذ بعد PR #18 (2026-11-01):** تم نشر خدمة إدارة اقتران Android متعدد العملاء والشريحتين في SaaS مع اختبارات CI وعزل توكنات الأجهزة. **المطلوب التالي هو تنفيذ الاقتران في APK والتحقق من استلام ملف الإيجنت للشريحة ثم تجربة مكالمة حقيقية؛ ليس بدء دورة لإصلاح مشكلة نشر جانبية.** الأدلة موجودة في بداية [CHECKPOINT](../memory/CHECKPOINT.md).\n\n
> **إغلاق دورة #8 — 2026-11-01:** قناة نشر PHP-only للهاتف مدمجة عبر PR #15/#16/#17. CI `41316597775` وpost-merge `41316660497` نجحا؛ Cron receipt `pilot-server-41316660497` = SUCCESS نشر 20 ملف PHP إلى `/saas/mobile/` مع حفظ auth وOTA وrollback. التحقق المستقل `pilot-verify-20261101143317` = SUCCESS؛ لا بناء APK، ولا اتصال حقيقي. **Android DEVICE_E2E وmulti-tenant SIM ما زالا OPEN.** [التفاصيل](../execution/2026-11-01_PILOT_PHP_HOST_PUBLISH_CYCLE_08_AR.md).\n\n
> **دورة #8 (2026-11-01):** PR #15 ينشئ قناة نشر مستقلة لـ `saas/mobile/server/**` بعد اختبارات PHP/DNC، بحزمة immutable وSHA256 وhost Cron receipt ونسخة احتياطية، دون بناء APK أو UI OTA. **حالة النشر على الهوست تظل OPEN حتى ظهور receipt مطابق**. مرجع التنفيذ: [تقرير الدورة](../execution/2026-11-01_PILOT_PHP_HOST_PUBLISH_CYCLE_08_AR.md).\n\n
> **قرار المالك المعتمد 2026-11-01 (يحكم ما تحته):** [المرجع الرئيسي للقرارات](../discovery/OWNER_APPROVED_MASTER_DECISIONS_2026_11_01_AR.md). لم تعد عبارة «لا تبدأ SaaS قبل انتهاء Demo» صالحة كحاجز؛ المالك طلب SaaS كامل وربط الأجهزة متعدد المستأجرين من البداية. جداول «PLANNED» القديمة لا تعني أن التطوير ممنوع. **كل بند لا يعتبر منجزًا إلا بإثبات مناسب.**\n\n## خط تنفيذ متجدد — الأولويات الملزمة بعد اعتماد جميع الأسئلة\n\n| ترتيب | عنوان المهمة | الحالة بداية 2026-11-01 | اختبار الإغلاق |\n|---|---|---|---|\n| 01 | حماية Identity/Order من ربط العملاء بالإيميل غير المثبت | CI_PASS + HOST_DEPLOYED (PR #7 / receipt saas-console-41280245756) | signed hook + replay + payment status + no cross-account membership, CI |\n| 02 | Claim/Invitation/SSO مؤكد الهوية من WHMCS/Laravel/OnTrack والتفعيل المؤجل | PARTIAL: staff invitations PR #8 CI_PASS + HOST_DEPLOYED; external SSO/order claim OPEN | unique subject mapping + nonce/jti replay + email collision + portal sign-in E2E |\n| 03 | Payment orchestrator متعدد العملات والفترات، Kashier TEST، Manual/Instapay/Wallet بمراجعة | OPEN | webhook verification + anti-fraud + reseller merchant assignment + refund/duplicate |\n| 04 | Multi-tenant Android device/SIM pairing QR/code/revoke وslot isolation | **PARTIAL:** PR #18 merged, CI_PASS, HOST_DEPLOYED for SaaS; Android native app pairing, QR, bound SIM audio, and DEVICE_E2E OPEN | حسابان وجهاز/شريحتان، رفض cross-account، ACK حقيقي على الجهاز |\n| 05 | Admin 2FA + تسجيل ذاتي ودعوات + Support policy per company | OPEN | login + 2FA + consent/audit + RBAC negative tests |\n| 06 | Trash / retention / permissions for all CRUD, preserve finance/audit | OPEN | restore vs purge, deny unprivileged, retention tests |\n| 07 | تشغيل Agent Flow revision داخل Gemini/Provider hub واختبار المكالمات | OPEN | real interrupted call resumes graph node and pinned revision |\n| 08 | Google Drive KB OAuth scoped + incremental secure sync | OPEN | tenant/site isolation + revoke/refresh + stale errors |\n| 09 | Campaigns/contacts/retries/DNC/reporting/recordings + full Android compatibility matrix | PARTIAL: PR #11 ingestion guard + PR #12 durable SaaS DNC ledger CI_PASS/HOST_DEPLOYED; PR #13 pilot DNC pre-dial CI_PASS/MERGED; PHP HOST_DEPLOYED verified via PR #17 / Cron pilot-server-41316660497; Android DEVICE_E2E unverified; tenant-bound Android pre-dial + verified re-consent + retention OPEN | inbound/outbound SIM E2E across devices, safe opt-in & receipts |\n| 10 | Reseller branding/custom domain + WHMCS/Laravel outbox and external SSO E2E | OPEN | DNS/TLS/cookie + source acknowledgements; no duplicated activation |\n| 11 | Full regression, browser/tablet/mobile UI, accessibility, finance, security, staged rollout | OPEN | separate CI, staging, AUTH_UI, Android DEVICE_E2E + approved go-live |\n\n**تحديث دورة 2026-11-01:** تنفيذ دعوات الموظفين بدل الربط الفوري بالبريد في PR #8 المدمج إلى main؛ يتم منح عضوية فقط بعد قبول المستخدم المسجل دخوله، مع انتهاء/إلغاء/عزل/اختبارات. **لا بريد دعوات مرسل ولا تسجيل جديد موثّق ولا SSO خارجي في هذه المرحلة**. CI #41284341013 نجح، ودمج PR #8 تم؛ Cron receipt saas-console-41284374964 نجاح، مع بقاء AUTH_UI غير مثبت.\n\n**تحديث دورة #4 (2026-11-01):** PR #11 مدمج، CI #41297247472 PASS، main CI #41297275347 PASS، publish #41297275398 SUCCESS، host receipt `saas-console-41297275398` SUCCESS (22 ملفًا، commit `49ecf6b9c212`)؛ حماية DNC على مستوى الحساب في API/booking/contact CRUD مع اختبارات HTTP signed وCLI action، دون إثبات pre-dial أو حذف/رفع حظر موثق. راجع [تفاصيل الدورة](../execution/2026-11-01_DNC_INGESTION_CYCLE_04_AR.md).\n\n**تحديث دورة #5 (2026-11-01):** PR #12 مدمج إلى main `d6a9a883153f`؛ ledger DNC دائم مفهرس لكل حساب مع backfill idempotent وحماية الحظر بعد حذف جهة الاتصال. PR CI `41301426937` PASS، main CI `41301462574` PASS، publish `41301462639` SUCCESS، host Cron receipt `saas-console-41301462639` SUCCESS (23 ملفًا، PHP 8.3، backup=true). **لا يعتبر منع اتصال فعلي** قبل ربط gate بعملية Dial على Android/الحملات. راجع [تقرير الدورة](../execution/2026-11-01_DNC_LEDGER_CYCLE_05_AR.md).\n\n**تحديث دورة #6 (2026-11-01):** PR #13 مدمج، CI #41306010120 و#41306044753 PASS، ويضيف حماية DNC دائمة خاصة بتجربة الهاتف، مع منع رفع الحظر بدون موافقة جديدة، وفحص قبل Dial تحت القفل واختبارات منع إعادة الاستيراد/الـLease. هذه ليست دمجًا مع SaaS متعدد الشركات؛ لا إثبات نشر على الهوست ولا اختبار هاتف حتى الآن. راجع [تقرير الدورة](../execution/2026-11-01_PILOT_DNC_PREDIAL_CYCLE_06_AR.md).\n\n**تحديث دورة #7 (2026-11-01):** اكتُشف تناقض خطير في إصدار Android: GitHub run `41306044730` SUCCESS لكن host UI OTA receipt FAILED بسبب revision collision، بينما native APK v22 نُشر فعليًا. **الإصلاح مدمج في PR #14** (main `aed13a68bc3f`): منع نشر native إلا بعد receipt UI ناجح ومطابق لنفس run/revision/hash؛ CI PR `41310627580` وmain `41310655875` كلاهما PASS، ولم يُشغّل بناء APK بسبب تعديل أداة النشر. **هذا لا يثبت نشر PHP pilot ولا تثبيت APK على الهاتف.** راجع [تقرير الدورة](../execution/2026-11-01_NATIVE_UI_RELEASE_GATE_CYCLE_07_AR.md).\n\n**تنفيذ كل ساعة:** [Runbook الاستكمال](../execution/AUTONOMOUS_HOURLY_RUNBOOK_AR.md). كل تشغيل يختار أصغر خطوة قابلة للاختبار من أول بند غير مكتمل، ويحدث checkpoint. لا يُعطل العمل على بنود مستقلة لأن اختبار هاتف حقيقي ينتظر المستخدم.\n\n
> **ملحق إلزامي أحدث (31 أكتوبر 2026):** [قائمة تسليم ديمو OnTrack Voice القابلة للتعليم](VOICE_DEMO_DELIVERY_CHECKLIST_AR.md). هذه القائمة هي المرجع عند التعارض مع أي توصيات تاريخية عن ترتيب المراحل؛ لا بدء SaaS قبل نجاح E2E للديمو.

## توجيه المالك الأحدث — 31 أكتوبر 2026: ترتيب الديمو قبل جودة 4G/5G الضعيف

- **المرحلة الأولى الآن:** إكمال سجل المكالمات Call ID/History في التطبيق والموقع، جهات الاتصال والاستيراد، الحملات والجدولة والاتصال الصادر، الـAgent Builder والسيناريوهات، التقارير والتسجيل والمراقبة، ثم توقيع E2E الأساسي على شبكة مستقرة.
- **المرحلة الثانية المؤجلة:** قياس/إصلاح تقطيع أو صمت الإيجنت على بيانات الهاتف عندما تكون الشبكة ضعيفة، reconnect/sessionResumption. معرّف التذكير `PHASE-2-NETWORK-STABILITY` في `docs/plan/VOICE_DEMO_DELIVERY_CHECKLIST_AR.md`.
- **عند نهاية الديمو** راجع مع المالك البنود المؤجلة قبل SaaS، ولا تعتبر تأجيلها نجاحًا أو تصريحًا بجاهزية الإنتاج. هذه الأولوية تُقدَّم على أي قسم قديم في الخطة يقترح استكمال اختبارات الشبكة أولًا.

## أحدث قرار بحثي — 30 أكتوبر 2026

Shizuku وAccessibility وأي شراء هاردوير للعميل وأي عقد/اشتراك اتصالات جديد مستبعدة صراحة. الشريحة والباقة والصوت الموجودون هم الأساس. المرشح الجديد **embedded ADB داخل APK مع مساعد shell**؛ اقتراح مشروط لا يثبت صوت هاتفنا، ولا يزيل قيود المصنع أو الإعداد الأول. بحث المصدر وجد مسار تحية في JemRec، لكنه لا يثبت حوار AI مستمرًا أو استعادة قبل أول unlock.

راجع [التقرير الموسع](../research/2026-10-30/SOFTWARE_SIM_VOICE_RESEARCH_AR.md) و[خطة إثبات الصوت](../research/2026-10-30/AUDIO_PROOF_PLAN_AR.md). الحالة PENDING_DEVICE_AUDIO_PROOF؛ لا تنفيذ تطبيق/هوست أو مكالمات في جلسة البحث.

**29 أكتوبر 2026 — تعلو على الخطة التاريخية v1.1 في اختيار ناقل الشريحة.** هذه خطة، وليست ادعاء بأن المنصة أو جسر المكالمات منفذان.

## مساحة العمل ونقطة البدء الحالية

أحدث توجيه للمالك: التطبيق في saas/ بالريبو و /home/appontrack/public_html/saas على الهوست، والرئيسية كما هي. الميموري والخطة والسجلات والبرومبت على GitHub فقط خارج saas. مرحلة التجهيز اكتملت بمجلد فارغ وتقرير حماية المصدر؛ التنفيذ يبدأ بأمر المالك التالي. اقرأ docs/memory/CHECKPOINT.md والبرومبت الرئيسي قبل العمل.

نطاق المنصة يشمل أيضًا القنوات الاجتماعية وTelegram وفق docs/project/SOCIAL_CHANNELS_AR.md، بعقود adapters وقدرات وصلاحيات واختبارات منفصلة لكل قناة. يمكن تطوير الوحدات المستقلة بعد أمر البدء دون ادعاء أن صوت SIM ثبت.

## ترتيب التنفيذ ومعيار الإغلاق

| المرحلة | العمل | الحالة الحالية | دليل الإغلاق المطلوب |
|---|---|---|---|
| P0: الوصول والمصدر | حفظ الملفات، قناة إدارة هوست، توثيق القرارات والتجارب | قناة الإدارة مثبتة؛ تنظيم المرجع في هذا التحديث | تقرير exec من الهوست + repo منظم بجرد وروابط وتاريخ. |
| P1: صوت الجهاز | بيانات الجهاز/الإصدارات، embedded ADB readiness، ملف صوت A/B، logs لاتجاهي الصوت | **المرحلة التالية؛ لم يثبت** | capture/injection عبر شبكة المحمول دون صدى سماعة، مع نتيجة على الجهاز المستهدف. |
| P2: أول حوار AI/SIM | bridge مناسب، current Gemini، وارد وصادر تجريبيان | محجوب بنجاح P1 | الطرفان يسمعان كلامًا حقيقيًا بالمصرية، answer/hangup مسجلان وCaller ID صحيح. |
| P3: دمج tenant | ربط رقم الشركة بالإيجنت/المعرفة/المهمة، تخزين المحاولات | PLANNED | شركتان بأرقام/مسارات مستقلة، اختبارات منع تسرب الصوت والبيانات. |
| P4: الموظف والقوائم | CSV/XLSX/XLS/TXT/paste، مهام متعددة، loop متتابع وdelay | PLANNED | قائمة اختبار تعمل تلقائيًا؛ الفاصل يبدأ من نهاية الاتصال، لا تكرار عند إعادة التشغيل. |
| P5: الدوام والاستئناف | أيام/فترات/منطقة/عطلات/إجازات/إيقاف/استكمال/retry | PLANNED | لا بدء خارج الوقت؛ إعادة فحص الأهلية لحظة dial؛ حالات unknown لا تعاد عميانيًا. |
| P6: الملاحظات والتقارير | live notes/transcript، business outcomes، follow-up | PLANNED | تقرير مطابق للمكالمة؛ فشل التقرير لا يعيد الاتصال ولا يختلق بيانات. |
| P7: تبسيط الربط والتشغيل | companion login/QR، دعم الأجهزة، quotas، monitoring/load | مشروط بثبات P2 | setup اقتصادي قابل للتكرار ومصفوفة دعم وإعادة تشغيل، واختبار سعة موثق. |
| P8: إدارة المدير بالصوت | رقم/ملف/تحقق/أوامر وأذونات | مؤجل صراحة | تحقق يتجاوز Caller ID، أفعال مسموحة ومراجعة مستقلة للأفعال الحساسة. |

## P0 — ما أُنجز

- فحص الملفات المرفوعة والتمييز بين specification وruntime code.
- تثبيت GitHub pull worker على الهوست وتأكيد Cron + exec + ملف كتابة/قراءة بتقرير حي.
- حفظ source code للربط واختباراته، ومخرجات الإثبات الخام.
- جمع المتطلبات والقرارات والبحث والخطة السابقة والمرفقات في تقسيم واضح؛ عدم نقل secrets.
- لا root ولا تغيير runtime التطبيق أو نشر Laravel ضمن هذه المرحلة.

## P1 — خطة الاختبار الأقرب

ابدأ اختبار ملف صوتنا الجاهز والتقاط الطرف البعيد وفق `docs/research/2026-10-30/AUDIO_PROOF_PLAN_AR.md`. إجراء Shizuku القديم محفوظ تاريخيًا ومستبعد من التنفيذ. لا نطلب من المستخدم إعادة token أو SSH؛ الوصول للسيرفر أصبح متاحًا بالفعل.

نجاح echo مؤشر أولي فقط. نسجل طراز الجهاز/النظام/التطبيقات/المشغل واتجاهات الصوت وON/OFF وإجابة/إنهاء، ثم ننتقل لاختبار ربط AI. فشل الجهاز يحتاج logs وسبب واضح، ولا يعالج بتصميم داشبورد جديدة.

## P2 — قرار الخدمة بعد الدليل

1. حدد adapter الهاتف الذي ثبت على الجهاز. عند الحاجة SIP، جهز endpoint مع credentials خاصة بالقناة على خدمة صوت تسمح بالشبكة والعمليات الطويلة.
2. افحص بيئة التشغيل بالفعل: PHP web/CLI، إمكانية Python/Node/SDK طويل التشغيل، منافذ الصوت/NAT/TLS، وقيود الاستضافة. الحساب المشترك الحالي مثبت للإدارة فقط.
3. أعد استخدام current mission/knowledge/session builder؛ نقل الصوت يأتي من المكالمة ويعود لها، لا ميكروفون صفحة المشغّل.
4. مكالمة واردة على SIM واحدة ثم صادرة من نفس الشريحة لرقم اختبار مصرح به، مع voice timestamps وإيقاف الجلسة عند hangup.
5. سجل المقاطعة والصمت وفشل AI/الشبكة وسلوك إعادة الاتصال. لا تستعمل العملاء الحقيقيين لإثبات أولي غير مستقر.

لا تفرض Asterisk/Node أو شراء Gateway أو مزود دقائق بديلًا عن فحص المشكلة. لو ثبت أن الهاتف/النظام لا يتيحان المسار المطلوب، يعرض القرار بالدليل والتكلفة والقيود ويختار المالك بديلًا.

## المنتج بعد إثبات النقل

المنصة modular monolith وفق handoff: Identity/Tenancy/AgentStudio/ProviderHub/Knowledge/Conversations/Channels/Voice/Operations/Commercial. قاعدة MySQL، وخدمات queue/scheduler مناسبة للبيئة المتحققة. تبقى خدمة الصوت مستقلة عن HTTP requests وعن قناة host-link.

ابدأ vertical slice لشركة ورقم ومهمة وقائمة صغيرة ثم أضف tenants والجداول. كل مرحلة تتضمن backend وواجهة وحفظ حقيقي واختبارات، مع حالة واضحة للميزات غير المتصلة. لا يتم تسليم preview على أنه SaaS.

## قرارات ممنوع نسيانها

- كل tenant له رقم موبايله وخطه/باقته، لا رقم ثابت بديل غير معتمد.
- sequential calls وdelay قابل للضبط، مع سياسة مستقلة لإعادة المحاولة.
- كل صيغ الاستيراد وknowledge/multiple tasks والدوام/العطلات والملاحظات أثناء الحديث.
- AI credentials/models خاصة بـOnTrack؛ tenant channel settings ليست AI BYOK.
- نقطة المدير محفوظة مؤجلة.
- QR onboarding يأتي بعد إثبات النقل؛ لا يمنح صلاحيات Android ولا يحل تلقائيًا أول unlock أو إعدادات المصنع.

## شروط القبول

مصفوفة القبول القابلة للقراءة آليًا في `docs/project/acceptance-matrix.json`. لا تتحول أي حالة PENDING إلى VERIFIED بلا رابط تقرير/نتيجة مناسبة لمستوى الادعاء. التحقق من هوست الحساب لا يغير حالة P1 أو P2.

## تحديث30أكتوبر2026 — منفذ Android

تطبيق الموبايل، بعد استلام حزمة الحملة من الداشبورد، يدير طلب الأرقام والإنهاء والفاصل والتقدم في الطابور محليًا. السيرفر يدير السياسات والملكية والإيقاف والتزامن والتقارير. لا AccessibilityService. العقد التفصيلي في docs/architecture/MOBILE_EXECUTOR_CONTRACT_AR.md؛ لا يتجاوز هذا التعديل بوابة إثبات الصوت ولا يجيز إعلان نجاح تطبيق Android عادي في نقل صوت SIM.

## 2026-11-01 — دورة #3: إصلاح API للأرشفة (PR #10)

- **مكتمل بالكود والـCI:** `main` commit `04e5851ed960a82fff7f6b3f7655cccfed7c33f6` يمنع مفاتيح API من عرض المواقع والإيجنتات المؤرشفة أو قراءة معرفتها، ويمنع إنشاء Lead على موقع مؤرشف. HTTP regression اختبر API الحقيقي محليًا، مع استمرار وظائف الموقع النشط وعزل المستأجرين. CI PR `41292501176` وmain `41292532680` ناجحان.
- **HOST_DEPLOYED مؤكد:** Workflow `41292532708` نجح، وCron receipt `ops/host-link/results/appontrack-server11/saas-console-41292532708.json` = success، exit 0، المصدر `04e5851ed960`، **21 ملفًا**، PHP 8.3، seed already_seeded، نسخة احتياطية محفوظة، انتهى **2026-11-01 09:19:04 UTC**. لا يوجد AUTH_UI أو DEVICE_E2E أو PAYMENT_E2E. هذه الخطوة لا تنفذ Trash/Restore الشامل ولا تغير قرار Q04.
- **التوازي:** PR #9 للـSSO مفتوح ولم تُعدل ملفاته؛ يجب تقييم staging security قبل دمجه.
- تفاصيل الإصلاح: `docs/execution/2026-11-01_API_ARCHIVE_CYCLE_03_AR.md`.
