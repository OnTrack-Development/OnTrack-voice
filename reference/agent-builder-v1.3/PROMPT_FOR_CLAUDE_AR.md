# البرومبت الرئيسي — ابدأ تنفيذ OnTrack AI Agent Builder فورًا

أنت الآن **Lead Architect + Senior Laravel Full-Stack Engineer + Product Designer + QA/Security Engineer** المسؤول عن بناء منتج **OnTrack AI Agent Builder** داخل مجلد المشروع المفتوح في بيئتك.

**أريد كودًا حقيقيًا شغالًا، وواجهات احترافية جدًا، وتجارب مترابطة، وليس تحليلًا أو Figma أو صفحات شكلية. ابدأ التنفيذ فور قراءة الملفات.**


## أمر تنفيذي إضافي من مالك المشروع — لا تتوقف قبل اكتمال الممكن (أولوية عالية)

**"ميرجعلش خالص غير لما يخلص كل حاجة وتبقى جاهزة على الشغل، ولو وقع في لوب ينهيه ويشوف طريقة يطلع منه، ولو التيرمنال وقف يستخدم طريقة تانية غيره."**

طبّق هذا عمليًا بهذه القواعد الملزمة:

- **لا ترجع لي بعد تقرير أو خطة أو أول صفحة أو أول Sprint.** ابدأ بالتنفيذ فورًا وواصل تلقائيًا المرحلة التالية، حتى إغلاق جميع معايير القبول الممكنة في بيئتك. لا تطلب مني اختيار ألوان، أقسام، مكتبات، أو تفاصيل محسومة بهذه الحزمة. سجل تقدمك داخليًا في `docs/IMPLEMENTATION_STATUS.md` بدل رسائل توقف متكررة.
- **لا ترجع باستفسار إلا عند مانع خارجي لا يمكن تخطيه بعمل آخر** (مثل صلاحية غير موجودة فعليًا، موافقة مالية جديدة، أو سر غير متاح). وحتى في هذه الحالة أنجز أولًا **كل الأعمال المستقلة** الممكنة، واعزل المانع بدقة.
- **ممنوع اللوب:** لكل خطأ بصمة ورسالة في سجل الأخطاء، بحد أقصى محاولتي إصلاح متماثلتين؛ عند التكرار غيّر الاستراتيجية (مسار آخر، مكتبة أخرى متوافقة، تقسيم المهمة، fixture اختبار محلي، طريقة كتابة ملفات أخرى)، وليس إعادة نفس الأمر أو مطالبة الموديل بنفس JSON للأبد.
- **تعطل التيرمنال لا يوقف التطوير:** جرّب أدوات التحرير وإدارة الملفات المتاحة في الـIDE، أو أدوات Git والمستودع أو لوحة الاستضافة المتصلة فعليًا، أو test runner مستقل، حسب الصلاحيات الحقيقية. **لا تتخيل أدوات غير متاحة ولا تتجاوز قيود الأمان.** لو تعذر اختبار جزء بسبب التيرمنال، أكمل إنشاء ملفات آمنة واختبارات قابلة للتشغيل وسجّل بوضوح أن التحقق لم يجرِ، ثم حاول قناة تحقق أخرى.
- **نفّذ المنتج بالكامل على مراحل مترابطة**: Marketing كامل + Auth + Multi-Tenancy + مشاريع ووكلاء + Provider Hub + Knowledge + Buttons + Integrations + Widget + Inbox/CRM + Operations + API + QA. لا تستبدل Backend الحقيقي بواجهات Mock دون تمييز واضح. عندما تكون مفاتيح Meta أو Google أو مزوّد Custom غير متوفرة لا تنشر كذبًا Connected؛ نفّذ الأكواد وMocks الاختبارية والواجهة والإعدادات، وواصل الباقي.
- **اختبر وأصلح حتى النجاح كلما توفر runtime حقيقي:** migrations، tests، build، test fixtures، المتصفح في desktop/mobile، وتدفق المستخدم E2E. لا تصف المشروع بـ`Production Ready` إلا بدليل تشغيلي فعلي. إن كانت بيئة staging مصرّح بها ومتصلة، انشر عليها بعد النسخ الاحتياطي/التحقق، لكن لا تنشر تلقائيًا على Production أو WHMCS العملاء.
- **استمر ذاتيًا ولا تستهلك API عشوائيًا.** إذا كانت الجلسة أو الميزانية محدودة، احفظ نقطة استئناف دقيقة وواضحة قابلة للتشغيل؛ لا تعد أن العمل سيستمر في الخلفية إن لم تتوفر أداة تدعم ذلك. المخرجات النهائية يجب أن تكون تطبيقًا قابلًا للتشغيل مع سجل اختبارات، أو بيانًا صريحًا ومحددًا بأي مانع لا يمكن حله بعد استنفاد البدائل، وليس ادعاء إنجاز وهمي.

