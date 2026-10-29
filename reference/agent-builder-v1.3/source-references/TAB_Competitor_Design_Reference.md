# The Agent Builder — تحليل المنافس ومرجع تجربة المستخدم والتصميم لـ OnTrack AI Agent Builder

**نسخة البحث:** 27 أكتوبر 2026  
**الحالة:** جرد روابط عامة مؤكد + تحليل محتوى ووثائق + تحليل بصري جزئي.  
**استخدام المستند:** المرجع المعتمد لهذه المحادثة أثناء تصميم منتج OnTrack AI Agent Builder، لا يُعد استنساخًا للتصميم أو كوده.  
**تنبيه منهجي مهم:** تعذّر عمل لقطة متصفح مرئية فعلية لجميع الصفحات وقراءة CSS computed styles؛ لذلك لا أدّعي معرفة قيم الألوان Hex أو أسماء الخطوط أو أنصاف أقطار البطاقات أو الحركة بالمللي ثانية. التفاصيل البصرية المذكورة أدناه إما ملاحظة من أصل صورة الموقع، أو استنتاج من بنية الصفحات، أو **قرار مقترح لـOnTrack** موضح باسمه. كما أن التطبيق الداخلي بعد الدخول لم يُختبر بحساب مستخدم. لا توجد خريطة موقع XML صالحة للفحص، وبالتالي هذا فهرس شامل للصفحات المكتشفة من التصفح وروابط الوثائق، وليس ضمانًا بغياب صفحات غير مرتبطة من التنقل العام.

## خلاصة تنفيذية

- TAB منصة SaaS لموظفي دعم ومبيعات بالذكاء الاصطناعي، متصلة بقنوات Web/WhatsApp/Instagram/Messenger، مع CRM أساسي، Knowledge Base، Analytics، Workspaces، تجربة Playground، وإجراءات عبر Shopify/Salla/Calendly/External REST API.
- ميزته الحقيقية: **رحلة شراء/إعداد قصيرة جدًا** + تسويق متعدد الصناعات + كل القنوات في باقات مختلفة + توثيق يشرح التنفيذ خطوة خطوة.
- نقاط تفوق ممكنة لـOnTrack: Deep Integrations أصلية للارافيل والووردبريس وWHMCS (وليس مجرد embed)، أزرار ديناميكية واعية بالحالة، سياسة الصلاحيات قبل الأوامر، BYO AI Provider يشمل Google وCustom مع اختبار الاتصال والموديلات، Routing/Reviewer، مسارات Business Workflows، Operations Room، تشخيص وقابلية ملاحظة مستوى Trace، SaaS عربي/إنجليزي فعلي، Templates جاهزة لكل صناعة.
- **تمييز مهم:** TAB نفسه يملك External REST API configurable ويدعم import JSON API spec، وأزرار WhatsApp Templates (quick replies وما شابه). لا يجوز الادعاء بأنه لا يقدم أي تكامل مخصص أو أزرار. نقطة التميّز المقصودة لدينا: **التحكم العميق بالبيانات والصلاحيات وDynamic Buttons وفق الحالة الحية**.

## وصف تجربة الصفحة الرئيسية (بنية موثقة)

1. Hero: عنوان كبير جدًا يبدأ بـ Meet TAB؛ رسالة مركزية حول دعم العملاء بالذكاء الاصطناعي المتاح دائمًا؛ جملة فرعية، CTA أساسي Start Free Now؛ عناصر محادثة/رسائل عائمة؛ فيديو في الواجهة.
2. Why TAB / Our Edge: ست فوائد مرتبة: كل القنوات، إعداد سريع، CRM، 24/7، أمان، موديلات AI. بعض المحتوى مكرر في العرض المستخرج نتيجة نسخ متجاورة لعناصر responsive/animation؛ ليس دليلًا بصريًا على أن المستخدم يراها مكررة.
3. How TAB Works: أربع خطوات بالترتيب (أنشئ Workspace → درّب بالملفات/FAQ → خصص الوكيل → انشر على القنوات).
4. Key Features / Inside TAB: No-Code Agent Builder، Smart Knowledge Base، Multi-channel، CRM، Enterprise Ready؛ لكل ميزة عنوان ووصف قصير.
5. Industries: قائمة ست صناعات على الرئيسية، مع اختيار صناعة وشرح سيناريو وإجراء Book a demo؛ صفحة Industries منفصلة بها سبعة قطاعات منها Healthcare.
6. Testimonials: Carousel أو مجموعة اقتباسات عملاء؛ لم يمكن التحقق من صحتها أو أصحابها من النص المستخرج.
7. Conversion CTA: Ready To Automate Customer Support → Start Free Now مع No Credit Card Required.
8. Footer: أعمدة روابط Sitemap + Policies + Products + Contacts + شارة ISO وصور اجتماعية.

