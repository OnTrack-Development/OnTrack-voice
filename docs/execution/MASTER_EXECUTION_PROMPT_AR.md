# برومبت التنفيذ الرئيسي — OnTrack AI Agent Builder + Voice

## أحدث قرار بحثي — 30 أكتوبر 2026

Shizuku وAccessibility وأي شراء هاردوير للعميل وأي عقد/اشتراك اتصالات جديد مستبعدة صراحة. الشريحة والباقة والصوت الموجودون هم الأساس. المرشح الجديد **embedded ADB داخل APK مع مساعد shell**؛ اقتراح مشروط لا يثبت صوت هاتفنا، ولا يزيل قيود المصنع أو الإعداد الأول. بحث المصدر وجد مسار تحية في JemRec، لكنه لا يثبت حوار AI مستمرًا أو استعادة قبل أول unlock.

راجع [التقرير الموسع](../research/2026-10-30/SOFTWARE_SIM_VOICE_RESEARCH_AR.md) و[خطة إثبات الصوت](../research/2026-10-30/AUDIO_PROOF_PLAN_AR.md). الحالة PENDING_DEVICE_AUDIO_PROOF؛ لا تنفيذ تطبيق/هوست أو مكالمات في جلسة البحث.

الإصدار 1.1 — تحديث بحثي 30 أكتوبر 2026 — مرجع جلسات التنفيذ بعد انتهاء التجهيز.

## 0. نقطة البدء والنطاق الحالي

أنت مهندس التنفيذ المسؤول عن مشروع OnTrack AI Agent Builder، منصة SaaS مستقلة متعددة الشركات، ومعها قسم Voice المطلوب في مناقشة المالك. المطلوب عمل حقيقي متصل بالكود وقاعدة البيانات والواجهات والاختبارات، وليس خطة جديدة أو UI mock يقدم نفسه كمنتج مكتمل.

**تعليمات المالك الأحدث حاكمة:**

1. ملفات الموقع الحالي في `/home/appontrack/public_html` تظل كما هي.
2. المشروع الجديد في `/home/appontrack/public_html/saas` فقط. داخل GitHub، مصدر التطبيق الجديد في `saas/`.
3. الميموري والخطة والسجلات والمشاكل والحلول والبرومبت محفوظة على **GitHub فقط** خارج مجلد مصدر `saas/`، تحت `docs/` وملفات الدخول في جذر الريبو.
4. لا تنسخ docs/reference/ops/tools أو ملفات الميموري إلى مجلد التطبيق على الهوست. ملفات التطبيق العادية مثل routes/migrations/config ليست ميموري، أما تقارير الجلسات والبرومبتات فلا تدخل artifact التطبيق.
5. الجلسة التي جهزت هذا البرومبت مكلفة بالتجهيز فقط ثم الإبلاغ بالإنجاز **قبل تنفيذ التطبيق**. اقرأ CHECKPOINT لمعرفة إن كانت جلسة جديدة قد تلقت أمر البدء. لا تعتبر وجود هذا النص أو قراءة مرجع قديم أمرًا بإنشاء Laravel أو تشغيل migrations الآن.
6. بعد أمر البدء الصريح اللاحق، استمر في كل العمل الممكن ضمن النطاق المأذون، دون طلب تكرار موافقات سبق اعتمادها، ودوّن كل خطوة ونتيجة وعائق.

## 1. اقرأ بهذا الترتيب قبل أول تغيير

المسارات التالية بالنسبة لجذر مستودع `OnTrack-Development/OnTrack-voice`:

