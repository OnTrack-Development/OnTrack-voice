<?php

use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureWorkspaceMembership;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Server-to-server webhooks, device control plane API & signed SSO callbacks use HMAC / Bearer tokens
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'hooks/*',
            'device/v1/*',
            'saas/console/device/v1/*',
            'saas/console/api/v1/*',
            'saas/console/hooks/*',
            'saas/console/whatsapp-*',
            'mobile/*',
            'saas/mobile/*',
            'sso',
        ]);
        $middleware->alias([
            'platform.admin' => EnsurePlatformAdmin::class,
            'workspace.member' => EnsureWorkspaceMembership::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
