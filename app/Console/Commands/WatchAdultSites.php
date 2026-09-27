<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Notifications\SystemAlert;
use App\Services\AdultSiteDetector;
use App\Services\PiholeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Tells admins when a guest looks up a probable adult site.
 *
 * Reads Pi-hole's query log rather than anything in this app, because DNS is
 * the one place every guest lookup passes through. Reports both outcomes:
 * a blocked attempt (the block list is doing its job, but someone tried) and
 * an allowed one (a site the list does not cover yet — the alert links to
 * Site Blocking so it can be added). Only sees lookups that actually reach
 * Pi-hole; a device resolving through its own DNS or a VPN is invisible here.
 */
class WatchAdultSites extends Command
{
    protected $signature = 'network:watch-adult-sites';

    protected $description = 'Notify admins when a guest device looks up a probable adult site (via the Pi-hole query log).';

    public const CURSOR_KEY = 'adult_watch.cursor';

    /** One page visit fires a burst of lookups; one alert per device per site per this long. */
    public const COOLDOWN_SECONDS = 1800;

    /** Pi-hole v6 statuses that mean the lookup was refused. */
    private const BLOCKED_STATUSES = [
        'GRAVITY', 'REGEX', 'DENYLIST', 'GRAVITY_CNAME', 'REGEX_CNAME', 'DENYLIST_CNAME',
        'EXTERNAL_BLOCKED_IP', 'EXTERNAL_BLOCKED_NULL', 'EXTERNAL_BLOCKED_NXRA', 'SPECIAL_DOMAIN',
    ];

    public function handle(PiholeService $pihole, AdultSiteDetector $detector): int
    {
        $now = time();
        // First run (or a cleared cache) looks back two minutes rather than
        // replaying the whole log into a flood of stale alerts.
        $cursor = (int) Cache::get(self::CURSOR_KEY, $now - 120);

        $queries = $pihole->queriesSince($cursor);
        if ($queries === []) {
            return self::SUCCESS;
        }

        $notGuests = $this->nonGuestIps();
        $hits = [];

        foreach ($queries as $q) {
            if (in_array($q['client'], $notGuests, true) || ! $detector->isAdult($q['domain'])) {
                continue;
            }

            $site = $detector->siteFor($q['domain']);
            $key = $q['client'].'|'.$site;
            $blocked = in_array($q['status'], self::BLOCKED_STATUSES, true);

            // Any allowed lookup for the site means it got through, even if
            // some of its sub-domains were blocked.
            $hits[$key] = [
                'client' => $q['client'],
                'site' => $site,
                'allowed' => ($hits[$key]['allowed'] ?? false) || ! $blocked,
            ];
        }

        Cache::forever(self::CURSOR_KEY, max(array_column($queries, 'time')));

        $admins = null;
        foreach ($hits as $key => $hit) {
            if (! Cache::add('adult_watch.seen.'.md5($key), true, self::COOLDOWN_SECONDS)) {
                continue;
            }

            $admins ??= User::whereIn('role', ['admin', 'super_admin'])->get();
            Notification::send($admins, $this->alertFor($hit));
            $this->line(sprintf('%s -> %s (%s)', $hit['client'], $hit['site'], $hit['allowed'] ? 'allowed' : 'blocked'));
        }

        return self::SUCCESS;
    }

    private function alertFor(array $hit): SystemAlert
    {
        $voucher = Voucher::where('ip_address', $hit['client'])->whereNotNull('used_at')->latest('used_at')->first();
        $who = $voucher ? "Guest on voucher {$voucher->code} ({$hit['client']})" : "Device {$hit['client']}";

        return $hit['allowed']
            ? new SystemAlert(
                'Adult site opened — not blocked',
                "{$who} opened {$hit['site']}, which is not on the block list. Tap to block it.",
                'alert-triangle',
                route('network.site-blocking', ['domain' => $hit['site']])
            )
            : new SystemAlert(
                'Blocked adult site attempt',
                "{$who} tried to open {$hit['site']}. Pi-hole blocked it.",
                'shield-check',
                route('network.site-blocking')
            );
    }

    /** Infrastructure, VIP and loopback addresses — anything that isn't a customer's device. */
    private function nonGuestIps(): array
    {
        $vip = array_map('trim', explode(',', (string) Setting::get('network_vip_ips', '')));

        return array_values(array_filter(array_merge(
            Setting::infrastructureIps(),
            config('services.opnsense.protected_ips', []),
            $vip,
            ['127.0.0.1', '::1'],
        )));
    }
}