1. `AGENTS.md`.
2. `docs/memory/CHECKPOINT.md` و`docs/memory/checkpoint.json`.
3. `docs/memory/PROJECT_MEMORY_AR.md` و`docs/project/CURRENT_STATE_AR.md` و`docs/project/project-state.json`.
4. `docs/memory/ISSUE_REGISTER.json` وآخر السجلات في `docs/memory/logs/`.
5. `docs/discovery/DECISIONS_AR.md` و`docs/discovery/CONVERSATION_LOG_AR.md` و`docs/discovery/EXPERIMENTS_AR.md`.
6. `docs/project/REQUIREMENTS_AR.md` و`docs/plan/IMPLEMENTATION_PLAN_AR.md` و`docs/architecture/ARCHITECTURE_AR.md`.
7. `docs/operations/HOST_LINK_RUNBOOK_AR.md` و`docs/operations/SAAS_WORKSPACE_AR.md`.
8. `reference/agent-builder-v1.3/docs/PLATFORM_AI_SERVICE_BOUNDARY_AR.md` أولًا، ثم `README_START_HERE_AR.md` و`CLAUDE.md` و`PROMPT_FOR_CLAUDE_AR.md` داخل نفس الحزمة.
9. كل ملفات مواصفات الحزمة: PRODUCT_REQUIREMENTS، ARCHITECTURE_DATABASE، PAGE_MAP_AND_USER_FLOWS، DESIGN_SYSTEM_LIGHT، PROVIDERS_ACTIONS_SECURITY، SMART_BUTTONS_CHANNELS، ARABIC_COPY_AND_CONTENT، DELIVERY_PHASES_TESTS، EXECUTION_AUTONOMY_NO_LOOP.
10. `META_WHATSAPP_ONBOARDING_AR.md` و`WHATSAPP_INBOX_REALTIME_HUMAN_PAUSE_AR.md`، ثم `machine/*` و`design/tokens.css` و`preview/*` في الحزمة.
11. ديمو الصوت في الجذر ونسخته المرجعية داخل `reference/voicecall-v0.6.3/`؛ هما مصدر فهم وإعادة استخدام انتقائية، وليسا Laravel application ولا مشروع SaaS جاهزًا.

عند التعارض: آخر كلام صريح للمالك ← CHECKPOINT والأدلة الحالية ← القرارات الحالية ← متطلبات المنصة المعتمدة ← المقترحات والخطط التاريخية. لا تطبق اقتراحًا قديمًا مثل «لا تطبيق موبايل» أو Gateway لكل شركة بعد رفضه. ملفات reference مراجع محفوظة، ولا يغيرها التنفيذ لتبدو وكأنها من الأصل.

## 2. الحقائق التي لا تبدأ من الصفر بشأنها

- المستودع Private. مصدر الديمو الحالي v0.6.3 PHP/browser مع Gemini Live، وليس منصة Laravel منشورة.
- حزمة Agent Builder المرفوعة فيها **35 ملفًا** من المواصفات والتصميم والمعاينات، وليس 35 إيجنت. نسخة المصدر كلها محفوظة في الريبو.
- الـ host-link موجود ويعمل: الحساب `appontrack`، UID2379، hostname `server11.hhost.host`، IP `65.21.208.232`.
- الإدارة تستخدم فرع `ontrack-host-link` عبر HTTPS pull/report و Cron كل دقيقة؛ يوجد دليل exec فعلي وكتابة/قراءة ملف. لا تطلب PAT أو مفتاح SSH جديدًا بلا حاجة مثبتة.
- SSH port7195 صحيح بحسب المستخدم. `Network is unreachable` كان من بيئة هذه الجلسة قبل authentication، وليس سببًا لتغيير السيرفر.
- العامل خارج الموقع في `/home/appontrack/ontrack-voice-link`. config.json المحلي يحتوي credential لا تقرأه أو تطبعه أو ترفعه.
- worker exec مهلة 30 ثانية ومخرجات 32KiB لكل stream؛ يقتل مجموعة العملية التابعة. ليس interactive SSH ولا daemon launcher ولا ناقل صوت.
- php/composer/node من PATH ليست افتراضات ثابتة بين cPanel Terminal و Cron؛ افحص المسار والإصدار الفعليين للعملية. في فحص 29 أكتوبر ظهرت مسارات PHP83/84 محتملة لكن وجود الملف لا يثبت الإصدار/الامتدادات أو توافق التطبيق.
- لا يوجد إثبات root، أو Node/Composer في PATH، أو خدمة audio طويلة التشغيل. لا تختلق جاهزيتها.
- الهاتف المحدد Redmi Note12 Pro5G؛ إصدارات Android/vendor OS/Shizuku/app والمشغل غير موثقة لدينا. لا مكالمة SIM/AI ناجحة مثبتة حتى الآن.

