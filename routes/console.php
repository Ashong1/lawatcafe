<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('network:enforce-sessions')->everyMinute();
Schedule::command('agent:analyze')->everyFifteenMinutes();

// Runs at half the forecast's own 1h freshness window so the cache is topped
// up well before it expires, and the dashboard only ever reads cache.
// withoutOverlapping: a slow AI cascade must not stack runs on top of itself.
Schedule::command('ai:warm-forecast')->everyThirtyMinutes()->withoutOverlapping();
