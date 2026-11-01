<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/_core/Checkout.php';
function test_checkout():void {
    $ref='ovc_'.str_repeat('a',32);
    $p=checkout_session_payload($ref,2575,'buyer@example.com','MID-123');
    ok($p['amount']==='25.75'&&$p['currency']==='EGP','checkout uses exact minor-unit prices');
    ok($p['serverWebhook']==='https://app.ontrackegy.com/saas/console/hooks/kashier.php','server callback fixed');
    ok(checkout_session_url('https://payments.kashier.io/session/abcdef?mode=test'),'official Kashier checkout URL');
    ok(!checkout_session_url('https://payments.kashier.io.evil.test/session/abcdef'),'checkout redirect host locked');
    ok(!checkout_session_url('http://payments.kashier.io/session/abcdef'),'no cleartext checkout');
    $bad=false;try{checkout_session_payload('ovc_wrong',100,'buyer@example.com','MID-123');}catch(InvalidArgumentException $e){$bad=true;}
    ok($bad,'session requires server-generated order reference');
    echo "AUTHENTICATED_CHECKOUT_PASS\n";
}
