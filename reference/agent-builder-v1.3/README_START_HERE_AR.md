# OnTrack AI Agent Builder — حزمة تسليم تنفيذ Laravel

**27 أكتوبر 2026 | نسخة التسليم v1.2 | اللغة الأولى: العربية RTL | الثيم الافتراضي: Light**

هذه **حزمة تعليمات ومواصفات وتصميم ومعاينات مرجعية** لتسليمها إلى Claude لبناء منتج SaaS حقيقي. ليست Laravel application جاهزة، ولا دليلًا على اختبار تكامل أو نشر إنتاجي.

## طريقة الاستخدام
1. فكّ الضغط داخل مجلد مشروع Laravel المستهدف (أو مجلد العمل الجديد). حافظ على الهيكل والمراجع؛ لا تستبدل ملفات التطبيق الموجودة بنفس الأسماء دون مراجعة.
2. افتح **`PROMPT_FOR_CLAUDE_AR.md`** وانسخ محتواه كأول رسالة لكلود، أو اطلب منه مباشرة قراءة `CLAUDE.md` وباقي الملفات ثم التنفيذ.
3. افتح `preview/landing.html` و`preview/dashboard.html` و`preview/studio.html` لمعاينة **مرجع بصري أصلي** للـLight Theme؛ المكونات فيها Concept وليست نظام SaaS حقيقيًا.
4. لو يوجد Laravel موجود: أول إجراء هو inventory شامل، حفظ Git baseline، وتعديل آمن داخل نفس المشروع. لو فارغ: إنشاء Laravel بتوافق PHP الموجود ثم تنفيذ الخطة.
5. بعد كل مرحلة: تنفيذ اختبارات فعلية وحفظ تقرير `docs/IMPLEMENTATION_STATUS.md` في مشروعه (ينشئه Claude)، مع تمييز VERIFIED وMOCKED وPENDING بوضوح.

## محتويات الحزمة
- `CLAUDE.md` — عقد العمل، ترتيب المرجعية، قواعد التنفيذ والجودة.
- `PROMPT_FOR_CLAUDE_AR.md` — الأمر الرئيسي الجاهز للإرسال.
- **`docs/PLATFORM_AI_SERVICE_BOUNDARY_AR.md` — قرار إلزامي: OnTrack هي مقدمة AI، ولا يوجد BYOK للعملاء**.
- `docs/PRODUCT_REQUIREMENTS_AR.md` — وظائف المنتج والقرارات المعتمدة/غير المعتمدة.
- `docs/DESIGN_SYSTEM_LIGHT_AR.md` — هوية الـLight كاملة بالتوكنز والـRTL والحركة والمكونات.
- `docs/PAGE_MAP_AND_USER_FLOWS_AR.md` — صفحات الموقع ولوحة التحكم وتدفقات المستخدم.
- `docs/ARCHITECTURE_DATABASE_AR.md` — Laravel architecture، MySQL والـqueues وحدود الشركات.
- `docs/PROVIDERS_ACTIONS_SECURITY_AR.md` — Google/Custom والموديلات وTools والهويات والأذونات.
- `docs/SMART_BUTTONS_CHANNELS_AR.md` — محرك الأزرار الديناميكية وWhatsApp وشات الموقع.
- `docs/ARABIC_COPY_AND_CONTENT_AR.md` — نصوص عربية أصلية للصفحات والأخطاء.
- `docs/DELIVERY_PHASES_TESTS_AR.md` — مراحل التنفيذ ومعايير القبول والأوامر والاختبارات.
- `preview/superadmin-ai.html` — معاينة منفصلة لإدارة مزودي الذكاء على مستوى OnTrack فقط.
- `machine/design-tokens.json`, `design/tokens.css`, `machine/ar-ui.json`, `machine/pages.csv`, `machine/acceptance-tests.json`, `machine/smart-buttons.example.json`, `machine/provider-draft.example.json` — بيانات قابلة للقراءة آليًا.
- `preview/*.html` — معاينات تفاعلية لواجهة العميل ولوحة OnTrack Super Admin؛ تم استبعاد Screenshots القديمة من v1.0 لأنها كانت تعرض إعدادات AI Provider عند العميل بالخطأ. اطلب من Claude إعادة التقاط صور بعد تشغيل HTML/الواجهة الجديدة.
- `source-references/*.md` — قرارات المستخدم الأصلية وتحليل المنافس؛ **ليست دعوة للنسخ**.

