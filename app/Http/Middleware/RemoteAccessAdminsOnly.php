<?php

namespace App\Http\Middleware;

use App\Services\RemoteAccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Through the remote link only the owner and admins are let in (owner, Oct 30)
 * unless the super admin allows staff too: a staff account signed in there
 * otherwise is signed out. A device's MAC address isn't visible over the
 * internet, so the account's role is the check.
 */
class RemoteAccessAdminsOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if ($user && ! RemoteAccessService::allows($user, $request)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => RemoteAccessService::STAFF_MESSAGE], 403)
                : redirect()->route('login')->withErrors(['email' => RemoteAccessService::STAFF_MESSAGE]);
        }

        return $next($request);
    }
}
