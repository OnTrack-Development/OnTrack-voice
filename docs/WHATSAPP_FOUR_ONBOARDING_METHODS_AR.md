# OnTrack Voice — WhatsApp onboarding (قرار 2026-11-02)

## الطرق المتاحة للعميل
1. **واتساب مربوط من قبل**: يحتفظ العميل بـCallback URL وVerify Token الموجودين بالفعل في Meta (مثال: موديول WhatsApp Notifications في WHMCS). يدخل هذه البيانات كمرجع ويضيف WABA ID وPhone Number ID وبيانات إرسال Meta (Access Token/App Secret). يولّد OnTrack **رابط Forwarding ثانوي** ومفتاح توقيع خاصين بحساب العميل. لا يغير أي إعداد لدى Meta. الموديول الحالي يجب أن يمرّر الرسائل الموقّعة إلى OnTrack. إدخال Callback القديم وحده لا يقوم بربط الرسائل. رد AI يخرج عبر Meta Cloud API الخاص بالرقم ولا يستدعي AI Support Agent في WHMCS.
2. **رقم لم يرتبط من قبل**: العميل يملأ WABA ID وPhone Number ID وMeta App ID وApp Secret وAccess Token وGraph API Version. ينشئ OnTrack Callback URL وVerify Token من عنده، فينسخهما العميل إلى Meta Developers / WhatsApp / Configuration. كل حقل له رابط/إرشاد رسمي إلى موضع المعلومة (تحت الحقل). تُحفظ الأسرار مشفرة أو كـHash ولا يُعاد عرضها.
3. **Meta Embedded Signup v4**: خيار يظهر في صفحة العميل فقط عندما يفعّله *أدمن المنصة* من إعدادات المنصة مع Meta App ID/App Secret/Configuration ID/Graph Version/Platform Webhook token؛ يمنح العميل الموافقة عبر نافذة Meta الرسمية. الكود المؤقت يُستبدل في السيرفر، ويتم التحقق من WABA + رقم الهاتف وإجراء اشتراك Webhook قبل إنشاء سجل **pending_verification**؛ لا يُفعّل AI تلقائيًا.
4. **Coexistence**: مثل Embedded Signup لكن بConfiguration ID مستقل وfeatureType الخاص بـWhatsApp Business App onboarding حتى يحتفظ العميل بتطبيق WhatsApp Business عند أهلية الرقم. يظهر فقط عندما يفعّله الأدمن ويكمل إعداداته. لا ضمان للأهلية حتى تتحقق Meta.

## أماكن البيانات (روابط داخل الصفحة)
- WABA ID: https://business.facebook.com/settings/whatsapp-business-accounts
- Phone Number ID: https://business.facebook.com/wa/manage/phone-numbers/
- Meta App ID / App Secret: https://developers.facebook.com/apps/ ← Settings / Basic
- Access Token: https://business.facebook.com/settings/system-users ← Users / System Users / Generate Token مع whatsapp_business_messaging و whatsapp_business_management
- Graph Version: https://developers.facebook.com/docs/graph-api/guides/versioning/
- Verify Token / Callback URL: **ينشئهما OnTrack** ولا يبحث العميل عنهما خارج النظام. Meta Developers / WhatsApp / Configuration هو موضع **لصقهما**، وليس مصدرهما.
- الموقَع الداخلي والإيجنت: روابط إلى OnTrack / Sites وAgent Studio.

## الصلاحيات والأمان
- حساب العميل يملك قنواته فقط. ممنوع تحويل رقم رُبط بالفعل إلى عميل آخر تلقائيًا. لا يُعد Meta النجاح مكتملًا إلا بعد خطوة اختبار أصلية.
- الأدمن فقط يغير إتاحة التلقائي وCoexistence (مربوط بصلاحية منصة فعلية، مش صلاحية مستخدم). الإعدادات الحساسة ملك المنصة ولا تظهر للعملاء.
- Platform Callback المشترك للطرق التلقائية يتحقق من Meta App Secret ثم يوجّه كل رقم إلى tenant الصحيح بناءً على Phone Number ID؛ دون الاعتماد على account_id من webhook.
- الإيجنت OnTrack مستقل، وفي الربط الجديد يبدأ الرد الآلي **OFF** حتى يختاره العميل ويفعّله بعد التحقق.
- مفتاح relay الخاص بعميل وصلة موجودة: HMAC SHA-256 وHeader X-OnTrack-Webhook-Signature-256، مجدد بسهولة دون إعادة ربط Meta.
- ربط الموديول الموجود فعليًا يحتاج إضافة Forwarder في الموديول نفسه. كود forwarder متاح في integrations/whatsapp-webhook-relay/forwarder.php لكنه لا يعمل تلقائيًا بمجرد النشر. يلزم E2E من مصدر واتساب الحقيقي.
- لا تدّع أن Embedded Signup/Coexistence مفعّلان لدى Meta أو أنهما مرخّصان لمجرد ظهور إعدادات في لوحة الأدمن؛ يلزم صلاحيات Meta App Review + Business Verification + Embedded Signup config واستلام حدث وموافقة واختبار على رقم التجربة.

## النشر والاختبار
- اختبار بناء: tools/test_whatsapp_four_onboarding.php
- اختبار Relay: tools/test_whatsapp_webhook_relay.php
- اختبار Inbox/AI: tools/test_whatsapp_native_agent.php
- Run CI: .github/workflows/whatsapp-four-methods-tests.yml
- اختبارات الـMock والـCI لا تثبت Live Meta فعليًا. لا تغيّر Webhook الخاص بموديول WHMCS الحالي أثناء تطوير هذه النسخة.
