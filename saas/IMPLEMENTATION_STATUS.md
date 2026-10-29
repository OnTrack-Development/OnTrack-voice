# حالة تنفيذ OnTrack SaaS

**آخر تحديث: 29 أكتوبر 2026 — بعد شريحة Agent Studio**

## الحالة الدقيقة

| النطاق | الحالة |
|---|---|
| مصدر التطبيق `saas/` في GitHub | `IMPLEMENTED_LOCAL` |
| اختبارات التطبيق | `TESTED_LOCAL` — 9 اختبارات / 26 assertion، ناجحة |
| النشر على `public_html/saas` | `BLOCKED_EXTERNAL` — قناة host-link متوقفة |
| قاعدة بيانات الإنتاج | `PENDING_EXTERNAL` — لا بيانات اعتماد MySQL |
| النطاق الفرعي للمنصة | `PENDING_OWNER_DECISION` |
| AI Providers | `PLANNED` (platform-only، لا BYOK) |
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
- CRUD أولي للمشروعات: list/create/store مع tenant/workspace ownership.
- Agent Studio: list/create/store/show/update/publish.
- حقول الوكيل: name/kind/persona/instructions/status/published_at.
- النشر ممنوع بدون instructions.
- project_id يتحقق من workspace والtenant قبل إنشاء أو تحديث الوكيل.
- الروابط الفعلية في sidebar وDashboard.

## أدلة الاختبار الفعلية

البيئة: Laravel 12.70.2 / PHP 8.2.12 / SQLite in-memory.

```text
php artisan migrate --force
6 custom migrations ... DONE

php artisan test
9 passed (26 assertions)
- AgentStudioTest: 4 passed
- TenancyIsolationTest: 3 passed
- Laravel example tests: 2 passed

PHP lint: all saas/app, saas/database, saas/routes, saas/tests files passed.
```

## ما لم يُثبت
- لا Laravel مثبت فعليًا داخل `/home/appontrack/public_html/saas`.
- لا migrations على قاعدة الإنتاج.
- لا فحص متصفح أو screenshot.
- لا تكامل حي مع AI/WhatsApp/Voice.

## العائق الحالي
قناة `host-link` توقفت بعد تعديل cron في `runner-setup-20261029a`. التفاصيل وأمر الاستعادة في:
`docs/memory/INCIDENT_2026-10-29_CONTROL_CHANNEL_AR.md`.

## حزمة النشر الجاهزة
`tools/pack_deploy.py` تنتج 3 chunks + final. يجب إعادة تشغيلها بعد كل تغيير في `saas/`، ثم إرسال jobs بالتتابع بعد عودة القناة.

## الخطوة التالية فور استعادة القناة
1. فحص bootstrap Laravel على الهوست.
2. إرسال chunks حزمة الشريحة الحالية ثم final.
3. تشغيل `artisan migrate --force`, `route:list`, واختبار HTTP.
4. تثبيت MySQL مستقل قبل اعتبار البيئة جاهزة للإنتاج.
