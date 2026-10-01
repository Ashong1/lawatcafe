<?php

namespace App\Mail\Concerns;

/**
 * For queued mail: keep retrying through an internet outage instead of
 * dropping the message. The queue worker retries every 5 minutes for up to
 * 3 days, so an email written while the shop is offline goes out once the
 * connection returns.
 */
trait WaitsForInternet
{
    public int $backoff = 300;

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addDays(3);
    }
}
