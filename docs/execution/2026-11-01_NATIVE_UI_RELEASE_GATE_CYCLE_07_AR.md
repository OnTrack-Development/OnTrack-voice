# دورة #7 — منع نشر APK عند فشل نشر واجهة OTA على الهوست
**2026-11-01 · نطاق المشكلة: سلسلة إصدار Android فقط**

## السبب والأثر المثبت
- تشغيل Android السابق `41306044730` انتهى SUCCESS على GitHub، لكن مرحلة `publish_ota` كانت فقط **queue** وليست إثبات نجاح الهوست.
- receipt الفعلي `mobile-ota-41306044730`: `status=failed`, `exit_code=1`, `Revision cannot be reused with different content`.
- مع ذلك `publish_native` استمر بعد انتهاء Job الإرسال (وليس بعد نجاح التثبيت)، والـreceipt `mobile-native-41306044730` = **success**, native versionCode **22**، sha256 `ff60a4d49dd86294a8fb1c1c7254ae2ccc587f19ad21af504e1271d4e02b548b`، 52,534,849 bytes، **2026-11-01 12:29:08 UTC**.
- هذا يعني أن إصدار native نُشر مع فشل تحديث UI المرافق. لا يوجد إثبات أن هاتف المستخدم ثبّت الإصدار الجديد؛ نشر APK على الهوست ليس Device E2E.

## الإصلاح
- `tools/queue_mobile_native.py`: لا يُرسل طلب نشر native إلا بعد استلام receipt موثوق من نفس تشغيل `mobile-ota-$GITHUB_RUN_ID`، بنفس `target` و`job_id` وSHA256 لوظيفة الـqueue، وبـ`status=success` و`exit_code=0`. يقرأ `stdout` ويتأكد `status=installed`, `scope=saas/mobile/updates`, `revision` و`sha256` مطابقان للـUI artifact في نفس حزمة الإصدار.
- فشل UI أو receipt قديم/ناقص/متعارض/ملف مختلف يوقف native fail-closed، دون إلغاء التحديث المنشور سابقًا أو تجاوز سياسة revision.
- `.github/workflows/android-audio-proof.yml`: إزالة `tools/queue_mobile_native.py` من trigger الخاص ببناء APK؛ تغييرات أداة النشر تُختبر وحدها ولا تطلق بناء/توقيع/نشر جديدًا.
- `.github/workflows/mobile-release-gate.yml`: CI صغير مستقل (لا Android build أو أي نشر) مع `tools/test_mobile_release_gate.py` للحالات الإيجابية والسلبية.
- لم يتم تغيير أي APK أو رقم revision أو كود SIM في هذا الإصلاح.

## معايير الإثبات
- `CODED`: commit على فرع إصلاح مستقل، PR وCI.
- `CI_PASS`: اختبارات Python للـhost receipt ومصفوفة حالات الفشل، وفحص trigger isolation.
- `HOST_DEPLOYED`: غير مطلوب لهذا التعديل لأنه يغير GitHub workflow وأداة نشر فقط؛ **ليس** دليل نشر DNC PHP.
- `DEVICE_E2E_VERIFIED`: ما زال OPEN؛ لا مكالمات فعلية.

## التالي
1. تأكيد PR CI ثم دمج التعديل الآمن. عدم تشغيل Android build تلقائيًا من تعديل release tool.
2. معالجة سياسة UI revision وتوافق محتواها في إصدار منفصل قبل طلب Android native جديد؛ لا إعادة استخدام revision لنص مختلف.
3. بناء مسار نشر PHP pilot آمن ومثبت بالـreceipt بدون تحديث APK، ثم اختبار DNC على الجهاز.