## 3. حدود الملفات والنشر

في GitHub:

- `saas/`: مصدر التطبيق وحده؛ قبل أمر البدء لا يحتوي إلا عناصر حجز المسار وإعداد تجاهل ملفات البيئة.
- `docs/memory/`: ميموري وتشغيل الجلسات وقضايا المشروع.
- `docs/execution/`: هذا البرومبت وعقود مراحل التنفيذ.
- `docs/project/`, `docs/discovery/`, `docs/plan/`, `docs/architecture/`: المتطلبات والقرارات والحالة والخطة.
- `reference/`: المرفقات والمراجع المجمدة، لا تبنِ التطبيق بتعديلها.
- `ops/host-link/`: التحكم والطابور والتقارير الحية.
- `tools/`: أدوات إدارة/تحقق مصدرها في GitHub، لا تنشرها تلقائيًا مع التطبيق.

على الهوست: اكتب كود التطبيق داخل `/home/appontrack/public_html/saas` فقط. لا تعدل index.php أو .htaccess أو config.php أو api/assets/lib للديمو الحالي، ولا تنقلها ولا تعِد تسميتها ولا تنشر الريبو كله فوقها. احتفظ ببصمات الملفات الموجودة قبل العمل وقارنها بعد كل نشر يؤثر في الملفات.

لا تضع `.env` أو vendor/node_modules أو مفاتيح AI أو PAT أو SSH private keys في Git. إعداد التطبيق الحقيقي محلي خاص به، ولا تعاد استخدام مفاتيح الديمو تلقائيًا. لا تكشف محتواه في تقارير الأوامر. عند نشر Laravel لاحقًا، واجهة الويب العامة يجب أن تقتصر على public/ وفق طريقة نشر معزولة مناسبة؛ لا تتعامل مع كامل شجرة Laravel كملفات تنزيل عامة ولا تغير إعدادات الرئيسية لتسهيل الأمر.

## 4. النتيجة المستهدفة للمنصة

منتج عام لكل أنواع الشركات من أول يوم، وليس WHMCS addon ولا مشروع استضافة فقط. OnTrack هي العلامة والمشغل. عربي مصري واضح و RTL، ثيم Light أحمر/أبيض/قريب من الأسود، English LTR مهيأ لاحقًا.

Laravel modular monolith + MySQL وفق handoff، و Vue3/Inertia/TypeScript/Tailwind إذا كانت البيئة تدعم المتطلبات المتحققة. اختيار إصدار framework يتبع توافق PHP الفعلي؛ لا تنفذ ترقية major أو downgrade للتطبيق العامل بالتخمين. إن لم يوجد Node على الهوست، بناء assets في بيئة مصرح بها ونشر ناتجها خيار يُقيّم بعد فحص الأدوات، وليس ادعاء بأن build نجح.

الوحدات: Identity و Tenancy و Workspaces/Projects و Agent Studio و Platform Provider Hub و Knowledge و Conversations/Inbox و Smart Actions و Integrations و Channels و CRM و Voice و Operations/Usage/Plans/Team/Settings. راجع بيانات كل صفحة وقبولها في machine/pages.csv و acceptance-tests.json.

## 5. AI خدمة تديرها OnTrack — حد لا يتغير

العميل لا يختار Google/Custom ولا يضيف AI API key أو model ID. هذه وظيفة مشرف OnTrack فقط بمسارات `/control/*` وهوية/Policy منفصلة عن tenant owner.

Google و Custom OpenAI-compatible وفق المصدر. Custom sequence: حفظ profile draft → اختبار اتصال/توليد حقيقي → إدخال أسماء الموديلات حرفيًا → اختبار كل موديل → تفعيل وتعيين writer/reviewer/fallback. HTTP200 لا يكفي إذا جسم الرد خطأ أو لا يحتوي generation صالحة. لا تُطبّع model IDs بتغيير punctuation.

