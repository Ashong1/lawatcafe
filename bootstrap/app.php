<?php

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
        $middleware->trustProxies(at: '*');
        // These are plain client-side JS cookies (document.cookie), not written
        // through Laravel's Cookie facade — EncryptCookies would otherwise fail
        // to decrypt them and silently null them out on every request, which is
        // exactly why the sidebar's sticky-submenu cookie never actually
        // persisted across page loads despite looking correct in code review.
        $middleware->encryptCookies(except: [
            'lk_sidebar_open',
            'lk_admin_menus',
            'lk_staff_menus',
        ]);
        $middleware->validateCsrfTokens(except: [
            'portal/authenticate',
            'portal/verify-payment',
            'portal/upload',
            'portal/chat',
        ]);
        $middleware->appendToGroup('web', [
            \App\Http\Middleware\IdleSessionTimeout::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
