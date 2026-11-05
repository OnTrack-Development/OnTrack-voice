<?php
declare(strict_types=1);
/** Isolated PHP built-in server router for browser-layout CI.
 * Never deployed by the SaaS bundler; production is served by Apache.
 */
$raw=(string)(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');
$prefix='/saas/console';
$path=str_starts_with($raw,$prefix.'/')?substr($raw,strlen($prefix)):$raw;
if($path==='/assets'||str_starts_with($path,'/assets/')){
    $root=realpath(__DIR__.'/../saas/console/assets');
    $file=realpath(__DIR__.'/../saas/console'.rawurldecode($path));
    if(!$root||!$file||!str_starts_with($file,$root.DIRECTORY_SEPARATOR)||!is_file($file)){
        http_response_code(404);exit;
    }
    $extension=strtolower(pathinfo($file,PATHINFO_EXTENSION));
    $mime=['css'=>'text/css','js'=>'text/javascript','svg'=>'image/svg+xml',
        'png'=>'image/png','webp'=>'image/webp','jpg'=>'image/jpeg'][$extension]??'application/octet-stream';
    header('Content-Type: '.$mime);
    readfile($file);return true;
}
require __DIR__.'/../saas/console/index.php';