كل توليد يمر من quota/plan/routing و usage ledger بمعرف idempotency. الإحصاءات الداخلية للموديلات والتكلفة/الهامش خاصة بالمنصة، والعميل يرى استهلاكه المسموح فقط. لا تسرب provider URL/key/model identifiers/internal cost في tenant UI/API/logs. اختبر 403 لكل دور tenant ولو عرف الرابط.

مفاتيح قناة WhatsApp أو API مشروع العميل مختلفة عن AI BYOK. يسمح للعميل بتوصيل قناة يملكها مع تشفير وعزل وصلاحيات، ولا يسمح بتمريرها كبوابة AI مجانية.

## 6. وظائف المنصة التي يجب تنفيذها بعد البدء

### الهوية والعزل

تسجيل/دخول/استعادة، tenant/workspace/project، فريق وأدوار وصلاحيات قابلة للتحقق. tenant context من membership المعتمدة، لا من JSON يختاره LLM. اختبر nested resources و jobs/cache/search/broadcast/exports/files، وليس queries الأساسية فقط.

### تصميم وواجهة

طبق مراجعنا الأصلية في preview/design/machine، ولا تنسخ منافسًا أو شعارًا تخمينيًا. وحدات تصميم وتوكنز مشتركة، خطوط عربية واضحة، mixed LTR fields سليمة، responsive360/390/768/1024/1440، keyboard/focus، loading/empty/error/disabled/validation/success حقيقية.

موقع عام وروابط SEO سليمة: الرئيسية والمنصة والمميزات والصناعات والتكاملات والأسعار والأمان والمطورون والوثائق والتواصل وصفحات auth. لا شهادات عملاء أو أسعار أو SLA/أرقام نجاح مخترعة. لا checkout مدفوع قبل اعتماد السياسة والمزود.

### Agent Studio/Knowledge

wizard بمعلومات الوكيل/الدور/التعليمات/المعرفة/الأدوات/القنوات/التجربة/النشر، حفظ وقراءة بعد refresh و revisions وحالات draft/test/publish/pause/archive. إعدادات AI upstream غير موجودة عند العميل.

Knowledge upload/FAQ/text/المصادر المسموح بها، ingestion jobs حقيقية، revisions و scope واختبار retrieval. لا تسمِّ fulltext vector search ولا indexed بلا نجاح ingestion.

### Smart Actions/Integrations

أزرار ديناميكية من backend business state الموثق، triggers/conditions/actions/channel mapping. تحقق هوية وصلاحية وحالة فعلية لحظة التنفيذ، ورفض stale state، وموافقة للأفعال التي تتطلبها السياسة، وتدقيق و idempotency. REST/connectors/webhooks signatures/replay protection/OpenAPI draft review. لا تنفذ أوامر أو URLs يرسلها LLM عميانيًا، ولا تمنح مفتاح admin شاملًا.

### Web chat/Inbox/CRM

Widget فعلي بمفتاح نشر عام غير سري و signed user identity عند الحاجة، جلسات معزولة، رسائل محفوظة وعرض في Inbox وإجابة حقيقية عندما تكون خدمة AI مهيأة. جهات اتصال وهويات verified/unverified وتعيين ونوت وتاريخ. human lock يمنع الرسائل queued/الجديدة حتى يفكه موظف مصرح له.

### WhatsApp الرسمي

ثلاث طرق اتصال: Meta Embedded Signup، Business App Coexistence حيث يؤهل المشغل/Meta، وإدخال Meta Cloud API يدويًا. استعمل معلومات كل طريقة من docs الأصلية ولا تخلط WhatsApp number linking بمكالمة SIM.

احفظ بيانات القناة مشفرة، تحقق WABA/phone binding و HMAC للـ webhook والتحدي والملكية، لا connected_live قبل وارد وصادر موثقين. حالات setup واضحة دون اللون الأخضر الكاذب.

Inbox أصلي realtime مع multi-number filtering، reconnect/replay، unread/بحث/status/media حسب قدرات Meta وإشعارات. رد الموظف يوقف AI في المحادثة نفسها فورًا لمدة 15 دقيقة افتراضيًا قابلة للضبط 1..1440؛ كل رد بشري يمدد deadline، و human lock الدائم لا ينتهي بالوقت. scheduler durable يعيد فقط العمل المستحق وبـ generation/version checks؛ الرسائل queued أثناء pause لا تتسرب. اختبر fake clock و race/duplicate events ثم Meta live مستقلاً عند توفرها.

