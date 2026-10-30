# سجل التجارب والنتائج

| ID | ما جُرّب/بُحث | النتيجة | درجة الإثبات وما لا يثبته |
|---|---|---|---|
| X-001 | فحص ملفات handoff وvoicecall | مواصفات Agent Builder وديمو Gemini browser v0.6.3 | SOURCE_REVIEW؛ لا telephone transport في الكود المرفوع. |
| X-002 | Phone Link + Voicemeeter السابق | مستخدم في تعليمات الديمو القديم؛ المستخدم رفضه | REJECTED_PATH؛ لا يُعاد تقديمه كحل SaaS. |
| X-003 | فكرة LiveKit Agents | طُرحت ثم سُحبت كاعتماد لازم | NOT_ADOPTED؛ لا يحل مشكلة SIM audio بنفسه. |
| X-004 | CPaaS/Verified Caller ID/forwarding | بدائل بحثية، رُفضت كافتراضي لتكلفة API | RESEARCH_ONLY؛ لا خط أو عقد أو مكالمة فعلية هنا. |
| X-005 | GSM/VoLTE Gateway | معمارية تقنية مقترحة ثم اعترض المالك على تكلفة جهاز لكل عميل | NOT_SELECTED_DEFAULT؛ لا جهاز تم شراؤه/اختباره. |
| X-006 | Voice SIP Agent + Shizuku | المستخدم حاول الإعداد ولم يتم الربط | USER_REPORTED_UNSUCCESSFUL؛ لا logs لتشخيص هل السبب readiness أم SIP أم audio. |
| X-007 | OnTrack AI Phone السابق | pairing/control/dashboard بحسب تاريخ المناقشات؛ المستخدم يؤكد لا مرور مكالمة | HISTORICAL_UNVERIFIED_SOURCE؛ لا مصدر/APK هنا لإعادة اختباره. |
| X-008 | direct SSH باسم appontrack | Network is unreachable | LOCAL_CONNECTIVITY_FAILURE قبل auth، ليس رفض key أو port من السيرفر. |
| X-009 | direct SSH باسم ai على7195 | نفس الخطأ من بيئتنا؛ إيجنت آخر يعمل بحسب المستخدم | لا نغير خادم SSH بسبب مشكلة routing محلية. |
| X-010 | Browser/cPanel Terminal | التبويب قُرئ وظهر input؛ أوقف المستخدم المسار | READ_ONLY_STOPPED؛ لم ينفذ shell command عبر المتصفح. |
| X-011 | Echo incoming calls local test | وُثقت الخطوات من upstream؛ لا نتيجة من الهاتف | PENDING_DEVICE_AUDIO_PROOF. |
| X-012 | اختبارات GitHub worker محليًا | 11/11 pass | TESTED_LOCAL؛ fake API يختبر الاعتمادية، لا يثبت اتصال الهوست. |
| X-013 | تثبيت العامل على الهوست | CONNECTED وتقرير bootstrap مرفوع | VERIFIED_LIVE_CONTROL؛ HTTPS poll/upload فعلي. |
| X-014 | exec يصدر من هنا وينفذ عبر Cron | success، exit0، ملف nonce وقراءة، PHP وCron | VERIFIED_LIVE_CONTROL؛ هذا هو إثبات التحكم الفعلي. |

## تفاصيل الوصول SSH

- عنوان المستخدم: `65.21.208.232:7195`؛ account العامل appontrack، والـai مستخدم الإيجنت الآخر.
- ولّدنا public Ed25519 لتجربة محددة، fingerprint: `SHA256:o0TQL7N3UfaedXhRTlSed2lFfseggfWmwRJOUB8qVuo`.
- الأصل العام موجود في المرفقات السابقة؛ المفتاح الخاص ليس جزءًا من المستودع ولا يلزم للربط الحالي.
- خطأ محاولاتنا: `ssh: connect to host 65.21.208.232 port 7195: Network is unreachable`.
- لم نلاحظ مصادقة ناجحة أو فشل مصادقة على السيرفر. لا نستنتج من هذا أن البورت مغلق أو المفتاح غير مصرح.
- بعض تشخيص DNS المحلي أعاد failure أيضًا بحسب الجلسة. لم يُستخدم ذلك مبررًا لتغيير DNS المستخدم أو الجدار الناري.

## دليل exec الفعلي

المهمة مسجلة في queue history وcommit `34bc235b70afeea8b4d4dabb3eb0072bdefa3e8e`، والنتيجة في `3f33123bc6f9f525dc9cecc4604858a4014fabeb`.

- Job: `verify-terminal-20261028234301727`.
- كتب وقرأ `/home/appontrack/ontrack-voice-link/connection-check.txt`؛ هذا ملف اختبار معلوم، وليس ملف إعداد أو بيانات عملاء.
- Returned nonce: `ontrack-host-link-verify-terminal-20261028234301727`.
- PHP: `PHP 8.1.34 (cli)`، app version `0.6.3`، Cron count `1`، stderr فارغ.
- [التقرير الخام](../../ops/host-link/results/appontrack-server11/verify-terminal-20261028234301727.json) يحفظ SHA-256 لبعض ملفات الديمو أيضًا.

## قواعد إغلاق اختبار الهاتف

Echo مسموع وحده ليس نهاية إثبات المنتج: نمنع صدى السماعة/السيدتون باختبار A/B وlogs، ثم نثبت مسار صوت الإيجنت في الاتجاهين. نحتاج واردًا وصادرًا من الشريحة نفسها، start/answer/hangup حقيقية وظهور رقم صحيح ومقاطعة، قبل اعتبار مرحلة الهاتف ناجحة.

## بحث30أكتوبر2026 بعد الاستبعادات الجديدة

| ID | البحث | النتيجة وحد الإثبات |
|---|---|---|
| X-015 | Android الرسمي ومشاريع الجسور والشركات والأسعار |39مصدرًا أوليًا في سجل البحث؛ ليست39تجربة أجهزة. |
| X-016 | JemRec عند f011062adfb3a2154c4d5a9cc4394032fb4345f2 | SOURCE_REVIEW: مسارا حقن وتحية ومساعد ADB؛ لا نجاح Redmi أو AI streaming. |
| X-017 | P2P gateway عند bb82907f6cb9fc5a1afa5a30f2fd2c923bff8821 | وضع no-root المقروء mic/acoustic؛ ليس دليل full-duplex رقمي. |
| X-018 | BootReceiver ومراقبة adbd | ينتظر أول unlock؛ لا استعادة unattended مثبتة لجهازنا. |
| X-019 | حاسبة أسعار وافتراضات | ناتج حسابي فقط؛ لا throughput أو فاتورة مكالمة فعلية. |

الخطوات والروابط والبصمات في docs/research/2026-10-30/. إجراء Shizuku السابق مستبعد؛ حالة الصوت لم تتغير.


## 30 أكتوبر2026 — وصول صوت للطرف البعيد حسب المالك

«سمعت صفاره والصوت وصل هناك». رصد مكالمة فعلي جزئي، لا إثبات حوار AI. نسخة APK والاتجاه والمسار غير محددة، ولا تقرير/تسجيل مرفق. نحتفظ بالإعداد العامل ونختبر سماع ملف الكلام بعد الصفارة ثم تسجيل عبارة الطرف البعيد؛ لا إعادة ضبط أو اقتران لمجرد الرجوع للحالة الأقدم. الدليل: [رصد التجربة](../mobile/DEVICE_OBSERVATION_2026_10_30.json). السبب الأصلي لرفض الصلاحية ما زال مجهولًا.
