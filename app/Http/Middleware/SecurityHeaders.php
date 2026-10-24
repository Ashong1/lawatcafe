<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The headers nginx doesn't already add (it sets X-Frame-Options and
 * X-Content-Type-Options in sites-available/lawatcafe). No HSTS: the shop
 * phones and the captive portal use plain http on the LAN by design.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), payment=()');

        return $response;
    }
}