## قرار تجاري نهائي يتقدم على أي وصف قديم — OnTrack توفر الذكاء بنفسها
**اقرأ `docs/PLATFORM_AI_SERVICE_BOUNDARY_AR.md` قبل أي ملف آخر.** منصة OnTrack هي صاحبة مفاتيح/عناوين Google/Custom/Omnira وكل الموديلات، وتوفّر AI للمشترك كخدمة مدفوعة. العميل **لا يدخل ولا يرى أي مزوّد AI أو API Key أو Model ID أو إعدادات Writer/Reviewer**. ابنِ Provider Hub تحت `/control` للمشرفين الداخليين فقط، وافصله أمنيًا عن لوحة العميل `/app`. العميل يختار باقة وينشئ وكيلًا ويضيف Knowledge وBusiness Integrations ويستخدم الذكاء من حصته. لا BYOK نهائيًا. أي كلام مختلف في أرشيف v0.4 أو ملفات سابقة ملغي.

## قبل أول تعديل
- اقرأ `CLAUDE.md` وجميع ملفات `docs/` و`machine/` و`design/` و`preview/` والمرجعين الموجودين في `source-references/` **بترتيب القراءة**.
- اعمل Inventory لملفات المشروع الحالي، PHP/Composer/Node، قواعد البيانات المتاحة، Git، Routes، الاختبارات، والخدمات الفعالة. لا تفترض أنه مشروع جديد ولا تدمّر ملفات Laravel موجودة. لو المجلد لا يحتوي تطبيقًا ابدأ مشروع Laravel حديثًا متوافقًا مع بيئة التنفيذ، وطبّق فيه التصميم والمواصفات.
- أنشئ `docs/IMPLEMENTATION_STATUS.md` وسجل فيه Baseline + المراحل المنفذة + الاختبارات/الأخطاء. خليك سريع في التخطيط ولا تتوقف تنتظر موافقة على كل تفصيلة اعتمدناها بالفعل.

## المنتج المطلوب
منصة **SaaS عامة لجميع أنواع الشركات من اليوم الأول**. لكل شركة حساب مستقل ومساحات عمل ومشروعات ووكلاء AI ونظام معرفة وقنوات واتصالات ومحادثات. وكيل AI يستطيع الإجابة والعمل عبر API APIs مصرح بها، ويعرض أزرارًا ديناميكية تتغير حسب حالة العمل الحقيقية. مزود AI مرن يشمل **Google Gemini** و**Custom OpenAI-compatible**؛ لا ارتباط إجباري بـOmnira، ويمكن إضافة مزودات أخرى عبر Adapter لاحقًا. Custom: **Super Admin من فريق OnTrack فقط** يختبر الاتصال ثم يضيف Model IDs يدويًا ويختبرها. العميل لا يعرف مصدر الموديلات. لا تتظاهر باتصال فعلي بدون بيانات اعتماد أو اختبار.

## الواجهة المطلوبة (Critical)
- **Light Mode حصريًا كاختيار بداية التنفيذ**، الأبيض والأوف وايت خلفية، أسود للنصوص، **OnTrack Red** عنصر أساسي مركز على الـCTA والتمييز المحدود، بدون Dark في الإصدار الحالي. استخدم tokens في `machine/design-tokens.json` و`design/tokens.css` (القيم مقترحة قابلة لتبديل شعار الشركة الفعلي)، وحافظ على مظهر نظيف ومكلف وCinematic خفيف.
- **العربية أولًا وبشكل كامل** `ar-EG`, RTL بما فيه Sidebar على اليمين وحقول النماذج والمحاذاة. جهز Translation Keys وLocale Switch architecture للإنجليزية لاحقًا **بدون ملء المشروع بنصوص إنجليزية في واجهة العربية** أو إنشاء واجهتين منفصلتين.
- أنشئ الصفحة الرئيسية وموقع التسويق متعدد الصفحات، وتجربة التسجيل، ولوحة SaaS حقيقية متصلة، وAgent Studio وPlayground ولوحة Provider Hub **الداخلية المنفصلة للمشرفين فقط** وKnowledge Hub وSmart Buttons وIntegrations وInbox وCRM وOperations وSettings. التزم بملف خريطة الصفحات والـPhases.
- الموقع منافس TAB، لكن **أصلي التصميم والنصوص والأصول**. المعاينتان `preview/landing.html` و`preview/dashboard.html` و`preview/studio.html` مراجع تصميم أصلية لتوحيد الهوية؛ نفّذها كمكونات Laravel/Vue قابلة للتطوير وليست مجرد iframe أو ملف HTML خام ملصوق.
- لا تبنِ UI متكررة مسطحة: Hero يتضمن Demo حيًا يمكن التفاعل معه، cards دقيقة، جداول ببيانات صحيحة، انتقالات محسوبة، خاصية hover/focus واضحة، حالة كل إجراء مرئية، مكونات حقول وDialog/Drawer سليمة.

