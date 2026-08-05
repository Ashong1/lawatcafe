<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;
use Illuminate\Console\Command;

/**
 * Inspect — and optionally build — the OPNsense traffic shaping chain.
 *
 * Exists because the failure this fixes was invisible from inside the app:
 * the admin page happily showed "2 Mbps free tier" while OPNsense held no
 * pipes, no rules and not even the tier aliases. `--dry-run` (the default)
 * reports what is actually on the gateway without writing anything.
 */
class ProvisionTrafficShaping extends Command
{
    protected $signature = 'shaper:provision {--apply : Write the missing pipes, aliases and rules to OPNsense}';

    protected $description = 'Report (or build) the OPNsense pipes, aliases and shaper rules that enforce the bandwidth tiers';

    public function handle(OpnSenseService $opnsense, TrafficShapingService $shaping): int
    {
        $limits = [
            'bw_free_down' => Setting::get('bw_free_down', '2'),
            'bw_free_up' => Setting::get('bw_free_up', '1'),
            'bw_premium_down' => Setting::get('bw_premium_down', '10'),
            'bw_premium_up' => Setting::get('bw_premium_up', '5'),
        ];

        $this->line('Configured caps:');
        foreach ($limits as $key => $value) {
            $this->line("  {$key} = {$value} Mbit");
        }

        $config = $opnsense->readShaperConfig();
        $rows = [];

        foreach (TrafficShapingService::TIERS as $tier) {
            $alias = $opnsense->tierAliasName($tier);
            $aliasExists = $this->aliasExists($opnsense, $alias);
            $rows[] = ["alias {$alias}", $aliasExists ? 'present' : 'MISSING'];

            foreach (TrafficShapingService::DIRECTIONS as $direction) {
                $name = $opnsense->shaperObjectName($tier, $direction);
                $rows[] = ["pipe {$name}", $config['pipes'][$name] ?? 'MISSING'];
                $rows[] = ["rule {$name}", $config['rules'][$name] ?? 'MISSING'];
            }
        }

        $this->newLine();
        $this->table(['Object', 'State'], $rows);

        if (! $this->option('apply')) {
            $this->newLine();
            $this->info('Dry run — nothing was written. Re-run with --apply to provision.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('Writing to OPNsense…');

        if (! $shaping->applyLimits($limits, $opnsense)) {
            $this->error('Provisioning failed. See storage/logs/laravel.log for the OPNsense response.');

            return self::FAILURE;
        }

        $this->info('Traffic shaping provisioned and applied.');

        return self::SUCCESS;
    }

    private function aliasExists(OpnSenseService $opnsense, string $name): bool
    {
        foreach ($opnsense->listAliases() as $alias) {
            if (($alias['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
}