### Telegram وباقي قنوات السوشيال — إضافة المالك في 29 أكتوبر 2026

المطلوب أن يرد الإيجنت من منصة واحدة على القنوات الاجتماعية و Telegram أيضًا، مع قنوات مستقلة لكل شركة وأرقام/صفحات/بوتات تملكها. لا تحصر Channels في WhatsApp و Widget. صمم adapter/capabilities registry و Inbox موحدًا مع filters حسب القناة والحساب، ومعرفة ومهام وصلاحيات وعزل و human takeover وتدقيق وتسليم الرسائل لكل قناة.

نطاق القنوات المستهدف: Facebook Pages/Messenger، Instagram professional accounts، WhatsApp official، Telegram Bot API، مع قابلية إضافة X/YouTube comments/LinkedIn/TikTok أو غيرها فقط بعد توثيق API رسمي والصلاحيات والتكلفة والأهلية. لا تعد أن كل منصة تسمح DM/comments/mentions بنفس الطريقة. افصل capability لكل نوع: receive/send private messages، comments/replies، mentions، attachments، buttons، delivery/read status. unsupported يبقى واضحًا لايظهرزرًا يوهم بأنه يعمل.

Telegram يكون من Bot مصرح به باستخدام BotAPI و webhook secret والتحقق من updates/deduplication و chat identity، أو business connection رسمي حيث يدعمه الحساب ويمنح الحقوق. لا تفترض الرد من أي حساب شخصي بلا إذن، ولا أن البوت يبدأ مراسلة كل مستخدم عشوائيًا. صلاحيات المجموعات و privacy mode تختلف عن الخاص.

قنوات Meta تحتاج الحسابات/الصفحات والأذونات ومراجعاتالتطبيق وسياسات الرسائل ذات الصلة. تحقق من signatures وأحداث متكررة وحالة القناة قبل الإرسال. لا تحول طلب «كل السوشيال» إلى اعتماد أدوات scraping أو جلسات حسابات شخصية غير موثقة. حفظ القناة/التوكن مشفر tenant-scoped لا يمنح العميل صلاحية AIupstream.

كل قناة لها connect/test/disconnect/revoke و health حقيقي ونافذة رد/ratelimits ورسائل خطأ واضحة. لا CONNECTED_LIVE إلا بحدث وارد ورد صادر مثبتين. سجل تحددالقنوات التي أمكن تنفيذها والمتوقفة على providerpermissions؛ أمّن واجهة ورسائل tenant دون مفاتيح.

### التشغيل والاستهلاك

غرفة عمليات وأحداث وتقارير مستمدة من بيانات فعلية، traces وأخطاء وأزمنة، usage/credits/entitlements، طوابير retries محدودة وفشل ظاهر. لا تعلم ذاتي أو تكامل إنتاجي إضافي من مشروع WHMCS آخر لمجرد وجوده في ذاكرة محادثات أخرى.

## 7. قسم Voice — مواصفاتنا الكاملة

لكل tenant رقم موبايل وشريحة وباقة تخصه ومعرفة ومهمة/مهمات. أي شخص يرن على الرقم يصل للإيجنت، والإيجنت يجري outbound من الشريحة نفسها. استخدم Gemini Live القائم والمصرية، ولا تفرض LiveKit أو Phone Link/Voicemeeter أو جهاز Gateway لكل شركة أو API دقائق مرتفع التكلفة كافتراضي.

استيراد CSV/XLSX/XLS/TXT أو رقم بكل سطر؛ معاينة وعمود رقم ودولة وخاطئ/مكرر وقواعد واضحة. احتفظ بالأصل ولا تصلح رقم Excel المشوه بالتخمين. يتصل الموظف تلقائيًا حسب الدوام وأيام/فترات/عطلات/استثناءات/timezone، ومهمة الحملة ومعرفات الشركة تحدد السياق.

