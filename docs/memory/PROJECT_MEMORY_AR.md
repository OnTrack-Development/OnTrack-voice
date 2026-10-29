# ميموري المشروع الدائمة

## المالك وطريقة العمل

OnTrack Development، العربية المصرية، تنفيذ فعلي وتحديثات قصيرة. لا تعِد طلب حقائق موجودة أو صلاحيات سبق اعتمادها، ولا تكرر محاولة فاشلة دون دليل جديد. سجّل كل خطوة ومشكلة ومحاولة ونتيجة وخطوة تالية في GitHub.

## آخر تحديد للمسارات

المستخدم طلب ترك الرئيسية كما هي، ومساحة تطبيق جديدة باسم saas. ثم صحح مكان الميموري: **على GitHub فقط؛ فولدر saas على الهوست للمشروع وحده**. هذا التصحيح يتقدم على أول رد للمساعد الذي فُهم منه حفظ الوثائق على الهوست. لم تُنشر ميموري على الهوست.

- كود التطبيق GitHub: `saas/`.
- هدف الهوست: `/home/appontrack/public_html/saas`.
- الديمو الموجود: `/home/appontrack/public_html`، ملفاته القائمة محمية من التعديل في هذا العمل.
- البرومبت: `docs/execution/MASTER_EXECUTION_PROMPT_AR.md`.
- الميموري: `docs/memory/`، الحالة: `docs/project/`، الأدلة: `docs/evidence/` و`ops/host-link/results/`.
- المرحلة المطلوبة الآن **تجهيز قبل التنفيذ**؛ لا Composer install أو Laravel bootstrap أو migration/DB أو شراء/تثبيت خدمة صوت ضمن جلسة التجهيز.

## المنتج الأساسي

OnTrack AI Agent Builder SaaS مستقل عن WHMCS، عام لجميع الشركات، MySQL، عربي RTL و Light، تصميم OnTrack من المرفقات. الحزمة المرجعية 35 ملف مواصفات/تصميم/معاينات، وليست 35 إيجنت أو backend مكتملًا. المصدر الكامل في `reference/agent-builder-v1.3/`.

Tenants/workspaces/projects/agents/knowledge/CRM/inbox/smart actions/integrations/operations/usage/team/plans. Platform AI Provider Hub لمشرف OnTrack فقط، لا AI BYOK ولا model/provider controls للعميل. Google/Custom و exact model IDs واختبار اتصال/موديل حقيقي قبل التفعيل.

WhatsApp رسمي بثلاث طرق، Inbox أصلي و human reply pause15 دقيقة افتراضيًا قابلة للتعديل 1..1440 للمحادثة فقط؛ permanent human lock لا ينتهي بالوقت. لا يعتبر WhatsApp audio حلًا لمكالمة SIM.

## Voice المطلوب

قسم مستقل لكل شركة ورقم موبايلها وشريحتها وباقتها؛ inbound/outbound آليان. importCSV/XLSX/XLS/TXT/paste، معرفة ومهمة/مهام، دوام/عطلات/timezone، مكالمة متتابعة ثم delay قابل للضبط بعد نهايتها، نوت أثناء المكالمة وتقرير أمام الرقم. الحالة الهاتفية مستقلة عن نتيجة الحوار، واستئناف دون تكرار عمياني.

محرك Gemini Live القائم يُعاد استخدامه. Phone Link و Voicemeeter مرفوضان. LiveKit غير مفروض. تكلفة API المرتفعة وشراء Gateway لكلعميل مرفوضان كافتراضي. Android/Shizuku مرشح اقتصادي يحتاج دليلًا على Redmi Note12Pro5G، لا وعدًا.

رقم المدير ومعلوماته والاستفسار/الأوامر مؤجل. Caller ID وحده لا يمنح صلاحية. كل شركة معزولة في الصوت والمعرفة والبيانات والقنوات.

## ما ثبت بالفعل

GitHub Private repo `OnTrack-Development/OnTrack-voice`. قناة التحكم تعمل عبر branch `ontrack-host-link` و Python/Cron كلدقيقة، account appontrack/UID2379/server11. تقرير bootstrap ثم exec كتب ملفًا وقرأه وأكد Cron و PHP8.1.34 وديمو 0.6.3. SSH المحلي فشل Network is unreachable قبل auth، وإيجنت آخر كان يعمل بحسب المستخدم؛ لا تغيّر port7195 بسببه.

الـ worker مثبت خارج الموقع، config محلي خاص لا يُقرأ أو يُرفع. حد exec30 ثانية، ليس process manager أو ناقل صوت. تقارير الهوست تذهب إلى GitHub، وتُقرأ من الفرع الحي قبل إصدار المهمة التالية.

## ما لم يثبت

صوت SIM ثنائي الاتجاه و Gemini عبره، نجاح Voice SIP Agent على جهاز المالك، PBX أو daemon audio، سعة الاتصالات،LaravelSaaS منفذ، Meta live، أو جاهزية dependencies. لا تخلط حالات الأدلة.

فحص تجهيز saas لاحظ مسارات PHP83/84 محتملة و PATH مختلفة للـ Cron، و Composer/Node/NPM غير ظاهرة. هذه ملاحظات بيئة، لا تثبيت ناجح أو إثبات إصدار/امتدادات.

## مراجع التفاصيل

التفاصيل الكاملة لا تُختصر إلى ذاكرة هذه الصفحة: اقرأ سجل المناقشة والقرارات والتجارب والخطة والـ handoff والمصفوفات. CURRENT_STATE و CHECKPOINT يحددان أين وصلنا الآن، و ISSUE_REGISTER يحفظ ما بقي ومحاولاته. كل استكمال يحدّثهم بالأدلة.
