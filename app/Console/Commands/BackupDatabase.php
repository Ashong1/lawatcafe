<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Nightly gzipped dump of the database into storage/app/backups, keeping the
 * newest KEEP files. A restore also needs the same APP_KEY (MACs and AI audit
 * records are encrypted with it); see docs/OPERATIONS.md#backups.
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Dump the database to storage/app/backups and keep the last two weeks.';

    private const KEEP = 14;

    public function handle(): int
    {
        $db = config('database.connections.mysql');
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir, 0750);
        $file = $dir.'/'.$db['database'].'_'.now()->format('Y-m-d_His').'.sql.gz';

        $result = Process::env(['MYSQL_PWD' => (string) $db['password']])->timeout(600)->run(['bash', '-c', sprintf(
            'set -o pipefail; mysqldump --single-transaction --routines --no-tablespaces -h %s -P %s -u %s %s | gzip > %s',
            escapeshellarg($db['host']), escapeshellarg((string) $db['port']), escapeshellarg($db['username']),
            escapeshellarg($db['database']), escapeshellarg($file)
        )]);

        if (! $result->successful() || ! is_file($file) || filesize($file) < 100) {
            File::delete($file);
            ActivityLog::record('backup_failed', null, trim($result->errorOutput()) ?: 'mysqldump produced no output');
            $this->error('Backup failed: '.$result->errorOutput());

            return self::FAILURE;
        }

        collect(File::glob($dir.'/*.sql.gz'))->sortDesc()->slice(self::KEEP)->each(fn ($old) => File::delete($old));
        ActivityLog::record('backup_made', null, basename($file).' ('.round(filesize($file) / 1048576, 1).' MB)');
        $this->info("Saved {$file}");

        return self::SUCCESS;
    }
}
