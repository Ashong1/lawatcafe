<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('network:enforce-sessions')->everyMinute();

// Guest sessions were observed vanishing from OPNsense with zero
// application-side trigger, in as little as ~25 seconds when a device sat
// idle. This loops internally for ~55s (see KeepaliveGuestSessions) rather
// than relying on schedule granularity, since a once-a-minute ping would
// still leave that ~25s window uncovered. withoutOverlapping is defensive —
// the internal loop is hard-capped under 60s so a normal run should never
// still be going when the next minute's invocation starts.
Schedule::command('network:keepalive-guests')->everyMinute()->withoutOverlapping();
Schedule::command('agent:analyze')->everyFifteenMinutes();

// Runs at half the forecast's own 1h freshness window so the cache is topped
// up well before it expires, and the dashboard only ever reads cache.
// withoutOverlapping: a slow AI cascade must not stack runs on top of itself.
Schedule::command('ai:warm-forecast')->everyThirtyMinutes()->withoutOverlapping();

// The learning loop. Hourly rather than continuous on purpose: lessons are
// generalisations, and generalising from the last four minutes of traffic
// produces noise. It also self-limits — the command exits early when there is
// not enough new evidence, so a quiet shop costs one cheap query per hour.
Schedule::command('ai:learn')->hourly()->withoutOverlapping();

// Backstop for bandwidth-tier alias membership. Every five minutes rather than
// every minute: EnforceSessionLimits already clears members on the normal path,
// so this only catches what that missed, and each run costs one alias read per
// tier. See ReconcileTierMembership for why it must exist before any firewall
// rule passes traffic based on those aliases.
Schedule::command('shaper:reconcile-tiers')->everyFiveMinutes()->withoutOverlapping();

// The adaptive fair-use loop. Every five minutes because that is how fast a
// café fills up, not because the ceiling moves that often — most runs only take
// a throughput sample, since the deadband and cooldown in
// AdaptiveBandwidthService stop a model being woken for noise.
//
// It samples even while adaptation is switched off: the line speed and the
// busy-hour profile are both learned from that history, so turning the loop on
// later should find it already knows the shop.
//
// withoutOverlapping matters more here than elsewhere — each run holds a
// ~11-second sample window open, and two overlapping runs would derive their
// rates from each other's counter reads.
Schedule::command('shaper:adapt')->everyFiveMinutes()->withoutOverlapping();
