<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/database/Seed.php';
require_once __DIR__.'/../saas/console/_core/VoiceKnowledgeWeb.php';
if(is_dir('/home/appontrack') && !getenv('ONTRACK_CI_NAMESPACE'))
    throw new RuntimeException('Isolated CI fixture only');
function search_check(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException('SITE06_INDEX_FAIL '.$label);
}
seed_console();
$site=(int)one("SELECT id FROM sites WHERE account_id=4 AND status='active' LIMIT 1")['id'];
$agent=(int)one("SELECT id FROM agents WHERE account_id=4 AND site_id=? AND status!='archived' LIMIT 1",[$site])['id'];
$private=voice_knowledge_add(4,$site,'فواتير داخلية','note','معلومات سرية عن فواتير المدير','staff');
$approved=voice_knowledge_add(4,$site,'أسعار حجز المعرض','md',
    "رسوم الحجز: 350 جنيه\nالتأكيد: يتطلب رقم حجز صادر من API");
$conflict=voice_knowledge_add(4,$site,'جدول رسوم الحجوزات','md',
    "رسوم الحجز: 500 جنيه\nالمبيعات: متاحة");
$returns=voice_knowledge_add(4,$site,'سياسة الاسترجاع','md',"فترة الارجاع: يومين\nيتطلب الإيصال");
$pending=voice_knowledge_add(4,$site,'جدول رسوم الحجوزات','md',
    'رسوم الحجز: 1000 جنيه');
search_check((string)one('SELECT status FROM voice_knowledge_sources WHERE id=?',
    [$pending])['status']==='review','conflicting revision requires owner approval');
voice_kb_scope_set(4,$site,$approved,'all',0,900,0);
voice_kb_scope_set(4,$site,$conflict,'all',0,100,0);
voice_kb_scope_set(4,$site,$returns,'all',0,400,0);
$indexed=voice_kb_search_reindex(4,$site);
search_check($indexed['scanned']===3 && $indexed['indexed']===3,
    'only three approved public knowledge sources indexed');
search_check(!one('SELECT source_id FROM voice_kb_search_index WHERE source_id=?',[$private]) &&
    !one('SELECT source_id FROM voice_kb_search_index WHERE source_id=?',[$pending]),
    'staff/private and unapproved revisions absent from index');
search_check(voice_kb_search_reindex(4,$site)['indexed']===0,
    'reindex is idempotent when source hashes unchanged');
$prices=voice_kb_agent_context(4,$site,$agent,4000,'كام سعر ورسوم الحجز');
search_check(str_contains($prices,'350 جنيه') && !str_contains($prices,'500 جنيه') &&
    !str_contains($prices,'1000 جنيه'),
    'higher priority approved fact wins, review source and lower-priority contradiction excluded');
search_check(!str_contains($prices,'معلومات سرية'),
    'index never returns staff-only information');
$returnsCtx=voice_kb_agent_context(4,$site,$agent,4000,'ايه سياسة الاسترجاع');
search_check(str_contains($returnsCtx,'يومين') &&
    !str_contains($returnsCtx,'350 جنيه'),
    'Arabic synonym search restricts context to relevant sources');
$none=voice_kb_agent_context(4,$site,$agent,4000,'مجهولzz365');
search_check($none==='', 'unknown query returns no unrelated factual context');
voice_kb_scope_set(4,$site,$approved,'all',0,100,0);
$equal=voice_kb_agent_context(4,$site,$agent,4000,'رسوم الحجز');
search_check(!str_contains($equal,'350 جنيه') && !str_contains($equal,'500 جنيه'),
    'equal-priority conflicting structured facts are suppressed, not fabricated');
voice_kb_scope_set(4,$site,$approved,'agent',$agent,900,0);
search_check(voice_kb_agent_context(4,$site,$agent+99999,4000,'سعر الحجز')==='',
    'unknown agent cannot query public index');
search_check(voice_kb_agent_context(5,$site,$agent,4000,'سعر الحجز')==='',
    'cross-tenant knowledge isolation includes search index');
voice_knowledge_update(4,$site,$approved,'رسوم الحجز: 360 جنيه');
$afterEdit=voice_kb_agent_context(4,$site,$agent,4000,'سعر الحجز');
search_check(str_contains($afterEdit,'360 جنيه') && !str_contains($afterEdit,'350 جنيه'),
    'old index hash is ignored immediately after source edit');
search_check(voice_kb_search_reindex(4,$site)['indexed']===1,
    'reindex refreshes changed version without duplicate rows');
voice_knowledge_delete(4,$approved);
voice_kb_search_reindex(4,$site);
search_check(!one('SELECT source_id FROM voice_kb_search_index WHERE source_id=?',[$approved]),
    'archive purges active search index entry');
execute("UPDATE accounts SET status='suspended' WHERE id=4");
$blocked=false;
try{voice_kb_search_reindex(4,$site);}catch(InvalidArgumentException){$blocked=true;}
search_check($blocked && voice_kb_agent_context(4,$site,$agent,4000,'رسوم الحجز')==='',
    'suspended tenant cannot reindex or retrieve any facts');
echo "SITE06_PUBLIC_SCOPED_KNOWLEDGE_SEARCH_PASS\n";
