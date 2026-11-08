# OnTrack Voice v68 — المرحلة التانية كاملة (عمليات حقيقية، مش شاشات فقط)

**المستودع:** OnTrack-Development/OnTrack-voice  
**الفرع:** feat/io-premium-native-v51-20261107  
**التاريخ:** 2026-11-08  
**مكان النشر:** /home/appontrack/public_html/  
**النشر المؤكد للسيرفر:** [GitHub Actions 42458205969](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42458205969) — SUCCESS، اختبار كامل للدفع والـSSO والبريد والـOutbox وسياسات DNC/حملات الشريحة، مع تحقق PHP 8.3 وHTTPS وتسجيل Cron PASS.  
**نسخة التطبيق:** v56 / 0.3.33 — لا تُعتبر منشورة إلا بعد نجاح بوابة Android والبصمة والنشر العام على مسار OTA.

## 1. الدفع الخارجي WHMCS/Laravel — استحقاق مدفوع حقيقي
- أنشأنا ExternalOrderClaim.php وأضفنا استقبال subject خارجي *موقّع* عند `order.created`، مع الحدث الموقّع `order.identity_attested` لاستكمال الطلبات القديمة.
- تفعيل الاشتراك مرتبط بتطابق المزوّد/المصدر/طلب الدفع/الهوية الخارجية SSO/عضوية مالك الحساب، ولا يعتمد إطلاقًا على اسم أو بريد المشتري وحده.
- العميل صاحب الحساب يضغط «تفعيل الطلب المدفوع» بعد إثبات ربطه بنفس external_subject؛ من غير proof ترجع paid_pending_claim بدون استحقاقات.
- المعاملة تحفظ تاريخ نهاية الأيام المدفوعة الموجودة، وإصدار الفاتورة يبقى باسم جهة إصدارها وإن تغيّرت ملكيتها لحساب العميل.
- الاختبار `V68_EXTERNAL_SIGNED_SUBJECT_PAID_ORDER_CLAIM_AND_INVOICE_PASS` نجح على قاعدة اختبارات مستقلة.
- **فجوة الإنتاج المتبقية:** التكامل WHMCS/Laravel المُصدِر يجب أن يرسل external_subject موقّعًا أو addendum بنفس سر HMAC؛ من غيره يفضل الطلب آمنًا في pending claim بدل تفعيل وهمي.

## 2. Android SIM campaigns — واقعي مع قيود الأمان
- التطبيق يستدعي `workspace_campaign_claim` ثم `workspace_campaign_verify` عبر توكن الشريحة المربوطة، ويتحقق من consent وDNC والتوقيت و10 ثوانٍ قبل بدء الاتصال.
- `TenantCampaignDialer.kt` يستخدم TelecomManager برقم مصرح به من SaaS، والشريحة المربوطة فقط، ويصنّف نتائج المكالمات عبر سجل المكالمات الحقيقي (أو unknown).
- تم تفعيل الزر خلف checkbox وموافقة صريحة بتكاليف المكالمات؛ الوضع يتوقف عند إعادة تشغيل الخدمة، وزر «إيقاف» يعطل طلب اتصالات جديدة.
- تمنع الوظيفة تشغيل حملات في وجود مكالمة أخرى أو تحويل بشري، وتوقف تكرار المكالمة عند unknown.
- **مهم:** `ONTRACK_TENANT_CAMPAIGN_DIAL_ENABLED` يظل مغلقًا افتراضيًا على الإنتاج حتى نجاح فحص شريحة حقيقية وإجازة صاحب المنصة؛ كود التنفيذ أو build APK ليس دليلًا أن مكالمات الشبكة تعمل فعلًا على جهاز المالك.

## 3. Outbox / Workers / Cron
- `operational_event_receipts` سجل محلي دائم مستند إلى SHA-256 ومرجع outbox لا يمكن تكراره، ومهام `pending/processing/failed/delivered_local` تدعم انتهاء leases واسترداد المهام بأمان.
- `delivered_local` معناه **سجل محلي تم قبوله**، وليس رسالة وصلت WHMCS أو Email.
- worker مستقل مع lock لتشغيل Outbox والبريد، وworker Flow AI مستقل، وWhatsApp worker، وفحص Knowledge Sync؛ جميعها تكتب heartbeat وحالة آخر تشغيل للأدمن.
- إعداد cron الخاص بهذه العمال في [v68 host publish](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42458205969) فقط، دون تفعيل Host-Link الموقوف أو مساس بإدخالات cron الأخرى.
- اختبارات `V68_OUTBOX_RECOVERY_SMTP_RETRY_SECRET_ERASURE_AND_SIGNUP_PASS` PASS. اختبار استحقاقات الشريحة `V68_CAMPAIGN_PAIRED_SIM_CONSENT_DNC_E2E_GATE_UNCERTAIN_CALL_PASS` أيضًا PASS في قاعدة اختبار معزولة؛ **ليس** إثبات اتصال هاتفي في شبكة المحمول.

## 4. بريد التسجيل وإعادة كلمة السر
- `EmailDelivery.php` يوفّر SMTP/TLS موثقًا عبر ملف خاص خارج webroot، أو PHP mail حسب الإعداد. لا توجد كلمات مرور SMTP داخل الريبو.
- يتم تشفير التوكنات على الخادم داخل طابور البريد، وحذف payload بعد قبول الإرسال أو انتهاء الصلاحية، وتسجيل محاولات الفشل مع retry محدود.
- إن تعطل العامل وسط DATA تكون الحالة unknown / needs_review بدل إرسال توكنات مكررة تلقائيًا.
- لوحة Admin تعرض حالة worker والبريد، وAccepted **ليس** دليل وصول البريد لصندوق المستخدم.
- **فجوة الإنتاج المتبقية:** التحقق من إعداد SMTP الحي وإيصال بريد تجريبي حقيقي، وليس mock.

## الحالة
**جزء SaaS backend:** منفذ ومنشور مع تأكيد CI/SSH وrollback.  
**جزء Android:** لا تدّعي نجاح البناء والنشر حتى يظهر PASS مستقل لـ v56.  
**E2E الإنتاج:** WHMCS/SMTP/Carrier SIM التجارب الحقيقية ما زالت تحتاج إثباتًا من الأنظمة الخارجية، وليس واجهات شكلية.
