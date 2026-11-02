<?php
declare(strict_types=1);
require __DIR__.'/../saas/console/database/Seed.php';
require __DIR__.'/../saas/console/_core/Views.php';
function role_assert(bool $ok,string $why):void{if(!$ok)throw new RuntimeException('ROLE_SURFACE_FAIL '.$why);}
seed_console();
foreach(['customer','reseller'] as $kind){
    $tabs=nav_entries($kind);
    foreach(['keys','integrations','campaigns','domains'] as $tab)
        role_assert(!isset($tabs[$tab]),$kind.' must not expose '.$tab.' placeholders');
    foreach(['overview','agents','flows','answering','devices','billing','releases','settings'] as $tab)
        role_assert(isset($tabs[$tab]),$kind.' lost functional '.$tab);
    role_assert(isset($tabs['voices']),$kind.' lacks granted ready voice selection');
}
role_assert(isset(nav_entries('platform')['keys'])&&isset(nav_entries('platform')['integrations']),
    'platform retains engine integrations and administrative secrets');
$src=file_get_contents(__DIR__.'/../saas/console/_core/Actions.php');
role_assert(str_contains($src,"case 'new_integration':\n                if(\$kind!=='platform'"),'nonplatform integration writes forbidden');
role_assert(str_contains($src,"case 'create_key':\n                if(\$kind!=='platform'"),'nonplatform API key creation forbidden');
$voice=file_get_contents(__DIR__.'/../saas/console/_core/Views.php');
role_assert(str_contains($voice,"v.status='active' AND v.ready=1"),'show only active ready voices');
$mobile=file_get_contents(__DIR__.'/../saas/mobile/audio-proof/overlay/shellserver/src/com/jemcik/jemrec/shell/LivePcm.java');
role_assert(str_contains($mobile,'audio.setMicrophoneMute(true)'),'retain proven GSM AI mute behavior');
role_assert(str_contains($mobile,'audio.setMicrophoneMute(wasMuted)'),'restore original microphone state after AI takeover');
$live=file_get_contents(__DIR__.'/../saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/MobileLiveCall.kt');
role_assert(str_contains($live,'inputTranscription'),'ask Google for uplink transcription evidence');
echo "VOICE_ROLE_SURFACE_PASS\n";
