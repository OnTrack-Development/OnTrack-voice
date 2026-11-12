<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Shared-host production keeps Laravel core outside public_html at /home/appontrack/iovoice-app.
$basePath = is_dir('/home/appontrack/iovoice-app') && is_file('/home/appontrack/iovoice-app/vendor/autoload.php')
    ? '/home/appontrack/iovoice-app'
    : dirname(__DIR__);

if (file_exists($maintenance = $basePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $basePath.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $basePath.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