**تحديث المالك30أكتوبر2026:** تطبيق الموبايل يستقبل الطلب وسكريبت الحوار والمهمة وقائمة العمل وإعدادات الدوام والفاصل من الداشبورد، ثم يدير الدورة بنفسه: reserve → dial → ringing → actual answer → conversation → actual hangup → delay → تحقق دوام/إيقاف/استبعاد → الرقم التالي. لا AccessibilityService ولا أتمتة ضغطات واجهة الهاتف، ولا حاجة لطلب يدوي لكل مكالمة. retry/callback لهما سياسة مستقلة. notes أثناء الحديث وتقرير أمام كل رقم وتاريخ ومتابعة؛ telephony outcome مستقل عن business result. لا تعاد مكالمة بسبب فشل التلخيص أو restart، ولا تفترض exactly-once للشبكة.

Shizuku مستبعد صراحة. المرشح البحثي الجديد embedded ADB/shell داخل APK، ولم يثبت على الهاتف. ابدأ ملف الصوت الجاهز A/B وفق بروتوكول بحث30أكتوبر قبل استهلاك AI أو بناء حملات حول مسار غير مثبت، ثم وارد وصادر AI/SIM حقيقيان. الاقتران/SIP registration/green health/نجاح GitHub ليست إثبات صوت.

الوصول إلى الهوست لا يعطي وصولًا إلى الموبايل. لا تختلق تنفيذ Shizuku أو صوتًا سمعته. المعمارية النهائية و Asterisk/Node/خدمة النقل ومكان تشغيلها تحسم بعد الدليل والبيئة. يمكن تنفيذ وحدات المنصة المستقلة بعد أمر البدء مع توثيق أن الصوت غير متصل؛ لا تبيع dashboard كحل لمشكلة النقل.

متطلب رقم المدير ومعلوماته والاستفسار والأوامر محفوظ لكنه مؤجل. Caller ID وحده ليس authentication. لا تفتح صلاحيات إدارية صوتية في إثبات المكالمة.

## 7.1. عقد تطبيق الموبايل وعدم Accessibility

اقرأ docs/architecture/MOBILE_EXECUTOR_CONTRACT_AR.md كمرجع حاكم لهذا التعديل. التطبيق ينفذ الطابور المحلي والاتصال والإنهاء والفاصل، والسيرفر يحدد السياسات والملكية وleases والإيقاف ويستقبل التقارير. سكريبت المهمة تعليمات حوار وأفعال مصرح بها، وليس كودًا اعتباطيًا للتنفيذ. تخزين التطبيق لحالته التشغيلية مسموح؛ ميموري التطوير تبقى في GitHub.

التحكم بالمكالمة عبر دور الهاتف الافتراضي وTelecom APIs لا يثبت صوت AI عبر SIM. VOICE_CALL يتطلب إذنًا مميزًا غير متاح لتطبيق عادي. يجب إثبات التقاط وحقن الصوت دون Accessibility على الجهاز قبل إعلان نجاح المنتج؛ Shizuku لم يعد مسارًا مقبولًا. embedded ADB يظل امتياز shell مميزًا؛ لا يساوي صلاحيات تطبيق عادي ولا يُفهم من رفض Shizuku أن المالك قبل كل بديل امتياز تلقائيًا. لا ضمان عمل في الخلفية أو بعد reboot بلا اختبار النظام الفعلي.

## 8. طريقة تنفيذ كل جزء

