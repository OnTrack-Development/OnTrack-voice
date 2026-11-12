# OnTrack Voice — تسليم مراجعة Stitch (7 نوفمبر 2026)

## المرجع والألوان

- التصميم الأصلي كاملًا: [stitch_ontrack_new_initiative.zip](stitch_ontrack_new_initiative.zip).
- بصمة المصدر SHA-256: `c7120fab9847a1d9a04410acd3c055bc013c22ec00b5603dd7610b2a5b69f492`؛ Git blob `19549aad718ccb50aec5d3b6ce5d604a0c80d82b`.
- الألوان المعتمدة: Black Bean `#280B08`، Champagne `#F9E7C9`، Scarlet Red `#C92925`، Warm Canvas `#FBF7F0`.
- اختير من المرجع: بطاقات الحالة، أيقونات وحالات تشغيل SIM والمساعد، هرمية المعلومات، مناطق اللمس، بانر التحديث، وRTL. لم يتم نسخ النصوص الطبية الوهمية أو بيانات عيادات وهمية.

## الموقع — منشور ومثبت

- الملفات: `saas/console/assets/css/app.css` و`saas/console/_core/Views.php`.
- إصلاح رابط تنزيل Android من مسار `/mobile/updates/native/` بدل المسار القديم داخل `/saas/mobile/`؛ لا يظهر رابط APK غير مطابق لملف الـmanifest الموقّع.
- بانر تحديث Android في الرئيسية، ورقم الإصدار وزر التنزيل ومركز التحديثات في قسم الإصدارات.
- [GitHub Actions 42330106266](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42330106266): **SUCCESS**، `STITCH_COLORS_WEB_NATIVE_UPDATE_BANNER_AND_JS_PASS` و28 PHP suite و237 browser measurement بلا فشل؛ Host QA **70 PHP** isolated، Host deploy **86 files / PHP 8.3** من `65bf91068a38`، Rollback `/home/appontrack/backup/releases/65bf91068a38`، و`signed_mobile_unchanged=true`.

## التطبيق — كود جاهز، توقيع ونشر OTA لم يحدثا

- الملفات: `saas/mobile/ota/ui.html` و`saas/mobile/audio-proof/overlay/app/src/main/assets/ontrack-ui.html` متطابقان، مع `saas/mobile/ota/release.json` revision **50**، native compatibility **34–50**.
- الإصلاح: مركز تحديث Android ظاهر دائمًا داخل واجهة `v1Minimal`، وبانر جديد ينبه للإصدار المتاح، فحص يدوي من الشاشة الرئيسية، زر تحميل وتثبيت يفتح التدفق الأصلي الآمن في Android، والزر معطّل عند عدم وجود تحديث أو أثناء مكالمة.
- `tools/test_stitch_theme_update.py` يتحقق من الملفات والألوان والأزرار وبنية JavaScript، و`.github/workflows/voice-stitch-ui-ota-release.yml` يحدد طريقة التوقيع بهوية التطبيق الحالية (شهادة SHA-256 المثبّتة) والتحقق من Host receipt.
- **لم يكتمل** تشغيل هذا الـWorkflow أو توقيع `UI50` أو تأكيد Host receipt لآخر UI. لذلك لا تدعِ أن المستخدمين حصلوا على الواجهة تلقائيًا. يلزم إطلاق الـWorkflow المصرح [Publish reviewed OnTrack Stitch UI OTA](https://github.com/OnTrack-Development/OnTrack-voice/actions/workflows/voice-stitch-ui-ota-release.yml) على فرع `release/voice-v1-20261106`، مع انتظار `STITCH_UI50_HOST_PUBLISHED_PASS`. لا تُعيد استخدام إصدار 49 أو مفتاح توقيع جديد.
- نظام الصوت، SIM، APK الأصلي وتوقيعه لم تتغير في دفعة التصميم.

## تنبيه أمني

مفتاح Stitch الذي أُرسل داخل الدردشة لم يتم تخزينه في ملفات GitHub أو واجهة العميل. من الأفضل تدويره بعد مشاركته في رسالة.
