## تحقيق محرك الأصوات — اكتشاف 31 أكتوبر 2026

- تم تحديد **البنية المرجحة جدًا** لملفات AYO الثلاثة: ملفات `Conditionals` المخزنة بواسطة **Resemble AI Chatterbox Multilingual**؛ تطابق حقول `t3.speaker_emb`, `t3.clap_emb`, `t3.cond_prompt_speech_tokens`, `t3.cond_prompt_speech_emb`, `t3.emotion_adv` و`gen.prompt_token`, `gen.prompt_token_len`, `gen.prompt_feat`, `gen.prompt_feat_len`, `gen.embedding` تعريف `Conditionals` حرفيًا.
- مصدر المطابقة: https://github.com/resemble-ai/chatterbox/blob/master/src/chatterbox/mtl_tts.py ويستخدم `torch.load(...,weights_only=True)`؛ المصدر الرسمي https://github.com/resemble-ai/chatterbox يوثق دعم العربية في Multilingual.
- كتالوج المصدر الآن `provider=chatterbox_multilingual` و`language=ar`، مع `ready=false` **إلزاميًا**. يلزم إثبات تحميل ملفات الأصول عبر `Conditionals.load` في تشغيل معزول ومرحلة `ChatterboxMultilingualTTS` وقياس أداء/نطق مصري وحقوق استخدام صوت AYO قبل تفعيل الصوت Premium.
- هذا **لا يثبت** أن الملفات قابلة للتشغيل على Android أو في جلسة Gemini Live؛ الجمع بين صوت Gemini وتوليد Chatterbox قد يتطلب تبديل مزود/تغيير pipeline للصوت، ويجب ألا يُغيّر جسر `mix` العامل قبل E2E.

# OnTrack Voice — أصوات Premium والباقات والمدفوعات

## ملفات AYO الأصلية (المالك — 31 أكتوبر 2026)

| المعرّف | الملف | SHA-256 | النوع | جاهز للتشغيل؟ |
|---|---|---|---|---|
| ayo.calm | AYO-calm.pt | 10b536280f44431af3a42b2b3ae68ab0e60a44ca116c417b9a9ba9a12bbb8105 | PyTorch preset | لا |
| ayo.excited | AYO-excited.pt | 77b67c9d95dbf2ea8b50fa88778c1d83eb917a0e9d8eeb3be14f055f9b9e38cf | PyTorch preset | لا |
| ayo.sleepy | AYO-sleepy.pt | fef549851711566631866708f135cf3d512b55c3b98766431bd54742250aeb1e | PyTorch preset | لا |

تحتوي ملفات PT على speaker_emb وclap_emb وprompt_feat وprompt_token وemotion_adv وغيرها. **ليست ملفات MP3/WAV ولا يمكن تشغيلها عبر Gemini Live مباشرة.** يجب تحديد المحرك/الموديل المتوافق وحقوق استخدامه والـruntime ثم اختبار نطق عربي على جهاز حقيقي قبل تغيير حالة ready من false إلى true. لا تستدع torch.load على ملفات غير موثوقة في خدمة الإنتاج.

الكتالوج النصي المبدئي وبصماته في saas/config/voice_catalog.php. **ملفات PT الثنائية لم تُرفع فعليًا إلى GitHub بواسطة واجهة رفع الأكواد الحالية.** لا تعتبر كتابة اسم الملف رفعًا له. الملفات الأصلية المرفقة بالمحادثة هي المصدر، ويجب تخزينها في مساحة خاصة/Git LFS في خطوة منفصلة، لا داخل public_html.

## نظام الباقات

جدول plans يتضمن سعرًا اختياريًا بالقروش EGP وفترة شهرية، وplan_entitlements يحدد كل ميزة وقيمتها، وsubscriptions للاشتراك. تمنع VoiceEntitlements::canUseVoice استخدام Premium غير المجهز حتى مع توافر الباقة.

الميزات: voice.campaigns، voice.contact_import، voice.call_history، voice.agent_builder، voice.recording، voice.reports، voice.ayo.calm، voice.ayo.excited، voice.ayo.sleepy.

لا تعتمد على إخفاء الميزة بالواجهة فقط. يجب حماية أي API أو تنفيذ عالي المستوى بالتحقق من الاشتراك والميزة على الخادم. لا أسعار مفترضة؛ السعر null يعني «غير معروض للبيع».

## Kashier Gateway — TEST قبل LIVE

المرجع الرسمي: https://developers.kashier.io/docs/accept-payments/payment-sessions و https://developers.kashier.io/docs/webhooks

يستخدم Hosted Checkout بدلاً من الاحتفاظ ببيانات البطاقة. إعدادات البيئة (لا تُرفع إلى Git):
- KASHIER_MODE=test (أو live بعد اجتياز اختبارات الإنتاج)
- KASHIER_MERCHANT_ID
- KASHIER_API_KEY (Payment API Key، يستخدم لتوقيع Webhook)
- KASHIER_SECRET_KEY (Authorization)

طلب الدفع يكوّن أمرًا داخليًا فريدًا ثم يستدعي Kashier؛ يعرض صفحة الدفع الخارجية على HTTPS. مسار POST /api/billing/kashier/webhook لا يحتاج browser CSRF لأنه يتحقق من HMAC SHA-256 وsignatureKeys وتطابق المرجع والسعر والعملة والمُعاملة وعدم التكرار، ثم يفعّل الاشتراك بعد pay/SUCCESS فقط. رجوع المستخدم من الدفع لا يفعّله. ردود PENDING/FAILURE ليست نجاحًا.

**حدود مهمة:** لا يوجد حساب/مفاتيح Kashier متصلة في هذه الجلسة، ولا تنفيذ تعاملات حقيقية. اشتراكات الديمو ذات التجديد الشهري اليدوي؛ خصم تلقائي/recurring API ليس منفذًا. يجب اختبار webhook replay والتسعير والتزامن والاسترجاع على بيئة Kashier TEST ثم مراجعة أمان قبل LIVE.

## حزمة الأعمال المتفق عليها

- سجل المكالمات على الهاتف والموقع: id وحالة وتوقيت ومدة وفلترة، ثم ربط الاسم/الرقم وتقارير AI الحقيقية فقط.
- استيراد جهات الاتصال الكاملة أو المختارة بموافقة الهاتف، وTXT/CSV/XLSX/لصق، DNC.
- حملات متجدولة وسيناريوهات متغيرة بالأسماء، وتشغيل SIM outbound فقط بعد موافقة أصحاب الأرقام وتوثيق E2E.
- تجديد واجهة التطبيق بحركات خفيفة، أقسام Dashboard / Calls / Campaigns / Voices / Settings، Dark/Light مع إظهار الحالات الواقعية.
- كتالوج Premium وربط TTS فعلي لاحقًا، ثم صلاحيات باقات واختبارات دفع.
- تحسين ثبات بيانات الهاتف الضعيفة مؤجّل رسميًا إلى PHASE-2-NETWORK-STABILITY وليس إغلاقًا له.

حالة كل عنصر تتبع القائمة التنفيذية docs/plan/VOICE_DEMO_DELIVERY_CHECKLIST_AR.md لا هذه الوثيقة وحدها.
