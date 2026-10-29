# حادثة انقطاع قناة التحكم — 29 أكتوبر 2026

## الملخص
أثناء إعداد منفّذ المهام الطويلة (`runner-setup-20261029a`) عدّل التنفيذ قائمة cron الخاصة بالحساب `appontrack` لإضافة مهمة `run-queue.sh`. النتيجة المرصودة: توقف وصول تقارير العامل نهائيًا بعد تقرير `runner-setup-20261029a` (01:40 UTC)، رغم دفع مهام صحيحة لاحقًا (`laravel-status-20261029b`, `cron-repair-20261029a`).

## الأدلة
- آخر تقرير وصل: `runner-setup-20261029a` — stdout لم يحتج سطور `crontab -l` المتوقعة.
- مهام لاحقة بصيغة JSON صحيحة وتوقيت صحيح: لا تقرير.
- `ssh appontrack@65.21.208.232 -p 7195` بمفتاح `ontrack_ai_ed25519`: `Permission denied (publickey)`.
- `dev_bridge.php` على `app.ontrackegy.com`: `ok=true`, `locked=false`, `root_writable=true`, github config = OnTrack-Development/OnTrack-voice@main. القناة تعمل لكنها لا تنفّذ أوامر.

## التصنيف
`BLOCKED_EXTERNAL` — استعادة قناة التنفيذ تحتاج خطوة واحدة من المالك (أو مفتاح SSH صالح).

## الإصلاح المطلوب من المالك (سطر واحد في cPanel Terminal)

```sh
(crontab -l 2>/dev/null | grep -v 'ontrack-voice-link'; echo '* * * * * /bin/python3 /home/appontrack/ontrack-voice-link/worker.py >/dev/null 2>&1 # ontrack-voice-link'; echo '* * * * * /bin/bash /home/appontrack/bin/run-queue.sh >/dev/null 2>&1 # ontrack-saas-build') | crontab - && crontab -l
```

ثم التحقق: أي دفع لمهمة جديدة على فرع `ontrack-host-link` يجب أن يُنتج تقريرًا خلال دقيقة.

## ما لم يتأثر
- الموقع الحالي وملفاته لم تُمس (لم يُنفّذ أي كتابة في الجذر).
- مصدر التطبيق في `saas/` محفوظ على GitHub في `main` (commit `589201e`).
- لا تغيير في DB أو DNS أو SSL.

## الدروس
- لا تُعدّل crontab حساب يحمل قناة تحكم إلا بإلحاق آمن وتحقق فوري، وبعد التأكد من شكل `crontab -l` الحقيقي.
- أي تغيير تشغيلي على المجدول يحتاج خطوة تحقق مستقلة قبل الاعتماد عليه.