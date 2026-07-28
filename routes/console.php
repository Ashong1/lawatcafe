<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('network:enforce-sessions')->everyMinute();
Schedule::command('agent:analyze')->everyFifteenMinutes();
