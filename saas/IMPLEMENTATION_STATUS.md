# حالة تنفيذ OnTrack SaaS

**آخر تحديث: 29 أكتوبر 2026 — 02:20 UTC**

## الحالة الدقيقة

| النطاق | الحالة |
|---|---|
| مصدر التطبيق `saas/` في GitHub | `IMPLEMENTED_LOCAL` |
| اختبارات العزل والتسجيل | `TESTED_LOCAL` — 3 اختبارات / 14 تحقق، ناجحة |
| النشر على `public_html/saas` | `BLOCKED_EXTERNAL` — قناة host-link متوقفة |
| قاعدة بيانات الإنتاج | `PENDING_EXTERNAL` — لا بيانات اعتماد MySQL |
| النطاق الفرعي للمنصة | `PENDING_OWNER_DECISION` |
| AI Providers | `PLANNED` (platform-only، لا BYOK) |
| WhatsApp / Inbox | `PLANNED` |
| Voice | `PLANNED` — ينتظر إثبات الجهاز |

## ما أُنجز وثُبت

### المصدر (GitHub `main`)
- صفحة هبوط عربية RTL كاملة متجاوبة (360/768/1024/1280+).
- نظام تصميم Light مطابق للتوكنز الرسمية (`--ot-red:#D5222C` ...).
- تسجيل حساب ينشئ `tenant` + `workspace` + عضوية `owner` داخل transaction.
- تسجيل دخول/خروج مع rate limiting (5 محاولات/دقيقة).
- لوحة تحكم تعرض بيانات حقيقية من DB مع empty states صادقة.
- Models + Migrations: `tenants`, `workspaces`, `tenant_memberships`, `projects`, `agents`.
- عزل tenant داخل `OverviewController` (403 عند الوصول لمساحة غير مسموحة).
- `workspace.slug` فريد عالميًا لحسم التوجيه.

### الأدلة المحلية (منفذة فعليًا)
```
php artisan migrate --force
2026_10_29_000001_create_tenants_table .......... DONE
2026_10_29_000002_create_workspaces_table ....... DONE
2026_10_29_000003_create_tenant_memberships_table DONE
2026_10_29_000004_create_projects_table ......... DONE
2026_10_29_000005_create_agents_table ........... DONE

php artisan route:list --except-vendor
8 routes (/, app, app/{workspace}/overview, login x2, logout, register x2)

php artisan test --filter=TenancyIsolationTest
✓ registration creates tenant workspace and owner membership
✓ tenant b cannot open tenant a workspace
✓ agent belongs to tenant and project
Tests: 3 passed (14 assertions)
```
بيئة الاختبار: Laravel 12.70.2 / PHP 8.2.12 / SQLite in-memory.

## ما لم يُثبت
- لا Laravel مثبت فعليًا داخل `/home/appontrack/public_html/saas` حتى الآن.
- لا migrations على قاعدة الإنتاج.
- لا بناء assets متقدم ولا فحص متصفح.
- لا أي تكامل حي (AI/WhatsApp/Voice).

## العائق الحالي
قناة `host-link` توقفت بعد تعديل الـcron في `runner-setup-20261029a`.
التفاصيل وأمر الاستعادة: `docs/memory/INCIDENT_2026-10-29_CONTROL_CHANNEL_AR.md`.

## الخطوة التالية فور استعادة القناة
1. التحقق من نجاح `bootstrap-laravel` أو إعادة إنشائه.
2. نشر الـoverlay عبر `tools/pack_deploy.py` (جاهز: 3 chunks + final).
3. `artisan migrate --force` + `route:list` على الهوست.
4. فحص HTTP للصفحة الرئيسية و/login.
5. ربط النطاق الفرعي بعد قرار المالك.