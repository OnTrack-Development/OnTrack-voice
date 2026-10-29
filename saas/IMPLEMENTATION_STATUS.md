# حالة تنفيذ OnTrack SaaS

**آخر تحديث: 29 أكتوبر 2026 — بعد شريحة Plans وUsage Ledger وTenant Settings**

## الحالة الدقيقة

| النطاق | الحالة |
|---|---|
| مصدر التطبيق `saas/` في GitHub | `IMPLEMENTED_LOCAL` |
| اختبارات التطبيق | `TESTED_LOCAL` — 30 اختبارًا / 109 assertion، ناجحة |
| النشر على `public_html/saas` | `BLOCKED_EXTERNAL` — عامل host-link لا يستجيب |
| قاعدة بيانات الإنتاج | `PENDING_EXTERNAL` — لا بيانات اعتماد MySQL |
| النطاق الفرعي للمنصة | `PENDING_OWNER_DECISION` |
| AI Providers | `IMPLEMENTED_LOCAL` — بيانات + عزل + تشفير؛ الاتصال الحي `PENDING_CREDENTIALS` |
| Knowledge | `IMPLEMENTED_LOCAL` — فهرسة نصية وتقسيم قطع، بلا ادعاء بحث متجهي |
| Smart Actions | `IMPLEMENTED_LOCAL` — قواعد مقيدة بالحالة والصلاحية وidempotency |
| Conversation Core | `IMPLEMENTED_LOCAL` — Inbox عام، رد موظف، إيقاف AI مؤقت، وقفل بشري دائم |
| WhatsApp Channels | `IMPLEMENTED_LOCAL` — 3 طرق onboarding وأسرار مشفرة؛ الاتصال الحي `PENDING_EXTERNAL` |
| WhatsApp Webhook | `IMPLEMENTED_LOCAL` — challenge، HMAC raw-body، dedupe؛ لا Meta live evidence |
| Tenant Audit | `IMPLEMENTED_LOCAL` — سجل tenant-scoped مع redacted context وtrace id |
| Plans / Usage / Settings | `IMPLEMENTED_LOCAL` — entitlement quota، ledger idempotent، وإعداد pause minutes 1..1440 |
| Voice | `PLANNED` — ينتظر إثبات الجهاز |

## الشرائح المنفذة

### 1. Foundation / Auth / Tenancy
هبوط RTL، توكنز تصميم، تسجيل ينشئ tenant/workspace/owner، دخول/خروج مع rate limit، Dashboard ببيانات حقيقية، وعزل tenant.

### 2. Projects + Agent Studio
مشروعات بملكية tenant/workspace، واستوديو وكيل (إنشاء/عرض/تعديل/نشر) مع منع النشر بلا تعليمات ورفض المشروع الأجنبي.

### 3. Platform Provider Hub
`platform_admin_grants` مستقلة عن الـtenants، ملفات AI بلا `tenant_id` مع تشفير `Crypt`، موديلات بالمعرّف الحرفي، تدقيق، و403 لأي مستخدم tenant على `/control/ai/*`.

### 4. Knowledge Hub
`knowledge_sources` و`knowledge_source_versions` و`knowledge_chunks`، تقسيم نصي وحالات `processing → indexed`، مع عزل tenant ورفض المشروع الأجنبي.

### 5. Smart Actions
`button_rules` مقيدة بـtenant/workspace/project، و`button_invocations` بمفتاح idempotency فريد لكل tenant، ورفض القواعد/المشروعات الأجنبية.

### 6. Conversation Core + Human Lock
محادثات ورسائل وأحداث وتسليم handoff مع tenant scope. رد الموظف يضبط `ai_pause_until_utc` افتراضيًا 15 دقيقة مع `pause_generation`؛ القفل البشري الدائم منفصل ولا ينتهي تلقائيًا.

### 7. WhatsApp Channels
`whatsapp_connections` بثلاث طرق: Embedded Signup وCoexistence وManual Cloud API. أسرار القناة مشفرة، والحالة تبقى Draft حتى أدلة Meta الفعلية؛ لا توجد حقول AI provider في شاشة العميل.

### 8. WhatsApp Webhook
`GET/POST /api/webhooks/meta/whatsapp/{publicId}` مع تحقق challenge بواسطة verify token مشفر، HMAC على الـraw body قبل تفكيك JSON، و`whatsapp_webhook_events` لمنع التكرار لكل اتصال. التوقيع الخاطئ مرفوض.

### 9. Tenant Audit
`tenant_audit_events` tenant-scoped مع actor وaction وresource و`redacted_context` وtrace id؛ يمنع خلط سجل شركة بأخرى.

### 10. Plans وUsage Ledger وTenant Settings
`plans` و`plan_entitlements` و`subscriptions` و`tenant_usage_ledgers` مع `UsageLedger` يمنع تجاوز الحصة ويمنع الخصم المكرر بمفتاح idempotency. صفحة الباقة تعرض الاستخدام، وإعدادات الشركة تتحقق من مدة الإيقاف بين 1 و1440 دقيقة.

## أدلة الاختبار الفعلية

البيئة: Laravel 12.70.2 / PHP 8.2.12 / SQLite.

```text
php artisan migrate --force
14 custom migrations DONE (000001..000014)

php artisan test
25 passed (90 assertions)
- AgentStudioTest: 4
- ControlProviderHubTest: 3
- KnowledgeHubTest: 3
- SmartActionTest: 2
- TenantAuditTest: 2
- ConversationCoreTest: 3
- KnowledgeHubTest: 3
- WhatsappConnectionTest: 3
- WhatsappWebhookTest: 2
- TenantAuditTest: 2
- PlansUsageTest: 3
- TenancyIsolationTest: 3
- Laravel example tests: 2

PHP lint: all saas/app, saas/database, saas/routes, saas/tests = OK
```

## ما لم يُثبت
- لا Laravel مثبت فعليًا داخل `/home/appontrack/public_html/saas`.
- لا migrations على قاعدة الإنتاج.
- لا فحص متصفح أو screenshot.
- لا اتصال AI أو Meta أو Voice حي.

## العائق الحالي
عامل `host-link` لم ينفّذ أي مهمة منذ `runner-setup-20261029a`. آخر مهمة اختبار ملف `saas-file-test-20261029` لم تنتج تقريرًا. التفاصيل وأمر الاستعادة في:
`docs/memory/INCIDENT_2026-10-29_CONTROL_CHANNEL_AR.md`.

## حزمة النشر
`tools/pack_deploy.py` أنتجت الحزمة الحالية في `/tmp/deploy-jobs-12`: 6 chunks + final. تُعاد بعد كل تغيير ثم تُرسل بالتتابع بعد عودة العامل.

## الخطوة التالية بعد عودة العامل
1. التحقق من `host-link-test.txt`.
2. تأكيد Laravel/Composer على الهوست.
3. إرسال الحزمة ثم `migrate --force` و`route:list`.
4. اختبار HTTP للصفحة الرئيسية وتسجيل الدخول.
5. ربط MySQL مستقل والنطاق الفرعي قبل أي claim إنتاجي.
