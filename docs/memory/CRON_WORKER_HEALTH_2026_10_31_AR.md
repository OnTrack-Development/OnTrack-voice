# Cron العامل — فحص 31 أكتوبر 2026 (بتوقيت القاهرة)

## الطلب
المالك يريد تشغيل `/home/appontrack/ontrack-voice-link/worker.py` تلقائيًا من cPanel Cron بدل استدعائه يدويًا كل مرة.

## حقائق مثبتة
- تقرير `verify-terminal-20261028234301727` في الفرع `ontrack-host-link` أكد `host_link_cron_count=1` و`host_link_cron_present=true` و`cron_read_exit=0` يوم 28 أكتوبر.
- تقرير `mobile-ota-41131334617` أكد تنفيذ المهمة بنجاح 30 أكتوبر 21:38:30Z، دون إثبات أنها بدأت من Cron وليس استدعاء يدوي.
- في 30 أكتوبر 22:03Z أُرسلت مهمة قراءة فقط `cron-health-20261030T220400` للفرع الحي بعد التأكد أن التقرير السابق مكتمل. تعيد عدد Cron الفعلي ومطابقة مسار العامل وغياب STOP وعمر last-run.log، ولا تطبع crontab كاملًا أو config/token.
- حتى 22:06Z لم يظهر التقرير المطابق؛ **Cron الحالي غير مثبت التشغيل**، ولا نكرر إنشاء سطر من دون فحص فعلي.
- Android 0.1.7 build `41134782898` نجح، لكن `publish_ota` توقف بخطأ 404 عند البحث عن تقرير المهمة الحالية؛ لا يُعامل على أنه نشر OTA.

## سطر Cron المقصود — للمراجعة إذا فشل التشغيل
```cron
* * * * * /bin/python3 /home/appontrack/ontrack-voice-link/worker.py >/home/appontrack/ontrack-voice-link/last-run.log 2>&1 # ontrack-voice-link
```
في واجهة cPanel Cron Jobs: كل حقول الجدولة `*`، وحقل Command هو الجزء بعد الخمس نجوم. **تحقق من السطر الموجود قبل إضافة أو استبدال أي شيء**؛ لا تنشئ نسخة مكررة ولا تعيد تثبيت العامل أو تقرأ config.json. `worker.lock` يمنع التداخل لكن لا يصلح الجدولة المتكررة.

## التالي
تحقق من وصول `ops/host-link/results/appontrack-server11/cron-health-20261030T220400.json` أولًا. إذا لم يصل، افحص Crontab وتشغيل Cron وSTOP وlast-run.log على الهوست، باستخدام cPanel أو قناة تحكم حقيقية. إذا صُحح Cron ووصل التقرير، تحقق من `cron_count=1` و`cron_schedule_valid=true` و`cron_command_uses_worker=true` و`last_run_age_seconds` صغير؛ ثم أعد `publish_ota` الفاشلة فقط بعد اكتمال المهمة وقبل أي استبدال للـqueue.
