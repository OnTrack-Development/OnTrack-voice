# OnTrack Voice — إصلاح مشاكل «أولاً: منطق النظام» كدفعة موحدة — v67 — 8 نوفمبر 2026

## السياق
طلب المالك معالجة **جميع مشاكل القسم الأول مرة واحدة**، وليس مشكلة واحدة من كل مجموعة. هذه وثيقة دليل تنفيذ فعلي منفصل عن ادعاءات تجربة الهاتف أو مزودات الدفع/Meta الحية.

- المستودع: `OnTrack-Development/OnTrack-voice`.
- الفرع: `feat/io-premium-native-v51-20261107`.
- نسخة الإصلاح الأول `v66`: [42449682572](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42449682572) **SUCCESS / host deployed**.
- دفعة `v67`: [42450818200](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42450818200) **SUCCESS / host deployed**؛ ثم [42451096873](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42451096873) **SUCCESS / host deployed** عند تعزيز تسوية استردادات الدفع؛ تحسينات لاحقة في نفس الفرع يجب أن تمر بنشر وتحقق مستقلين قبل اعتبارها منشورة.
- الـWorker الخاص بواتساب: [42451045352](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42451045352) **SUCCESS**، تثبيت Cron مستقل لدقيقة واحدة `status=newly_installed`، مع إثبات الحفاظ على بقية إدخالات Cron وعدم لمس Host-Link الموقوف.
- المكان الفعلي: `/home/appontrack/public_html/`، SSH مباشر من BEDIR، نسخ الاسترجاع داخل `/home/appontrack/.ontrack-mobile/saas-first-logic-v67/`.
- لم يتم إصدار APK جديد، ولا تعديل بيانات حسابات العملاء أو أي مدفوعات قائمة.

## 8 أعطال وأدلة الإصلاح

| # | العطل | تم في الكود/السيرفر | الحالة خارج الاختبارات |
|---|---|---|---|
| 1 | أرشفة باقة الريسيلر تقطع صوت اشتراك ساري | تعديل استعلام `tenant_voice_entitled` يسمح باستخدام نسخة تاريخية للباقة إذا كانت الاشتراكات والحسابات حية، مع رفض الإلغاء/الانتهاء/التعليق؛ v66 منشور. | مكالمة SIM حقيقية بعد تغيير باقة: غير مثبتة. |
| 2 | Builder graph مجرد تعليمات، مش تنفيذ حتمي | `device/v1` عمليات `flow_start`/`flow_step`/`flow_state`؛ منشور، مرتبط بشريحة موثقة ونسخة منشورة غير قابلة للتغيير، وتعارض نسخة محمي. اختبار paired device state PASS. | **تطبيق Android/Gemini Live لا يستدعي هذا التنفيذ بعد**؛ الصوت نفسه ما زال يحتاج تكامل ومكالمة E2E. لا يوصف DONE كاملًا. |
| 3 | CRM/WHMCS لا ينفذ من المكالمة | طلبات Flow تؤجل الأدوات حتى وصول موافقة مالك مصادق + نتيجة API مثبتة بنفس Call ID/policy/device؛ `voice_flow_complete_tool` والتحقق من الأدوار والواجهة، اختبار استهلاك نتيجة مرة واحدة PASS. | التكامل الفعلي من Gemini/DTMF إلى WHMCS/REST على مكالمة حقيقية غير مثبت. |
| 4 | رقم واتساب موجود يستقبل لكن الرد يفشل | قناة `existing_webhook` لها موصل إرسال عبر HMAC إلى مسار ثابت في WHMCS، واختبار قدرات قبل تفعيل AI؛ لا تكوين Meta مكرر. اختبارات عقد الإرسال PASS. | يجب نشر/إعداد موصل الإرسال الحقيقي في إضافة WHMCS على `services.ontrackegy.com`، ثم اختبار Meta الرسمي. في غيابه النظام يرفض التفعيل ولا يدّعي نجاح الإرسال. |
| 5 | WA jobs عالقة Processing | استرداد jobs القديمة قبل إرسال HTTP، تمييز `sending` المجهولة إلى `needs_review` بدل تكرار الإرسال؛ Cron مستقل مفعّل بدون استئناف المراقبة القديمة. | راقب جدول المهام الحقيقي ومعالجة الحالات المعلقة في لوحة المسؤول. |
| 6 | إرسال Meta داخل معاملة DB | استبدال المسار بحجز `sending` في DB ثم commit ثم إرسال خارجي ثم كتابة الإيصال؛ عند الفشل تُعلّم مجهولة ولا يعاد الإرسال أعمى. اختبارات idempotency PASS. | انقطاع الشبكة وأحداث التسليم من Meta تحتاج E2E حقيقي. |
| 7 | التجديد المدفوع يمسح الأيام القديمة | الاستحقاق الجديد ينتهي بعد `max(now, previous ends_at)+30 days`، مع مرجع subscription في حدث الدفع، واختبارات مدفوعات متكررة/replay PASS؛ تحديث نص الباقات حتى لا يدعي ضياع الأيام. | اختبار Kashier LIVE بعملية دفع تجريبية موثقة ما زال مطلوبًا. |
| 8 | Refund/Reversal بلا أثر مالي | جدول أحداث مستقلة، تطابق الطلب والمبلغ والعملة ومرجع المعاملة الأصلية، حماية من إعادة الإرسال، رد أصل الحساب المطابق فقط، مراجعة الحالات الجزئية والتاريخية/المتناقضة بدل إلغاء اشتراك آخر، ومؤشر مراجعة مالية في الباقات. | يحتاج مطابقة نموذج Webhook LIVE من Kashier، وإجراءات مراجعة/استرداد فعلي مستقلّة. |

## أدلة وحدود الجودة
- `V67_PAID_TERM_CARRY_AND_TRANSACTION_LINKED_REVERSAL_PASS`.
- `V67_WA_SIGNED_EXISTING_OUTBOUND_STALE_RECOVERY_NO_DUPLICATE_PASS`.
- `V67_PAIRED_DEVICE_DETERMINISTIC_FLOW_AND_VERIFIED_TOOL_BRIDGE_PASS`.
- `V67_ALL_LOGIC_FILES_PUBLISHED_WITH_ROLLBACK_PASS` / `V67_LIVE_CANONICAL_PHP83_RECHECK_PASS` / `V67_LIVE_PAYMENT_WA_FLOW_PHP_AND_HTTPS_SMOKE_PASS`.
- `V67_WHATSAPP_DURABLE_WORKER_CRON_INSTALLED_PASS`.
- **لا تساوي فحوص SQLite ومعاملات Meta mock تحققًا حيًا من الحسابات والمدفوعات والهواتف**. لم نتعامل مع حساب Meta/WHMCS خارجي حقيقي أو هاتف المالك في هذه الدفعة.
- ممنوع إظهار بيانات الاعتماد في المستودع أو استئناف Host-Link/الجدولة التي أوقفها المالك. الـCron الجديد مخصص فقط للردود التي فعّلها صاحب القناة صراحةً في منصة SaaS.
