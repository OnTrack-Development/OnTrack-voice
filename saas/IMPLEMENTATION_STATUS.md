# حالة تنفيذ OnTrack SaaS

**آخر تحديث: 29 أكتوبر 2026 — بعد شريحة Platform Provider Hub**

## الحالة الدقيقة

| النطاق | الحالة |
|---|---|
| مصدر التطبيق `saas/` في GitHub | `IMPLEMENTED_LOCAL` |
| اختبارات التطبيق | `TESTED_LOCAL` — 12 اختبارًا / 37 assertion، ناجحة |
| النشر على `public_html/saas` | `BLOCKED_EXTERNAL` — قناة host-link متوقفة |
| قاعدة بيانات الإنتاج | `PENDING_EXTERNAL` — لا بيانات اعتماد MySQL |
| النطاق الفرعي للمنصة | `PENDING_OWNER_DECISION` |
| AI Providers | `IMPLEMENTED_LOCAL` (نموذج البيانات والعزل والتشفير) — الاتصال الحي `PENDING_CREDENTIALS` |
| WhatsApp / Inbox | `PLANNED` |
| Voice | `PLANNED` — ينتظر إثبات الجهاز |

## الشرائح المنفذة محليًا

### 1. Foundation / Auth / Tenancy
- صفحة هبوط عربية RTL متجاوبة.
- Light design system بتوكنز OnTrack.
- تسجيل حساب وإنشاء tenant/workspace/owner membership داخل transaction.
- دخول وخروج مع rate limiting.
- Dashboard ببيانات DB وempty states.
- Models ومigrations لـ tenants/workspaces/memberships/projects/agents.
- عزل workspace/tenant مع رفض الوصول الأجنبي.

### 2. Projects + Agent Studio
- CRUD أولي للمشروعات مع tenant/workspace ownership.
- Agent Studio: إنشاء/عرض/تعديل/نشر، والنشر ممنوع بدون instructions.
- project_id يتحقق من workspace والtenant.
- روابط فعلية في sidebar وDashboard.

### 3. Platform Provider Hub (OnTrack-only)
- `platform_admin_grants` منفصلة تمامًا عن عضوية الـtenants.
- `platform_ai_provider_profiles` بلا `tenant_id` إطلاقًا + بيانات اعتماد مشفرة بـ`Crypt`.
- `platform_ai_models` بأسماء موديلات محددة حرفيًا + سجل تدقيق `platform_ai_audit_events`.
- مسارات `/control/ai/providers` ممنوعة على أي مستخدم tenant (403 حتى لو عرف الرابط).
- المفاتيح لا تظهر بأي حال في قوائم/استجابات الـUI.
- الحالة الافتراضية للمزود `pending_test` ولا يُدّعى اتصال حي دون اختبار حقيقي.

## أدلة الاختبار الفعلية

البيئة: Laravel 12.70.2 / PHP 8.2.12 / SQLite in-memory.

```text
php artisan migrate --force
7 custom migrations ... DONE (000001..000007)

php artisan test
12 passed (37 assertions)
- AgentStudioTest: 4 passed
- ControlProviderHubTest: 3 passed
  · tenant user forbidden from provider hub
  · platform admin creates provider without plaintext secret
  · provider profile has no tenant_id column
- TenancyIsolationTest: 3 passed
- Laravel example tests: 2 passed

PHP lint: all saas/app, saas/database, saas/routes, saas/tests files passed.
```

## ما لم يُثبت
- لا Laravel مثبت فعليًا داخل `/home/appontrack/public_html/saas`.
- لا migrations على قاعدة الإنتاج.
- لا فحص متصفح أو screenshot.
- لا اتصال AI حي (يحتاج مفاتيح تُدخل من `/control` فقط).
- لا تكامل WhatsApp/Voice.

## العائق الحالي
قناة `host-link` توقفت بعد تعديل cron في `runner-setup-20261029a`. التفاصيل وأمر الاستعادة في:
`docs/memory/INCIDENT_2026-10-29_CONTROL_CHANNEL_AR.md`.

## حزمة النشر الجاهزة
`tools/pack_deploy.py` تنتج 3–4 chunks + final. يجب إعادة تشغيلها بعد كل تغيير في `saas/`، ثم إرسال jobs بالتتابع بعد عودة القناة.

## الخطوة التالية فور استعادة القناة
1. فحص bootstrap Laravel على الهوست.
2. إرسال chunks حزمة الشريحة الحالية ثم final.
3. تشغيل `artisan migrate --force`, `route:list`, واختبار HTTP.
4. تثبيت MySQL مستقل قبل اعتبار البيئة جاهزة للإنتاج.