## التقنيات
- الافتراضي Laravel 13 (PHP 8.3+) مع MySQL وVue 3/Inertia/TypeScript/Tailwind، Queue/Redis عند التوفر، API Contracts واضحة، اختبارات Feature/Unit/Browser. لو التطبيق موجود حافظ على نسخته وثبّت نسخة متوافقة مع بيئته؛ لا ترقيات مدمرة.
- Laravel backend يدير Authorization/Domain/Queues/Payments + منصة AI داخلية تملكها OnTrack وحدها: platform provider keys, policies, quotas and ledger (الأسعار غير محسومة، لا تتصل ببوابة دفع وتخترع أسعارًا)، وVue يعرض Dashboard/Studio/Widget Preview. استخدم REST API/Webhooks للتكامل العام، ثم Adapters لـLaravel/WordPress/WooCommerce/WHMCS.
- لا تستخدم قواعد بيانات أو Keys وهمية بشكل يوحي بأنها إنتاجية. بيانات الديمو Synthetic ومميزة كـDemo.

## معايير التنفيذ الحاسمة
1. `signup -> select plan -> workspace -> project -> agent -> configure knowledge/actions -> playground (platform-managed AI) -> publish web widget` رحلة متصلة فعلًا، مع كل مرحلة تتحفظ بقاعدة بيانات MySQL.
2. **Platform Super Admin only**: `Custom Provider`: إضافة الاسم وHTTPS Base URL وAPI Key -> Test Connection حقيقي -> إدخال Model IDs حرفيًا -> Test Model -> اختيار Writer/Reviewer/Fallback مركزي من مشرف OnTrack -> حفظ وReadback صحيح بعد Refresh؛ ممنوع دخول أي Tenant لمسارات المزود؛ كل فشل مفهوم بالعربي ولا يكشف المفتاح.
3. `Dynamic Buttons`: State machine وbusiness events وRules مرتبطة بالصلاحيات؛ أزرار Preview تعمل وتختلف حسب الحالة؛ التنفيذ الحقيقي مشروط بهوية موثقة وAction مسموح وIdempotency وAudit. WhatsApp لديه Renderer/Fallback مستقل ولا تعتبره Live قبل Meta credentials وE2E.
4. `Multitenancy`: لا يمكن لمستخدم شركة A رؤية أو تعديل أي Record من B بتغيير ID أو route أو request body؛ اختبارات أمنية إلزامية.
5. `Knowledge`: رفع ملف، حالة ingestion فعلية، تحديث وحذف، Scope شركة/مشروع/وكيل، retrieval grounded مع إشارة للمصدر، وعدم خلط customer live data بالمعرفة الثابتة.
6. `Integrations`: Generic secure connectors مع dry-run وتنفيذ read-only أولًا، وتفعيل write فقط بعد policy/approval. Laravel/WordPress/WHMCS كحزم/موصلات مستقلة تستفيد من Core API وليس عبثًا بكود المشاريع.
7. Dashboard وCRM وInbox يعرضوا نتائج حقيقية من قاعدة البيانات؛ Empty States واضحة لو مفيش بيانات؛ لا mock metrics بلا وسم.
8. اختبر `composer test / php artisan test`, `npm run build`, typecheck/lint عند التوفر، ومراجعة UI بمتصفح إن أمكن؛ أصلح failures بدل قائمة TODO.

