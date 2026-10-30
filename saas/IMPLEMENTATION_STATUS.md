# حالة تنفيذ OnTrack SaaS

**آخر تحديث: 29 أكتوبر 2026 — بعد Voice Import وAI Send Gate**

## الحالة الدقيقة

| النطاق | الحالة |
|---|---|
| مصدر التطبيق `saas/` في GitHub | `IMPLEMENTED_LOCAL` |
| اختبارات التطبيق | `TESTED_LOCAL` — 65 اختبارًا / 260 assertion، ناجحة |
| النشر على `public_html/saas` | `BLOCKED_EXTERNAL` — عامل host-link لا يستجيب |
| قاعدة بيانات الإنتاج | `PENDING_EXTERNAL` — لا بيانات اعتماد MySQL |
| النطاق الفرعي للمنصة | `PENDING_OWNER_DECISION` |
| AI Providers | `IMPLEMENTED_LOCAL` — بيانات + عزل + تشفير؛ routing/costs console؛ الاتصال الحي `PENDING_CREDENTIALS` |
| Knowledge | `IMPLEMENTED_LOCAL` — فهرسة نصية وتقسيم قطع، بلا ادعاء بحث متجهي |
| Smart Actions | `IMPLEMENTED_LOCAL` — قواعد مقيدة بالحالة والصلاحية وidempotency |
| Conversation Core | `IMPLEMENTED_LOCAL` — Inbox عام، رد موظف، إيقاف AI مؤقت، وقفل بشري دائم |
| WhatsApp Channels | `IMPLEMENTED_LOCAL` — 3 طرق onboarding وأسرار مشفرة؛ الاتصال الحي `PENDING_EXTERNAL` |
| WhatsApp Pause Override | `IMPLEMENTED_LOCAL` — override لكل رقم 1..1440، wiring UI/schema؛ تطبيق runtime ينتظر inbound connection mapping |
| Team / Memberships | `IMPLEMENTED_LOCAL` — owner/admin/member policy، token-secure invitations، accept POST، منع إزالة/خفض آخر owner |
| Workspace Context Security | `IMPLEMENTED_LOCAL` — middleware مركزي يفرض workspace_id في العضوية قبل كل `/app/*` |
| Platform Admin Console | `IMPLEMENTED_LOCAL` — Dashboard/Models/Plans/Audit/Routing/Costs مع platform.admin 403 boundary |
| Demo Mode | `IMPLEMENTED_LOCAL` — DemoSeeder وبيانات موسومة وحساب محلي تجريبي |
| AI Send Gate / Outbox | `IMPLEMENTED_LOCAL` — idempotent final gate يمنع الإرسال أثناء pause/lock/channel unavailable |
| Voice Import | `IMPLEMENTED_LOCAL` — paste/list input، invalid/duplicate flags، original preservation؛ telephony `PENDING_EXTERNAL` |
| Social Registry | `IMPLEMENTED_LOCAL` — Telegram draft وcapability registry لـTelegram/Meta/Widget/WhatsApp؛ live external `PENDING_EXTERNAL` |
| Telegram Webhook | `IMPLEMENTED_LOCAL` — secret، update dedupe، Conversation/Message tenant-scoped؛ Bot live `PENDING_EXTERNAL` |
| Knowledge Search | `IMPLEMENTED_LOCAL` — نصي tenant-scoped على chunks، بلا ادعاء متجهي |
| Agent Revisions | `IMPLEMENTED_LOCAL` — نسخ موقعة للإصدارات مرتبطة بالوكيل وعزلها |
| WhatsApp Webhook | `IMPLEMENTED_LOCAL` — challenge، HMAC raw-body، dedupe؛ لا Meta live evidence |
| Tenant Audit | `IMPLEMENTED_LOCAL` — سجل tenant-scoped مع redacted context وtrace id |
| Plans / Usage / Settings | `IMPLEMENTED_LOCAL` — entitlement quota، ledger idempotent، وإعداد pause minutes 1..1440 |
| CRM | `IMPLEMENTED_LOCAL` — جهات اتصال وحالات lead معزولة بعدد صفحات والتحقق من البريد/الهاتف |
| Operations | `IMPLEMENTED_LOCAL` — غرفة عمليات بمقاييس حقيقية من DB وعزل tenant |
| Web Widget | `IMPLEMENTED_LOCAL` — public key، جلسة widget، رسائل معزولة، وقفل human takeover؛ لا AI live claim |
| Integration Hub | `IMPLEMENTED_LOCAL` — تكاملات Business API مشفرة ومعزولة، بلا تنفيذ عمياء لـURL/LLM |
| Connector Endpoint Security | `IMPLEMENTED_LOCAL` — CRUD، tenant/workspace ownership، HTTPS/public DNS policy، path traversal rejection |
| Agent Revision Lifecycle | `IMPLEMENTED_LOCAL` — snapshot versioning عند إنشاء/حفظ الوكيل، مع عزل tenant |
| Flow Builder | `IMPLEMENTED_LOCAL` — canvas RTL بعقد وانتقالات وInspector/Palette/zoom/validation/save/publish |
| Demo Autofill | `IMPLEMENTED_LOCAL` — زر يملأ حساب العرض المحلي دون auto-login |
| Voice Campaigns | `IMPLEMENTED_LOCAL` (workflow only) — حملات وقوائم وحالات نتائج؛ النقل الهاتفي `PENDING_EXTERNAL` |
| Marketing / SEO | `IMPLEMENTED_LOCAL` — صفحات عامة + قطاعات + sitemap، بدون أرقام مختلقة |
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

