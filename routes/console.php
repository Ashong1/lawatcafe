<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('imap:scan-receipts')->everyMinute();
