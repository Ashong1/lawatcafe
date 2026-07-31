<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /** staff < admin < super_admin — a user passes if their level >= the route's required level. */
    private const HIERARCHY = [
        'staff' => 1,
        'admin' => 2,
        'super_admin' => 3,
    ];

    public function handle(Request $request, Closure $next, string $role): Response
    {
        // 0. Ensure user is actually logged in
        if (! auth()->check()) {
            // A bare redirect() is invisible to fetch()-based callers (e.g. the AI
            // chat widget's streaming request): fetch follows it silently, reads
            // the login page's HTML as if it were the response body, and the
            // caller sees a completely dead widget with no error at all — this
            // is exactly what was happening on an expired admin/staff session.
            // ajax() is checked in addition to expectsJson() because the latter
            // requires an empty/"*/*"/json Accept header — the AI chat widget's
            // "Accept: text/event-stream" fails that even with X-Requested-With
            // set, which would otherwise make this fall through to a redirect().
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['message' => 'Not authenticated.'], 401);
            }

            return redirect()->route('login');
        }

        $user = auth()->user();
        $userLevel = self::HIERARCHY[trim($user->role)] ?? 0;
        $requiredLevel = self::HIERARCHY[$role] ?? 0;

        // 1. Sufficient level — let them through (covers exact match and "above").
        if ($userLevel >= $requiredLevel) {
            return $next($request);
        }

        // 2. Under-privileged but logged in — bounce to their own dashboard rather than a bare 403.
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        if ($userLevel === self::HIERARCHY['staff']) {
            return redirect()->route('staff.dashboard')->with('error', 'Unauthorized Access.');
        }

        if ($userLevel === self::HIERARCHY['admin']) {
            return redirect()->route('dashboard')->with('error', 'Unauthorized Access.');
        }

        // 3. Unknown/no role — final fallback.
        abort(403, 'Unauthorized access.');
    }
}
