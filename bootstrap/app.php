<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // SetLocale dans le groupe web (APRÈS StartSession, sinon session non disponible)
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\TrackUserPresence::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/oo-callback/*',
            'public/api/oo-callback/*',
            'e-administration_laravel/api/oo-callback/*',
            'e-administration_laravel/public/api/oo-callback/*',
            'api/signature/platform-webhook',
            'e-administration_laravel/api/signature/platform-webhook',
            'e-administration_laravel/public/api/signature/platform-webhook',
            'public/api/signature/platform-webhook',
            'api/mobile-money/*/callback',
            'e-administration_laravel/api/mobile-money/*/callback',
            'e-administration_laravel/public/api/mobile-money/*/callback',
            'public/api/mobile-money/*/callback',
        ]);
        $middleware->alias([
            'admin'                => \App\Http\Middleware\EnsureAdmin::class,
            'verify.webhook'       => \App\Http\Middleware\VerifySignaturePlatformWebhook::class,
            'verify.mm.webhook'    => \App\Http\Middleware\VerifyMobileMoneyWebhook::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