## ما تم اعتماده نهائيًا من المالك
- المنتج عام لكل أنواع الشركات من أول يوم، بدون قطاع افتراضي مقيِّد.
- البداية عربية RTL، مع تهيئة كاملة لإضافة English LTR في مرحلة لاحقة.
- **Light** هو الثيم الفعّال عند الانطلاق؛ ألوان OnTrack الأساسية: أحمر + أبيض + أسود.
- Google Gemini + Custom API provider **داخل لوحة OnTrack Super Admin فقط**؛ العميل يستهلك AI كخدمة من باقته، ولا يرى الموديلات أو مفاتيحها. Custom Model IDs تُدخل من فريق OnTrack بعد اختبار الاتصال.
- اشتراكات بحدود استهلاك قابلة للتعديل تديرها OnTrack من منصتها؛ لا BYOK للمشتركين ولا حقول AI API Key عند العميل.
- Dynamic Smart Buttons حسب حالة العميل الحقيقية، وليس توليد نصوص عشوائية.
- تكامل مفتوح مع Laravel/WordPress/WHMCS وغيرها + REST/Webhooks.

## غير معتمد بعد: لا تخترع قرارًا من تلقاء نفسك
- أسعار الباقات، العملة، مزود الدفع، سياسات الاسترداد والتعاقد.
- ما إذا كانت كل القنوات الاجتماعية جاهزة يوم إطلاق أول Release.
- هل يحتاج كل رد إلى Reviewer أم يُطبَّق بناء على سياسات المخاطر/الباقة.
- الكود الإنتاجي الحالي للـWHMCS AI Support Agent ليس مصدرًا يسمح بالنسخ أو إدعاء نجاح الـWhatsApp.

## مصادر تقنية للتوافق
- Laravel 13 releases: https://laravel.com/docs/13.x/releases
- Laravel Vue starter kit: https://laravel.com/docs/13.x/starter-kits
- Vue 3: https://vuejs.org/
- واجهة OnTrack الحالية لاستلهام الهوية فقط: https://ontrackegy.com/

**تذكير:** الألوان الرقمية المحددة هنا هي `proposed tokens` مطابقة لاتجاه ألوان الشركة، **وليست استخراجًا موثقًا من Brand Book رسمي**. إن توفّر شعار OnTrack أو ملفات العلامة الأصلية بالمشروع استخدمها مرجعًا ولا تعيد رسم شعار تقريبي وتعتبره رسميًا.

## قاعدة المستخدم الأخيرة
**لا توقف Claude في منتصف المراحل لمجرد تقرير تقدم.** يلزمه الاستمرار وإكمال كل جزء ممكن، ثم إجراء اختبارات حقيقية. عند التكرار يبدل المنهج بعد محاولتين، وعند تعطل التيرمنال يستخدم محرر ملفات/IDE/Git/test runner بديلًا **إذا كان متاحًا ومصرحًا به**. العوائق الخارجية لا تبرر ترك باقي المشروع؛ تستثنى فقط مفاتيح أو موافقات تشغيل/نشر غير متاحة، مع إظهار ما بقي بدقة ودون ادعاء النشر.

## تحديث معتمد — WhatsApp 3 onboarding choices — v1.2
**اقرأ `docs/META_WHATSAPP_ONBOARDING_AR.md` أولًا عند تنفيذ القنوات.** للعميل 3 طرق اتصال رسمية: تلقائي Meta Embedded Signup، Coexistence مع WhatsApp Business App إذا كان مؤهلًا، و**إدخال بيانات Meta Cloud API يدويًا** كما في موديول واتساب OnTrack الحالي. مدخلات Meta اليدوية خاصة برقم واتساب العميل وليست مفاتيح الذكاء الاصطناعي، التي تبقى حصرية لإدارة OnTrack. أُضيفت معاينة `preview/channels.html` واختبارات قبول خاصة بالطرق الثلاث.

**تحديث:** معاينة اختيار 3 طرق متاحة في `preview/channels.html`، بما فيها Manual Setup. كل الملفات دي مواصفات وليست تطبيق Meta متصل حاليًا.

## آخر إضافة معتمدة — v1.3 (WhatsApp Inbox + AI Auto-Pause)
بعد قراءة ملف ربط واتساب الثلاثي `docs/META_WHATSAPP_ONBOARDING_AR.md` اقرأ **`docs/WHATSAPP_INBOX_REALTIME_HUMAN_PAUSE_AR.md`**. رقم Cloud API (غير مرتبط بتطبيق) يحتاج تبويب شات أصلي بالكامل، بتحديث فوري وإشعارات ودعم أنواع الرسائل الرسمية. **أول ما موظف الشركة يرسل رسالة يدويًا، AI يتوقف في هذه المحادثة فقط 15 دقيقة افتراضيًا، صاحب الشركة يعدل المدة بالدقايق، وكل رد جديد يمددها، وبعد الوقت يستأنف تلقائيًا؛ Human Lock الدائم لا ينتهي بالوقت.** معاينة جديدة `preview/inbox-whatsapp.html`، وقواعد قابلة للقراءة آليًا `machine/whatsapp-human-pause-policy.json`، واختبارات WA-INBOX/WA-PAUSE.