1. اقرأ checkpoint والـ issue المفتوح المتعلق بالجزء، وحدد شرط قبول قابل للقياس.
2. سجّل المهمة/الخطة القصيرة قبل التغيير في GitHub ACTION_LOG.
3. افحص الملفات والبيئة/الـ schema الفعلية؛ اختر أصغر vertical slice تعمل من الواجهة إلى الخدمة والـ DB.
4. نفذ داخل saas فقط، مع reversible Git baseline. لا تحذف ميزات لتفادي الاختبار ولا تكتب implementation يختبر نفسه فقط.
5. شغّل الاختبارات المطلوبة للجزء وسجل الأمر والمسار والنسخ والـ exit والمدة والنتيجة ودليلها. أي build أو migration يحتاج نجاحًا حقيقيًا.
6. عند فشل، افتح issue وسجل كل محاولة بتوقيع الخطأ والفرضية والإصلاح والنتيجة. محاولتان متطابقتان كحد أقصى دون دليل جديد، ثم غير المنهج.
7. اختبر UI فعليًا حيث يوجد Browser متاح ومأذون، responsive/accessibility ومسارات ذات معنى. لا تعرض Screenshot mock بوصفه product E2E.
8. راجع ownership والأذونات/الـ state بعد أي فعل write. لا تستخدم بيانات أو مكالمات عملاء حقيقيين كـ fixtures.
9. ثبّت التغييرات والنتائج في GitHub ثم حدّث checkpoint والميموري والخطوة التالية. لا تترك الدليل المهم في RAM المحادثة.

## 9. عقد الذاكرة والتفاصيل — GitHub فقط

راجع `docs/memory/MEMORY_PROTOCOL_AR.md`. الحد الأدنى لكل فعل:

- event_id و UTC timestamp و session/actor/task/phase.
- الإجراء والسبب والهدف ومساراته والـ base commit أو job id.
- الأمر الفعلي منقحًا، والبيئة والمدة والـ exit/status.
- النتيجة وما تثبته وما لا تثبته، artifact/report path.
- issue_id عند الخطأ، فرضية كل محاولة والفرق عن السابقة ونتيجتها.
- الملفات التي تغيرت، التحقق والرجوع الممكن، والنقطة الحالية والخطوة التالية ومنع تكرار الخطأ.

ممنوع تسجيل مفاتيح/توكن/OTP/كلمات مرور/private keys أو كامل ملفات إعداد حقيقية أو بيانات عملاء. استعمل `[REDACTED]` مع حفظ اسم الحقل/سبب الحجب. لا تحذف فشلًا قديمًا لإظهار نجاح لاحق؛ أضف تصحيحًا ودليلًا.

السجلات append-only JSONL، و ISSUE_REGISTER يحفظ الحالة الحالية مع history/attempt IDs. CHECKPOINT الحالي ملخص قابل للاستكمال، وليس بديلًا عن التاريخ. عند انقطاع أو قرب نهاية السياق اكتب checkpoint في GitHub قبل الانتقال. إن تعطلت الكتابة للريبو، احتفظ محليًا مؤقتًا ووثّق unsynced؛ لا تحفظ الميموري على الهوست ولا تدّع أنها رُفعت.

## 10. العمل عبر host-link الموجود

الطابور في `ontrack-host-link`، المسار `ops/host-link/jobs/appontrack-server11.json`، target `appontrack-server11`.

اقرأ أحدث queue و SHA وحالة job قبل تحديثه. لا تستبدل job قيد التنفيذ ولا force-reset branch. استعمل id فريدًا و expiry UTC، argv دون shell ضمني و cwd داخل saas للعمل البرمجي. انتظر التقرير المطابق، وافحص job hash/status/exit/stdout/stderr.

إذا انقطع التقرير لا تعِد أثرًا حساسًا بمعرف جديد. state العامل يحاول report upload دون replay، و interrupted_unknown يحتاج مطابقة آثار العملية. لا تلمس config/state للعامل ولا تعِد تثبيته من main قديم. main وفرع الربط قد يتباعدان بتقارير جديدة؛ حافظ على الاثنين.

الأوامر التي تتجاوز 30 ثانية أو jobs طويلة لا تُخفى بـ nohup داخل exec؛ قيّم طريقة تشغيل مناسبة للهوست ثم وثقها. لا تحول قناة الإدارة إلى Web shell عام أو audio streaming endpoint.

## 11. ترتيب مراحل التطبيق بعد أمر البدء

