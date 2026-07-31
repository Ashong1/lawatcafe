<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces a logout for authenticated (staff/admin) sessions idle past
 * config('session.idle_timeout'), separate from the absolute session
 * lifetime. Guest captive-portal traffic is never authenticated via this
 * guard, so it passes through untouched.
 */
class IdleSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $timeoutMinutes = (int) config('session.idle_timeout', 20);
        $lastActivity = $request->session()->get('last_activity_at');

        // diffInMinutes()'s $absolute param defaults to true in Carbon 2 but to
        // false in Carbon 3 (this app's version) — without passing it explicitly,
        // now()->diffInMinutes($pastTime) returns a *negative* number, so this
        // check silently never fired and idle sessions were never logged out.
        if ($timeoutMinutes > 0 && $lastActivity && now()->diffInMinutes($lastActivity, true) >= $timeoutMinutes) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // A plain redirect() is invisible to fetch()-based callers (e.g. the
            // AI chat widget's streaming request) — fetch follows it silently,
            // ends up reading the login page's HTML as if it were the response
            // body, and the SSE parser just finds nothing and gives up with no
            // error shown at all. ajax() is checked alongside expectsJson()
            // because the latter requires an empty/"*/*"/json Accept header —
            // the widget's "Accept: text/event-stream" fails that on its own.
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['message' => 'Your session expired due to inactivity.'], 401);
            }

            return redirect()->route('login')->with('error', 'You were logged out due to inactivity.');
        }

        $request->session()->put('last_activity_at', now());

        return $next($request);
    }
}
