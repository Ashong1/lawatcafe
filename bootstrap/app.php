<?php

use App\Http\Middleware\IdleSessionTimeout;
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
            IdleSessionTimeout::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Laravel's default expectsJson() requires the Accept header to be
        // empty/"*/*" or contain "json" — the AI chat widget sends
        // "Accept: text/event-stream" (it's an SSE stream), which fails that
        // check even though X-Requested-With makes ajax() true. Without this,
        // an expired admin/staff session hitting auth-gated fetch() endpoints
        // (like /admin/ai/chat) got Laravel's default redirect-to-login
        // response instead of a 401 — fetch() follows the redirect silently,
        // reads the login page's HTML as the "response", and the caller sees
        // a dead widget with no error at all.
        $exceptions->shouldRenderJsonWhen(function ($request, \Throwable $e) {
            return $request->ajax() || $request->expectsJson();
        });
    })->create();
