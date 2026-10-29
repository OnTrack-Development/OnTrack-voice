# حالة تنفيذ OnTrack SaaS

**آخر تحديث: 29 أكتوبر 2026 — بعد شريحة Smart Actions**

## الحالة الدقيقة

| النطاق | الحالة |
|---|---|
| مصدر التطبيق `saas/` في GitHub | `IMPLEMENTED_LOCAL` |
| اختبارات التطبيق | `TESTED_LOCAL` — 17 اختبارًا / 57 assertion، ناجحة |
| النشر على `public_html/saas` | `BLOCKED_EXTERNAL` — عامل host-link لا يستجيب |
| قاعدة بيانات الإنتاج | `PENDING_EXTERNAL` — لا بيانات اعتماد MySQL |
| النطاق الفرعي للمنصة | `PENDING_OWNER_DECISION` |
| AI Providers | `IMPLEMENTED_LOCAL` (بيانات + عزل + تشفير) — اتصال حي `PENDING_CREDENTIALS` |
| Knowledge | `IMPLEMENTED_LOCAL` — فهرسة نصية/تقسيم قطع، بلا ادعاء بحث متجهي |
| Smart Actions | `IMPLEMENTED_LOCAL` — قواعد أزرار مقيدة بالحالة والصلاحية + إثبات النداء/التكرار |
| WhatsApp / Inbox | `PLANNED` |
| Voice | `PLANNED` — ينتظر إثبات الجهاز |

## الشرائح المنفذة

### 1. Foundation / Auth / Tenancy
هبوط RTL، توكنز تصميم، تسجيل ينشئ tenant+workspace+owner، دخول/خروج مع rate limit، Dashboard ببيانات حقيقية، 5 migrations، عزل tenant.

### 2. Projects + Agent Studio
مشروعات (list/create/store) بملكية tenant/workspace، واستوديو وكيل (إنشاء/عرض/تعديل/نشر) مع منع النشر بلا تعليمات ورفض project_id الأجنبي.

### 3. Platform Provider Hub
`platform_admin_grants` مستقلة عن الـtenants، `platform_ai_provider_profiles` بلا `tenant_id` مع تشفير `Crypt`، موديلات بالمعرّف الحرفي، تدقيق، و403 لأي مستخدم tenant على `/control/ai/*`.

### 4. Knowledge Hub
`knowledge_sources` / `knowledge_source_versions` / `knowledge_chunks`، تقسيم النص إلى قطع بمحتوى محفوظ، حالات `processing → indexed`، رفض `project_id` الأجنبي 422، ورفض الكتابة من خارج المساحة 403.

### 5. Smart Actions
`button_rules` مقيدة بـ tenant/workspace/project مع `business_state_in` و`condition_ast` منفصلين، `button_presentations` برمز عرض موقّت، `button_invocations` بمفتاح idempotency فريد لكل tenant، ورفض أي مشروع/قاعدة أجنبية (422/404)، وإرجاع `duplicate` عند تكرار نفس المفتاح.


`knowledge_sources` / `knowledge_source_versions` / `knowledge_chunks`، تقسيم النص إلى قطع بمحتوى محفوظ، حالات `processing → indexed`، رفض `project_id` الأجنبي 422، ورفض الكتابة من خارج المساحة 403.

## أدلة الاختبار الفعلية

البيئة: Laravel 12.70.2 / PHP 8.2.12 / SQLite.

```text
php artisan migrate --force
9 custom migrations DONE (000001..000009)

php artisan test
15 passed (49 assertions)
- AgentStudioTest: 4
- ControlProviderHubTest: 3
- KnowledgeHubTest: 3
- SmartActionTest: 2
- TenancyIsolationTest: 3
- Example tests: 2

PHP lint: all saas/app, saas/database, saas/routes, saas/tests = OK
```

## ما لم يُثبت
- لا Laravel على `/home/appontrack/public_html/saas` حتى الآن.
- لا migrations إنتاج، لا فحص متصفح، لا تكامل AI/WhatsApp/Voice حي.

## العائق
عامل host-link لم ينفّذ أي مهمة منذ `runner-setup-20261029a`؛ آخر مهمة مرسلة `saas-file-test-20261029` بلا تقرير. التفاصيل وأمر الاستعادة: `docs/memory/INCIDENT_2026-10-29_CONTROL_CHANNEL_AR.md`.

## حزمة النشر
`tools/pack_deploy.py` جاهزة: 5 chunks + final للحزمة الحالية. تُعاد بعد كل تغيير ثم ترسل بالتتابع بعد عودة العامل.

## الخطوة التالية
1. عودة العامل → التحقق من `host-link-test.txt`.
2. `composer create-project` أو تأكيد وجود Laravel في `saas`.
3. إرسال chunks ثم final + `migrate --force` + `route:list`.
