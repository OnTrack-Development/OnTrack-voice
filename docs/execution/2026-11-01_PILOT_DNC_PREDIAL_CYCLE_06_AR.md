# دورة 6 — حماية DNC قبل طلب اتصال Android
**2026-11-01 · PR #13 MERGED · CODED + CI_PASS · HOST/DEVICE NOT VERIFIED**

## المشكلة الحقيقية
في `saas/mobile/server/pilot_workspace.php` كان `pilot_dnc(id,false)` يرفع الحظر مباشرة بلا موافقة جديدة موثقة، والحظر موجودًا فقط في `contacts.json`. حذف/إعادة استيراد السجل يمكن أن يفتح الاتصال مجددًا. `pilot_campaign_verify_claim` كان يقرأ بلا قفل، بينما opt-out يكتب تحت قفل. رغم أن Android `CampaignDialer.kt` يطلب `claim` ثم `verify` قبل `TelecomManager.placeCall`، لم تكن حماية الحظر دائمة.

## التنفيذ
- `saas/mobile/server/pilot_workspace.php`: ملف `dnc.json` خاص بالتجربة، append-only حتى وجود موافقة جديدة مثبتة؛ الترحيل التلقائي من الأرقام القديمة؛ قفل القراءة/التحقق قبل Dial؛ فشل مغلق عند فساد البيانات؛ رفض رفع الحظر بمتغير Boolean؛ منع reimport/claim/arm للأرقام المحظورة؛ صلاحية Lease 30 ثانية؛ عدم تكرار الاتصال الغامض.
- `saas/mobile/server/contacts_api.php`: الحالة الفعلية للحظر + consent في الرد، و422 عند محاولة رفع الحظر غير الموثقة.
- `saas/mobile/server/ops.php`: إزالة زر «إلغاء الحظر» غير الآمن، وإظهار السبب؛ عرض عدد المستوردين المحظورين.
- `tools/test_pilot_workspace.php`: اختبارات لحظر أثناء Lease، رفض رفع الحظر، بقاء الحظر بعد حذف جهة الاتصال وإعادة استيرادها، منع Arm، وفساد ملف DNC.
- `.github/workflows/voice-p1-p2-smoke.yml`: تشغيل الاختبارات عند Pull Request بدل الاعتماد على push إلى main فقط.

## حدود الإثبات
- `pilot/dnc.json` **مستقل** عن `saas/console` متعدد المستأجرين؛ لا تخلطهما قبل ربط جهاز/شريحة بهوية tenant معتمدة. لا تدعي حل عزل الشريحتين.
- التحقق يحصل مباشرة قبل `placeCall`، لكن يوجد فاصل زمني صغير بين جواب الخادم وطلب Android لا يمكن إلغاؤه تمامًا بدون بروتوكول تنفيذ موثّق وACK على الجهاز.
- لا توجد مكالمة حقيقية أو اختبار Android/host في هذه الدورة. رفع الحظر بموافقة جديدة، مراقبة المكالمات، وتوصيل DNC إلى tenant-aware dialer تبقى OPEN.
- نشر `saas/mobile/server` **ليس ضمن workflow SaaS Console**؛ نجاح CI أو دمج PR لا يعني نشر هذه الملفات على الهوست. يلزم مسار نشر موثق وreceipt مستقل.

## معايير الإغلاق
PHP lint + `php tools/test_pilot_workspace.php` + `php tools/test_mobile_call_ledger.php` في CI، ثم مراجعة PR ودمج إذا نجحت الاختبارات. تحقق النشر على الهوست منفصل.

## نتيجة الدورة المؤكدة
- PR #13 merged: `c303880e94b74263a4f5afd3bc1a3a790fb6720a`.
- Final PR CI: `41306010120` (pilot) SUCCESS و`41306010195` (SaaS) SUCCESS؛ post-merge `41306044753` (pilot) SUCCESS.
- لا يوجد receipt نشر جديد لـ`saas/mobile/server`؛ آخر receipt خاص بـSaaS Console فقط. **لم يتم اختبار اتصال SIM فعلي.**
- اكتُشف أن workflow Android القديم يبني وينشر APK بمجرد تعديل PHP في server؛ تشغيل `41306044730` بدأ بالفعل. أصلحت trigger المستقبلي في commit `eef9327049101c2a6798ba985a127fc9156d480e`، لكن هذا لا يلغي التشغيل الجاري؛ يلزم متابعة نتيجته قبل أي ادعاء إطلاق.
