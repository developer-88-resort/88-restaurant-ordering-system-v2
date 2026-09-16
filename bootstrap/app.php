<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\VerifyPrinterBridgeToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'printer-bridge' => VerifyPrinterBridgeToken::class,
        ]);

        // Trust the Cloudflare Tunnel's X-Forwarded-Proto header so Laravel
        // knows the original request was HTTPS even though cloudflared
        // forwards it to this dev server as plain HTTP — otherwise every
        // generated asset/storage URL comes back as http:// and gets
        // blocked as mixed content on the https tunnel URL.
        $middleware->trustProxies(at: '*');

        // SetLocale must run before HandleInertiaRequests — Inertia's shared
        // 'locale' prop reads app()->getLocale(), which is only correct once
        // SetLocale has resolved it from the cookie/user preference.
        $middleware->web(append: [
            SetLocale::class,
            EnsureAccountIsActive::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
