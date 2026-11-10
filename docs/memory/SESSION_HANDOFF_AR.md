> **UPDATE 2026-11-10 — SaaS CODE WAVE:** مراجعة CORE-06/08 وUX-01 أضافت Pilot backward compatibility، Usage period/carryover، 404/503 safe UI مع اختبارات CI. أحدث نقطة مرجعية [الماب](../audits/ARCHITECTURE_GAP_MAP_CURRENT_HANDOFF_AR.md) و[To-Do](../audits/SAAS_21_PARTIAL_CLOSURE_TODO_2026_11_10_AR.md)؛ **3/3 لا تزال PARTIAL حتى CI والقبول الحقيقي**. Automation كل ساعة لمتابعة الخطة. Android/VS لا تعديل.

> **LIVE MAP DEPLOY CONFIRMED — 2026-11-10:** [Architecture Map](https://app.ontrackegy.com/architecture-map/) منشورة ومحمية بالـPIN؛ [GitHub Actions #42739520075](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42739520075) نجح عليه JOB النشر على BEDIR، إيصال `GAP_MAP_DIRECT_SSH_PUBLISHED_AND_PIN_HTTP_VERIFIED` لكود `b407c690fc35` مع 200 صفحة PIN، 401 API مجهول، و200 API بمفتاح جلسة PIN، وSHA مطابق. هذا **إثبات نشر الماب فقط**، لا اكتمال إصلاحات SaaS ولا الصوت. بعد أول نشر **احتفظ بالـPIN hash الموجود على الهوست**، والتغيير يكون من Actions Secret فقط؛ لا ترجع إلى PIN مكشوف بتاريخ Git. رابط التطبيق الرئيسي `app.ontrackegy.com`؛ لم تُنفذ إعادة هيكلة المسارات `GAP-7`.

## 2026-11-10 — SaaS Architecture Gap Map handoff (الأحدث)

**عند بدء أي محادثة جديدة:** افتح [ARCHITECTURE_GAP_MAP_CURRENT_HANDOFF_AR.md](../audits/ARCHITECTURE_GAP_MAP_CURRENT_HANDOFF_AR.md) أولاً، ثم [SAAS_21_PARTIAL_CLOSURE_TODO_2026_11_10_AR.md](../audits/SAAS_21_PARTIAL_CLOSURE_TODO_2026_11_10_AR.md)، `ops/gap-map/status.seed.json` و[ملف الوصول المباشر](../ops/ONTRACK_DIRECT_SSH_DEPLOY_ACCESS_20261107_AR.md). هذه الفقرة **تحل محل** التوجيه التاريخي أدناه بتشغيل Host-Link وطابور الوظائف أو افتراض أن نشر الماب تم.

**النطاق الحالي:** SaaS-only على `fix/saas-21-closure-20261110` / PR #84 Draft. 18/21 بنود عليها code patches و3 CORE-06/CORE-08/UX-01 تحتاج إغلاق كودي، 10 منفصلة مؤجلة. لا تعلن 21 DONE؛ لا اختبار هاتف يدوي ولا Android/OTA ولا استعادة `VS/`. Gap Map الجديدة فيها GAP-1..8، PIN منفصل hashed private، زر تأكيد المالك وسجل timestamp/source fingerprint، محاكاة معزولة، وVoice Latency hot path. `GAP-7` تحويل URL تسجيل الدخول مسجّل للإصلاح لاحقاً فقط؛ Host-Link قديم **STOP**، وBEDIR direct SSH موثق، و`/architecture-map/` **غير مثبت نشرها بعد**.

**قاعدة العمل:** بعد كل تغيير متحقق، حدث To-Do وGap Map بنفس الشيفرة ودليل الاختبار، مع `OPEN/PARTIAL/CODE_COMPLETE/HOST_VERIFIED/USER_VERIFIED/COMPLETE` وsource SHA وNext Action. لا تكرر تحديثات "تم" غير مثبتة، ولا تحفظ PIN أو private key في GitHub.

---

# استلام جلسة جديدة

1. افتح نفس الريبو الخاص واقرأ AGENTS.md ثم CHECKPOINT.md و checkpoint.json وأحدث السجلات والقضايا.
2. اقرأ PROJECT_MEMORY والبرومبت الرئيسي والمراجع ذات الصلة؛ لا تطلب من المستخدم إعادة رفع ملفات أو إعطاء PAT/SSH جديدًا والربط يعمل.
3. حدد نطاق أمر المستخدم الحالي. إذا ما زالت الحالة تجهيزًا ولم يصدر أمر تنفيذ لاحق، لا تبدأ بناء التطبيق؛ أبلغ بمكان الملفات ونقطة البدء.
4. عند أمر التنفيذ، اعمل على مصدر saas/ وهدف `/home/appontrack/public_html/saas` فقط، واحفظ docs/memory والسجلات في GitHub خارج التطبيق.
5. اقرأ queue الحي ونتيجة آخر job قبل تغييره. سجل الإجراء ثم انتظر نتيجة مناسبة قبل الخطوة التابعة.
6. راجع issue المفتوح بدل إعادة اختبار نفس الفشل دون معلومة جديدة. هاتف المالك لا يمكن التحكم فيه بمجرد هوست SSH.
7. قبل نهاية الجلسة ثبّت كل عمل ونتائج وعوائق و next_action في GitHub، ثم قدم ردًا قصيرًا لا يدعي ما لم يُختبر.

البرومبت التفصيلي: [MASTER_EXECUTION_PROMPT_AR.md](../execution/MASTER_EXECUTION_PROMPT_AR.md).
