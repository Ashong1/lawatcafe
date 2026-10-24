<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use RuntimeException;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Dump the database to storage/app/backups and keep the last two weeks.';

    public function handle(BackupService $backups): int
    {
        try {
            $this->info('Saved '.$backups->create());

            return self::SUCCESS;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
