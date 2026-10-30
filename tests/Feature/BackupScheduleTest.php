<?php

namespace Tests\Feature;

use App\Services\BackupService;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * Evaluators (Oct 28): one backup at 11:30 PM loses the whole day if the
 * server fails in the evening (and it is often switched off by then).
 */
class BackupScheduleTest extends TestCase
{
    public function test_the_database_is_backed_up_every_hour(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'backup:database'));

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
    }

    public function test_a_week_of_hourly_backups_is_kept(): void
    {
        $this->assertSame(168, BackupService::KEEP);
    }
}
