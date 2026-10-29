# أين وصلنا؟ — 29 أكتوبر 2026

## أحدث توجيه — مساحة SaaS والميموري

المشروع الجديد مخصص للمسار `/home/appontrack/public_html/saas`، ومصدره في `saas/` بالريبو، مع ترك ملفات الرئيسية كما هي. **الميموري والخطة والسجلات والبرومبت على GitHub فقط خارج مجلد التطبيق**. نطاق الجلسة الحالية تجهيز المساحة والملفات ثم الإبلاغ قبل تنفيذ التطبيق.

اقرأ [CHECKPOINT](../memory/CHECKPOINT.md) و[برومبت التنفيذ](../execution/MASTER_EXECUTION_PROMPT_AR.md) لمعرفة أحدث نتيجة التجهيز ودليلها. طلب المالك أضيف له الرد على القنوات الاجتماعية وTelegram حسبAPIرسمي وصلاحيات كل قناة؛ لا تكاملات جديدة متصلة في هذه الجلسة.

## النتيجة المؤكدة

**تم إثبات التحكم في حساب الاستضافة من هذه المحادثة من خلال GitHub. لم يتم إثبات مرور صوت مكالمة شريحة إلى الإيجنت والعودة منها.**

| الجزء | الحالة | الدليل |
|---|---|---|
| ديمو الصوت PHP/browser | كود موجود v0.6.3؛ المستخدم لديه تجربة عربية سابقة | ملفات الجذر وREADME القديم ونسخة المرفق. لا يوجد اختبار جودة حديث مسجل هنا. |
| استيراد handoff | مواصفات وتصميم v1.3، لا Laravel backend | `reference/agent-builder-v1.3/`. |
| دخول SSH من بيئة هذه الجلسة | فشل قبل المصادقة | Network is unreachable على 65.21.208.232:7195 باسمَي appontrack وai. |
| SSH لدى إيجنت آخر | المستخدم يؤكد أنه يعمل؛ لقطة الأمر موجودة | لقطة Bash؛ لا نعتبرها مخرج SSH ناجحًا صادرًا من جلستنا. |
| قناة GitHub ← الهوست ← GitHub | **VERIFIED_LIVE_CONTROL** | تقرير bootstrap + تقرير exec ناجح. |
| Cron | موجود مرة واحدة، ونفذ أمرنا دون تشغيل يدوي | host_link_cron_count=1، host_link_cron_present=true. |
| قراءة وكتابة ملف | **VERIFIED_LIVE_CONTROL** | connection-check.txt كتب nonce وأعاد نفس النص. |
| SIP/PBX | غير مُجهز/غير مُثبت في هذا المسار | Asterisk غير ظاهر في PATH؛ لم ننشر سنترالًا هنا. |
| Shizuku وVoice SIP Agent على الهاتف | المستخدم جرب إعدادًا ولم ينجح الربط؛ تفاصيل الخطأ غير متاحة | لا نتيجة echo ولا logs ولا إصدارات موثقة حتى الآن. |
| نقل صوت SIM ثنائي الاتجاه | **PENDING_DEVICE_AUDIO_PROOF** | لا اختبار فعلي ناجح حتى الآن. |
| SaaS والجداول والحملات والتقارير | PLANNED | متطلبات وخطة، لا تنفيذ إنتاجي مثبت. |

## بيئة الهوست

