<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Settings → System Power. The web app side uses Laravel's maintenance mode
 * (so it can be turned back on from the browser); the server side runs the
 * three commands /etc/sudoers.d/lawatkape-power allows www-data, and nothing
 * else. Every action is logged before it happens.
 */
class SystemPowerService
{
    public const SUDOERS_FILE = '/etc/sudoers.d/lawatkape-power';

    private const COMMANDS = [
        'reload' => '/usr/bin/systemctl reload php8.2-fpm',
        'reboot' => '/usr/bin/systemctl reboot',
        'poweroff' => '/usr/bin/systemctl poweroff',
    ];

    /** Whether the one-time sudoers setup is in place (sudo -n -l lists the commands). */
    public function serverControlReady(): bool
    {
        $result = Process::run(['sudo', '-n', '-l']);

        return $result->successful() && Str::contains($result->output(), self::COMMANDS['reboot']);
    }

    public function restartApp(User $by): void
    {
        ActivityLog::record('app_restarted', $by);
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');
        Artisan::call('queue:restart');
        if ($this->serverControlReady()) {
            $this->later('reload');
        }
    }

    /** Returns the secret that lets the person who shut it down keep using it. */
    public function shutDownApp(User $by): string
    {
        $secret = Str::random(32);
        ActivityLog::record('app_shut_down', $by);
        Artisan::call('down', ['--secret' => $secret, '--render' => 'errors.503', '--retry' => 60]);

        return $secret;
    }

    public function startApp(User $by): void
    {
        Artisan::call('up');
        ActivityLog::record('app_started', $by);
    }

    public function restartServer(User $by): void
    {
        ActivityLog::record('server_restarting', $by);
        $this->later('reboot');
    }

    public function shutDownServer(User $by): void
    {
        ActivityLog::record('server_shutting_down', $by);
        $this->later('poweroff');
    }

    /** A few seconds later, detached, so the page can answer before PHP or the machine goes away. */
    private function later(string $command): void
    {
        Process::run('setsid nohup bash -c '.escapeshellarg('sleep 3; sudo -n '.self::COMMANDS[$command]).' >/dev/null 2>&1 &');
    }
}
