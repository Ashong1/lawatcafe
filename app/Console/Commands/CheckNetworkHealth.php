<?php

namespace App\Console\Commands;

use App\Models\NetworkHealthCheck;
use App\Models\User;
use App\Notifications\SystemAlert;
use App\Services\NetworkHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Runs the network health checks every minute, keeps a week of history for
 * the Health page, and tells admins when a check CHANGES state — once when it
 * goes wrong, once when it recovers, never every minute in between. No AI is
 * involved: an outage alert must not depend on the AI allowance.
 */
class CheckNetworkHealth extends Command
{
    protected $signature = 'network:health';

    protected $description = 'Check the shop network (internet, firewall, DNS, DHCP, login page, equipment) and alert on changes.';

    public const STATE_KEY = 'network_health_states';

    private const KEEP_DAYS = 7;

    public function handle(NetworkHealthService $health): int
    {
        $result = $health->run();
        $health->record($result);

        $this->notifyChanges($result['checks']);

        NetworkHealthCheck::where('checked_at', '<', now()->subDays(self::KEEP_DAYS))->delete();

        foreach ($result['checks'] as $check) {
            $this->line(str_pad(strtoupper($check['status']), 8).$check['label'].': '.$check['summary']);
        }

        return self::SUCCESS;
    }

    /** Alert on ok -> warn/fail and on recovery; 'unknown' never alerts. */
    private function notifyChanges(array $checks): void
    {
        $previous = Cache::get(self::STATE_KEY, []);
        $now = array_map(fn ($c) => $c['status'], $checks);
        Cache::forever(self::STATE_KEY, $now);

        if ($previous === []) {
            return; // first run: nothing to compare against
        }

        $messages = [];
        foreach ($checks as $key => $check) {
            $before = $previous[$key] ?? 'ok';
            $after = $check['status'];
            if ($before === $after || $after === 'unknown' || $before === 'unknown') {
                continue;
            }
            if (in_array($after, ['warn', 'fail'], true)) {
                $messages[] = [($after === 'fail' ? 'Network problem: ' : 'Network warning: ').$check['label'], $check['summary'], 'alert-triangle'];
            } elseif ($after === 'ok') {
                $messages[] = ['Back to normal: '.$check['label'], $check['summary'], 'check'];
            }
        }

        if ($messages === []) {
            return;
        }

        $admins = User::whereIn('role', ['admin', 'super_admin'])->get();
        foreach ($messages as [$title, $body, $icon]) {
            Notification::send($admins, new SystemAlert($title, $body, $icon, route('network.health')));
        }
    }
}
