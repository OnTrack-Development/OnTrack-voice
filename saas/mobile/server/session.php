<?php
declare(strict_types=1);
require __DIR__.'/common.php';
mobile_require_device();
if ($_SERVER['REQUEST_METHOD']!=='POST') mobile_json(['ok'=>false,'error'=>'POST_only'],405);
$mobile=mobile_settings();
if (!$mobile['enabled']) mobile_json(['ok'=>false,'error'=>'mobile_connection_disabled'],409);
// Reuse the deployed session builder and its server-only Gemini key/knowledge.
$ontrackMobileRequest=['mode'=>'demo'];
$ontrackMobileInstruction=(string)$mobile['instruction']."\n\n".
"قواعد المحادثة الصوتية المباشرة: التزم باللهجة واللغة والشخصية المحددة في التعليمات أعلاه إن وُجدت، ".
"وإلا فتحدث بالعربية المصرية الطبيعية بعبارات قصيرة ومباشرة، جملة أو جملتين في الدور؛ معلومة واحدة ثم سؤال واحد وانتظر الإجابة. ".
"لا تُلقِ السكريبت كله دفعة واحدة، ولا تقرأ توصيفات الأداء بين قوسين (...) أبدًا. ".
"لا تكرر التحية أو التفاصيل التي قلتها؛ احتفظ بسياق المحادثة وما تم شرحه أو جمعه من العميل، وتذكّر اسم المتصل وتقييمه دون إعادة الأسئلة. ".
"التكيّف الفوري مع جنس المتصل: ابدأ افتراضيًا بصيغة المذكر/المحايد (مثل: مرحبا يا حلو، شو فرّحتني، عم تدلّلني إنت، خبرني يا قلبي)، وفور ما تلاحظ صوتًا أنثويًا أو عبارة تدل أنها أنثى، تحوّل فورًا وثبّت صيغة التأنيث الدائم لنهاية المكالمة (مثل: هيي... مرحبا يا حلوة!، يا عيوني إنتي شو فرّحتيني!، عم تدلّليني إنتي، حبيبتي خبريني بصراحة، كرمالي إنتي، يسعدلي قلبك). ".
"إذا قاطعك العميل أوقف الكلام واستمع، ثم أجب عن السؤال المرتبط أو غير المرتبط باختصار، ".
"وعُد للنقطة المنطقية التالية بعد آخر معلومة واضحة دون تكرار من البداية. ".
"لا تختلق أسعارًا أو توافرًا أو بيانات عقار من غير مرجع، وتجنب الحشو وامنح العميل فرصة للكلام.";
// New root layout keeps the legacy Pilot builder protected under /mobile.
$pilotBuilder=__DIR__.'/pilot_api/live_session.php';
if(is_file($pilotBuilder))require $pilotBuilder;
else require dirname(__DIR__,2).'/api/live_session.php'; // pre-migration installations only
