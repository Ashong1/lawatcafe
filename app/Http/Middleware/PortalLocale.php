<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * English or Filipino for the guest portal. ?lang=fil|en switches and is
 * remembered in a cookie, so every portal page and message follows it.
 */
class PortalLocale
{
    public const LOCALES = ['en', 'fil'];

    private const COOKIE = 'portal_lang';

    public function handle(Request $request, Closure $next): Response
    {
        $asked = $request->query('lang');
        $locale = in_array($asked, self::LOCALES, true) ? $asked : $request->cookie(self::COOKIE);

        if (in_array($locale, self::LOCALES, true)) {
            app()->setLocale($locale);
        }

        if (in_array($asked, self::LOCALES, true)) {
            Cookie::queue(self::COOKIE, $asked, 60 * 24 * 365);
        }

        return $next($request);
    }
}
