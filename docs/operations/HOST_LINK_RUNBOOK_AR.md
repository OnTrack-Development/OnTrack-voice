# إدارة الهوست من GitHub — القناة الموجودة

## حادثة مؤكدة وإصلاح — 1 نوفمبر 2026

**لا تطلب تشغيل Cron يدويًا قبل مراجعة هذه الحادثة.** أثبتت صور cPanel أن Cron يعمل مرة كل دقيقة وSTOP غير موجود، وGitHub HTTP 200، بينما سجل العامل يعرض `Host Link: ValueError` وملف state فيه active=None وcompleted=71. السبب: مهمة `p2-selected-target-api-web-20261031` انتهت صلاحيتها ولم ينشئ العامل تقرير رفض؛ ظل يكرر استثناء التحقق كل دقيقة.

استُعيدت القناة عبر مهمة probe آمنة `recover-cron-20261101000753` بعد انتهاء صلاحية المهمة السابقة، وتأكد تقرير `success` في 2026-11-01 00:08:03 UTC. ثم نجح إصدار 0.2.9/OTA15 من GitHub Actions run 41253328620 (attempt 2) وتم تأكيد نشر APK من تقرير `mobile-native-41253328620` عند 00:11:08 UTC.

تم تعديل **مصدر** `ops/host-link/worker.py` في commit `8dd3677` لإصدار تقرير `expired` للمهام التي لم تبدأ، لكن العامل المثبت على السيرفر **غير محدث تلقائيًا**. يلزم نشر هذا التعديل بنفس الضوابط والتحقق قبل اعتباره دائمًا. لا تستنتج أن Cron موقوف من غياب تقرير فقط؛ افحص حالة صلاحية المهمة وتحقق من آخر خطأ أولًا. مهمة P2 السابقة غير مؤكدة التطبيق؛ تحقق من البصمات الحية قبل إعادة أي أثر.


## بيانات الربط

| الحقل | القيمة |
|---|---|
| Repository | OnTrack-Development/OnTrack-voice — Private |
| الفرع التشغيلي | ontrack-host-link |
| target | appontrack-server11 |
| الحساب | appontrack، UID2379 |
| المسار | /home/appontrack/ontrack-voice-link |
| worker | /home/appontrack/ontrack-voice-link/worker.py |
| credentials | config.json محلي 600، والمجلد700؛ لا يُرفع أو يُطبع |
| queue | ops/host-link/jobs/appontrack-server11.json |
| reports | ops/host-link/results/appontrack-server11/ID.json |
| poll | Cron كل دقيقة؛ ليس قناة تفاعلية فورية |
| interpreter | /bin/python3 — 3.9.25 |

مصدر المثبت والعامل مثبت على commit `08363de8125d454e5df3180cab86f0113c947ba6`. المثبت تحقق من checksum العامل، وbootstrap تحقق من checksum المثبت. لا auto-update للكود من branch. ملفات المصدر متاحة هنا في ops/host-link؛ تعديلها في Git لا يغير العامل المثبت تلقائيًا.

## إرسال مهمة من جلسة جديدة

1. اقرأ queue الحالي من **ontrack-host-link** بأداة GitHub واحفظ SHA الحالي، لا SHA ثابت من هذه الوثيقة.
2. إذا كان في queue job لم يصل تقريره، لا تستبدله قبل معرفة حالته. مشغل واحد للقناة في كل مرة.
3. ابن JSON بـschema1 وtarget الصحيح وid فريد وcreated_at/expires_at UTC. أقصى صلاحية7 أيام؛ المعتاد ساعة.
4. action إما probe أو exec. exec يتلقى argv كقائمة، cwd داخل home الحساب، timeout_seconds من1إلى30.
5. حدث ملف المهمة تسلسليًا بأداة update_file وSHA الحالي؛ أداة GitHub متاحة في الجلسة ولا تحتاج PAT المستخدم.
6. انتظر دورة Cron بفترات قصيرة لا تمنع تحديث المستخدم؛ اقرأ ملف تقرير **نفس id** من الفرع التشغيلي.
7. تحقق من job_id وtarget وjob_sha256 وstatus وexit_code وstdout/stderr والتوقيت. غياب التقرير ليس دليلًا على فشل الأمر؛ لا تعِد إرسال نفس الأثر بمعرف جديد بالتخمين.
8. حدّث checkpoint بتاريخ ودليل. للتعديل التالي اقرأ أحدث blob SHA؛ قد يكون تغير على يد مشغل آخر.