### وصف الأصول البصرية المتحقق منها

- صفحات الموقع تشير إلى **خطوط خلفية مرسومة كـSVG** مثل `main-left-mv.svg` وخطوط لليمين والمنتصف؛ فهذا يدل على زخارف خطوط/مسارات وليست مجرد صورة خلفية مسطحة.
- الـHero يحتوي صور Bubble مثل `message-order.svg` ورسالة محادثة أخرى، ما يعزز ثيمة الرسائل العائمة.
- أصل صورة يد ثلاثية الأبعاد من الموقع (`https://storage.theagentbuilder.ai/assets/modal-images/hand.png`) فيه يد بلون بشري فاتح وردي/خوخي، سوار برتقالي، وبرق أصفر. **لون الأصل ليس قياسًا للوحة ألوان الواجهة ككل**.
- صفحات الصناعة تحتوي صور أو رسومات SVG موضوعة في Hero بحسب القطاع، مثل `/assets/landing/industries/ecommerce/hero.svg`.
- لا توجد لدينا صور موثوقة كافية لتأكيد خط الواجهة، ألوان كافة الخلفيات، قياسات الأزرار، hover animations، breakpoints أو responsive spacing؛ يجب قياسها من متصفح حقيقي أو أدوات DevTools قبل وصفها بأنها 'مطابقة 100%'.

## وصف الأنماط المشتركة للصفحات

| قالب الصفحة | ترتيب السكشنات المؤكد | ملاحظات عملية |
|---|---|---|
| Home | Hero → benefits → four steps → features → industries → testimonials → CTA → footer | تحويل سريع للمنتج مع Social Proof |
| Pricing | عنوان وسعر بناءً على المحادثات → Monthly/Yearly toggle → Enterprise → تعريف Conversation → المميزات → 3 model tiers → footer | اعتماد نقطة حساب واضحة وتوعية المستخدم بالحدود |
| Industries Index | عنوان موحد → سبع بطاقات قطاع → CTA عام → footer | Landing SEO بفروع صناعية |
| Real Estate / Education / Healthcare / Logistics / Hospitality / Agencies | Hero متخصص → 3 KPI claims → 4 ألم العميل → 4 workflows → قنوات → 4 FAQ → صناعات أخرى → CTA → footer | قالب متكرر يمكن بناؤه Data Driven بمحتوى مختلف لكل قطاع؛ أرقام النتائج الواردة تسويقية غير مثبتة بدراسات مستقلة |
| E-commerce | Hero وإيضاح بصرية → pain points → ربط المتجر/المعرفة → with vs without comparison → مراحل رحلة الشراء → CTA → footer | صفحة أعمق تخصيصًا من بقية القطاعات |
| Help | نموذج اتصال + تصنيفات FAQ قابلة للتبديل + فوتر | الـFAQ يغطي إعداد ودعم وتكامل وسياسات |
| Signup / Login | نماذج مصادقة مركزة، خيارات Google Sign in وتوجيه متبادل | لا يمكن اختصار onboarding بدون مراعاة التحقق والهوية |
| Docs | شريط تنقل جانبي موضوعي + محتوى Markdown موحد + Table of Contents + Next/Previous | يوحي ببنية Docusaurus ذات قابلية قراءة وملاحة جيدة؛ ليس لقطة لفعلية المنتج الداخلي |