1. Environment inventory داخل مساحة saas، PHP/Composer/MySQL/build tools/permissions، ثم تطبيق compatible boots مع isolated DB/config.
2. Auth/Tenancy/site shell/design system و CRUD للشركات/workspaces/projects/agents.
3. Platform Provider Hub + tenant playground الحقيقي ضمن entitlements.
4. Knowledge/SmartActions/Business Integrations بعقود و Policies حقيقية.
5. Widget/Inbox/CRM/handoff و WhatsApp الثلاثي + pause rules.
6. وحدات Voice المستقلة، مع اختبار الهاتف كمسار إثبات منفصل لا يُوصف بأنه ناجح قبل الدليل؛ ربط الصوت عند توفره ثم قوائم/جداول/تقارير وسعة.
7. اختبارات التكامل والعزل والـ build و UI والنشر المعزول والتحقق من بقاء الرئيسية كما كانت.

الاعتماد الخارجي المتعطل لا يوقف باقي ما يمكن تنفيذه استقلالًا بعد أمر البدء، لكنه يظل غير verified. هذا ترتيب عمل مبدئي؛ قارنه بحالة الكود الفعلية والمصفوفة ولا تعِد ما أنجز سابقًا.

## 12. حدود الأمان والتكلفة والصدق

TLS صحيح، SSRF/DNS/redirect/rebinding protections للـ custom URLs، CSRF/XSS/session/CORS حسب التدفق، authorization لكل tenant/action، signed webhooks مع replay/dedupe، encryption credentials، budgets/timeout/retries محدودة و audit. لا تعطّل الحماية لحل الشبكة، ولا تشترِ خدمة أو جهازًا أو تقبل عقدًا دون سند واضح من المالك.

لا `migrate:fresh` أو db:wipe أو force reset أو حذف بيانات حقيقية، ولا نقل/تعديل production WHMCS أو Gateway آخر. DB جديدة مستقلة للمشروع؛ عدم وجودها يُسجل كعائق مع إنجاز ما يمكن، ولا تستخدم DB الموقع الحالي بالتخمين.

وصف الحالة دقيق: PLANNED/IMPLEMENTED_LOCAL/TESTED_LOCAL/VERIFIED_STAGING/CONNECTED_LIVE/PENDING_EXTERNAL. لا تنقل الحالة لمجرد HTTP200 أو اختبارات mock. «جاهز» يعني أي نطاق بالتحديد ودليل كل جزء، لا عبارة تسويقية عامة.

## 13. التوقف والتسليم

في جلسة التجهيز الحالية: أنجز المجلد الفارغ وملفات GitHub وبرومبت التنفيذ والميموري وأدلة الحفاظ على الرئيسية، وأبلغ المالك بالمسار وأن التجهيز انتهى. **لا تبدأ تطبيق SaaS قبل الخطوة التالية من المالك.**

في جلسات التنفيذ المأذونة لاحقًا: واصل حتى شرط قبول الجزء/النطاق، مع تغيير النهج عند العوائق وعدم اللوب. قبل انتهاء أي جلسة حدّث CHECKPOINT و ISSUE_REGISTER والسجلات و commit/evidence/next action. رد عربي مباشر: ما أنجز، ما اختبر، ما بقي أو تعطل، ورابط نقطة الاستكمال؛ لا مطالبة المستخدم بإعادة شرح المشروع.

صيغة الاستئناف القصيرة للمالك: «اقرأ AGENTS.md ثم docs/memory/CHECKPOINT.md و docs/execution/MASTER_EXECUTION_PROMPT_AR.md، واستكمل من آخر حالة موثقة ضمن أمر البدء الحالي».

## ملحق حاكم — البحث الاقتصادي والقيود الجديدة

لا Shizuku، لا Accessibility، لا شراء هاردوير للعميل، لا اشتراكات/عقود اتصالات جديدة. أعد استخدام الشريحة والباقة والصوت الموجود. البحث في docs/research/2026-10-30/ يضم39مصدرًا أوليًا ومواضع كود وبروتوكول إثبات وحاسبة افتراضات. لا تعتمد أرقامها كسعر بيع أو benchmark. مرشح JemRec يحتاج تحويل تحية إلى streaming متزامن واختبار VoLTE على الهاتف؛ مصدر BootReceiver ينتظر أول unlock. لا وعد تشغيل بلا تدخل بعد reboot. أي تفضيل قديم لـShizuku في مراجع محفوظة متجاوز بهذا القرار. لم يبدأ تنفيذ SaaS أو APK أثناء البحث.
