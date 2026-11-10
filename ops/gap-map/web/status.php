<?php
declare(strict_types=1);
require_once __DIR__.'/_auth.php';
gap_map_authorize(true);
// Host-private data, not a publicly fetchable JSON asset.
$path='/home/appontrack/.ontrack-gap-map/status.json';
if(!is_file($path)||is_link($path)||!is_readable($path)||filesize($path)>131072){
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"ok":false,"error":"map_snapshot_unavailable"}';
    exit;
}
$body=@file_get_contents($path,false,null,0,131073);
$json=is_string($body)?json_decode($body,true):null;
if(!is_array($json)||($json['schema']??null)!==2){
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"ok":false,"error":"map_snapshot_invalid"}';
    exit;
}
header('Content-Type: application/json; charset=utf-8');
header('X-Map-Snapshot: '.preg_replace('/[^0-9a-f]/','',(string)($json['commit']??'')));
echo $body;
