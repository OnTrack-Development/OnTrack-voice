# OnTrack Voice — سجل تنفيذ إصلاحات G0–G3 يوم 3 نوفمبر 2026

**النطاق:** خارطة `docs/plan/MASTER_REPAIR_ROADMAP_2026_11_03_AR.md`. هذا سجل واقع التنفيذ، وليس شهادة إنجاز كل مخرجات G0–G3. البيئة **اختبار داخلي فقط**، وكل الحسابات الحالية Demo؛ لا عملاء دفع حقيقيين.
**الكود:** branch `feat/voice-production-gap-20261103` عند `7c9d440fbfe60065969b1d2ec62c5e10d53917f4`، منشور من نفس المصدر الموثق في GitHub إن وصل receipt مطابق؛ فرع `main` يحتوي خارطة المشروع لكنه ليس مصدر نشر SaaS الحالي.
**الـCron التشغيلي:** لا تعِد تشغيل أتمتة التطوير كل ساعة التي أوقفها المالك في 2 نوفمبر.

## الإصلاحات المنفذة مع أدلتها
| المرحلة | إصلاح في الكود | أدلة الاختبار والنشر | حالة قبول المرحلة |
|---|---|---|---|
| G0 | تحديث console pricing/Relay tests؛ إصلاح WhatsApp/WHMCS durable job؛ توسيع بوابة نشر الروت لاختبارات SaaS/SSO؛ serialized concurrency وpath triggers | Commit `a8f6ebf` + نجاح full CI 41583180427 ونشر 41583180437 مع host receipt؛ آخر اختبارات feature `41585289343` وpublisher `41585289380` SUCCESS | **جزئي**: main متباعد 603/71 commit؛ لا force-merge أو تغيير default branch دون reconciled integration |
| G1 | عند فصل/إلغاء SIM يتم إيقاف حملة الشريحة وجعل Lease غير محسوم، ومنع stale bearer من pre-dial verification بعد الإلغاء؛ عزل SIM/Agent موجود مع اختبارات | Commit `5c24045`، CI `41584935182` PASS؛ فحص الروابط وقاعدة البيانات منفصل عن المكالمة | **جزئي:** Xiaomi inbound/outbound/reboot/ADB/OTA E2E غير مثبت في هذه الجولة؛ APK لم يُحدث |
| G2 | Gemini 3.8 Live Assistant يستخدم BLOCKING editor tool، بدون تغيير نموذج الصوت في الهاتف؛ مكالمات الشركة تستلم مسارات Flow المنشورة والعقد والشرط كتعليمات مقيدة بدل تعليمات تجاهل الشجرة | Commits `6fae2ded`, `d6f3ef44`، CI + host receipts ناجحة | **جزئي:** النموذج قد لا يرجع function call فعلية؛ تتبع عقدة حقيقي أثناء مكالمة ليس مثبتًا |
| G3 | حملات مرتبطة بالـSIM، explicit consent proof hash، DNC قبل arm وبعد lease، per-SIM attempt idempotency، ready/pause/cancel، Cairo→UTC، عرض/إدارة من الويب؛ all eligible contacts when selection empty؛ unknown result quarantine | Commits `6d90e3ec`, `9275e83c`, `3f5ba89c`, `0a75f354`, `7c9d440`; CI `41585289343` SUCCESS | **جزئي:** الـAndroid native automatic dialer غير موصول؛ provider flag مغلق افتراضيًا، ولا اختبار مكالمة حقيقية |

## مسائل ما زالت توقف اعتماد G0–G3
1. **G0/R001** — لا تدمج feature على main بالقوة: سجل مقارنة 2026-11-03 `ahead_by=603,behind_by=71` وراجع التغييرات المتعارضة والـPRs المفتوحة. مطلوب فرع دمج معزول + CI + مطابق release hash.
2. **G1/R010–R019** — إثبات المكالمات الواردة والصادرة على هاتف المالك، Dual SIM مع حسابين، الرد بعد 5 ثوانٍ، التقاط الصوت ثنائي الاتجاه، recovery بعد reboot، أمن المنفذ المحلي ADB 5555، وتوافق أجهزة أخرى. Emulator أو Google API token وحدهما لا يثبتان صوت GSM.
3. **G2/R021/R023** — أول LIVE tool proposal صالح، ثم إتمام مسار graph مشروط مع interruption/side questions وtrace للعقد وتثبيت revision. إدراج graph في prompt تحسّن عملي، **ليس engine state-machine deterministic**؛ عدم توفر أدوات CRM يعني لا تنفيذ خارجي.
4. **G3/R030–R035** — لا تشغيل GSM تلقائي حتى يتوفر native Android device executor + device acknowledgments + dial/no-answer reconciliation + timezone/business hours/holidays + DNC+consent fail-closed + clear user enable/disable + per-SIM incoming precedence. أي محاولة `unknown` توقف هذا الخط حتى حلّها؛ لا إعادة اتصال آلية.
5. **G3/R037** — عرض كل جهات الموقع محصور حاليًا بـ2000؛ التوسع الملاييني يتطلب cursor streaming، لا API response دفعة واحدة.

## Release/Recovery
- root: `https://app.ontrackegy.com/`; rollbacks داخل `/home/appontrack/backup/releases/`.
- Full private snapshot `/home/appontrack/backup/ontrack-full-2026-11-03/`; لا تُعرض للتنزيل العام ولا تُمس بيانات العملاء أو توكنات الجهاز.
- هذه الجولة لم تفعّل `ONTRACK_TENANT_CAMPAIGN_DIAL_ENABLED` ولم تُنشئ اتصالات فعلية أو مدفوعات.
- لا تغير Android native APK لتفادي تعطيل الهاتف دون E2E أو شهادة توقيع/نسخة سليمة.
- أي تقدّم لاحق يُقفل فقط إذا: Unit/CI PASS → host receipt مطابق → Auth UI E2E → Device/Payment E2E عند الحاجة.

## أول عمل متبقٍ
**R001** reconcile main/release branch؛ ثم **R021** real Gemini structured tool test، **R010/R011** physical owner SIM E2E، **R030/R034** Android executor with consent + DNC + scheduler and manual review of uncertain call. لا تفتح عملاء خارجية قبل تحقيق شروط G8/Q01.

## G2 provider experiment correction 05:45Z
Actual isolated Google Live run was rejected with WebSocket 1007 at setup when tool behavior was BLOCKING. Extended Thinking model-specific official skill requires NON_BLOCKING; reverted experiment and CI assertions. **This is failed E2E, not a model success.** See FLOW_AI_ASYNC doc and redacted private host receipt. All published agent flows and phone voice session remain untouched.