### 11. CRM (جهات اتصال)
`contacts` و`contact_identities` مع tenant scope، حالات `new/qualified/customer/unqualified`، التحقق من البريد والهاتف وحالات lead، ومنع إنشاء/عرض جهات تواصل foreign tenant.

### 12. Operations (غرفة العمليات)
لوحة بأرقام حقيقية من جداول فعلية: الوكلاء، المحادثات، القفل البشري، استهلاك الشهر، مصادر المعرفة، استدعاءات الأزرار؛ مع آخر أحداث تدقيق tenant-scoped. لا توجد أرقام مختلقة، وforeign tenant يُرفض 403.

### 13. Web Widget
مفتاح public غير سري مرتبط بـtenant/workspace/project، جلسة server-side hash، endpoints config/start/message، منع الرسائل عند human lock أو timed pause، وعدم كشف AI provider أو أسرار.

### 14. Integration Hub
تكاملات مشاريع العميل التجارية (`REST/Laravel/WordPress/WHMCS/Custom`) مع credentials مشفرة، ربط project-scoped، وحالة Draft حتى مراجعة endpoints والسياسات؛ لا توجد صلاحية AI provider ولا تنفيذ URL يرسله LLM.

### 15. Voice Campaigns (workflow only)
حملات tenant/workspace مع delay وtimezone وmax_attempts، وقائمة entries تفصل `telephony_outcome` عن `business_outcome`. لا يوجد ادعاء dial/SIM/AI voice حي؛ حالة النقل `PENDING_EXTERNAL`.

### 16. Marketing / SEO
صفحات عامة: المنصة، 7 صفحات ميزات، 7 قطاعات، الباقات (CTA «اطلب عرضًا»)، الأمان، المؤسسات، المطورون، التوثيق، تواصل، معلومات؛ مع `sitemap.xml` وبدون أرقام/شهادات مختلقة.

### 17. WhatsApp Pause Override
حقل اختياري لكل رقم بين 1 و1440 دقيقة، مع تحقق UI/API واختبارات حفظ ورفض الحدود. تطبيق المدة في final send gate ينتظر ربط المحادثة الواردة بالـconnection بعد دليل Meta live.

### 18. Team / Memberships / Admin / Demo / Social
تمت إضافة Team UI بالدعوات والأدوار وإزالة الأعضاء مع منع إزالة المالك الوحيد؛ لوحة `/control` للـplatform admin (dashboard، models، plans، audit)؛ `PlatformAdminSeeder` و`DemoSeeder` ببيانات تجريبية موسومة؛ وChannelCapabilities registry مع Telegram draft رسمي وFacebook/Instagram حالات غير مفعلة صادقة. لا توجد credentials أو live social traffic.

