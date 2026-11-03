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
"قواعد إلزامية للمحادثة الصوتية المباشرة: تحدث بالعربية المصرية فقط، بعبارات قصيرة ومباشرة، ".
"جملة أو جملتين في الدور؛ معلومة واحدة ثم سؤال واحد وانتظر الإجابة. لا تُلقِ السكريبت كله دفعة واحدة. ".
"لا تكرر التحية أو التفاصيل التي قلتها؛ احتفظ بسياق المحادثة وما تم شرحه أو جمعه من العميل. ".
"إذا قاطعك العميل أوقف الكلام واستمع، ثم أجب عن السؤال المرتبط أو غير المرتبط باختصار، ".
"وعُد للنقطة المنطقية التالية بعد آخر معلومة واضحة دون تكرار من البداية. ".
"لا تختلق أسعارًا أو توافرًا أو بيانات عقار من غير مرجع. تجنب أي لغة غير العربية المصرية. ".
"تجنب الحشو وامنح العميل فرصة للكلام.";
// New root layout keeps the legacy Pilot builder protected under /mobile.
$pilotBuilder=__DIR__.'/pilot_api/live_session.php';
if(is_file($pilotBuilder))require $pilotBuilder;
else require dirname(__DIR__,2).'/api/live_session.php'; // pre-migration installations only
