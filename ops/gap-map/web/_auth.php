<?php
declare(strict_types=1);
// This page is an operator-only architectural security diagnostic, not public product UI.
require_once dirname(__DIR__).'/_core/Bootstrap.php';
function gap_map_authorize(bool $json=false):void {
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Content-Security-Policy: default-src \'none\'; script-src \'self\'; style-src \'self\'; connect-src \'self\'; img-src \'self\'; base-uri \'none\'; form-action \'self\'; frame-ancestors \'none\'');
    try {
        startsession();
        $actor=me();
        $allowed=false;
        if($actor) {
            foreach(memberships((int)$actor['id']) as $m){
                if($m['kind']==='platform' && in_array($m['role'],['owner','admin'],true)){
                    $allowed=true;
                    break;
                }
            }
        }
    }catch(Throwable $e) {
        http_response_code(503);
        if($json){header('Content-Type: application/json; charset=utf-8');echo '{"ok":false,"error":"map_auth_unavailable"}';}
        else {header('Content-Type: text/plain; charset=utf-8');echo 'Architecture map temporarily unavailable';}
        exit;
    }
    if(!$allowed) {
        http_response_code($actor?403:401);
        if($json){header('Content-Type: application/json; charset=utf-8');echo '{"ok":false,"error":"platform_owner_required"}';}
        else {header('Content-Type: text/html; charset=utf-8');echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><title>صلاحيات الإدارة</title><body style="font-family:system-ui;padding:40px;background:#090b0f;color:#fff">الماب متاحة لأدمن المنصة فقط. <a style="color:#fb7185" href="/login">تسجيل الدخول</a></body></html>';}
        exit;
    }
}