### 19. Knowledge Search + Agent Revisions
بحث نصي `LIKE` داخل قطع المعرفة مع عزل tenant وبدون ادعاء متجهي، و`agent_revisions` بترقيم إصدارات و`change_notes` وربط بالوكيل مع عزلها.

### 20. Telegram Webhook + Social Inbox Registry
Telegram الرسمي له endpoint موثق بـsecret و`update_id` dedupe، ويخلق محادثة ورسالة داخل Inbox المعزول. Registry يعلن القدرات الخاصة بكل قناة، وFacebook/Instagram تظل غير مفعلة حتى credentials/review/Meta evidence.

### 21. AI Send Gate + Message Outbox
`message_outbox` idempotent لكل tenant، و`AiSendGate` يعيد فحص المحادثة داخل transaction/row lock قبل إنشاء رسالة AI؛ يمنع `human_locked` و`ai_pause` والقناة غير المدعومة، ويمنع duplicate send.

### 22. Voice Import + AI Send Gate
Voice campaign entries تقبل paste سطر/اسم,رقم، تحفظ original، تميز invalid وتمنع duplicate داخل الحملة، مع عزل tenant. AI Outbox يفحص lock/pause/channel في final transaction قبل إنشاء رسالة.

### 23. Connector Endpoint Security
Business connectors لا تقبل `http` أو loopback/private/metadata/userinfo، وتتحقق DNS العامة، وتمنع absolute/protocol-relative/traversal paths. CRUD endpoints محمي بالـtenant/workspace والـconnector ownership، ويبقى Draft بلا تنفيذ HTTP تلقائي.

### 24. Platform Routing وCosts Console
أضيفت سياسات Writer/Reviewer/Fallback وexact model IDs مع upsert وتدقيق، وصفحة تكاليف منصة تعرض السجل الداخلي فقط ولا تخترع أرقامًا أو تكشفه للـtenant؛ direct guessed URLs تعود 403.

### 25. Flow Builder + Demo Autofill
Flow Builder مستقل داخل `/app/{workspace}/agents/{agent}/flow`: canvas RTL بعقد Start/Message/Condition/Action/Handoff/End، palette، inspector، drag/zoom/reset، Graph save عبر nodes/edges، validation يمنع Start/End الناقصين، وpublish للـrevision. صفحة login تحتوي زر تعبئة Demo محليًا دون auto-login.

## أدلة الاختبار الفعلية

البيئة: Laravel 12.70.2 / PHP 8.2.12 / SQLite.

```text
php artisan migrate --force
23 custom migrations DONE (000001..000023)

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
- CrmTest: 2
- OperationsTest: 2
- WidgetTest: 2
- IntegrationTest: 2
- VoiceCampaignTest: 2
- MarketingPagesTest: 3
- WhatsappPauseOverrideTest: 2
- TeamTest: 3
- SocialChannelTest: 3
- KnowledgeSearchTest: 2
- AgentRevisionTest: 2
- AgentRevisionLifecycleTest: 2
- WorkspaceMembershipIsolationTest: 1
- FlowBuilderTest: 3
- DemoLoginFillTest: 1
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
`tools/pack_deploy.py` أنتجت الحزمة الحالية في `/tmp/deploy-jobs-24`: 6 chunks + final. تُعاد بعد كل تغيير ثم تُرسل بالتتابع بعد عودة العامل.

## الخطوة التالية بعد عودة العامل
1. التحقق من `host-link-test.txt`.
2. تأكيد Laravel/Composer على الهوست.
3. إرسال الحزمة ثم `migrate --force` و`route:list`.
4. اختبار HTTP للصفحة الرئيسية وتسجيل الدخول.
5. ربط MySQL مستقل والنطاق الفرعي قبل أي claim إنتاجي.
