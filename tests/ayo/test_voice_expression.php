<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/ayo/voice-expression.php';
function check(bool $ok, string $label): void {
    if (!$ok) {fwrite(STDERR,"FAIL: ".$label."\n");exit(1);}
    echo "PASS: ".$label."\n";
}
$warm = ayo_expression_prepare('أهلاً بيك في أونتراك', 'auto', 'inworld-tts-2', 'مساء الخير');
check($warm['reasoningEnabled'] && $warm['synthesisContext']['reasoning']==='REASONING_ON','native TTS-2 reasoning enabled');
check($warm['text']==='أهلاً بيك في أونتراك','auto does not add unwanted text');
check(str_contains($warm['synthesisContext']['reasoningContext'],'Customer said:'),'caller context provided');
$empathy=ayo_expression_prepare('معلش يا فندم، بنعتذر عن التأخير', 'expressive', 'inworld-tts-2');
check($empathy['nonverbalCue']==='sigh' && str_starts_with($empathy['text'],'[sigh] '),'sigh is added on apology');
$laugh=ayo_expression_prepare('دي نكتة طريفة جدًا', 'expressive', 'inworld-tts-2');
check($laugh['nonverbalCue']==='chuckle','laugh when explicitly humorous');
$plain=ayo_expression_prepare('عندنا استضافة ومواقع', 'expressive', 'inworld-tts-2');
check($plain['nonverbalCue']==='' && $plain['text']==='عندنا استضافة ومواقع','no forced sound for neutral support reply');
$manual=ayo_expression_prepare('[laugh] يا سلام!', 'expressive', 'inworld-tts-2');
check($manual['nonverbalCue']==='' && $manual['text']==='[laugh] يا سلام!','manual tag preserved without duplicate');
$off=ayo_expression_prepare('بنعتذر', 'off', 'inworld-tts-2');
check(!$off['reasoningEnabled'] && $off['synthesisContext']===null && $off['text']==='بنعتذر','off means off');
$flash=ayo_expression_prepare('معلش', 'expressive', 'inworld-tts-2-flash');
check(!$flash['reasoningEnabled'] && $flash['nonverbalCue']==='sigh','Flash nonverbals but no reasoning');
$long=ayo_expression_prepare('رد قصير','auto','inworld-tts-2',str_repeat('تفاصيل ',300));
check(strlen($long['synthesisContext']['reasoningContext'])<900,'context bounded');
echo "AYO VOICE EXPRESSION TESTS PASS\n";
