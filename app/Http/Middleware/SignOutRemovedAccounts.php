<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Removing someone who is signed in ends their session on their next click,
 * rather than whenever the session would have expired.
 */
class SignOutRemovedAccounts
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::user()?->isDeactivated()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['message' => 'This account was removed.'], 401);
            }

            return redirect()->route('login')->with('error', 'This account was removed. Ask the owner if you need access again.');
        }

        return $next($request);
    }
}
