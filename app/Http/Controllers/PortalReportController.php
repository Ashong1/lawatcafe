<?php

namespace App\Http\Controllers;

use App\Models\PortalEvent;
use App\Models\Voucher;
use Illuminate\Http\Request;

/**
 * How guests use the captive portal over the last N days: the sign-in funnel,
 * busiest hours, wrong codes, "Need more time?" and dropped sessions.
 */
class PortalReportController extends Controller
{
    public const REASONS = [
        'no_match' => 'Code not found (mistyped)',
        'used_up' => 'Code already used up',
        'used' => 'Code already used',
        'other_device' => 'Code in use on another phone',
        'banned' => 'Blocked device',
        'expired_unused' => 'Unused code expired',
    ];

    public function index(Request $request)
    {
        $days = in_array((int) $request->query('days'), [1, 7, 30], true) ? (int) $request->query('days') : 7;
        $since = now()->subDays($days);
        $events = PortalEvent::where('created_at', '>=', $since);

        $devices = fn (string ...$types) => (clone $events)->whereIn('type', $types)->distinct()->count('ip_address');
        $funnel = [
            ['label' => 'Opened the portal', 'value' => $devices(PortalEvent::VISIT, PortalEvent::TIME_UP, PortalEvent::CODE_TRIED, PortalEvent::CONNECTED)],
            ['label' => 'Typed a code', 'value' => $devices(PortalEvent::CODE_TRIED)],
            ['label' => 'Got online', 'value' => $devices(PortalEvent::CONNECTED)],
        ];

        $failures = (clone $events)->where('type', PortalEvent::CODE_FAILED)->get();
        $failedByReason = $failures->countBy(fn ($e) => $e->meta['reason'] ?? 'no_match')->sortDesc();

        // Connections by hour of day (0-23), in the shop's timezone.
        $byHour = array_fill(0, 24, 0);
        (clone $events)->where('type', PortalEvent::CONNECTED)->pluck('created_at')
            ->each(function ($at) use (&$byHour) {
                $byHour[(int) $at->copy()->timezone(config('app.timezone'))->format('G')]++;
            });

        $sold = Voucher::whereNotNull('activated_at')->where('activated_at', '>=', $since);

        return view('network.portal-report', [
            'days' => $days,
            'funnel' => $funnel,
            'wrongCodes' => $failures->count(),
            'failedByReason' => $failedByReason,
            'recentFailures' => $failures->sortByDesc('created_at')->take(10),
            'byHour' => $byHour,
            'avgMinutes' => (int) round((clone $sold)->avg('duration_minutes') ?? 0),
            'vouchersUsed' => (clone $sold)->count(),
            'moreTime' => (clone $events)->where('type', PortalEvent::MORE_TIME)->count(),
            'timeAdded' => (clone $events)->where('type', PortalEvent::TIME_ADDED)->get(),
            'timeUp' => (clone $events)->where('type', PortalEvent::TIME_UP)->distinct()->count('voucher_code'),
            'drops' => (clone $events)->where('type', PortalEvent::DROPPED)->latest('created_at')->get(),
        ]);
    }
}
