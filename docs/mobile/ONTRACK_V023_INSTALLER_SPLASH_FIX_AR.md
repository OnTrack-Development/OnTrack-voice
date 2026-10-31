# OnTrack Voice 0.2.3 — إصلاح APK Parsing + Premium Splash
تاريخ التنفيذ: 2026-10-31

## المشكلة التي قدمها صاحب المشروع
- Xiaomi Android يعرض `There was a problem parsing the package` بعد ضغط `تحميل وتثبيت ota-0.2.2` من OnTrack Voice 0.2.1.
- المستخدم يريد تحديثاً أنيقاً داخل التطبيق شبيهاً بتجربة التطبيقات المالية، وSplash Screen متحركة مع `يرجى الانتظار`، دون فقد ربط الشريحة أو بيانات التشغيل.
- يريد البناء الحالي Cloud + Android مترابطاً، وليس تقسيم المنتج إلى نظامين منفصلين.

## التغييرات المنفذة في الكود
1. `NativeUpdates.kt` يقرأ manifest موقّعاً، ويطابق SHA256، وطول الملف، وPackage Name، وversionCode وشهادة توقيع APK. ثم يسلم APK إلى `PackageInstaller.Session` عبر `openWrite("base.apk")` و`fsync` و`commit` بدل `ACTION_VIEW` مع `content://` الذي قد يفشل لدى بعض OEM.
2. `NativeInstallResultReceiver.kt` يتعامل مع `STATUS_PENDING_USER_ACTION` ويفتح شاشة الموافقة الرسمية، ويسجل STATUS_FAILURE_INVALID/BLOCKED/CONFLICT/INCOMPATIBLE/STORAGE/ABORTED بشكل تشخيصي بدون تسجيل أسرار.
3. `OnTrackLaunchScreen.kt` يغطي تحميل الـWebView الأول وتبديل OTA بعد المكالمة بشاشة متحركة أصلية (حلقات، علامة OnTrack، اسم المنتج، ورسالة الحالة)، وفيد خفيف. لا يقوم بإعادة تشغيل تطبيق Android أو إغلاق مكالمة.
4. `saas/mobile/ota/ui.html` ونسخة `assets/ontrack-ui.html` تضم Splash UI CSS متحركة وعربية، تدعم Reduced Motion، وتخفي نفسها بعد جاهزية الواجهة بحد أقصى 2.4 ثانية.
5. `saas/mobile/ota/release.json`: UI OTA revision9 متاح لـ Android native codes 12–14؛ أي أن الواجهة يمكن تحديثها من داخل النسخة 0.2.1 الموجودة عند العميل، قبل أي تثبيت APK جديد.
6. Native versionCode14, 0.2.3-ota؛ `prepare.py` يعلن BroadcastReceiver صراحة لحالة التثبيت، والتوقيع يستخدم نفس هوية APK المثبتة.

## ما لا يمكن أن يفعله OTA العادي
- لا يمكن لـ HTML/JS OTA تبديل Kotlin/Manifest/صلاحيات Android، ولذلك إصلاح PackageInstaller native يحتاج التثبيت مرة عبر المثبت الرسمي حتى يصل للجهاز.
- Android العادي **قد يطلب السماح بتثبيت التطبيقات من هذا المصدر** وموافقة المستخدم، خصوصاً عند أول مرة؛ لا يمكن ضمان حذف الخطوة لكل الأجهزة والتوزيعات. خدمة Device Owner/MDM أو توزيع رسمي بإدارة التحديثات حالة مختلفة لم نعتمدها.
- عمل خاصية التثبيت لا يثبت نجاحها على Xiaomi حتى اختبار APK نفسه بالجهاز. لا تطبع `PASS` للاختبار الميداني حتى يأتي دليل حقيقي.
- لا تستخدم/تقلد أصول ثاندر البصرية؛ الأنيميشن من إنتاج OnTrack داخل الكود.
- مرجع CI الحالي يُسجل بعد الاختبارات؛ التصريحات العامة يجب أن تتطابق مع نتائج بناء/نشر مثبتة.

## تجربة قبول لاحقة
- من النسخة 0.2.1: افتح التطبيق ليتلقى UI OTA9 من غير APK؛ تحقق من أن OnTrack Splash تعمل أثناء تحميل الواجهة، والاتصال لا يتوقف.
- ثبّت 0.2.3 فوق النسخة الحالية من مصدر OnTrack الرسمي بدون Uninstall؛ لا تضيع المفتاح أو إعداد `mix`.
- في إصدار 0.2.4 المستقبلي (عند توفره فقط) اضغط فحص ثم تثبيت، وأكد أن `PackageInstaller` لا يظهر `Parsing the package` ويعرض موافقة Android الرسمية، مع عدم تحديث مكالمة جارية.
- اختبر الانقطاع وتغيير Wi-Fi، handoff الحالي فقط، ورجوع الربط بعد التثبيت.
- لا تخلط بين وحدات CRM/Campaigns/Call Recording الموجودة جزئياً في Laravel المصدر وبين Phone/SIM end-to-end المؤكد؛ الربط الميداني لسه محتاج اختبار وتنفيذ.
