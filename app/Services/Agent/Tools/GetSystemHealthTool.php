<?php

namespace App\Services\Agent\Tools;

use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * super_admin only. Host and platform vitals for the machine this app runs on —
 * the same figures the System Control dashboard shows, so the assistant can
 * answer "is anything wrong" without the owner going and reading the page.
 *
 * Read-only, and deliberately reports no paths, credentials or versions: the
 * question this answers is "is it healthy", not "what is it built from".
 */
class GetSystemHealthTool implements AgentTool
{
    public function name(): string
    {
        return 'getSystemHealth';
    }

    public function description(): string
    {
        return 'Get the health of the server running this system: CPU load, memory, disk usage, CPU temperature, database connectivity and cache status. Use for questions like "is the system healthy", "is the server under load", or "is anything wrong". Read-only.';
    }

    public function parametersSchema(): array
    {
        return ['type' => 'object', 'properties' => [], 'required' => []];
    }

    public function permissionTier(): string
    {
        return 'auto';
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        $health = Cache::get('system_health', []);

        // The dashboard caches this every 30s. Rather than duplicate the
        // collection logic here (and risk the two drifting), fall back to the
        // cheap parts and say so if the cache is cold.
        $cpuLoad = $health['cpuLoad'] ?? (function_exists('sys_getloadavg') ? round(sys_getloadavg()[0] * 10) : null);
        $memory = $health['memoryUsage'] ?? null;
        $disk = $health['diskUsage'] ?? null;
        $temp = $health['cpuTemp'] ?? null;

        $dbUp = true;
        try {
            DB::select('select 1');
        } catch (\Throwable $e) {
            $dbUp = false;
        }

        $concerns = [];
        if ($disk !== null && $disk > 85) {
            $concerns[] = "Disk is {$disk}% full";
        }
        if ($memory !== null && $memory > 90) {
            $concerns[] = "Memory is {$memory}% used";
        }
        if ($temp !== null && $temp > 80) {
            $concerns[] = "CPU is running at {$temp}°C";
        }
        if (! $dbUp) {
            $concerns[] = 'The database is not responding';
        }

        $summary = empty($concerns)
            ? 'Server health looks normal.'
            : 'Attention needed: '.implode('; ', $concerns).'.';

        return ToolResult::ok($summary, [
            'cpu_load_percent' => $cpuLoad,
            'memory_used_percent' => $memory,
            'disk_used_percent' => $disk,
            'cpu_temp_celsius' => $temp,
            'database_responding' => $dbUp,
            'concerns' => $concerns,
        ]);
    }
}