- Domain context: `http://app.ontrackegy.com/`، ومسار كود الديمو المؤكد `/home/appontrack/public_html`. لم نتحقق من كامل mapping الدومينات في cPanel.
- Hostname: `server11.hhost.host`، IP: `65.21.208.232`، SSH port supplied by owner: `7195`.
- حساب التحكم الفعلي: `appontrack`، UID `2379`؛ الحساب `ai` ذُكر لاتصال الإيجنت الآخر وليس حساب عاملنا.
- PHP CLI: `/usr/local/bin/php`، `8.1.34`. Python: `/bin/python3`، `3.9.25`.
- Git: `/usr/local/cpanel/3rdparty/lib/path-bin/git`؛ Cron: `/bin/crontab`.
- node/npm/asterisk/ffmpeg/adb لم تظهر في PATH الخاص بالحساب. لا يعني هذا نفي وجودها على السيرفر كله.
- Linux kernel من مخرجات المستخدم: `5.14.0-687.29.1.el9_8.x86_64`، وGID `2383`؛ ليست معلومات فحص kernel جديد منا.
- لم يتم منح root أو إثبات السماح بخدمات صوت طويلة أو منافذ SIP/RTP عامة. لا ترقِّ PHP ولا تثبّت PBX دون التحقق.

## ربط الإدارة الموجود — لا تعِد إنشاؤه

- Repository: `OnTrack-Development/OnTrack-voice`، **Private**، default branch `main`.
- Live control branch: `ontrack-host-link`.
- Local install: `/home/appontrack/ontrack-voice-link`.
- Credential: `config.json` على الهوست فقط، mode 600، لا يُطلب من المستخدم إرساله ولا يُقرأ في تقارير.
- Queue: `ops/host-link/jobs/appontrack-server11.json`، target `appontrack-server11`.
- إثبات التحكم الأساسي: `verify-terminal-20261028234301727`، exit0. أحدث عمليات تجهيزsaas مسجلة مستقلة في CHECKPOINT ولا تلغي هذا الدليل.
- Evidence UTC: `2026-10-28T23:44:02.919046+00:00` إلى `23:44:03.950991+00:00`؛ يقع في 29 أكتوبر بتوقيت القاهرة. زمن التنفيذ 1032ms، ولا يشمل زمن انتظار Cron.
- التقرير: [verify-terminal result](../../ops/host-link/results/appontrack-server11/verify-terminal-20261028234301727.json).
- Worker/installer pinned source: `08363de8125d454e5df3180cab86f0113c947ba6`. نشر bootstrap: `7886478da65ae4d140a295dae70cedd8c0cc9bfa`.
- التوكن اختير بمدة مقترحة 7 أيام؛ **تاريخ انتهاء التوكن الفعلي غير معروف ولم نقرأه**. إذا توقف الربط لاحقًا، تشخيص الصلاحية أولًا، لا إعادة الطلب تلقائيًا.

## الهاتف

المالك حدد Xiaomi/Redmi Note 12 Pro 5G. لا نملك إصدار Android أو MIUI/HyperOS أو إصدار Voice SIP Agent/Shizuku الفعليين. لا يوجد وصول تحكم إلى الهاتف من هوست الحساب. مسار Shizuku مرشح منخفض تكلفة الإعداد المادي، لكنه لم ينجح بعد في اختبارنا.

## الخطوة التالية الخاصة بإثبات الصوت بعد أمر البدء

1. تثبيت بيانات الهاتف/التطبيقات الفعلية، ثم اختبار echo المحلي الموثق في PHONE_AUDIO_TEST؛ هو ممكن دون نشر PBX.
2. حفظ النتيجة الواردة من الهاتف، مع auto-answer والتقاط صوت المتصل وإرجاع الصوت عبر المكالمة؛ اختبار ON/OFF وسجلات لمنع الخلط مع sidetone أو صدى السماعة.
3. إن نجح، نختبر قناة هاتف↔سيرفر↔Gemini كاملة. نحدد خدمة الصوت/PBX وبيئة تشغيلها بناء على الاختبار، ولا نفرض Node/Asterisk أو نبدّل Gemini مقدمًا.
4. إن فشل، نراجع logs والإصدارات وسبب downlink/uplink، ونسجل فشل الجهاز؛ لا نعود لبناء داشبورد pairing ونعتبره حل المكالمة.

تجهيز المستودع في هذا التحديث عمل توثيق وتنظيم فقط، ولا يتضمن نشر تعديل على الموقع أو إنشاء مكالمة أو تغيير إعدادات الهاتف.