## ترتيب التسليم بدون مماطلة
**أولًا:** Foundation + Design System + Marketing site احترافي متعدد الصفحات + Auth + dashboard shell فعلية، مع كشف routes والـscreenshots إن أمكن.
**ثانيًا:** Core workspace/project/agent creation & editing + DB + Playground بالذكاء الذي تقدمه OnTrack؛ **لوحة Super Admin منعزلة** لإعداد Google وCustom وموديلاتها، واختبار العزل والـQuotas.
**ثالثًا:** Knowledge, Smart Buttons, generic Integrations, first-party connection contracts, conversations and CRM.
**رابعًا:** Channel web widget E2E + WhatsApp adapter setup (لا تبعث رسائل Meta بدون إعداد حقيقي)، Reviewer/Handoff, analytics, production hardening.
لو المشروع كبير ما توقفش عند مرحلة وصف؛ نفّذ أكبر قدر ممكن في الجلسة الحالية، واحتفظ بحالة تقدم دقيقة وابدأ المرحلة التالية تلقائيًا لو الاختبارات نجحت.

## متطلبات البرهان
في نهاية كل مرحلة قدّم: الملفات المتغيرة، المسارات الجديدة، شكل الشاشات، migration status، الـtests الفعلية بالأرقام، screenshot paths عند وجودها، ما هو mock وما هو live، وأي blocker واضح. ممنوع إعلان «جاهز للإنتاج» من مجرد نجاح build أو وجود صفحة. لا يوجد إذن بالنشر الإنتاجي تلقائيًا ولا بالكشف عن مفاتيح API.

**ابدأ الآن: Inventory -> Implement -> Test -> Visual QA -> Continue. لا تعطيني برومبت آخر ولا تنتظر تصميمات جديدة.**

## شرط إلزامي إضافي معتمد من المالك — 3 طرق لإضافة رقم واتساب
اقرأ `docs/META_WHATSAPP_ONBOARDING_AR.md` ونفذ صفحة القنوات/واتساب بــ3 اختيارات واضحة: **Meta Embedded Signup**، **WhatsApp Business App Coexistence** إذا كان الرقم مؤهلًا، و**Manual Meta Cloud API Setup** بنفس فلسفة موديول OnTrack WhatsApp Notifications الحالي. الربط اليدوي يتضمن WABA ID وPhone Number ID وAccess Token وApp ID/App Secret وWebhook Callback URL + Verify Token، واختبار فعلي للبيانات والـWebhook، مع ربط الرقم بالوكيل ومتابعة حالة كل اتصال. لا تضع حقول AI Provider/AI Model/API Key للمزوّد في لوحة العميل؛ بيانات Meta المدخلة يدويًا هي بيانات **قناة واتساب الخاصة بالعميل** فقط. نفذ متعددة الأرقام لكل شركة وعزل المستأجرين والتوقيع وIdempotency وعدم الرد عندما يتولى موظف بشري المحادثة. لا ترجع لي لسؤال عن طرق الربط؛ القرار معتمد.

## شرط نهائي معتمد v1.3: واتساب ويب داخلي + إيقاف AI عند رد الموظف
لا تعتبر WhatsApp منتهيًا بصفحة ربط الرقم فقط. اقرأ `docs/WHATSAPP_INBOX_REALTIME_HUMAN_PAUSE_AR.md`. نفذ تبويب **WhatsApp Inbox** لكل رقم Cloud API نشط بشكل فوري وخفيف؛ الرسائل مع الحالات والـmedia/emoji/stickers المسموحة، إشعارات، بحث وتفويض موظفين، Reverb/WebSockets مع إعادة مزامنة وحماية webhook/outbox. **عند إرسال صاحب الشركة أو أحد الموظفين المخولين رسالة يدوية من Inbox: عطل الرد الآلي في هذه المحادثة وحدها لمدة 15 دقيقة افتراضيًا، وإعداد بالدقائق يملكه صاحب الحساب (1–1440)، وكل رد بشري يمدد المهلة من جديد، وترجع الردود تلقائيًا دون فتح المتصفح عند الانتهاء.** لا ترد على الرسائل التي وصلت أثناء المهلة إلا عند انتهاء المهلة مع التحقق من عدم رد موظف عليها، وتجميع الأسئلة المعلقة في رد واحد، ومنع الازدواج. لا يتأثر أي شات آخر. امنع إرسال رد AI كان مولَّدًا أو محجوزًا قبل تدخل الموظف؛ تحقق مرة أخرى مباشرة قبل إرسال Meta. **Human Lock الدائم منفصل ولا ينتهي بانتهاء مؤقت الـ15 دقيقة**. نفذ كل المعايير والاختبارات، ولا تعرض Preview فقط كأنه Live.
