<?php

namespace App\Http\Middleware;

use App\Models\SiteVisit;
use App\Services\RemoteAccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records each browser visit once (owner, Oct 30): who opened the system, and
 * whether from the shop or through the remote link. The guest Wi-Fi portal is
 * left out; every guest phone opens it, and it would bury everything else.
 */
class LogSiteVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->worthLogging($request)) {
            return $response;
        }

        try {
            $visitId = $request->session()->get('site_visit_id');
            if (! $visitId) {
                $visit = SiteVisit::create([
                    'user_id' => $request->user()?->id,
                    'remote' => RemoteAccessService::isRemoteRequest($request),
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                    'path' => Str::limit('/'.ltrim($request->path(), '/'), 250, ''),
                ]);
                $request->session()->put('site_visit_id', $visit->id);
            } elseif ($request->user()) {
                // Signed in after arriving: the visit becomes theirs.
                SiteVisit::whereKey($visitId)->whereNull('user_id')->update(['user_id' => $request->user()->id]);
            }
        } catch (\Throwable $e) {
            report($e);   // a log must never break a page
        }

        return $response;
    }

    private function worthLogging(Request $request): bool
    {
        return $request->isMethod('GET')
            && $request->hasSession()
            && ! $request->expectsJson() && ! $request->ajax()
            && ! $request->is('portal', 'portal/*', 'captive-portal-api', 'up')
            && ! str_starts_with($request->getHost(), 'wifi.');
    }
}
