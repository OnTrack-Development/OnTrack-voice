<?php
declare(strict_types=1);
require_once __DIR__.'/_auth.php';
gap_map_authorize(true);
$path=GAP_PRIVATE.'/status.json';
if(!is_file($path)||is_link($path)||!is_readable($path)||filesize($path)>131072)
    gap_map_json(['ok'=>false,'error'=>'map_snapshot_unavailable'],503);
$body=@file_get_contents($path,false,null,0,131073);
$json=is_string($body)?json_decode($body,true):null;
if(!is_array($json)||($json['schema']??null)!==2)
    gap_map_json(['ok'=>false,'error'=>'map_snapshot_invalid'],503);
// Owner confirmations are stored on the host in an isolated, private ledger.
// They survive CI snapshot replacement, and expire if the affected source changed.
$reviewFile=GAP_PRIVATE.'/reviews.json';
$reviews=[];
if(is_file($reviewFile)&&!is_link($reviewFile)&&is_readable($reviewFile)){
    $reviews=json_decode((string)@file_get_contents($reviewFile,false,null,0,65536),true);
    if(!is_array($reviews))$reviews=[];
}
if(!isset($json['gaps'])||!is_array($json['gaps']))
    gap_map_json(['ok'=>false,'error'=>'snapshot_gaps_invalid'],503);
foreach($json['gaps'] as &$gap){
    if(!is_array($gap))continue;
    $id=(string)($gap['id']??'');
    $review=$reviews[$id]??null;
    if(is_array($review)&&($review['state']??'')==='confirmed_by_owner'){
        $unchanged=hash_equals((string)($gap['basis_hash']??''),(string)($review['basis_hash']??''));
        $gap['verification']=[
            'state'=>$unchanged?'confirmed_by_owner':'recheck_required',
            'verified_at'=>$review['verified_at']??null,
            'verified_commit'=>$review['verified_commit']??null,
            'source_changed'=>!$unchanged
        ];
    }else{
        $gap['verification']=['state'=>'not_confirmed','source_changed'=>false];
    }
}
unset($gap);
$json['csrf']=gap_map_csrf();
$json['ok']=true;
gap_map_json($json);
