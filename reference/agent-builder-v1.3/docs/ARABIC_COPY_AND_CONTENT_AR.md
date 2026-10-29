# Arabic Content & Product Voice (ar-EG / RTL)

## Brand voice
Professional, clear, efficient, Arabic-first. Natural Arabic with product terminology understandable in Egypt and the broader Arabic-speaking region. Avoid exaggerated AI hype, false claims, stock testimonials, invented uptime/customer counts, bad Egyptian dialect in legally significant buttons, and claiming functionality not yet connected. Do not write English UI placeholders in Arabic interface. Use short functional labels, complete accessible explanations.

## Marketing approved first-pass copy (original)
Brand lockup: `OnTrack AI` / subtitle `منصة بناء وتشغيل الوكلاء الذكيين`.
- Hero kicker: `ذكاء اصطناعي يفهم شغلك، مش بس بيرد.`
- Hero title: `حوّل كل محادثة إلى تجربة أذكى.`
- Hero body: `أنشئ وكلاء ذكاء اصطناعي، اربطهم بمشروعاتك وقواعد معرفتك، وحدد الإجراءات المسموح بها. كل ده من مكان واحد، ولأي نشاط تجاري.`
- Primary CTA: `أنشئ وكيلك`
- Secondary CTA: `شاهد التجربة`
- Capabilities: `وكلاء مخصصون` / `أزرار تتغير حسب الحالة` / `ربط API آمن` / `مراقبة واضحة`
- Workflow heading: `من فكرة إلى وكيل جاهز للتجربة` / steps: `أنشئ مساحتك`، `صمّم وكيلك`، `اربط المعرفة والأدوات`، `اختبر وانشر`.
- Actions feature: `مش مجرد رد. خطوة تالية مناسبة لكل حالة.`
- Integration feature: `أي مشروع برمجي. نفس محرك الذكاء.`
- Knowledge feature: `المعلومة الصح، من المصدر المصرح به.`
- Customer AI heading: `الذكاء الاصطناعي جاهز ضمن باقتك، من OnTrack.` **ممنوع ذكر اختيار المزود/الموديل أو إدخال AI API Key للعميل.**
- Handoff feature: `ولما يلزم تدخل بشري، التحكم يرجع لفريقك.`
- Security heading: `أمان كل قرار، قبل تنفيذ أي إجراء.`
- Industries: `منصة واحدة. فرص لا حدود لها.` / `تشتغل مع الاستضافة، التجارة، العقارات، التعليم، المطاعم، الخدمات، وأي قطاع آخر.`
- Pricing title: `ابدأ بالقدرات اللي تحتاجها` / description `خطط مرنة قابلة للتخصيص. الأسعار النهائية تتحدد قبل تفعيل الفوترة.` / CTA `تواصل لمعرفة الخطط`.
- Final CTA: `موظف ذكي، على مقاس مشروعك.` / `ابدأ بتجربة الوكيل قبل إطلاقه للعملاء.`

## Dashboard labels
`نظرة عامة`، `المشروعات`، `الوكلاء`، `إنشاء وكيل`، `استوديو الوكيل`، `المعرفة`، `إمكانيات الباقة`، `اختبار المحادثة`، `الأزرار الذكية`، `التكاملات`، `القنوات`، `صندوق الوارد`، `العملاء`، `غرفة العمليات`، `التحليلات`، `الاستهلاك`، `الفريق والصلاحيات`، `الإعدادات`، `سجل التغييرات`.

## Creation wizard
`معلومات أساسية` → `الدور والتعليمات` → `المعرفة` → `الإجراءات والقنوات` → `الاختبار` → `النشر`. Step help text must explain what remains and never force third-party keys without a clear `تجربة محلية` option. **OnTrack is the provider: no customer keys required.**

## Provider profile copy — ONLY ONTRACK SUPER ADMIN `/control`, NEVER TENANT
- `إضافة مزوّد جديد` / `Google Gemini` / `مزوّد مخصص` / `اسم المزوّد` / `رابط واجهة API` / `مفتاح الاتصال` / `نوع البروتوكول`.
- Buttons: `حفظ كمسودة` → `اختبار الاتصال` → `إضافة موديل` → `اختبار الموديل` → `تفعيل`.
- Disabled models message: `اختبر الاتصال أولًا قبل إضافة الموديلات.`
- Connection failed: `تعذر التحقق من الاتصال. راجع العنوان والمفتاح ثم أعد المحاولة.`
- Auth failed: `مفتاح الاتصال غير مقبول لدى المزوّد (401/403).` (show HTTP category only if verified)
- Failed generation: `تم الوصول للمزوّد لكن اختبار توليد الرد لم ينجح؛ الموديل لم يُفعّل.`
- Success: `تم اختبار الموديل بنجاح ويمكن اختياره الآن.` (only after actual returned output)
- Readback: `تم حفظ إعدادات المزوّد.` only after successful server response and actual reload.

## Smart buttons & Action copy
- `أزرار العميل`، `حالة المحادثة`، `شرط الظهور`، `صلاحية التنفيذ`، `يحتاج تأكيدًا`، `الإجراء المرتبط`، `معاينة الحالة`، `سجل التنفيذ`.
- `تأكيد` / `إلغاء` / `تم تنفيذ الإجراء` / `لا يمكن تنفيذ الإجراء قبل تأكيد هويتك` / `تم تغيير حالة الطلب؛ حدّث المحادثة وجرّب مرة أخرى`.
- `ربط مشروع جديد` / `نسخ كود الدمج` / `اختبار Webhook` / `تفويض الموظف` / `استلام المحادثة` / `إرجاع التحكم للوكيل`.

## Transparent status taxonomy
`جاهز` only verified & configured, `تجريبي` synthetic UI/system, `غير متصل` no live connector, `بانتظار الاختبار` saved but not verified, `غير متاح حاليًا` not shipped, `فشل` actual failure. Never color badge green because view mounted.

## Localization engineering
- Laravel PHP `lang/ar/*.php` / `lang/en/*.php` structure and frontend typed messages using translation keys; Arabic complete, English placeholders only inside translation files (not user UI). Locale switch infrastructure prepared but English menu action hidden until QA completes.
- RTL `dir=rtl` and Arabic numerals formatting appropriate to locale, but price, models, UUID, code snippets, URLs explicitly LTR with `bdi`/CSS `unicode-bidi`.
- No auto-translate medical/legal/security claims; human-reviewed strings when expansion occurs.
