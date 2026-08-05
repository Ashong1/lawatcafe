<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the developer/system account out of the cashier's seat.
 *
 * RoleMiddleware is a hierarchy — a user passes if their level is at least the
 * route's — which by construction cannot express "staff and admin, but not
 * super_admin". That is the right shape for almost everything here, because
 * privilege genuinely accumulates upwards. The till is the exception: as
 * User::isSuperAdmin() puts it, super_admin is "exactly the developer/system
 * account", and its duty is managing the system, not ringing up sales.
 *
 * Sales made from that account would also be real rows in the shift and cash
 * reconciliation reports, so leaving the register open to it is a data-quality
 * problem as much as a role-clarity one.
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
