# OnTrack Voice — مرجع المشروع والتنفيذ

> **🔎 بحث Dograh وOskot لمحرك الصوت — 4 نوفمبر 2026:** [اقرأ الدراسة التقنية المنفصلة](docs/research/DOGRAH_OSKOT_VOICE_ENGINE_2026_11_04_AR.md) · [ثريد GitHub للمتابعة (#37)](https://github.com/OnTrack-Development/OnTrack-voice/issues/37). **قرار ثابت:** شريحة Android وصوت المكالمة في الاتجاهين كما هما؛ البحث عن الـpipeline والمقاطعة والـWorkflow والأصوات المصرية المحلية، **بدون تحويل مكالمات**. هذا توثيق فقط ولا يعني دمجًا أو نشرًا.

**خطوة التجربة التالية:** [دليل الاختبار المحلي Dograh + Habibi EGY](docs/research/DOGRAH_FIRST_LOCAL_TEST_2026_11_04_AR.md) — Docker على كمبيوتر منفصل، وقياس TTS مصري بدون لمس الشريحة؛ لم تُجرَ تجارب فعلية بعد.  

**آخر تحديث: 29 أكتوبر 2026 — Africa/Cairo. المستودع Private.**

المنتج المطلوب: موظف اتصالات ذكي داخل OnTrack AI Agent Builder. لكل شركة رقم موبايلها وشريحتها وباقتها، ومعرفة ومهام وقوائم عمل ودوام وتقارير مستقلة. يستقبل الاتصالات ويجري مكالمات آلية متتابعة ويعمل حتى مع إغلاق المتصفح.

**الحالة المؤكدة الآن:** التحكم في حساب الاستضافة من خلال GitHub يعمل، وتشغيل أمر وكتابة ملف وقراءته ووجود Cron ثبتت بتقرير حي. **صوت مكالمة الشريحة لم يثبت بعد**. كود الجذر هو ديمو المتصفح v0.6.3، وليس SaaS الاتصالات المكتمل.

## مساحة SaaS والميموري

مصدر التطبيق الجديد في `saas/`، وهدفه على الهوست `/home/appontrack/public_html/saas` فقط. ملفات الرئيسية تظل كما هي. الميموري والسجلات والبرومبت **على GitHub فقط خارج مجلد التطبيق**.

- [برومبت التنفيذ الشامل](docs/execution/MASTER_EXECUTION_PROMPT_AR.md).
- [نقطة الاستكمال الحالية](docs/memory/CHECKPOINT.md).
- [الميموري وسجلات القضايا والمحاولات](docs/memory/README_AR.md).
- [تجهيز مساحة التطبيق وحدود النشر](docs/operations/SAAS_WORKSPACE_AR.md).

التجهيز يسبق التنفيذ؛ لا يوجد Laravel app داخل saas حتى يصدر أمر البدء اللاحق.

## ابدأ من هنا

1. [تعليمات أي إيجنت يعمل على الريبو](AGENTS.md).
2. [الحالة الحالية ونقطة الاستكمال](docs/project/CURRENT_STATE_AR.md).
3. [متطلبات المنتج المعتمدة](docs/project/REQUIREMENTS_AR.md).
4. [الخطة الحالية ومراحل القبول](docs/plan/IMPLEMENTATION_PLAN_AR.md).
5. [طريقة التحكم في الهوست](docs/operations/HOST_LINK_RUNBOOK_AR.md).
6. [سجل المناقشة والقرارات](docs/discovery/CONVERSATION_LOG_AR.md).

## خريطة المستودع

| المسار | محتواه وحالته |
|---|---|
| ملفات PHP و`api/` و`assets/` و`lib/` و`tests/` في الجذر | الكود القائم للديمو، حافظنا على أماكنه لتوافق النشر الحالي. |
| [docs/](docs/README_AR.md) | مرجع المشروع، الخطة، المعمارية المقترحة، التجارب، الأدلة والتشغيل. |
| [reference/agent-builder-v1.3/](reference/agent-builder-v1.3/README_START_HERE_AR.md) | حزمة المستخدم كاملة: متطلبات Laravel/MySQL، تصميم، معاينات، عقود وبيانات آلية. مواصفات وليست تطبيق Laravel منفذًا. |
| [reference/voicecall-v0.6.3/](reference/voicecall-v0.6.3/README_AR.md) | نسخة مرجعية من كود الصوت المرفوع، مع حجب أسرار محلية وتسجيل الاستثناءات. |
| [reference/archives/](reference/README_AR.md) | حزمة Agent Builder الأصلية ونسخة voicecall منقحة؛ البصمات في manifests. |
| [reference/manifests/](reference/manifests/archives.json) | جرد الملفات الأصلية والمستوردة والمستثناة وبصماتها. |
| [ops/host-link/](ops/host-link/README_AR.md) | العامل والمثبت، طابور الأوامر وتقارير التنفيذ. الفرع التشغيلي هو `ontrack-host-link`. |
| [docs/evidence/](docs/evidence/README_AR.md) | تقارير حيّة ولقطات إعداد ومخرجات محلية، مع تمييز درجة الإثبات. |
| [tools/verify_repository.py](tools/verify_repository.py) | تحقق الجرد وJSON والروابط الداخلية وبصمات الملفات المرجعية. |

## قواعد مختصرة

- آخر قرارات المالك في `docs/discovery/DECISIONS_AR.md` تعلو على المقترحات القديمة والمراجع المستوردة.
- Phone Link وVoicemeeter مستبعدان. LiveKit ليس اعتمادًا مطلوبًا. لا شراء Gateway لكل عميل كشرط إطلاق.
- المسار الاقتصادي الجاري تقييمه هو Android + الشريحة الموجودة؛ توافقه مع Redmi Note 12 Pro 5G غير مثبت.
- Gemini Live الحالي يعاد استخدامه؛ لا نغيّر محرك AI لمجرد توصيل الهاتف.
- التوكنات والمفاتيح الخاصة وإعدادات الهوست المحلية لا تدخل Git، حتى مع كون المستودع Private.
- ترتيب الوثائق لا ينشر شيئًا على الموقع. لا ترفع `docs/` و`reference/` و`ops/` إلى مجلد الموقع العام.
- لا تساوِ بين نجاح GitHub/Cron أو SIP registration وبين مرور صوت مكالمة حقيقية.

## التحقق والبدء في جلسة جديدة

```sh
python3 tools/verify_repository.py
python3 -m unittest discover -s ops/host-link/tests -v
```

للجلسة الجديدة: «اقرأ AGENTS.md وdocs/project/CURRENT_STATE_AR.md ثم استكمل من نقطة التوقف، باستخدام ربط الهوست الموجود، دون إعادة طلب التوكن أو إنشاء قناة جديدة».

وصف تشغيل ديمو المتصفح واختباراته الأصلية: [README_AR.md](README_AR.md). تعليمات Phone Link الموجودة فيه **تاريخ لسلوك الديمو وليست مواصفات المنتج الجديد**.