## التسعير والـUsage

- الموقع يشرح Conversation-based billing: المحادثة تُعاد تهيئتها بعد أول حد يصل إليه الحوار: 24 ساعة أو 30 رسالة أو 15,000 tokens؛ هذا **شرحهم المنشور** وليس اقتراحنا.
- التخطيط الشهري والسنوي مع عرض توفير 20% سنويًا، وخيار Enterprise.
- طبقًا للدليل، Free: وكيل واحد و50 conversation credits؛ Lite: وكيلان؛ Starter: 3؛ Growth: 10.
- مستويات الموديل Standard 1x وPremium 3x وFlagship 7x؛ صلاحية اختيار الفئة مرتبطة بالباقة.
- صفحات التسعير المعتمدة على React لم تُظهر جميع الأسعار الرقمية أثناء الاستخراج؛ **لا ندوّن أسعارًا افتراضية**.

## تجربة الوكيل ولوحة التحكم كما توثقها TAB (وليست مشاهدة حيّة)

- Agent Creation: `Agents` في Sidebar → `Create New Agent` → Wizard من خطوتين؛ Name داخلي، Public Name، Model Tier، Tone، Lead Capture، Role، Instructions، Restricted Topics.
- Data Sources: FAQ / Files / Text؛ مصادر عامة قابلة لإعادة الاستخدام ومصادر خاصة لكل وكيل؛ **تتطلب Re-Train يدويًا** بعد تغييرات المحتوى.
- Agent Detail: تبويبات Agent Specifications / Data Sources / Channels / Integrations؛ Playground على يمين الصفحة للتجربة.
- Channel Widget: محرّر منفصل لألوان Header والنص وفقاعتي الرسائل وحقل الإدخال، صورة الوكيل، Greeting خارجي وWelcome داخلي، placeholder، مكان يمين/يسار، Preview، Watermark.
- CRM: قائمة عملاء، Conversations، بيانات التواصل، فلاتر القناة/الوكيل/التاريخ/الحالة، استيراد أو تصدير CSV، Human Takeover ومن ثم Mark Resolved أو Hand to Agent.
- Analytics: حجم المحادثات، Resolution Rate، Peak Hour، تاريخ المحادثات، القنوات، Sentiment، الموضوعات؛ **صور الوثائق المرتبطة بهذا الجزء Placeholder مكتوب عليها Screenshot coming soon**.
- Integrations: Shopify وSalla وCalendly، External REST API per agent: اسم، وصف، Base URL، endpoint path، متغيرات parameters، No Auth/Bearer/API Key/Basic، headers، GET/POST، body، custom instructions؛ Import Collection من JSON spec. تفعيل API بعد إضافته واختباره عبر Playground.
- Workspaces: اختيار مساحة، دعوات مستخدمين، صلاحيات Team وفق الوثائق. التسويق يذكر SSO/RBAC، بينما Roadmap يدرج Granular Permissions مستقبلًا؛ **لا تعتبر كل مزاعم Enterprise متحققة بدون اختبار**.
- WhatsApp: إعداد WhatsApp Business من Meta، إدارة قوالب رسائل (وفق What's New يونيو 2026) مع أزرار quick reply / URL / call / copy offer code، وترتيبها. لا يوجد دليل واضح في المصادر التي تمت مراجعتها على محرّك تغيّر أزرار context-aware فور تحديث state من ERP/CRM كما نريده.

## أين نتفوق في OnTrack AI Agent Builder؟ (متطلبات معتمدة للنقاش، غير منفذة)

| المحور | TAB حسب الوثائق | OnTrack المستهدف |
|---|---|---|
| البنية | وكلاء SaaS / Workspace | Multi-Tenant + Workspaces + Projects + Environments منفصلة + Agent per workspace/project |
| المحرك | Agent Builder / Playground | Orchestrator + Specialist Executors + mandatory Reviewer + صلاحيات أفعال |
| القنوات | Web/WhatsApp/IG/Messenger | نفس القنوات + لاحقًا Voice/Email/SMS/Telegram بقنوات قابلة للتوصيل |
| أزرار المحادثة | Widget customizations + WhatsApp template buttons | Dynamic Contextual Actions حسب state، role، identity، outstanding workflow + fallback لكل Channel |
| Laravel | API/web embed عام | Composer Package، Guards، Eloquent Resources safe، Events، Signed Webhooks، Action Contracts |
| WordPress | دليل لإضافة كود Widget | Plugin رسمي: OAuth، معرف المستخدم، WooCommerce، تنفيذ إجراءات، قواعد وأحداث حية |
| WHMCS | ليس تكاملًا أصليًا موثقًا في الدليل | OnTrack AI Support addon + ملكية الخدمات + Ticket/Invoice read-only + human lock |
| Any App | External API configured (GET/POST) | Integration Builder/OpenAPI import + REST + signed webhooks + tool permissions + tool dry run + schema validation + rate limits |
| Providers | model tiers ضمن باقات TAB | Google Gemini + Custom Provider + optional Omnira A/B + per-role model + test connection/model + secret vault |
| Knowledge | Files/FAQ/Text + retrain manual | Versioned KB + ingestion status + change detection + source attribution + evaluation gates + isolation |
| CRM | Customers/Orders/Conversations | CRM + pipeline + segments + workflow states + identity resolution (بضوابط خصوصية) |
| Reliability | Analytics ومؤشرات عامة | Traces، أسباب الإخفاق، retry/circuit breaker، idempotency، task queue، اختبارات E2E قابلة للتكرار |
| تصميم | موقع Landing تسويقي سهل المسح | هوية OnTrack مستقلة، تجربة سوداء/بيضاء/أحمر، مبنية على عرض حقيقي للمنتج لا Illustrations فقط |

## معمارية صفحات OnTrack الجديدة المقترحة

### الموقع العام (Public)
Home، Product، How It Works، Agent Builder، Dynamic Actions، Knowledge Base، Omnichannel Inbox، CRM، Integrations Hub، Developers/API، 7+ Industries، Pricing، Security، Enterprise، Case Studies، Documentation، Blog/Changelog، Help، Contact/Demo، Auth، Status Page.

### التطبيق بعد تسجيل الدخول (SaaS)
Workspace Switcher، Overview، Projects، Agents، New Agent Wizard، Agent Studio، Instructions & Guardrails، Knowledge Sources، Model Providers، Model Catalog، Playground، Evals & Tests، Channels، Widget Designer، Dynamic Buttons Builder، Integrations، API/OpenAPI Import، Webhooks، Actions Tool Studio، Permissions، CRM/Leads، Live Inbox، Human Handoff Queue، Analytics، Operations Room، Audit/Traces، Usage & Billing، Team/Roles، Settings، Developer Keys، Deployments/Environments.

### تفاصيل Dynamic Buttons المستهدفة

كل Button يحمل ID + label + channel rendering + position + icon + guard predicate + authorized action + payload schema + expiration + locale. ينتقل State Machine مثل `NEW → VERIFIED → NEEDS_SELECTION → ACTION_PENDING → DONE | HANDOFF`. تحديث الحالة يأتي من حقيقة backend، وليس مجرد prompt. تُعاد تقييم الخيارات بعد كل رسالة، webhook، ونتيجة tool. في واتساب نراعي قيود Meta على Interactive Messages؛ fallback نصي عند عدم دعم نوع الزر. الإجراء المحمي يتطلب tenant-scope وverified identity وpermission وidempotency key وaudit trail وconfirmation عند الحاجة.

## قواعد التصميم المستهدفة لـOnTrack (اقتراحنا وليس وصف TAB)

- هوية Black/White/OnTrack Red، Light + Dark بالتساوي. استخدام الأحمر كدلالة للإجراء، لا إغراق واجهة بالأحمر.
- أسلوب Editorial + cinematic ولكن Dashboard عملية للغاية: grid واضح، كثافة معلومات حسب السياق، بطاقات متجانسة، خطوط عربية/إنجليزية جيدة، حركة مقتصدة مع `prefers-reduced-motion`.
- Landing: Hero بواجهة Agent Studio حقيقية تفاعلية وليست Mockups مزيفة؛ visual flows لرسائل، أزرار متغيرة، واتساب، integrations، وقائمة نتائج E2E؛ sections واسعة، تباين مرتفع، screenshots حديثة، CTA واضح.
- Dashboard: Sidebar مرنة، Workspace+Project selector، breadcrumb، Command Palette، search، notification center، real-time status، right-side inspector لأدلة Tool Calls، logs برموز ألوان مستهدفة، sticky action header.
- في كل صفحة: empty/loading/error/success states، validation inline، save indicator، undo للعمليات القابلة للإلغاء، tooltips، i18n AR RTL / EN LTR، keyboard navigation، responsiveness، accessibility contrast.
- أرقام الألوان والخطوط الدقيقة لـTAB **غير مستخرجة**. عند توفر Screenshot/DevTools من الموقع سنلحق Appendix يحتوي قياسات مؤكدة مقارنة بـOnTrack tokens.

## المخاطر والحدود

1. **لا وصول موثق للتطبيق بعد الدخول**؛ أسماء الأقسام والوظائف الداخلية مأخوذة من دليل TAB الرسمي، وليس تجربة استخدام بحساب مفعل.
2. **لا وصول CSS حقيقي / screenshot كامل**؛ يجب تجنب الادعاء بالاستنساخ البصري pixel perfect من البحث النصي.
3. **بعض screenshots في Docs وهمية placeholder**، ولذلك لا يُعتد بها لوصف شكل Dashboard الفعلي.
4. **المزاعم التجارية عن نسب التحسين/SSO/SLA لم تُثبت خارجيًا**.
5. **هذه جرد صفحات مكتشفة**؛ لا يدعي اكتشاف URLs غير مربوطة أو وضع Login App الخاص.
6. من الأفضل بناء تصميم أصلِي مستلهم من الوضوح والتسويق وليس نسخ الأصول المحمية أو الشعارات أو النصوص.

## المصادر الرئيسية

- [الموقع الرسمي](https://theagentbuilder.ai/)
- [Pricing](https://theagentbuilder.ai/pricing)
- [Industries](https://theagentbuilder.ai/industries)
- [Documentation](https://docs.theagentbuilder.ai/)
- [Agent Specifications](https://docs.theagentbuilder.ai/agents/agent-specifications/)
- [External API Integration](https://docs.theagentbuilder.ai/deploying-your-agent/external-api-integration/)
- [Customizing Chat Widget](https://docs.theagentbuilder.ai/deploying-your-agent/customizing-appearance/)
- [Managing Customers](https://docs.theagentbuilder.ai/managing-leads/overview/)
- [Analytics](https://docs.theagentbuilder.ai/analytics/overview/)
- [Whats New](https://docs.theagentbuilder.ai/help/whats-new/)
- [Roadmap](https://docs.theagentbuilder.ai/help/roadmap/)

---

# فهرس الروابط المكتشفة (Verified Navigational Inventory)

كل عنوان أدناه رابط صفحة مستقلة؛ فهرس الدليل يشمل الصفحات الداخلية للدليل وتعليمات التثبيت المنبثقة من القوائم. `login` أمكن الوصول لعنوانه ونصه من رابط `signup`، لكن لم تُفحص لوحة المستخدم بعد تسجيل الدخول.


## أولًا: الموقع العام — 16 صفحة

- [الرئيسية](https://theagentbuilder.ai/)
- [الأسعار](https://theagentbuilder.ai/pricing)
- [القطاعات](https://theagentbuilder.ai/industries)
- [التجارة الإلكترونية](https://theagentbuilder.ai/industries/ecommerce)
- [العقارات](https://theagentbuilder.ai/industries/real-estate)
- [المطاعم والفنادق](https://theagentbuilder.ai/industries/hospitality)
- [التعليم](https://theagentbuilder.ai/industries/education)
- [الصحة والعيادات](https://theagentbuilder.ai/industries/healthcare)
- [الخدمات اللوجستية](https://theagentbuilder.ai/industries/logistics)
- [وكالات التسويق](https://theagentbuilder.ai/industries/marketing-agencies)
- [المساعدة والتواصل](https://theagentbuilder.ai/help)
- [إنشاء حساب](https://theagentbuilder.ai/signup)
- [تسجيل الدخول](https://theagentbuilder.ai/login)
- [الشروط والأحكام](https://theagentbuilder.ai/legal/terms-and-conditions)
- [الخصوصية](https://theagentbuilder.ai/legal/privacy-policy)
- [سياسة الاسترجاع](https://theagentbuilder.ai/legal/refund-policy)

## المدخل — 1 صفحة

- [الصفحة الرئيسية للدليل](https://docs.theagentbuilder.ai/)

## البداية والحساب — 5 صفحة

- [البدء السريع](https://docs.theagentbuilder.ai/getting-started/quick-start/)
- [إعداد الحساب](https://docs.theagentbuilder.ai/getting-started/account-setup/)
- [مساحات العمل](https://docs.theagentbuilder.ai/getting-started/workspace/)
- [إنشاء أول وكيل بالتفصيل](https://docs.theagentbuilder.ai/getting-started/first-agent-tutorial/)
- [الاعتمادات/المحادثات](https://docs.theagentbuilder.ai/getting-started/understanding-conversation-credits/)

## الوكلاء — 6 صفحة

- [مفهوم الوكيل](https://docs.theagentbuilder.ai/agents/overview/)
- [إنشاء وكيل](https://docs.theagentbuilder.ai/agents/creating-agents/)
- [إعدادات الوكيل](https://docs.theagentbuilder.ai/agents/agent-specifications/)
- [Playground اختبار الوكيل](https://docs.theagentbuilder.ai/agents/testing-playground/)
- [تعديل الوكيل](https://docs.theagentbuilder.ai/agents/editing-agents/)
- [حذف الوكيل](https://docs.theagentbuilder.ai/agents/deleting-agents/)

## قاعدة المعرفة والتدريب — 6 صفحة

- [نظرة عامة على التدريب](https://docs.theagentbuilder.ai/training-your-agent/overview/)
- [إضافة مصادر المعرفة](https://docs.theagentbuilder.ai/training-your-agent/adding-knowledge/)
- [رفع الملفات](https://docs.theagentbuilder.ai/training-your-agent/uploading-documents/)
- [إضافة FAQ](https://docs.theagentbuilder.ai/training-your-agent/creating-faqs/)
- [عملية التدريب](https://docs.theagentbuilder.ai/training-your-agent/training-process/)
- [تحسين الردود](https://docs.theagentbuilder.ai/training-your-agent/improving-responses/)

## القنوات والتكامل والنشر — 15 صفحة

- [نشر الوكيل](https://docs.theagentbuilder.ai/deploying-your-agent/overview/)
- [شات الموقع](https://docs.theagentbuilder.ai/deploying-your-agent/website-chat-widget/)
- [واتساب](https://docs.theagentbuilder.ai/deploying-your-agent/whatsapp-integration/)
- [إنستجرام](https://docs.theagentbuilder.ai/deploying-your-agent/instagram-integration/)
- [ماسنجر](https://docs.theagentbuilder.ai/deploying-your-agent/messenger-integration/)
- [Shopify](https://docs.theagentbuilder.ai/deploying-your-agent/shopify-integration/)
- [Salla](https://docs.theagentbuilder.ai/deploying-your-agent/salla-integration/)
- [Calendly](https://docs.theagentbuilder.ai/deploying-your-agent/calendly-integration/)
- [External API](https://docs.theagentbuilder.ai/deploying-your-agent/external-api-integration/)
- [تخصيص شكل الويدجت](https://docs.theagentbuilder.ai/deploying-your-agent/customizing-appearance/)
- [تثبيت WordPress](https://docs.theagentbuilder.ai/deploying-your-agent/installation-guides/wordpress/)
- [تثبيت Shopify](https://docs.theagentbuilder.ai/deploying-your-agent/installation-guides/shopify/)
- [تثبيت Wix](https://docs.theagentbuilder.ai/deploying-your-agent/installation-guides/wix/)
- [تثبيت Squarespace](https://docs.theagentbuilder.ai/deploying-your-agent/installation-guides/squarespace/)
- [تثبيت موقع مخصص](https://docs.theagentbuilder.ai/deploying-your-agent/installation-guides/custom-website/)

## العملاء والرسائل — 4 صفحة

- [إدارة العملاء](https://docs.theagentbuilder.ai/managing-leads/overview/)
- [عرض المحادثات](https://docs.theagentbuilder.ai/managing-leads/viewing-conversations/)
- [تصدير بيانات العملاء](https://docs.theagentbuilder.ai/managing-leads/exporting-leads/)
- [أفضل ممارسات CRM](https://docs.theagentbuilder.ai/managing-leads/lead-best-practices/)

## التحليلات — 2 صفحة

- [Analytics Dashboard](https://docs.theagentbuilder.ai/analytics/overview/)
- [شرح المؤشرات](https://docs.theagentbuilder.ai/analytics/understanding-your-metrics/)

## التكاملات العامة — 3 صفحة

- [نظرة عامة](https://docs.theagentbuilder.ai/integrations/overview/)
- [ربط التطبيقات](https://docs.theagentbuilder.ai/integrations/connecting-apps/)
- [Salesforce](https://docs.theagentbuilder.ai/integrations/salesforce/)

## الإعدادات والفواتير — 6 صفحة

- [إعدادات الحساب](https://docs.theagentbuilder.ai/settings/account/)
- [مساحة العمل](https://docs.theagentbuilder.ai/settings/workspace-settings/)
- [إدارة الفريق](https://docs.theagentbuilder.ai/settings/managing-team/)
- [الاشتراكات](https://docs.theagentbuilder.ai/settings/billing/)
- [الاستهلاك](https://docs.theagentbuilder.ai/settings/usage/)
- [تفضيلات الإشعارات](https://docs.theagentbuilder.ai/settings/notifications/)

## حالات الاستخدام — 5 صفحة

- [الدليل العام](https://docs.theagentbuilder.ai/use-cases/overview/)
- [بوت الدعم](https://docs.theagentbuilder.ai/use-cases/customer-support-bot/)
- [بوت المبيعات](https://docs.theagentbuilder.ai/use-cases/sales-lead-bot/)
- [بوت المتجر](https://docs.theagentbuilder.ai/use-cases/ecommerce-bot/)
- [بوت الحجوزات](https://docs.theagentbuilder.ai/use-cases/appointment-booking/)

## المشكلات — 6 صفحة

- [أخطاء شائعة](https://docs.theagentbuilder.ai/troubleshooting/common-issues/)
- [الوكيل لا يرد](https://docs.theagentbuilder.ai/troubleshooting/agent-not-responding/)
- [مشكلات الشات](https://docs.theagentbuilder.ai/troubleshooting/chat-widget-issues/)
- [مشكلات واتساب](https://docs.theagentbuilder.ai/troubleshooting/whatsapp-issues/)
- [أخطاء التدريب](https://docs.theagentbuilder.ai/troubleshooting/training-errors/)
- [مشكلات المدفوعات](https://docs.theagentbuilder.ai/troubleshooting/payment-issues/)

## الدعم والتغييرات — 5 صفحة

- [الأسئلة الشائعة](https://docs.theagentbuilder.ai/help/faq/)
- [المصطلحات](https://docs.theagentbuilder.ai/help/glossary/)
- [الدعم](https://docs.theagentbuilder.ai/help/getting-support/)
- [ما الجديد](https://docs.theagentbuilder.ai/help/whats-new/)
- [خارطة الطريق](https://docs.theagentbuilder.ai/help/roadmap/)

**الإجمالي:** 16 صفحة عامة + 64 صفحة توثيق = **80 رابطًا مؤكّدًا/مكتشفًا**. ليس إجمالي Routes النظام الداخلية.
