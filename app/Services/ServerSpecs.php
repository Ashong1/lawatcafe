<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * The server's real specs for the Application Host card (owner, Oct 30), read
 * from the machine. This app runs in a Proxmox container, so these are what
 * the container is given, and the card says so.
 */
class ServerSpecs
{
    /** @return array{processor: string, cores: int, memory: string, disk: string, system: string, runs_on: string, uptime: string, software: string} */
    public function read(): array
    {
        $specs = Cache::remember('server_specs', 3600, fn () => [
            'processor' => $this->processor(),
            'cores' => $this->cores(),
            'memory' => $this->gb($this->memoryKb() * 1024).' GB',
            'disk' => $this->gb((float) @disk_total_space('/')).' GB',
            'system' => $this->system(),
            'runs_on' => $this->runsOn(),
            'software' => 'PHP '.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.' · Laravel '.app()->version(),
        ]);

        // Uptime moves; never cached.
        return $specs + ['uptime' => $this->uptime()];
    }

    private function processor(): string
    {
        preg_match('/^model name\s*:\s*(.+)$/m', (string) @file_get_contents('/proc/cpuinfo'), $m);
        $name = trim($m[1] ?? php_uname('m'));

        // "Intel(R) Core(TM) i5-7500T CPU @ 2.70GHz" → "Intel Core i5-7500T @ 2.70GHz"
        return trim(preg_replace(['/\((R|TM)\)/i', '/\s+CPU\s+@/', '/\s{2,}/'], ['', ' @', ' '], $name));
    }

    private function cores(): int
    {
        return max(1, preg_match_all('/^processor\s*:/m', (string) @file_get_contents('/proc/cpuinfo')));
    }

    private function memoryKb(): float
    {
        preg_match('/^MemTotal:\s*(\d+)/m', (string) @file_get_contents('/proc/meminfo'), $m);

        return (float) ($m[1] ?? 0);
    }

    private function system(): string
    {
        preg_match('/^PRETTY_NAME="?([^"\n]+)/m', (string) @file_get_contents('/etc/os-release'), $m);

        return ($m[1] ?? PHP_OS_FAMILY).' · kernel '.php_uname('r');
    }

    private function runsOn(): string
    {
        $proxmox = str_contains(php_uname('r'), '-pve');
        $virt = trim((string) @shell_exec('systemd-detect-virt 2>/dev/null'));

        return match (true) {
            $virt === 'lxc' => $proxmox ? 'Container (LXC) on a Proxmox server' : 'Container (LXC)',
            $virt !== '' && $virt !== 'none' => ($proxmox ? 'Virtual machine on a Proxmox server' : 'Virtual machine').' ('.$virt.')',
            default => 'Its own machine',
        };
    }

    private function uptime(): string
    {
        $seconds = (int) (float) explode(' ', (string) @file_get_contents('/proc/uptime'))[0];
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $days ? "{$days}d {$hours}h" : ($hours ? "{$hours}h {$minutes}m" : "{$minutes}m");
    }

    private function gb(float $bytes): string
    {
        $gb = $bytes / 1024 ** 3;

        return $gb >= 10 ? (string) round($gb) : number_format($gb, 1);
    }
}
