# OnTrack Voice Demo v0.3.1

نسخة تجريبية Standalone للاستضافة المشتركة.

- العقل: Google Gemini API من PHP.
- الصوت: Microsoft Edge Read Aloud Neural TTS من السيرفر بدون Azure/ElevenLabs Voice API Key.
- السماع: Web Speech Recognition `ar-EG` من المتصفح.
- المعرفة: `data/knowledge.json` لخدمات OnTrack وفواتير Demo.
- الأصوات: `ar-EG-ShakirNeural` و `ar-EG-SalmaNeural`.

## فحص سريع
افتح `test.php` ثم `index.php` على HTTPS.

## ملاحظة تطوير
الإصدار ده معمول ليتوافق مع Dev Bridge الموجود في جذر الساب دومين. الـBridge يحافظ على `config.php` القديم، لذلك إعدادات الديمو الحالية موجودة في `api/demo_config.php`.

كل بيانات الفواتير Demo وغير متصلة بـ WHMCS الحقيقي.
