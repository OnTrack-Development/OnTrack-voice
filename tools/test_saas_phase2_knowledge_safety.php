<?php
declare(strict_types=1);
/** SaaS Phase 2 KB guard: no remote web, no user data or production DB. */
require_once __DIR__.'/../saas/console/_core/VoiceKnowledgeWeb.php';
function kb2_check(bool $ok,string $what):void {
    if(!$ok)throw new RuntimeException('SAAS_PHASE2_KB_FAIL '.$what);
}
$buf='';
kb2_check(voice_kb_bounded_chunk($buf,'Arabic ',12)===7,'valid chunk accepted');
kb2_check(voice_kb_bounded_chunk($buf,'EGY!',12)===4,'second chunk accepted');
kb2_check($buf==='Arabic EGY!','two valid chunks preserve bytes');
kb2_check(voice_kb_bounded_chunk($buf,'x',12)===1,'exact limit accepted');
kb2_check(voice_kb_bounded_chunk($buf,'x',12)===0,'overflow aborts transfer');
kb2_check($buf==='Arabic EGY!x','overflow does not store truncated body');
$buf='';
kb2_check(voice_kb_bounded_chunk($buf,str_repeat('A',180001))===0&&$buf==='',
    'oversized first network chunk cannot allocate unbounded buffer');
$buf='';
kb2_check(voice_kb_bounded_chunk($buf,'',180000)===0,'empty chunk remains zero');
kb2_check(voice_kb_bounded_chunk($buf,'hello',180000)===5&&$buf==='hello',
    'ordinary knowledge text preserved');
$sample=voice_kb_search_terms('سعر الحجز في مصر');
kb2_check(isset($sample['price'])&&isset($sample['booking']),
    'existing Arabic keyword normalization unchanged');
$withConflict=[
  ['priority'=>100,'content'=>"سعر: 350\nموعد: السبت",'source_id'=>11],
  ['priority'=>50,'content'=>"سعر: 500\nالضمان: سنة",'source_id'=>12]
];
$resolved=voice_kb_search_resolve_facts($withConflict);
kb2_check(count($resolved)===2 && str_contains($resolved[0]['content'],'350') &&
    !str_contains($resolved[1]['content'],'500') &&
    str_contains($resolved[1]['content'],'الضمان'),
    'conflicting public facts still honor approved priority');
kb2_check(voice_kb_search_resolve_facts([])===[],
    'optional empty knowledge remains empty rather than blocking an agent');
echo "SAAS_PHASE2_KNOWLEDGE_BOUNDED_FETCH_NONREGRESSION_PASS\n";
