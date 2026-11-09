# SaaS 1+2+3 — تقرير تكامل مرشح الاختبار
**تاريخ التقرير:** 2026-11-09
**الحالة:** MERGED ON ISOLATED BRANCH · OFFLINE CI PASS · HOST QA NOT RUN · NOT DEPLOYED
**فرع المصدر:** `fix/saas-integrated-phases123-20261109`

## نطاق الدمج
تم إنشاء Merge Commit **بين فرعي Phase 1 وStage 3 (المبني على Phase 2)** على فرع تكامل مستقل فقط. لم يجرِ Merge إلى `release/voice-v1-20261106` أو `main` أو نشر إلى `app.ontrackegy.com`.

- Phase 1: Token issuance guard, maintenance auth and private config, Omnira tester auth, signed Host-Link job guard، توافق Pilot adapter، حفظ ونشر سياسة الرد **5 ثوانٍ** من صفحة الـSaaS.
- Phase 2: tenant SIM contract probe، Flow policy reauthorization، KB bounded fetch، staff ACK compare-and-swap، عرض الاشتراك النشط الصحيح، توافق WhatsApp/WHMCS/Call Reports/Google keys.
- Phase 3: منصة الأدمن ترى وتتحكم في باقات الريسيلرز عبر action `assign_plan`، والريسيلر يرى باقته الخاصة وباقات السوق ورابط Checkout، دون تجاوز الدفع.
- **حل تداخل Views:** دمج نموذج الرد السريع من Phase 1 في `render_answering` مع الحفاظ على إضافات Plans وBilling من Phase 2/3. لم تُستبدل شاشة كاملة بنسخة قديمة.
- **اختبار Flow AI:** تم تصحيح فحص Test قديم كان يطلب `function showProposal(g)` بدل التوقيع الحالي `showProposal(g,baseline=null)`؛ أبقينا آلية Baseline وDraft collision safety الحقيقية وحميناها باختبارين إضافيين.

## دليل الاختبارات المجمعة — منفذة فعلاً

**[GitHub Actions #42557761596](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42557761596) — SUCCESS.**

شمل التشغيل:
- PHP syntax لجميع `saas/console` ومداخل الـAPI وJavaScript syntax + Python compile.
- Session-CSRF/Origin/Rate limit لـGemini Live وLocal HTTP بدون طلب فعلي إلى Google؛ رفض غير المصرح وحفاظ Contract الموبايل.
- Auth للصيانة وCSRF لأداة Omnira، تحقق توقيع Ed25519 لوظائف Host-Link دون تنفيذها.
- 30 اختبار SQLite معزولًا: Agent/Flow/KB، PDF حقيقي عبر Poppler المثبت على Runner، Tenant/SIM/Device Pairing، تقارير المكالمات والموظف، Google key failover بمزود Mock، اشتراكات/Checkout وكاشير، SSO، WHMCS/WhatsApp وDNC.
- UI static: Modal/AJAX، Graph Routing، Floating AI Flow وFullscreen، وQR.
- أكد `SAAS_INTEGRATED_ISOLATED_MATRIX_PASS suite_count=30`، `FLOATING_FLOW_CHAT_AND_FULLSCREEN_CANVAS_PASS`، `SAAS_INTEGRATED_NO_NATIVE_APK_OTA_DEPLOY_PASS`.
- جميعها **CI Offline**؛ ليس لها سلطة إثبات الصوت الحقيقي أو صلاحية مفاتيح Google أو مرور الدفع على الاستضافة.

## بوابات قبول قبل أي نشر / اختبار على النسخة الحية

| البوابة | ما يجب فحصه | الحالة |
|---|---|---|
| 1. Host snapshot | نسخ DB وVault/config، والتحقق من Restore على عزل، وتسجيل Source SHA وHost SHA وRollback جاهز | **[ ]** |
| 2. Maintenance secret | تثبيت digest خارج Webroot، تغيير السر المتسرب وإلغاء القديم، اختبار Legit Admin Tool قبل أي غلق | **[ ]** |
| 3. Auth/Tenants | Admin/Reseller/Customer، CSRF/SSO وLogin بالبريد/الباسورد في التجارب دون فرض OTP، وعدم كشف بيانات عميل آخر | **[ ]** |
| 4. Plans/Checkout | باقات الريسيلر Admin+Owner، اشتراك العميل الحالي، رسوم التجاوز، Kashier sandbox Webhook/Manual references | **[ ]** |
| 5. Device/SIM | Phone bearer/heartbeat/published revision + عدم فك الشريحة أو تغيير token/agent/voice بغير طلب صريح | **[ ]** |
| 6. Answer delay | حفظ ونشر 5 ثوانٍ على إيجنت تجريبي ومقارنة المسودة/المنشور؛ قياس الرد الفعلي على الجهاز | **[ ]** |
| 7. Voice continuity | اختبار مكالمة واردة وصادرة بالصوت من الطرفين على Wi-Fi والداتا، جودة الميوت والمقاطعات، والتقرير | **[ ]** |
| 8. Knowledge/Flow | PDF على هوست حقيقي، Knowledge اختيارية، API verified read/write consent، Flow tool provider sandbox | **[ ]** |
| 9. WhatsApp/WHMCS | تشغيل Webhook signed/routing/tenant scope دون إرسال مالي أو تسويقي غير متفق عليه | **[ ]** |
| 10. Rollback | مقارنة SHA، تنفيذ بروفة استعادة منفصلة، ثم اختبار موقع + هاتف بعد أي نشر مصرح | **[ ]** |

## محظورات عمل التحديث
- لم يُعدل Android/PCM/APK/OTA أو توقيع التطبيق؛ لا مساس بـ`VS/` لأنه محذوف وفق تأكيد المالك.
- لا تفعيل Host-Link الموقوف أو حملات خارجية/مدير تلقائي من اختبار.
- لا تغيير لقيم الباقات، مفاتيح مزودات AI، ربط SIM، أو بيانات مستخدمين.
- **ممنوع إعلان `DONE` لأي من مهام الخطة الـ31 بمجرد مرور CI.** الكود اجتاز بوابة الاختبارات المجمعة، لكن Host/Phone E2E لم يتم.

## قرار الإصدار
**HOLD / NO DEPLOY** حتى إغلاق بوابات Host/SIM الفعلية، وبناء تقرير مقارنة ما قبل/بعد وRollback. لا يمكن تنفيذ تلك البوابات من GitHub source وحده؛ يلزم بيئة استضافة وجهاز تجريبي مملوكان للمستخدم.
