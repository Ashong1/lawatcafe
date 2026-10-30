<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Notifications\SystemAlert;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;

/**
 * Remote access: a Cloudflare Tunnel (systemd lawatkape-remote) that makes
 * this server reachable at a public HTTPS address without opening a router
 * port. Off by default; stays on until someone turns it off (owner, Oct 30).
 * Every switch is logged and the other owners are told.
 * www-data may only enable/disable that one service (/etc/sudoers.d/lawatkape-remote).
 */
class RemoteAccessService
{
    public const SERVICE = 'lawatkape-remote';

    public function url(): string
    {
        return config('services.remote_access.url');
    }

    public function isOn(): bool
    {
        return trim(Process::run(['/usr/bin/systemctl', 'is-active', self::SERVICE])->output()) === 'active';
    }

    /** @return string|null why it couldn't start, or null when it did */
    public function turnOn(User $by): ?string
    {
        // enable --now: it comes back after a server restart too, until someone turns it off.
        if (! $this->systemctl('enable')) {
            return 'The server refused to start remote access. The one-time setup step may be missing (see docs/OPERATIONS.md).';
        }
        ActivityLog::record('remote_access_on', $by, $this->url());
        Notification::send(User::active()->whereIn('role', ['admin', 'super_admin'])->whereKeyNot($by->id)->get(), new SystemAlert(
            'Remote access turned on',
            "{$by->name} turned on remote access: {$this->url()}. It stays on until someone turns it off.",
            'globe',
            route('security')
        ));

        return null;
    }

    public function turnOff(User $by): void
    {
        $this->systemctl('disable');
        ActivityLog::record('remote_access_off', $by);
    }

    private function systemctl(string $verb): bool
    {
        return Process::run(['sudo', '-n', '/usr/bin/systemctl', $verb, '--now', self::SERVICE])->successful();
    }
}
