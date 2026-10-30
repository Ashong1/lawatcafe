<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Gzipped mysqldump backups in storage/app/backups, shared by the hourly
 * backup:database job and Settings → Security. A restore also needs the same
 * APP_KEY (MACs and AI audit records are encrypted with it).
 */
class BackupService
{
    /** A week of hourly copies (about 0.7 MB each). */
    public const KEEP = 168;

    private const NAME = '/^[a-z0-9_]+_\d{4}-\d{2}-\d{2}_\d{6}\.sql\.gz$/';

    public function dir(): string
    {
        return storage_path('app/backups');
    }

    /** @return Collection<int, array{name: string, size: int, made_at: int}> newest first */
    public function list(): Collection
    {
        return collect(File::glob($this->dir().'/*.sql.gz'))
            ->filter(fn ($f) => preg_match(self::NAME, basename($f)))
            // The time in the name, not the file's: a copied file gets a new mtime.
            ->map(fn ($f) => ['name' => basename($f), 'size' => filesize($f),
                'made_at' => Carbon::createFromFormat('Y-m-d_His', substr(basename($f), -24, 17), config('app.timezone'))->getTimestamp()])
            ->sortByDesc('name')
            ->values();
    }

    /** The full path of a listed backup; anything else (../, other files) is refused. */
    public function path(string $name): string
    {
        $path = $this->dir().'/'.$name;
        if (! preg_match(self::NAME, $name) || ! is_file($path)) {
            throw new RuntimeException('That backup does not exist.');
        }

        return $path;
    }

    /** Returns the new file's name. Logs success or failure to the Activity Log. */
    public function create(?User $by = null): string
    {
        $db = config('database.connections.mysql');
        File::ensureDirectoryExists($this->dir(), 0750);
        $file = $this->dir().'/'.$db['database'].'_'.now()->format('Y-m-d_His').'.sql.gz';

        $result = $this->mysql('mysqldump --single-transaction --routines --no-tablespaces %s | gzip > '.escapeshellarg($file));

        if (! $result->successful() || ! is_file($file) || filesize($file) < 100) {
            File::delete($file);
            $error = trim($result->errorOutput()) ?: 'mysqldump produced no output';
            ActivityLog::record('backup_failed', $by, $error);
            throw new RuntimeException('Backup failed: '.$error);
        }

        $this->list()->slice(self::KEEP)->each(fn ($old) => File::delete($this->dir().'/'.$old['name']));
        ActivityLog::record('backup_made', $by, basename($file).' ('.round(filesize($file) / 1048576, 1).' MB)');

        return basename($file);
    }

    /**
     * Replace the database with a backup. A fresh safety backup is made first,
     * so a restore can itself be undone. Tables added after the backup was made
     * are kept and brought up to date by the migrations.
     */
    public function restore(string $name, User $by): string
    {
        $path = $this->path($name);
        $safety = $this->create($by);

        $result = $this->mysql('gunzip -c '.escapeshellarg($path).' | mysql %s');
        if (! $result->successful()) {
            ActivityLog::record('backup_failed', $by, "Restore of {$name} failed: ".trim($result->errorOutput()));
            throw new RuntimeException('Restore failed: '.trim($result->errorOutput()).' Nothing was lost: '.$safety.' holds the data from just before.');
        }

        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('cache:clear');
        // After the restore, so the entry survives it.
        ActivityLog::record('backup_restored', $by, "{$name} (safety copy before it: {$safety})");

        return $safety;
    }

    /** Runs a pipeline under bash (sh here is dash, without pipefail); %s gets the connection args. */
    private function mysql(string $pipeline)
    {
        $db = config('database.connections.mysql');
        $args = sprintf('-h %s -P %s -u %s %s', escapeshellarg($db['host']), escapeshellarg((string) $db['port']),
            escapeshellarg($db['username']), escapeshellarg($db['database']));

        return Process::env(['MYSQL_PWD' => (string) $db['password']])->timeout(600)
            ->run(['bash', '-c', 'set -o pipefail; '.sprintf($pipeline, $args)]);
    }
}
