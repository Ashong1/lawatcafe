<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the developer/system account out of the cashier's seat.
 *
 * RoleMiddleware is a hierarchy ("at least this role"), so it can't say
 * "staff and admin, but not super_admin". super_admin manages the system;
 * sales from it would be real rows in shift and cash reconciliation reports.
 */
class DenySuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->isSuperAdmin()) {
            $message = 'The system administrator account cannot use the register. Sign in as an admin or staff account to take orders.';

            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return redirect()->route('dashboard')->with('error', $message);
        }

        return $next($request);
    }
}
