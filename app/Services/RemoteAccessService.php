<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Setting;
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

    public const STAFF_MESSAGE = 'From outside the shop only the owner and admins can sign in. Staff can sign in at the shop.';

    /** Setting that lets staff accounts in through the remote link too. Off by default; only the super admin changes it. */
    public const STAFF_SETTING = 'remote_access_staff';

    public static function staffAllowed(): bool
    {
        return Setting::get(self::STAFF_SETTING, '0') === '1';
    }

    /** Whether this account may use the app on this request: always inside the shop; through the remote link, admins and up, plus staff when allowed. */
    public static function allows(User $user, \Illuminate\Http\Request $request): bool
    {
        return $user->isAdminOrAbove() || self::staffAllowed() || ! self::isRemoteRequest($request);
    }

    public function setStaffAllowed(bool $allowed, User $by): void
    {
        Setting::set(self::STAFF_SETTING, $allowed ? '1' : '0');
        ActivityLog::record($allowed ? 'remote_access_staff_on' : 'remote_access_staff_off', $by);
    }

    /** Whether this request came in through the remote link (the tunnel only serves that name). */
    public static function isRemoteRequest(\Illuminate\Http\Request $request): bool
    {
        return $request->getHost() === parse_url(config('services.remote_access.url'), PHP_URL_HOST);
    }

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
