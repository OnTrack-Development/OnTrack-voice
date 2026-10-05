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
echo "Mission validation and prompt tests passed\n";
