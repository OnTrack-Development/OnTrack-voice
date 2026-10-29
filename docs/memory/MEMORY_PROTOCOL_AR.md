# بروتوكول الذاكرة وتقييد كل التفاصيل

## أين تحفظ؟

GitHub فقط، في هذا المستودع الخاص. مصدر المشروع في `saas/`، والميموري والسجلات والبرومبت خارجه. ملفات runtime اللازمة للتطبيق لاحقًا مثل logs التطبيق ليست بديلًا لهذه الميموري، ولا تُرفع أسرارها أو بيانات العملاء كما هي.

## دورة كل خطوة

1. قبل التغيير سجّل action بخطة محددة و task/issue وسبب الاختيار والهدف والملفات/البيئة ومرجع البداية.
2. نفذ العملية الفعلية. سجّل الأمر المنقح/أداة التنفيذ و job/commit IDs والمدة والـ exit/HTTP class والأثر الذي حدث.
3. تحقق من النتيجة. أرفق report path أو test output واذكر ما لا يثبته الاختبار.
4. عند أي مشكلة أنشئ issue قبل إعادة المحاولة. كل محاولة لها hypothesis/change/result، ويظهر الفرق عن السابقة.
5. حدّث الحالة الحالية في ISSUE_REGISTER ثم CHECKPOINT عندما يتغير ما يمكن تنفيذه أو الخطوة التالية.
6. ثبّت التغييرات في GitHub بعد كل مجموعة عمل متماسكة، وقبل إنهاء الجلسة/الانتقال/اقتراب فقد السياق. لا تنتظر نهاية المشروع لتكتب الذاكرة.

## السجلات المطلوبة

### Action

event_id، timestamp_utc، actor/session، task/phase، status، summary، rationale، target، command_redacted أو operation، input_refs، output_refs، exit_code إن وجد، duration_ms إن وجد، files_changed، issue_ids، next_action. لا تملأ مدة أو exit بالتخمين؛ استخدم null مع السبب عند عدم توفرهما.

السجل append-only. إذا احتاج تعديل حقيقة قديمة أضف event من نوع CORRECTION يشير إلى الحدث السابق. CHECKPOINT بطبيعته قابل للتحديث لكنه لا يمحو التاريخ.

### Issue

id/title/category/severity/status، first_seen، evidence، expected/actual، cause_confirmed أو hypothesis، attempts، workaround/fix، verification، affected_scope، current_blocker، next_action، history. إغلاق workaround لا يعني أن root cause زال؛ مثل SSH المحلي الذي لم نصلحه وإنما استُخدم GitHub بدلًا منه.

الحالات: OPEN، INVESTIGATING، BLOCKED_EXTERNAL، WORKAROUND_VERIFIED، RESOLVED_VERIFIED، DEFERRED، CLOSED_CLARIFIED. لا تغلق قضية صوت الهاتف بنجاح Cron.

### Attempt

attempt_id/issue_id/time، hypothesis، action/command_ref، expected_signal، actual_result، success، evidence، next_action، repeat_signature. بعد محاولتين متماثلتين بلا دليل جديد يلزم تغيير الفرضية أو الطريقة. لا تمسح الفشل بعد نجاح بديل.

### Test

test_id/time/scope/environment/command_redacted/result/exit/duration/evidence/proves/does_not_prove. اختبارات mock أو fake APIs تُوصف كذلك، ودليل هوست حي منفصل عن دليل مكالمة حية.

## تفاصيل يجب ألا تضيع

- كلام المالك الذي يغير المسار أو مكان العمل، ووقت التصحيح وترتيب مرجعيته.
- كل أمر يُرسل للهوست: task id و SHA وتوقيته وملفه ونتيجته، ومنع إعادة أثر مجهول.
- البيئة الفعلية لكل عملية؛ PATH في Cron قد يختلف عن Terminal.
- الملفات التي تغيرت وبصمات الملفات المحمية قبل/بعد وأي rollback متاح.
- البدائل المرفوضة وأسباب الرفض حتى لا تُقترح مجددًا دون معلومة جديدة.
- العوائق الحقيقية، ما أُنجز استقلالًا عنها، والنقطة التي تُستكمل منها بخطوة قابلة للتنفيذ.

## الأسرار والبيانات

لا API/PAT tokens أو passwords أو OTP أو private keys أو كامل .env/config حقيقي أو بيانات عملاء خاصة. احفظ أسماء الحقول وتفسير الحجب ومراجع الملف الآمنة بدل قيمها. الملفات منزوعة الأسرار لها manifest، ولا يوصف الأرشيف المنقح بأنه الأصل المطابق.

لا تحاول استخراج config.json للعامل كي تملأ الميموري؛ الربط مثبت بالفعل ولا يحتاج إلى نسخ اعتماده. أي credential expiry غير مقروء يبقى UNKNOWN.

## حداثة الميموري والتحقق

CHECKPOINT و project-state يحملان updated_at/phase/evidence/next_action. اقرأ أحدث نسخة من GitHub عند العودة؛ لا تعتمد على نص محادثة أقدم إذا تغير الملف. عند عجز الكتابة، وثق UNSYNCED محليًا مؤقتًا واستعد المزامنة، ولا تدّع أن الذاكرة الدائمة حدثت أو تنقلها للهوست.

`python3 tools/verify_repository.py` يفحص JSON/المرفقات والروابط الأساسية؛ فحص الذاكرة الإضافي يتأكد من parse JSONL ومراجع التقارير وحالة مساحة العمل. نجاح تحقق الملفات لا يثبت صحة كل feature أو مكالمة.
