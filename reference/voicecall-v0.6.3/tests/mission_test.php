<?php
declare(strict_types=1);
require __DIR__ . '/../lib/OutboundMission.php';

function check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
}
function rejects(callable $run, string $label): void {
    try { $run(); } catch (InvalidArgumentException | JsonException $e) { return; }
    throw new RuntimeException('Expected rejection: ' . $label);
}
$mission = [
    'agent_name' => 'عمر', 'company_name' => 'شركة تجريبية', 'agent_role' => 'مساعد مبيعات عقارات',
    'customer_name' => 'عميل تجريبي', 'customer_phone' => 'TEST-PHONE',
    'offer_name' => 'شقة تجريبية', 'offer_details' => "150 متر\n3 غرف", 'goal' => 'موعد مقترح',
    'asking_price' => '4500000', 'minimum_price' => '4300000', 'currency' => 'جنيه مصري',
];
$normalized = normalize_outbound_mission($mission);
check($normalized['offer_details'] === "150 متر\n3 غرف", 'preserve multiline details');
check(parse_live_request('{}')['mode'] === 'demo', 'legacy demo request');
check(parse_live_request(json_encode(['mode' => 'outbound', 'mission' => $mission]))['mission']['agent_name'] === 'عمر', 'outbound request');
foreach (['[]', 'null', '{bad', '{"mode":"unknown"}', '{"mode":"outbound"}'] as $raw) {
    rejects(fn() => parse_live_request($raw), 'invalid request ' . $raw);
}
rejects(fn() => parse_live_request(str_repeat(' ', 32769)), 'body limit');
foreach ([['agent_name' => ''], ['customer_name' => []], ['asking_price' => '-1'], ['asking_price' => '1e6'], ['asking_price' => '1.234'], ['minimum_price' => '4600000'], ['asking_price' => ''], ['currency' => ''], ['offer_details' => str_repeat('أ', 8001)]] as $bad) {
    rejects(fn() => normalize_outbound_mission(array_replace($mission, $bad)), 'invalid field');
}
$noDiscount = normalize_outbound_mission(array_replace($mission, ['minimum_price' => '']));
check($noDiscount['minimum_price'] === '', 'no implied discount authority');
$prompt = build_outbound_instruction($normalized);
check(str_contains($prompt, 'عمر') && str_contains($prompt, 'شركة تجريبية'), 'custom identity');
check(!str_contains($prompt, 'TEST-PHONE') && !str_contains($prompt, 'Starter Plan'), 'exclude phone and demo catalog');
check(str_contains($prompt, 'لا تعرض ولا تقبل أي مبلغ أقل منه'), 'price boundary');
check(str_contains($prompt, 'لا تبادر بإنهاء الحوار') && str_contains($prompt, '«براحتي»'), 'short replies do not end the dialogue');
check(str_contains($prompt, 'استأنف الحوار ورد على المعنى الجديد'), 'resume after customer speaks again');
check(str_contains($prompt, 'احترم ذلك برد موجز واحد ثم ابق صامتاً'), 'explicit stop requests remain respected');
check(str_contains($prompt, 'حافظ على المصرية حتى لو كلام العميل ظهر بالفرنسية'), 'do not switch language or end for foreign transcription');
check(!str_contains($prompt, 'واختتم فوراً'), 'remove conflicting immediate farewell rule');
$brief = "اسمك كريم، شركة تجريبية، العميل أحمد\nالسعر 320 جنيه وأقل سعر 300 جنيه سري\nاتكلم بالمصري وخليك رغاي";
$single = normalize_outbound_mission(['brief' => "  {$brief}  ", 'agent_name' => 'اسم مخالف']);
check($single === ['brief' => $brief], 'brief mode preserves multiline text and excludes hidden fields');
check(parse_live_request(json_encode(['mode' => 'outbound', 'mission' => ['brief' => $brief]], JSON_UNESCAPED_UNICODE))['mission'] === $single, 'single brief request');
$singlePrompt = build_outbound_instruction($single);
check(str_contains($singlePrompt, 'المهمة مكتوبة بالكامل في brief كنص واحد') && str_contains($singlePrompt, 'لا تتوقع حقولاً منفصلة'), 'one text source instruction');
check(str_contains($singlePrompt, 'كريم') && str_contains($singlePrompt, 'لا تعرض ولا تقبل أي مبلغ أقل منه'), 'brief identity and price rules');
check(str_contains($singlePrompt, 'لا تبادر بإنهاء الحوار'), 'brief retains conversation continuity');
foreach ([['brief' => []], ['brief' => ' '], ['brief' => str_repeat('أ', 8001)], ['brief' => "a\x00b"]] as $bad) {
    rejects(fn() => normalize_outbound_mission($bad), 'invalid single brief');
}
echo "Mission validation and prompt tests passed\n";
