# OnTrack Voice — إثبات تشغيل SaaS ونسخ الاسترجاع — 8 نوفمبر 2026 (v70–v75)

> مرجع تسليم أدلة هذه الدفعة، لا إعلان جاهزية تجارية. الفرع: `feat/io-premium-native-v51-20261107`. النشر على الجذر `/home/appontrack/public_html` عبر SSH المفوض من Windows Runner BEDIR فقط. كل اختبارات SQLite المعزولة لا تثبت MySQL أو اتصال SIM حقيقي.

## الإصلاحات والنشر الفعلي

- **v70:** تعميم سياسة تعليق الريسيلر على خدمات العملاء التابعين، تسجيل الشريحة والـAPI بجانب الاستحقاق الصوتي، دون إلغاء اشتراكاتهم أو المساس بتوكنات الـSIM. [Run 42493312629](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42493312629) — SUCCESS، نشر PHP8.3، HTTPS deny smoke، rollback.
- **v71:** WhatsApp Native + WHMCS Bridge + JobSafety + worker يتحققون من حساب الريسيلر قبل AI وقبل حجز الإرسال وقبل Meta. التعليق يحافظ على Pending ولا يحرق Attempts؛ الاستئناف لا يكرر الإرسال. [Run 42494057549](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42494057549) — SUCCESS؛ 4 ملفات منشورة واختبارات fixtures.
- **v72:** `OperationalOutbox.php` يحسب صحة 5 Workers (healthy/stale/failed/missing/invalid) وتعرضها لوحة Admin مع تنبيه واضح؛ حالة الـWorkers العامة لا تُكشف للريسيلر. [Run 42494627530](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42494627530) — SUCCESS، نشر ملفين، اختبار PHP8.3 وHTTPS، فحص إنتاج READ-ONLY بدون أسرار أو تحديث صفوف.
- **نتيجة فحص قاعدة الإنتاج v72:** `database_driver=sqlite` وليس MySQL، 10 جداول مطلوبة موجودة؛ كانت 4/5 Workers لديها Heartbeat صالح و`whatsapp_ai` مفقود. **لا تدّع MySQL E2E أو جاهزية إنتاج.**
- **v73:** إثبات Cron واتساب موجود مرة واحدة بدقيقة واحدة، لكن دون Heartbeat وبدون مهام معلقة. كشف Smoke معزول Fatal `Cannot redeclare wa_recover_stale_jobs()` بسبب تحميل `WhatsAppJobSafety.php` مرتين، وليس خلل Meta أو API.
- **v74:** تعديل `whatsapp-worker.php` لاستعمال `require_once` لجميع الـIncludes؛ [Run 42495706142](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42495706142) — SUCCESS؛ بناء PHP، اختبار Windows isolated، نشر ملف واحد مع rollback واختبار PHP 8.3 واستدعاء isolated على نفس السيرفر، **من دون dispatch لرسائل الإنتاج**. إعادة تشغيل فحص Cron القراءة فقط [Run 42495254986 (attempt 2)](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42495254986) أثبتت `whatsapp_heartbeat_seen=true`، `last_run_age_seconds=31`، والطابور = 0. هذا دليل تشغيل Cron تلقائي بعد الإصلاح، **وليس إثبات قبول Meta لتسليم رسالة فعلية**.
- **v75:** [Run 42496099903](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42496099903) — SUCCESS؛ أخذ Online SQLite backup داخل الاستضافة الخاصة دون تغيير DB الحية، SHA-256 `0fcbc62d77604f21db3820d9d92f3e0c907552e562e7b9fb490e106a062a26d3`، حجم 1,216,512 bytes، `PRAGMA integrity_check=ok`، مقارنة schema/row counts لـ7 جداول، Restore إلى ملف SQLite منفصل وفحص hash/integrity، نتيجة PASS. المسار الخاص محفوظ داخل `/home/appontrack/.ontrack-saas/backup-drills/backup-20261108T154206Z-b746220e/`؛ **ممنوع نقل SQLite الفعلي أو أسرار السيرفر إلى GitHub**.

## قواعد تشغيل ملزمة

1. `whatsapp-worker.php` يجب أن يستخدم `require_once` عند تحميل وحدات WhatsApp المشتركة. Syntax Lint فقط لا يكتشف redeclare؛ يجب تنفيذ **CLI runtime** على قاعدة اختبار معزولة لكل تعديل لخط التشغيل.
2. لا تُعِد تشغيل `Host-Link` أو Cron التطوير/المراقبة الذي أوقفه المالك؛ لا تُعَد جدولة WhatsApp إلا بعد فحص الموجود، وتحافظ على بقية Cron دون تغيير.
3. تشغيل Worker بلا أخطاء أو `delivered_local` ليس إثبات وصول الرسالة إلى Meta أو SMTP أو WHMCS. الحالات المجهولة تُراجع ولا يُعاد إرسالها عميانيًا.
4. `.github/workflows/voice-saas-second-ops-v68-direct-publish.yml` أصبح historical manual-only: إعادة نشر v68 تلقائيًا بعد v71/v72 كانت تفشل بسبب `BASE` القديم وعدم وجود `Signup.php` حينه، كما أنها قد تكتب كودًا أقدم فوق الأحدث. تحديثات التشغيل تستخدم workflows محددة الإصدار.
5. استعادة SQLite الحالية اختُبرت **في نسخة معزولة**، ولم تتم تجربة إقلاع التطبيق الكامل على نسخة مستعادة، ولا Backups خارج الخادم أو الترحيل إلى MySQL؛ كل ذلك OPEN.

## المهام المتبقية قبل قبول المرحلة الثالثة

- **MySQL migration / E2E (OPEN):** البيئة الحية حاليًا SQLite. يلزم MySQL staging مع نسخة استرجاع متسقة، ترحيل بيانات/sequence/constraints، تحققات عدد الصفوف ومراجع الاشتراكات والفواتير وقراءات/كتابات متزامنة، تجربة Cutover/Rollback؛ لا تقلب إعداد الإنتاج مباشرة أو تفترض وجود MySQL جاهز.
- **التعافي (PARTIAL):** Online backup واستعادة ملف منفصل نجحا؛ يلزم إعادة تشغيل نسخة SaaS معزولة باستخدام نسخة البيانات، واختبار restore/runbook كامل، ونسخ خارج الخادم مع تشفير وحفظ دوّار.
- **التكاملات (UNVERIFIED):** Meta inbound/outbound/delivery callbacks، WHMCS/Laravel paid order identity، SMTP الحقيقي، Kashier payment/refund/webhook.
- **الهاتف (UNVERIFIED):** SIM native GSM + Flow/Tool runtime داخل مكالمة فعلية، مقارنة Wi-Fi/LTE والـhandoff، حملات حقيقية بموافقة مالك الشريحة.
- **الإطلاق التجاري (BLOCKED):** اختبار صلاحيات إنتاجية بحسابات Admin/Reseller/User حقيقية، مراقبة الأخطاء وأداء التوازي، إعادة وسائل الحماية المؤجلة وقت الإطلاق التجاري.

**الحالة العامة:** v70/v71/v72/v74/v75 CODED + CI VERIFIED + HOST VERIFIED في النطاق المذكور؛ Real provider/device/MySQL production E2E **ما زال غير معتمد**.
