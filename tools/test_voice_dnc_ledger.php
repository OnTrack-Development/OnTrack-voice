<?php
declare(strict_types=1);

/** Synthetic-only regression: persistent suppression must outlive contact CRUD. */
function test_voice_dnc_ledger():void {
    require_once __DIR__.'/../saas/console/_core/DncGuard.php';

    // An existing opted-out contact predates the additive migration.
    $legacy=insert("INSERT INTO contacts(account_id,site_id,name,phone,dnc,created_at)
        VALUES (4,2,'Legacy opt-out',?,1,?)",['+201077700099',now()]);
    dnc_init_ledger();
    api_http_assert(is_file(private_path().'/schema.dnc.v1'),
        'additive DNC migration completed with durable marker');
    $repeatSeed=seed_console();
    api_http_assert(($repeatSeed['status']??'')==='already_seeded',
        'release seeder re-entry preserves existing data and DNC migration');
    $legacyKey=dnc_phone_key('01077700099');
    api_http_assert(dnc_ledger_contains(4,$legacyKey),
        'migration backfilled existing opted-out contact into indexed ledger');
    $before=(int)one('SELECT COUNT(*) n FROM dnc_suppressions WHERE account_id=4 AND phone_key=?',[$legacyKey])['n'];
    dnc_init_ledger();
    dnc_record_suppression(4,'00201077700099','legacy_contact');
    api_http_assert($before===1 &&
        (int)one('SELECT COUNT(*) n FROM dnc_suppressions WHERE account_id=4 AND phone_key=?',[$legacyKey])['n']===1,
        'repeated migration and equivalent phone opt-out are idempotent');

    // Deleting or changing a contact must not erase a phone-level opt-out.
    execute('DELETE FROM contacts WHERE id=?',[$legacy]);
    api_http_assert(dnc_is_suppressed(4,'01077700099') &&
        !dnc_is_suppressed(5,'01077700099'),
        'suppression survives physical contact deletion without cross-tenant leak');

    api_http_assert(dnc_effective_flag(4,'01277700123',true)===1,
        'new explicit opt-out is persisted without requiring a contact row');
    $key=dnc_phone_key('+201277700123');
    api_http_assert(dnc_ledger_contains(4,$key) &&
        dnc_effective_flag(4,'+201277700123',false)===1 &&
        dnc_effective_flag(5,'+201277700123',false)===0,
        'ledger is used by later imports and isolated per account');
    $entry=one('SELECT source FROM dnc_suppressions WHERE account_id=? AND phone_key=?',[4,$key]);
    api_http_assert(($entry['source']??'')==='contact_optout',
        'opt-out source retained for future privacy review');

    // A legacy SQL import after migration must self-heal into the ledger.
    $later=insert("INSERT INTO contacts(account_id,site_id,name,phone,dnc,created_at)
        VALUES (4,1,'Late legacy opt-out',?,1,?)",['01155512345',now()]);
    $lateKey=dnc_phone_key('+201155512345');
    api_http_assert(dnc_is_suppressed(4,'+201155512345') &&
        dnc_ledger_contains(4,$lateKey),
        'post-migration legacy opt-out is automatically promoted to ledger');
    execute('DELETE FROM contacts WHERE id=?',[$later]);
    api_http_assert(dnc_is_suppressed(4,'01155512345'),
        'healed legacy opt-out remains durable after source deletion');

    $bad=false;
    try{dnc_record_suppression(4,'not-a-phone');}
    catch(InvalidArgumentException $e){$bad=$e->getMessage()==='invalid_dnc_ledger_entry';}
    api_http_assert($bad,'invalid phone cannot poison the DNC ledger');
    $placeholderRejected=false;
    try{dnc_record_suppression(4,'DEMO-NO-DIAL-01');}
    catch(InvalidArgumentException $e){$placeholderRejected=true;}
    api_http_assert($placeholderRejected,
        'short digits inside synthetic demo placeholder cannot become suppression');
    echo "VOICE_DNC_LEDGER_PASS\n";
}
