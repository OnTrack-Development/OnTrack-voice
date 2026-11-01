# دورة التطوير الرابعة — منع كسر DNC أثناء إدخال جهات الاتصال
**التاريخ:** 2026-11-01 · **الفرع:** `fix/dnc-suppression-ingestion-20261101` · **PR #11 MERGED** إلى main commit `49ecf6b9c212c9a7aaac8f926d8dc07466ebe677`

## المشكلة المُعاد إنتاجها من المصدر
- `api/v1/index.php?op=lead.create` كان ينشئ كل lead مع `dnc=0` حتى لو العميل نفسه سبق وقال «لا تتصل مجددًا» في موقع آخر لنفس الحساب.
- `hooks/index.php` كان يجعل `dnc=0` عند `consent=true` حتى لو نفس الرقم موجود في سجل عدم الاتصال.
- `Actions.php` كان يسمح بإنشاء نسخة جديدة برقم محظور ثم إزالة علامة DNC من سجل قديم بمجرد تعديل checkbox، أو تغيير رقمه، أو حذف سجل الحظر. هذه ثغرة منطقية في تطبيق قرار Q13 وليست مسألة شكلية.

## الإصلاح المنفذ
- ملف `saas/console/_core/DncGuard.php` يقارن الأرقام بعد إزالة علامات العرض، ويوحد `01xxxxxxxxx` المصري مع `+201xxxxxxxxx` و`00201xxxxxxxxx` فقط؛ لا يفترض الدولة للأرقام الأخرى.
- lookup محصور بـ`account_id`، ويورّث الحظر عبر جميع المواقع التابعة للحساب دون مشاركة بيانات مستأجر آخر.
- API lead وsigned booking webhook وواجهة إضافة جهة اتصال تضع `dnc=1` عند وجود opt-out سابق، حتى لو جاء consent=true لاحقًا. `dial_requested=false` يظل ثابتًا في booking.
- تحرير سجل محظور لا يمحو DNC، ولا يسمح بتغيير رقم السجل الذي يمثل إثبات الحظر. الحذف الروتيني لسجل DNC ممنوع مؤقتًا حتى وجود مسار رفع الحظر بموافقة موثقة مع سياسة الاحتفاظ؛ الواجهة توضّح ذلك.
- أضيفت اختبارات HTTP للـAPI والـsigned webhook تشمل مصر المحلي/الدولي، الحظر عبر موقعين، العزل بين الحسابات، رفض غير الموافق، رفض cross-tenant، replay/duplicate، بالإضافة إلى تشغيل handlers الفعلية add/edit/delete في عمليات PHP CLI معزولة.

## حالة التحقق (تُحدّث بعد نتيجة CI)
- `CODED`: branch محفوظ في GitHub.
- `CI_PASS`: **PASS**: GitHub Actions PR run `41297247472`، PHP 8.3 / SQLite + HTTP API + signed hooks + subprocess form handlers.
- `HOST_DEPLOYED`: **VERIFIED**: main CI `41297275347` success؛ publish workflow `41297275398` success؛ host Cron receipt `ops/host-link/results/appontrack-server11/saas-console-41297275398.json` = success، exit 0، المصدر `49ecf6b9c212`، **22 ملفًا**، PHP 8.3، seed already_seeded، backup=true، انتهاء `2026-11-01 10:24:04 UTC`.
- `AUTH_UI_VERIFIED`: **OPEN** (لا جلسة مستخدم مصادقة متاحة في هذه الدورة).
- `DEVICE_E2E_VERIFIED`: **OPEN** (لا مكالمات حقيقية).

## حدود متعمدة وخطوة تالية
هذا **حاجز إدخال** وليس سجل DNC نهائيًا غير قابل للفقد. ما زال مطلوبًا ledger مستقل ومحدّد الاحتفاظ، وحماية من الحذف المصرح به مع مسار موافقة جديدة لإزالة الحظر، وتطبيق pre-dial DNC/consent على Android والحملات. الاستعلام الحالي يفحص فقط السجلات المحظورة داخل الحساب؛ يلزم فهرسة رقم موحّد لاحقًا للحجم الكبير. لا تَدّعِ حماية الاتصال الفعلي حتى اختبار جهاز.
- PR #9 الخاص بالـSSO ما زال مفتوحًا؛ لم يُلمس أي من ملفات runtime التي غيّرها في هذا الإصلاح.
