<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('network:enforce-sessions')->everyMinute();
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
