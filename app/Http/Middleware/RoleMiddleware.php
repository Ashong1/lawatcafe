<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // 1. If the user's role matches the required route role, let them through!
        if (auth()->check() && auth()->user()->role === $role) {
            return $next($request);
        }

        // 2. If they are a Staff member trying to access Admin pages, kick them to Staff Hub
        if (auth()->check() && auth()->user()->role === 'staff') {
            return redirect()->route('staff.dashboard')->with('error', 'Unauthorized Access.');
        }

        // 3. Default fallback: kick them to the Admin Dashboard
        return redirect()->route('dashboard');
    }
}