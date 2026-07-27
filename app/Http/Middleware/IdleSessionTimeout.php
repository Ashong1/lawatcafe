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
        if (!Auth::check()) {
            return $next($request);
        }

        $timeoutMinutes = (int) config('session.idle_timeout', 20);
        $lastActivity = $request->session()->get('last_activity_at');

        if ($timeoutMinutes > 0 && $lastActivity && now()->diffInMinutes($lastActivity) >= $timeoutMinutes) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'You were logged out due to inactivity.');
        }

        $request->session()->put('last_activity_at', now());

        return $next($request);
    }
}
