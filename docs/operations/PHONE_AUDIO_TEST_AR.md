# اختبار صوت الشريحة — ما زال معلقًا

**إجراء تاريخي متجاوز30أكتوبر2026:** المالك رفض Shizuku صراحة. الخطوات التالية محفوظة لشرح ما اقترح سابقًا ولا تُنفذ كالمسار الحالي. البديل البحثي وخطة الاختبار الحالية في [بروتوكول الصوت](../research/2026-10-30/AUDIO_PROOF_PLAN_AR.md).

الغرض هو إثبات أن الهاتف يلتقط صوت الطرف البعيد ويحقن ردًا في مسار المكالمة. لا يبدأ بإعادة بناء الداشبورد ولا يحتاج PBX لاختبار echo الموجود في Voice SIP Agent.

**لم ينفذ المساعد هذا الاختبار على الهاتف، ولم تصل نتيجة نجاح من المستخدم.** ما يلي إجراء مقترح موثق، لا تقرير إنجاز.

## سجل بيئة الاختبار قبل النتيجة

الهاتف المحدد: Redmi Note12 Pro5G. أكمل من الجهاز: Android وMIUI/HyperOS، نسخة Voice SIP Agent، نسخة Shizuku، حالة Shizuku، مشغل الشريحة، طريقة الاتصال VoLTE/غيره، وضع توفير الطاقة، وما إذا كان Bluetooth موصولًا. لا نفترض أي قيمة غير معلومة.

## الخطوات الأولية

1. شغّل Shizuku واسمح للتطبيق عند Grant Shizuku. أسماء القوائم حسب نسخة التطبيق؛ إذا لم توجد ميزة echo، نراجع النسخة بدل اختراع زر.
2. Advanced settings → Cellular audio → **Direct telephony**.
3. اجعل **Mute call audio on this phone = OFF**.
4. من Call فعّل **Echo incoming calls (test)** و**Take over calls**.
5. اتصل بالشريحة من هاتف اختبار آخر. افصل الجهازين مكانيًا وأوقف speakerphone/Bluetooth حتى لا نخلط المسار الرقمي بصوت السماعة.
6. سجل هل auto-answer حدث، وهل كلام المتصل يرجع عبر المكالمة، وهل ينتهي الالتقاط عند إنهائها. سجل الاتجاه غير العامل إن وجد.
7. أعد مقارنة قصيرة مع echo OFF دون تغيير باقي الإعدادات، وراجع logs/directions إن كانت متاحة. لا تعتبر sidetone الذي ينتجه هاتف المتصل نفسه نجاحًا للجسر.
8. بعد التجربة أوقف echo وtakeover حتى لا يجيب التطبيق على اتصالات غير مقصودة أو يبقى وضع الاختبار نشطًا.

المصدر: [upstream TECHNICAL.md](https://github.com/sparkcyf/android-voice-sip-agent/blob/main/TECHNICAL.md)، خصوصًا إعدادات routing وecho. آخر مراجعة مصدر ضمن توثيق هذه الجلسة29أكتوبر2026. لا ننفذ متطلبات Vendor security settings بلا حاجة ودليل من النظام.

## ورقة النتيجة

| الحقل | الحالة الحالية |
|---|---|
| Device/OS/app/Shizuku/carrier versions | غير مكتملة |
| Shizuku connected | غير موثق هنا |
| Auto-answer | غير مختبر في سجلنا |
| Caller downlink captured | غير مثبت |
| Injected uplink reaches caller | غير مثبت |
| Echo ON/OFF comparison | لم تصل نتيجة |
| Logs/audio counters | غير متاحة |
| Conclusion | PENDING_DEVICE_AUDIO_PROOF |

Echo لا يثبت جودة Gemini أو العربية أو outbound أو reconnect. بعد نجاحه نختبر endpoint السيرفر وحقن رد الإيجنت ثم مكالمة واردة وصادرة حقيقية. SIP registration واللون الأخضر لا يغنيان عن ذلك.

في non-root Shizuku، إعادة تشغيل الهاتف تحتاج بدء الخدمة مرة أخرى حسب دليل Shizuku. pairing QR في منتجنا يمكنه تبسيط إعداد القناة فقط؛ لا يزيل هذا القيد.
