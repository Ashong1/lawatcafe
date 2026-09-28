<?php

namespace App\Console\Commands;

use App\Models\NetworkHealthCheck;
use App\Models\PortalEvent;
use App\Models\User;
use App\Models\Voucher;
use App\Notifications\SystemAlert;
use App\Services\NetworkHealthService;
use App\Services\OpnSenseService;
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

    public const SESSIONS_KEY = 'network_health_guest_sessions';

    public function handle(NetworkHealthService $health, OpnSenseService $opnsense): int
    {
        $result = $health->run();
        $health->record($result);

        $this->notifyChanges($result['checks']);
        $this->trackDroppedSessions($opnsense);

        NetworkHealthCheck::where('checked_at', '<', now()->subDays(self::KEEP_DAYS))->delete();
        PortalEvent::where('created_at', '<', now()->subDays(PortalEvent::KEEP_DAYS))->delete();

        foreach ($result['checks'] as $check) {
            $this->line(str_pad(strtoupper($check['status']), 8).$check['label'].': '.$check['summary']);
        }

        return self::SUCCESS;
    }

    /**
     * A guest session that vanished since last minute while its code still
     * had time, and nobody disconnected it: a drop. Recorded with how long it
     * had been idle, to find the cause of guests being cut off early (the
     * keepalive ping is only a workaround).
     */
    private function trackDroppedSessions(OpnSenseService $opnsense): void
    {
        $now = [];
        foreach ($opnsense->listSessions() as $session) {
            $ip = str_replace('/32', '', (string) ($session['ipAddress'] ?? ''));
            if ($ip !== '' && ! $opnsense->isProtectedIp($ip)) {
                $now[$ip] = ['last' => (int) ($session['last_accessed'] ?? 0), 'start' => (int) ($session['startTime'] ?? 0)];
            }
        }

        $before = Cache::get(self::SESSIONS_KEY, []);
        Cache::forever(self::SESSIONS_KEY, $now);

        foreach (array_diff_key($before, $now) as $ip => $seen) {
            $voucher = Voucher::where('ip_address', $ip)->whereNotNull('used_at')->latest('used_at')->first();
            if (! $voucher || $voucher->disconnected_at) {
                continue;
            }
            $end = $voucher->used_at->copy()->addMinutes($voucher->duration_minutes);
            if ($end->lte(now()->addMinute())) {
                continue; // its time ran out: an expiry, not a drop
            }

            PortalEvent::record(PortalEvent::DROPPED, $ip, $voucher->code, [
                'idle_minutes' => $seen['last'] ? (int) round((time() - $seen['last']) / 60) : null,
                'session_minutes' => $seen['start'] ? (int) round((time() - $seen['start']) / 60) : null,
                'minutes_left' => (int) now()->diffInMinutes($end),
            ]);
        }
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
