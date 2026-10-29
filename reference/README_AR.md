# الملفات الواردة ودرجة مطابقتها للأصل

| المصدر | ما حُفظ هنا |
|---|---|
| OnTrack_AI_Agent_Builder_Claude_Handoff_v1.3(1).zip | [كل الملفات الـ35 مفكوكة](agent-builder-v1.3/README_START_HERE_AR.md) + [الأرشيف الأصلي](archives/agent-builder-handoff-v1.3-original.zip)، مطابق بالبايتات. |
| voicecall.zip | [مصدر مرجعي منقح](voicecall-v0.6.3/README_AR.md) + [ZIP منقح](archives/voicecall-v0.6.3-sanitized.zip)، لا يُسمى الأصل الكامل. |
| خطة Voice v1.1 | [النسخة التاريخية](../docs/history/IMPLEMENTATION_PLAN_v1.1_AR.md)، مع شرح ما تجاوزه النقاش. |
| لقطات cPanel/SSH/GitHub الأربع | [screenshots + index](../docs/evidence/screenshots/index.json) بأسماء واضحة وبصمات. |
| public SSH key السابق | [المفتاح العام](../docs/evidence/ssh/OnTrack_Voice_Test_ed25519.pub) وبصمته وسياقه في سجل التجارب. لا مفتاح خاص في Git. |

## ما حُجب من voicecall ولماذا

- `api/demo_config.local.php`: يحتوي مفتاح AI محلي حقيقي؛ لا يُحفظ في الريبو حتى وهو Private.
- `.devbridge/*`: deployment state وlogs ونسخ ZIP قديمة متداخلة قد تحتوي اعتمادًا؛ لم تُنسخ محتوياتها إلى الريبو.
- `api/.demo_setup_done`: حالة إعداد runtime، ليست مصدرًا مطلوبًا للبناء.
- سر صيانة ثابت في `dev_bridge.php` ورابطه في `UPLOAD_TO_ROOT.txt` استُبدلا بعبارة `REDACTED_LOCAL_SECRET`. الكود المرجعي الآخر محفوظ للمراجعة، وليس endpoint مصرحًا بنشره.

الجرد لا يخفي هذه الاستثناءات: [voicecall manifest](manifests/voicecall-v0.6.3.json) يسجل كل واحد من 64 مدخلًا أصليًا، بما فيها 33 مستثنى؛ 31 ملفًا نُسخت، بينها ملفان نُقحا. إذا تغير العدد عند التحقق، اعتمد الأرقام الفعلية في manifest بدل هذه الجملة.

في الحزمة الأصلية قد تكون بعض العناوين الداخلية أقدم (v1.2) رغم اسم archive v1.3؛ لم نعدّلها لتوهم اتساقًا تاريخيًا غير موجود. تحديثات v1.3 داخلها محفوظة كما وردت. `source-references` فيها مراجع تصميم/منتج وليست ترخيص نسخ إبداع منافس.

## البصمات والاستعادة

- [agent-builder manifest](manifests/agent-builder-v1.3.json): original/source/imported SHA-256.
- [voicecall manifest](manifests/voicecall-v0.6.3.json): source status لكل ملف، دون القيم السرية.
- [archive manifest](manifests/archives.json): بصمات ZIP المحفوظة.
- Original voicecall attachment: `libfile_d45f638efcf8819191344dc6b5437bfd`، handoff: `libfile_b535e0f9b3308191a0dc1a3d81a25b5c`. معرفات للاستدلال على الأصل، لا روابط تنزيل عامة أو credentials.

أسماء الملفات وبصماتها بيانات provenance، لا تعني أن backup قديم اختُبر أو أن محتواه آمن للنشر. لا تستعد إعدادات سرية من الأرشيف إلى history بحجة أنه Private.

## العلاقة بالكود الحالي

الكود الفعّال القائم في الجذر يظل مصدر تنفيذ الديمو؛ `reference/voicecall` لقطة مرفقة مستقلة. توجد اختلافات صغيرة مثل `lib/EdgeTtsClient.php` بين الجذر والمرفق؛ لم نستبدل الجذر بالنسخة المرفوعة. فحص source hashes للهوست في تقرير exec يحدد مصدره وقت الاختبار.
