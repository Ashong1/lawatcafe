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

    /** Owner (Oct 30): keep a week of backups, delete older ones automatically, by age not count. */
    public function test_backups_older_than_a_week_are_deleted_automatically(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-10-30 12:00:00');
        $dir = storage_path('framework/testing/backups-week');
        \Illuminate\Support\Facades\File::ensureDirectoryExists($dir);
        $this->mock(BackupService::class)->makePartial()->shouldReceive('dir')->andReturn($dir);
        config(['database.connections.mysql.database' => 'lawat_db']);
        foreach (['2026-10-20_090000', '2026-10-22_110000', '2026-10-24_100000', '2026-10-29_230000'] as $t) {
            \Illuminate\Support\Facades\File::put("{$dir}/lawat_db_{$t}.sql.gz", 'x');
        }
        \Illuminate\Support\Facades\Process::fake(function ($process) {
            if (preg_match("/> '([^']+\\.sql\\.gz)'/", implode(' ', (array) $process->command), $m)) {
                \Illuminate\Support\Facades\File::put($m[1], str_repeat('x', 200));
            }

            return \Illuminate\Support\Facades\Process::result();
        });

        app(BackupService::class)->create();

        $left = collect(\Illuminate\Support\Facades\File::files($dir))->map->getFilename()->sort()->values()->all();
        $this->assertSame(['lawat_db_2026-10-24_100000.sql.gz', 'lawat_db_2026-10-29_230000.sql.gz', 'lawat_db_2026-10-30_120000.sql.gz'], $left);
        $this->assertSame(7, BackupService::KEEP_DAYS);
        \Illuminate\Support\Facades\File::deleteDirectory($dir);
    }
}
