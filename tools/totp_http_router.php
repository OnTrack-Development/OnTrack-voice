<?php
declare(strict_types=1);
// Local CI-only router. Never expose this file from the public SaaS docroot.
if(getenv('GITHUB_ACTIONS')!=='true'){http_response_code(404);exit;}
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
if(str_starts_with($path,'/saas/console/assets/'))return false;
if(str_starts_with($path,'/saas/console')){
    require __DIR__.'/../saas/console/index.php';
    return true;
}
http_response_code(404);
