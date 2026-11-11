<?php
declare(strict_types=1);
require __DIR__.'/../saas/console/_core/VoiceToolGateway.php';
$secret='AIzaTESTFINGERPRINT_1234567890123456';
$text='operation failed HTTP 403 Bearer aabbccddeeff00112233 api_key=secret123 '.$secret.' call=123456';
$clean=voice_tool_safe_trace_detail($text);
foreach(['aabbccddeeff00112233','secret123',$secret] as $needle)
    if(str_contains($clean,$needle))throw new RuntimeException('SAAS_TRACE_SECRET_LEAK');
if(!str_contains($clean,'HTTP 403')||!str_contains($clean,'call=123456'))
    throw new RuntimeException('SAAS_TRACE_CONTEXT_LOST');
if(mb_strlen(voice_tool_safe_trace_detail(str_repeat('A',1000)))>250)
    throw new RuntimeException('SAAS_TRACE_LIMIT_BYPASS');
echo "SAAS_TOOL_TRACE_SECRET_REDACTION_PASS\n";
