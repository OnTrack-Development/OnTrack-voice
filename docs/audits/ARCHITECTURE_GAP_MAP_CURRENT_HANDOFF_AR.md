> **LIVE MAP DEPLOY CONFIRMED — 2026-11-10:** [Architecture Map](https://app.ontrackegy.com/architecture-map/) منشورة ومحمية بالـPIN؛ [GitHub Actions #42739520075](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42739520075) نجح عليه JOB النشر على BEDIR، إيصال `GAP_MAP_DIRECT_SSH_PUBLISHED_AND_PIN_HTTP_VERIFIED` لكود `b407c690fc35` مع 200 صفحة PIN، 401 API مجهول، و200 API بمفتاح جلسة PIN، وSHA مطابق. هذا **إثبات نشر الماب فقط**، لا اكتمال إصلاحات SaaS ولا الصوت. بعد أول نشر **احتفظ بالـPIN hash الموجود على الهوست**، والتغيير يكون من Actions Secret فقط؛ لا ترجع إلى PIN مكشوف بتاريخ Git. رابط التطبيق الرئيسي `app.ontrackegy.com`؛ لم تُنفذ إعادة هيكلة المسارات `GAP-7`.

# OnTrack Voice — Architecture Gap Map / SaaS Repair — CURRENT HANDOFF
**Updated:** 2026-11-10 · **Priority:** أعلى من وثائق التاريخ القديمة فيما يخص Gap Map وتصحيح الـSaaS. اقرأ الكود/الـActions الحالي قبل اعتبار هذا النص دليلاً على النشر.

## 1. ادخل هنا أول ما تبدأ شات أو Agent جديد
1. [AGENTS.md](../../AGENTS.md) و[CLAUDE.md](../../CLAUDE.md) والعقد التنفيذي في README.
2. **هذا الملف**: `docs/audits/ARCHITECTURE_GAP_MAP_CURRENT_HANDOFF_AR.md`.
3. **خطة إصلاح الـ21:** [SAAS_21_PARTIAL_CLOSURE_TODO_2026_11_10_AR.md](SAAS_21_PARTIAL_CLOSURE_TODO_2026_11_10_AR.md)، فحص كل checkbox وأدلة CI وأحدث commit، **حدّثها بعد كل دفعة**.
4. **الحالة الهيكلية الماكينة:** `ops/gap-map/status.seed.json` (Seed source-checked، لا يعني Live)، و`ops/gap-map/web/` (واجهة/API تحت البناء).
5. **صلاحيات النشر:** [docs/ops/ONTRACK_DIRECT_SSH_DEPLOY_ACCESS_20261107_AR.md](../ops/ONTRACK_DIRECT_SSH_DEPLOY_ACCESS_20261107_AR.md).
6. الفرع الحالي `fix/saas-21-closure-20261110`، و[PR #84](https://github.com/OnTrack-Development/OnTrack-voice/pull/84) **Draft** مبني على `fix/saas-integrated-phases123-20261109`. لا تُدمجه أو تنشر كود SaaS بدون البوابات.

## 2. ماذا نعرف الآن — لا تختلق إنجازًا
- **21 مهمة SaaS جزئية أصلية:** 18 فيها إصلاحات/اختبارات آلية على الفرع، و3 مطلوب إغلاق كودي/وظيفي أوسع: `WEB-CORE-06` (Legacy Pilot adapters)، `WEB-CORE-08` (Usage ledger and quota)، `WEB-UX-01` (role navigation + complete UI actions). الـ18 **CODE_PATCHED وليست FULLY_DONE** تلقائيًا؛ راجع الـCI والملفات قبل الترقية. **0/21 مُعتمدة بالكامل على Host/User E2E** حسب آخر دليل.
- **10 مهام منفصلة مؤجلة** حسب المالك: `WEB-CORE-05`, `WEB-CORE-09`, `WEB-UX-04`, `WEB-UX-05`, `WEB-UX-07`, `WEB-REL-02`, `WEB-REL-04`, `WEB-REL-05`, `WEB-REL-06`, `WEB-REL-07`. لا تدخلها بدل الـ21 الآن.
- **فجوات الخريطة 8:** `GAP-1` Laravel/Console source divergence؛ `GAP-2` API/auth fragmentation؛ `GAP-3` VS obsolete/removed وMobile observation only؛ `GAP-4` reference archive vs root؛ `GAP-5` diagnostics + tracked hash security؛ `GAP-6` CI/CD workflow overlap (69 workflows في branch، لا تعتمد رقم 16 القديم)؛ `GAP-7` user-reported Login redirect `/saas/console` **مؤجل الإصلاح**؛ `GAP-8` voice hot path latency incoming/outgoing **غير مقاس بعد**.
- **VS/ حذف نهائيًا**: لا إنشاؤه أو استعادته أو احتسابه فجوة مفتوحة. **Android/APK/OTA/Native خارج التنفيذ الحالي**، يظهر كمنطقة "observe-only" في الخريطة مع اتصالات SaaS/Shared.
- **تأخير الصوت:** الجهاز المقرون يستخدم Device Bearer + التحقق من عزل الشريحة وحالتها، **ليس Web Login لكل اتصال**؛ تجنب remote calls / re-index / dashboard في حلقات PCM؛ قِس p50/p95/p99 وRTT/jitter/data/Wi-Fi، لا تختلق ms. هدف استجابة المالك ≤2s، وليس نجاحًا مقاسًا. احتفظ ببوابات DNC/consent/quotas قبل الصادر.
- **Canonical Login:** طلب المالك إن تبقى روابط تسجيل الدخول على الدومين الرئيسي دون ظهور /saas/console؛ لا تغيير حالياً. الاسم الذي ذكره المالك مختلف كتابةً عن `app.ontrackegy.com` الموثق بالريبو؛ **تحقق من DNS/Host أولاً** دون افتراض.
- **Map PIN:** ستة أرقام اختارها المالك؛ **لا تكتبها في أي ملفات مصدر أو memory أو logs**؛ خزّن فقط private password_hash على الهوست خارج webroot، مع CSRF، sessions قصيرة، rate limits. منفصل عن تسجيل الدخول للمنصة.
- **زر "تم التحقق منها":** إقرار المالك محفوظ timestamp+commit+per-gap source fingerprint؛ إذا تغيّر source اعرض `RECHECK_REQUIRED` ولا تمسح تاريخ الإقرار. لا تحول review إلى "CI PASS" أو "HOST DEPLOYED".
- **Simulator:** سيناريوهات SaaS معزولة signup/login/password reset/reseller/plan/agent/flow/knowledge/call-events/DNC؛ PASS/FAIL/BLOCKED/NOT_TESTED + المسؤول + نقطة الانقطاع + رابط دليل. **لا real call، لا دفع حقيقي، لا إرسال بريد حقيقي** في التشغيل الافتراضي. الاتصال الحقيقي مؤجل لتجربة Android مصرح بها.

## 3. واجهة الماب والنشر
- **مسار مستهدف، غير مثبت نشره الآن:** `https://app.ontrackegy.com/architecture-map/`، Host directory `/home/appontrack/public_html/architecture-map/`. **لا تقدّم هذا كـرابط شغال قبل رفع ومراجعة HTTPS**.
- **مصدر الواجهة موجود الآن:** `ops/gap-map/web/index.php`, `style.css`, `app.js`, `_auth.php`, `status.php`, `verify.php`, `.htaccess`؛ حماية PIN وجلسة/CSRF وReview Ledger وواجهة RTL interactive SaaS/Mobile/Shared. `tools/run_gap_map_simulator.py` يشغّل 8 رحلات PHP/SQLite معزولة، و`tools/build_gap_map_snapshot.py` يعيد حساب Source Fingerprints ويربط الأدلة مع Snapshot. **CI SUCCESS** في [Run #42732197969](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42732197969)، مع Artifact `SaaS-Architecture-Gap-Map` على الـRun؛ **لكن Host deployment = UNVERIFIED** ولا رابط إنتاجي عامل حتى الرفع والفحص.
- Host private storage: `/home/appontrack/.ontrack-gap-map/` (rate-limit, pin.hash, reviews.json, review-events.jsonl, status.json) **كلها خارج public_html**. امنع أي عرض أو طباعة للمفاتيح أو PIN.
- **آلية النشر الآن مكتوبة في الكود**: `tools/publish_gap_map_direct_ssh.py` + job `publish-gap-map` في `.github/workflows/saas-21-closure-regression.yml`، يعمل **بعد نجاح CI فقط** على Windows BEDIR SSH port 7195. متوقف بشكل آمن لحين توفير Actions variable `ONTRACK_GAP_MAP_DEPLOY_ENABLED=true` وsecret `ONTRACK_GAP_MAP_PIN` (6 digits)، ولا تعرض قيمة PIN في Git/log. هذه الخطوة **لم تثبت أنها نُفّذت على الهوست**. الاحتفاظ بنسخة backup ومراجعة HTTP+SHA جزء من الناشر. Host-Link القديم **STOP**، لا Android أو SaaS runtime نشر.
- إضافة status snapshot فعلي بعد كل build/test/push، تحديث الصفحة Poll مثلاً 20 ثانية، واظهر `last_verified_at` و`commit` وأخطاء المزامنة. لا تدّع تدفقًا لحظيًا إن لا توجد telemetry.

## 4. قاعدة إغلاق وتحديث مُلزمة بعد كل تغيير
لكل `WEB-XX-YY` و`GAP-N` دوّن هذه الحقول أو مكافئها داخل To-Do والحالة:
`status` = `OPEN / PARTIAL / CODE_COMPLETE / HOST_VERIFIED / USER_VERIFIED / COMPLETE / BLOCKED`;
`source_sha`, `changed_files`, `evidence` (run/commit/host receipt), `next_action`, `owner`, `updated_at`, `scope`.
- **OPEN:** لم يُبدأ؛ **PARTIAL:** إصلاح بعض شروط القبول فقط أو CI لم ينجح؛ **CODE_COMPLETE:** كل متطلبات الكود اختُبرت وعُدّلت وثُبتت، ولكن Host/User مستقل.
- **COMPLETE:** كل معايير القبول المطلوبة لهذا البند مثبتة؛ غياب اختبار Host/E2E لا يسمح بإعلان اعتماد المنتج إذا كان ضمن شرطه. طلب المالك تأجيل تجارب المستخدم اليدوية؛ وضّح البوابة بوضوح.
- حدّث To-Do والماب **في نفس دفعة Commit أو دفعة وثائق تالية مباشرة** بعد تعديل source؛ اكتب "تم" على المطابق بالأدلة فقط، و"جزئي" حين توجد فجوة. لا تُحَوِّل 18 بندًا دفعة واحدة إلى 100%.
- بعد push قارن HEAD + GitHub Actions (لا تعتمد Run على SHA قديم)، وإذا أضاف/حذف فحص فجوة أدخلها/احذفها بدليل. **لا حذف فجوات أو تغيير source hash من دون سبب موثق**.
- بعد كل جلسة: حدث هذا الملف بسطر حالة Latest HEAD، الـCI، بلوكر النشر، NEXT ACTION؛ لا تترك ملفًا قديمًا يحكي قصة أخرى.

## 5. الخطوة التالية
**إكمال واجهة الماب + الـSimulator/auto scanner والـCI deployment الآمن، فحص HTTP، ثم استكمال CORE-06 وCORE-08 وUX-01 ومطابقة بقية الـ18 مع Gap Map، من غير Android.** واجهة map ما زالت غير مثبتة النشر؛ الرابط مش نجاح لحد receipt.
