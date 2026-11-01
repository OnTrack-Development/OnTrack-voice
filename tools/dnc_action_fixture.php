<?php
declare(strict_types=1);
/** CLI-only action fixture for isolated CI SQLite DB; never deployed as web code. */
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/../saas/console/_core/Bootstrap.php';
require __DIR__.'/../saas/console/_core/Actions.php';
init_schema();
$user=one("SELECT id FROM users WHERE email='user@direct.test'");
if(!$user)exit(2);
$_SESSION=['uid'=>(int)$user['id'],'account_id'=>4,'csrf'=>'dnc-test-csrf'];
$action=(string)($argv[1]??'');
$contactId=(int)($argv[2]??0);
$phone=(string)($argv[3]??'01012345678');
if(!in_array($action,['add_contact','edit_contact','delete_contact'],true))exit(3);
$_POST=[
    'csrf'=>'dnc-test-csrf','action'=>$action,'return'=>'/?tab=contacts',
    'site_id'=>'1','contact_id'=>(string)$contactId,
    'name'=>'Synthetic action fixture','phone'=>$phone,'consent'=>'1',
    // No DNC checkbox: tests must prove existing opt-out is not cleared.
];
act();
