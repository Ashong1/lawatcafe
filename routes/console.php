<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('imap:scan-receipts')->everyMinute();
Schedule::command('network:enforce-sessions')->everyMinute();
