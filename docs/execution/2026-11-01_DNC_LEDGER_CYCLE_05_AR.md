# دورة التطوير الخامسة — DNC ledger مستقل دائم لكل حساب
**التاريخ:** 2026-11-01 · **PR:** https://github.com/OnTrack-Development/OnTrack-voice/pull/12

## المشكلة
بعد PR #11 كان حظر «لا تتصل مجددًا» مربوطًا فقط بوجود `contacts.dnc=1`. حذف الصف مباشرة أثناء استيراد/تنظيف بيانات أو ترحيلها كان قادرًا على فقدان إثبات الحظر. البحث يمرّ على كل جهات الاتصال المحظورة، ولا يوجد سجل مستقل.

## التنفيذ
- `saas/console/_core/DncLedger.php`: جدول `dnc_suppressions` بمفتاح فريد `(account_id, phone_key)`، صيغة مصر موحدة، مصدر الحظر وتاريخه، `INSERT IGNORE`/ `INSERT OR IGNORE` idempotent. لا يمسح سجل الحظر عند حذف جهة الاتصال.
- ترحيل additive لقاعدتي SQLite/MySQL؛ backfill في دفعات 250 من `contacts.dnc=1`، وmarker خاص بعد اكتمال النقل. عند إعادة تشغيل النشر على DB قديمة، `database/Seed.php` يستدعي الترحيل قبل قبول أي Booking. يُرفض أي خطأ ترحيل بدلاً من السماح بالاتصال.
- `DncGuard.php`: يفحص الجدول الدائم أولًا، ويكتشف جهات الاتصال القديمة المحظورة ويضيفها إلى الجدول عند اللزوم؛ ويضيف opt-out صريحًا بشكل دائم.
- `hooks/index.php`: حجز غير موافق يسجل `booking_no_consent` داخل نفس transaction الموقّعة؛ تهيئة الجدول خارج transaction لحماية MySQL من implicit commit.
- `Actions.php`: opt-out يدوي يسجل `contact_optout`، بينما لا تزال عمليات حذف DNC العادية محجوبة.
- اختبارات PHP: backfill، الصيغ المصرية المختلفة، replay/idempotency، حذف جهة الاتصال، عدم تسريب الحظر بين شركتين، healing للبيانات القديمة، المصدر، invalid input، إعادة تشغيل seeder، HTTP signed booking.

## حدود هذه الدورة
هذا ليس اختبار اتصال Android. لا يوجد حتى الآن gate قبل Dial على الجهاز أو على Campaign Scheduler، ولا workflow رفع الحظر بموافقة جديدة أو سياسة retention/review قانونية. لا يجوز الادعاء بأن أي اتصال حقيقي سيُمنع حتى ربط الـledger بقرار الاتصال والتحقق على الهاتف.

## أدلة التشغيل
- PR #12: https://github.com/OnTrack-Development/OnTrack-voice/pull/12
- CI: يُحدَّث بعد آخر تشغيل على HEAD النهائي.
- Merge: يُحدَّث بعد نجاح CI ومراجعة التغييرات.
- Host Cron receipt: يُحدَّث بعد نجاح النشر؛ GitHub Actions publish وحده لا يثبت تثبيت الملفات على الهوست.
- Authenticated UI / Device E2E: OPEN.

## الخطوة التالية
تنفيذ DNC/consent **قبل كل محاولة Dial** مع جدولة الحملات، ومنع تكرار المحاولات بعد opt-out؛ إثبات device E2E مستقل. PR #9 SSO مفتوح ولم يتم دمجه أو تعديل كود الهوية الخاص به.