مثال هيكلي فقط؛ استبدل التوقيت والمعرف قبل الإرسال:

```json
{
  "schema": 1,
  "id": "php-check-UNIQUE",
  "target": "appontrack-server11",
  "action": "exec",
  "created_at": "UTC-NOW-ISO8601",
  "expires_at": "UTC-PLUS-ONE-HOUR-ISO8601",
  "cwd": "/home/appontrack/public_html",
  "argv": ["/usr/local/bin/php", "-v"],
  "timeout_seconds": 10
}
```

## الأفعال المتاحة وحدودها

القراءة والكتابة والاختبارات وCron وكل البرامج المتاحة للحساب ممكنة من خلال argv. الصلاحية هي صلاحية appontrack، لا root. cwd guard لا يمثل filesystem sandbox؛ البرنامج نفسه له كامل ما يسمح به نظام التشغيل لهذا الحساب.

لا shell ضمني أو stdin تفاعلي. Python/PHP scripts ممكنة كـargv، لكن لا تطبع مفاتيح أو .env أو كامل بيانات العملاء. المخرجات تصل إلى GitHub private history، وقراء المستودع يرونها. الحجب المدمج ليس ضامنًا عامًا لكل أنواع الأسرار.

مهلة exec30ثانية، وstdout/stderr32KiB لكل منهما. تنتهي مجموعة العملية التابعة حتى إذا أغلقت المخرجات مبكرًا. لذلك `nohup` أو background داخل المهمة ليس طريقة ثابتة لتشغيل خدمة. للخدمات الطويلة نختار process manager أو Cron مناسبًا ونختبر قيود الهوست قبل الاستخدام.

Worker يتابع طابور تطوير/إدارة؛ لا تستخدمه لstreaming audio أو تنفيذ dial لحظي أو كبديل جدولة منتج الاتصالات.

## الاسترجاع وعدم التكرار

- worker.lock يمنع تداخل Cron.
- active يثبت قبل بدء التنفيذ، والنتيجة تثبت في state.json قبل GitHub PUT.
- فشل رفع النتيجة يعيد الرفع دون إعادة الأمر.
- تعطل بين بداية التنفيذ وحفظ النتيجة ينتج interrupted_unknown؛ افحص الأثر قبل تكرار العمل.
- SHA-256 يحمي التتبع من تبديل محتوى job مع استعمال نفس id. completed يمنع إعادة id المنجز.
- لا يوجد ادعاء exactly-once للآثار إذا تعطل البرنامج؛ السلوك هو عدم replay تلقائيًا.

## إيقاف واستئناف

على الهوست عند الحاجة:

```sh
touch ~/ontrack-voice-link/STOP
# استئناف:
rm ~/ontrack-voice-link/STOP
# تشغيل دورة يدوية واحدة:
/bin/python3 ~/ontrack-voice-link/worker.py
```

لإيقاف exec فقط، allow_exec=false في config.json مع الإبقاء على probe. لا ترفع هذا الملف. للإزالة احذف سطر Cron ذي العلامة `# ontrack-voice-link` وألغ التوكن من GitHub.

## تشخيص تعطل الربط

تأكد أولًا من الفرع والـid والتوقيت ووجود تقرير وعدم STOP، ثم Cron وlast-run.log على الهوست. عند401/403 راجع انتهاء PAT وصلاحية Contents وقيود المؤسسة؛ عند404 تأكد من repo/path/ref وحق الوصول قبل استنتاج أن الملف مفقود. API عبر HTTPS يحتاج DNS/TLS صالحة وخروجًا إلى api.github.com. لا تستخدم verify=false أو تعطيل firewall عشوائيًا.

لا حاجة إلى تغيير port7195 أو مفتاح SSH كي يعمل هذا الربط. فشل SSH المحلي القديم مسار منفصل.

## الإثبات الحالي

bootstrap-probe-20261029 ثم verify-terminal-20261028234301727. الثاني أُرسل من الجلسة ونفذه Cron فعلًا وأعاد exit0 وقراءة الملف الذي كتبه. روابطه في [الأدلة](../evidence/README_AR.md).

## العمل الجديد داخلsaas

عند تنفيذ التطبيق لاحقًا يكونcwdهو `/home/appontrack/public_html/saas`. الميموري والبرومبت وسجلات الجلسات تبقى فيGitHubخارجdeployartifact؛ لا تكتبها علىالهوست. تقرير تجهيز المجلد وحماية ملفات الرئيسية في `docs/memory/checkpoint.json`.
