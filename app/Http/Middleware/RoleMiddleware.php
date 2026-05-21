<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // 0. Ensure user is actually logged in
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        $userRole = trim($user->role);

        // 1. If the user's role matches the required route role, let them through!
        if ($userRole === $role) {
            return $next($request);
        }

        // 2. Staff members are redirected to their own dashboard if they try to access something else
        if ($userRole === 'staff') {
            if ($role === 'admin') {
                return redirect()->route('staff.dashboard')->with('error', 'Unauthorized Access.');
            }
            return $next($request); // Allow staff to access non-admin shared routes
        }

        // 3. Admins can access staff routes
        if ($userRole === 'admin' && $role === 'staff') {
            return $next($request);
        }

        // 4. Final fallback: unauthorized
        abort(403, 'Unauthorized access.');
    }
}