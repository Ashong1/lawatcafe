<?php

namespace App\Services;

use App\Models\BandwidthSample;
use App\Models\NetworkHealthCheck;
use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * The one definition of "is the shop's network healthy?".
 *
 * Every consumer reads the same checks: the AI's checkNetworkHealth tool
 * (on demand), the network:health job (every minute, which stores history and
 * alerts on changes), the network status block in the AI's prompt (from the
 * cached latest result, so chat never waits on a ping), agent:analyze's
 * network signals, and the Network > Health page.
 *
 * Each check returns a status (ok / warn / fail / unknown) and a one-line
 * summary written for a person, so the page, the alerts and the AI all say
 * the same thing.
 */
class NetworkHealthService
{
    public const LATEST_KEY = 'network_health_latest';

    public const STATUSES = ['ok', 'warn', 'fail', 'unknown'];

    /** Public resolvers pinged to measure the shop's internet link. */
    public const INTERNET_TARGETS = ['1.1.1.1', '8.8.8.8'];

    /** A name Pi-hole must be able to resolve for DNS to count as working. */
    private const DNS_PROBE = 'google.com';

    /** @var callable(array<string, int>): array<string, array{loss: float, avg: ?float}> host => ping count */
    private $pinger;

    /** @var callable(string, string): ?string (dns server, name) => first answer */
    private $resolver;

    public function __construct(
        protected OpnSenseService $opnsense,
        protected PiholeService $pihole,
        protected GuestSessionService $guests,
        protected GhostDeviceDetectionService $ghosts,
        protected LinkCapacityLearner $capacity,
        ?callable $pinger = null,
        ?callable $resolver = null,
    ) {
        $this->pinger = $pinger ?? [$this, 'pingInParallel'];
        $this->resolver = $resolver ?? function (string $server, string $name): ?string {
            $out = Process::timeout(6)->run(['dig', '+short', '+time=2', '+tries=1', "@{$server}", $name, 'A'])->output();

            return trim(strtok($out, "\n") ?: '') ?: null;
        };
    }

    /**
     * Run every check now.
     *
     * @return array{checked_at: string, overall: string, checks: array<string, array{label: string, status: string, summary: string, details: array}>}
     */
    public function run(): array
    {
        $infrastructure = $this->infrastructureTargets();
        $pings = ($this->pinger)(array_fill_keys(self::INTERNET_TARGETS, 4) + array_fill_keys(array_keys($infrastructure), 2));

        $checks = [
            'internet' => $this->checkInternet($pings),
            'firewall' => $this->checkFirewall(),
            'dns' => $this->checkDns(),
            'dhcp' => $this->checkDhcp(),
            'portal' => $this->checkPortal(),
            'infrastructure' => $this->checkInfrastructure($infrastructure, $pings),
            'bandwidth' => $this->checkBandwidth(),
            'unknown_devices' => $this->checkUnknownDevices(),
        ];

        return [
            'checked_at' => now()->toIso8601String(),
            'overall' => $this->overall($checks),
            'checks' => $checks,
        ];
    }

    /** Store a run in history and as the cached latest result. */
    public function record(array $result): NetworkHealthCheck
    {
        $c = $result['checks'];
        $row = NetworkHealthCheck::create([
            'checked_at' => $result['checked_at'],
            'overall' => $result['overall'],
            'internet_latency_ms' => $c['internet']['details']['latency_ms'] ?? null,
            'internet_loss_pct' => $c['internet']['details']['loss_pct'] ?? null,
            'dns_ok' => isset($c['dns']) ? $c['dns']['status'] !== 'fail' : null,
            'dhcp_used' => $c['dhcp']['details']['used'] ?? null,
            'dhcp_size' => $c['dhcp']['details']['size'] ?? null,
            'guests_online' => $c['portal']['details']['guests_online'] ?? null,
            'infrastructure_down' => count($c['infrastructure']['details']['down'] ?? []),
            'results' => $result,
        ]);

        Cache::put(self::LATEST_KEY, $result, now()->addMinutes(10));

        return $row;
    }

    /** The most recent result, without running anything (null if none yet). */
    public function latest(): ?array
    {
        return Cache::get(self::LATEST_KEY) ?? NetworkHealthCheck::latest('checked_at')->first()?->results;
    }

    /** A few lines for an AI prompt: overall plus every check's summary. */
    public function promptSummary(): string
    {
        $latest = $this->latest();
        if (! $latest) {
            return "- Not checked yet — use checkNetworkHealth.\n";
        }

        $age = now()->diffForHumans(Carbon::parse($latest['checked_at']), CarbonInterface::DIFF_ABSOLUTE);
        $lines = ['- Overall: '.strtoupper($latest['overall'])." (checked {$age} ago)"];
        foreach ($latest['checks'] as $check) {
            $lines[] = "- {$check['label']}: ".strtoupper($check['status']).' — '.$check['summary'];
        }

        return implode("\n", $lines)."\n";
    }

    private function checkInternet(array $pings): array
    {
        $results = array_intersect_key($pings, array_flip(self::INTERNET_TARGETS));
        $reachable = array_filter($results, fn ($r) => $r['loss'] < 100);

        if ($results === [] || $reachable === []) {
            return $this->check('Internet link', 'fail', 'No internet — the public DNS servers do not answer pings.', ['targets' => $results]);
        }

        $latency = round(array_sum(array_column($reachable, 'avg')) / count($reachable), 1);
        $loss = round(array_sum(array_column($results, 'loss')) / count($results), 1);
        $status = $loss >= 20 || $latency > 150 ? 'warn' : 'ok';
        $summary = ($status === 'ok' ? 'Internet OK' : 'Internet is slow or unstable')
            ." — {$latency} ms".($loss > 0 ? ", {$loss}% packet loss" : ', no packet loss').'.';

        return $this->check('Internet link', $status, $summary, ['latency_ms' => $latency, 'loss_pct' => $loss, 'targets' => $results]);
    }

    private function checkFirewall(): array
    {
        $gateways = $this->opnsense->getGatewayStatus()['gateways'] ?? [];
        if ($gateways === []) {
            return $this->check('Firewall (OPNsense)', 'fail', "Can't reach the firewall — guest logins and blocking may not work.", []);
        }

        $down = array_values(array_filter($gateways, fn ($g) => ! in_array($g['status'], ['online', 'none'], true)));

        return $down === []
            ? $this->check('Firewall (OPNsense)', 'ok', 'Firewall reachable; '.count($gateways).' internet gateway(s) online.', ['gateways' => $gateways])
            : $this->check('Firewall (OPNsense)', 'fail', 'Gateway down: '.implode(', ', array_column($down, 'name')).'.', ['gateways' => $gateways]);
    }

    private function checkDns(): array
    {
        $server = parse_url((string) config('services.pihole.url'), PHP_URL_HOST) ?: '192.168.2.4';
        $answer = ($this->resolver)($server, self::DNS_PROBE);
        $stats = $this->pihole->summary();

        if (! $answer) {
            return $this->check('DNS (Pi-hole)', 'fail', 'Pi-hole is not answering DNS — guests will see "no internet" even when the link is up.', ['server' => $server, 'stats' => $stats]);
        }
        if ($stats === null) {
            return $this->check('DNS (Pi-hole)', 'warn', "DNS works, but Pi-hole's admin API can't be reached, so blocking can't be confirmed.", ['server' => $server]);
        }
        if (! $stats['blocking']) {
            return $this->check('DNS (Pi-hole)', 'warn', 'DNS works, but Pi-hole blocking is switched OFF — blocked sites are reachable.', ['server' => $server, 'stats' => $stats]);
        }

        return $this->check('DNS (Pi-hole)', 'ok', "DNS working; blocking on — {$stats['blocked']} of {$stats['queries']} lookups blocked today ({$stats['percent_blocked']}%).", ['server' => $server, 'stats' => $stats]);
    }

    private function checkDhcp(): array
    {
        $pools = $this->opnsense->getDhcpPools();
        if ($pools === []) {
            return $this->check('DHCP address pool', 'unknown', "Couldn't read the DHCP pool from the firewall.", []);
        }

        $size = array_sum(array_map(fn ($p) => $p['end'] - $p['start'] + 1, $pools));
        $used = collect($this->opnsense->getDhcpLeases())
            ->filter(fn ($l) => (string) ($l['state'] ?? '0') === '0')
            ->filter(fn ($l) => ($ip = ip2long((string) ($l['address'] ?? ''))) !== false
                && collect($pools)->contains(fn ($p) => $ip >= $p['start'] && $ip <= $p['end']))
            ->count();
        $pct = $size > 0 ? (int) round($used / $size * 100) : 0;
        $status = $pct >= 95 ? 'fail' : ($pct >= 80 ? 'warn' : 'ok');
        $summary = "{$pct}% of guest addresses in use ({$used} of {$size})"
            .($status === 'fail' ? ' — new devices may not get an address.' : ($status === 'warn' ? ' — getting full.' : '.'));

        return $this->check('DHCP address pool', $status, $summary, ['used' => $used, 'size' => $size, 'percent' => $pct, 'ranges' => array_column($pools, 'label')]);
    }

    private function checkPortal(): array
    {
        $host = (string) config('services.portal.host', 'wifi.lawatkape.lab');
        try {
            $ok = Http::withHeaders(['Host' => $host])->timeout(5)->get('http://127.0.0.1/portal')->successful();
        } catch (\Exception $e) {
            $ok = false;
        }

        try {
            $online = $this->guests->activeGuestCount();
        } catch (\Throwable $e) {
            $online = null;
        }

        return $ok
            ? $this->check('Wi-Fi login page', 'ok', 'Login page up'.($online !== null ? "; {$online} guest(s) online." : '.'), ['host' => $host, 'guests_online' => $online])
            : $this->check('Wi-Fi login page', 'fail', "The Wi-Fi login page isn't loading — new guests can't sign in.", ['host' => $host, 'guests_online' => $online]);
    }

    private function checkInfrastructure(array $targets, array $pings): array
    {
        if ($targets === []) {
            return $this->check('Network equipment', 'unknown', 'No infrastructure addresses are configured.', []);
        }

        // Up if it answers a ping OR the firewall has a live ARP entry for it:
        // many access points ignore ICMP, and that is not an outage.
        $seen = collect($this->opnsense->getArpTable())
            ->filter(fn ($a) => empty($a['expired']))
            ->pluck('ip')->all();

        $down = [];
        foreach ($targets as $ip => $label) {
            if (($pings[$ip]['loss'] ?? 100) >= 100 && ! in_array($ip, $seen, true)) {
                $down[] = ['ip' => $ip, 'label' => $label];
            }
        }

        return $down === []
            ? $this->check('Network equipment', 'ok', 'All '.count($targets).' devices respond.', ['down' => [], 'devices' => $targets])
            : $this->check('Network equipment', 'warn', count($down).' device(s) not responding: '.implode(', ', array_map(fn ($d) => $d['label'] === $d['ip'] ? $d['ip'] : "{$d['label']} ({$d['ip']})", $down)).'.', ['down' => $down, 'devices' => $targets]);
    }

    private function checkBandwidth(): array
    {
        $sample = BandwidthSample::latest('sampled_at')->first();
        $estimate = $this->capacity->estimate();
        $top = $this->topUsers(3);

        if (! $sample) {
            return $this->check('Bandwidth', 'unknown', 'No traffic samples yet.', ['top_users' => $top]);
        }

        $down = (float) $sample->down_mbps;
        $capacity = $estimate['learned'] ? $estimate['down'] : null;
        $busy = $capacity && $down >= 0.9 * $capacity;
        $summary = "Using {$down} Mbps down / {$sample->up_mbps} up"
            .($capacity ? " of about {$capacity} Mbps" : ' (line speed still being learned)')
            .($busy ? ' — the line is nearly full.' : '.');

        return $this->check('Bandwidth', $busy ? 'warn' : 'ok', $summary, [
            'down_mbps' => $down, 'up_mbps' => (float) $sample->up_mbps,
            'sampled_at' => $sample->sampled_at?->toIso8601String(),
            'capacity' => $estimate, 'top_users' => $top,
        ]);
    }

    private function checkUnknownDevices(): array
    {
        try {
            $lanPrefix = $this->lanPrefix();
            $infra = Setting::infrastructureIps();
            $unknown = $this->ghosts->detect()
                ->filter(fn ($d) => $d['ip_address'] && str_starts_with($d['ip_address'], $lanPrefix) && ! in_array($d['ip_address'], $infra, true))
                ->values()->all();
        } catch (\Throwable $e) {
            return $this->check('Unknown devices', 'unknown', "Couldn't compare the network against the login system.", []);
        }

        // Joined but not signed in is normal — that's every guest at the login
        // page, and the firewall blocks them until they sign in. Only a BANNED
        // device back on the network is a warning.
        $banned = array_values(array_filter($unknown, fn ($d) => $d['is_banned']));
        $describe = fn (array $list) => implode(', ', array_map(fn ($d) => $d['ip_address'].($d['manufacturer'] ? " ({$d['manufacturer']})" : ''), array_slice($list, 0, 5)));

        if ($banned !== []) {
            return $this->check('Unknown devices', 'warn', count($banned).' BLOCKED device(s) are back on the network: '.$describe($banned).'.', ['devices' => $unknown, 'banned' => $banned]);
        }

        return $unknown === []
            ? $this->check('Unknown devices', 'ok', 'Every device on the network has signed in or is trusted.', ['devices' => [], 'banned' => []])
            : $this->check('Unknown devices', 'ok', count($unknown).' device(s) connected but not signed in yet (normal for guests at the login page): '.$describe($unknown).'.', ['devices' => $unknown, 'banned' => []]);
    }

    /**
     * Guests using the most data this session (cumulative bytes from the
     * firewall's session list), infrastructure excluded.
     *
     * @return array<int, array{ip: string, mb: float}>
     */
    public function topUsers(int $limit = 5): array
    {
        $infra = Setting::infrastructureIps();

        return collect($this->opnsense->listSessions())
            ->map(fn ($s) => ['ip' => str_replace('/32', '', (string) ($s['ipAddress'] ?? '')), 'bytes' => (int) ($s['bytes_in'] ?? 0) + (int) ($s['bytes_out'] ?? 0)])
            ->filter(fn ($s) => $s['ip'] !== '' && ! in_array($s['ip'], $infra, true))
            ->sortByDesc('bytes')->take($limit)
            ->map(fn ($s) => ['ip' => $s['ip'], 'mb' => round($s['bytes'] / 1048576, 1)])
            ->values()->all();
    }

    /** @return array<string, string> infrastructure ip => readable name */
    private function infrastructureTargets(): array
    {
        $known = (array) config('services.network.labels', []);
        $leases = collect($this->opnsense->getDhcpLeases())->keyBy('address');

        $targets = [];
        foreach (Setting::infrastructureIps() as $ip) {
            $lease = $leases->get($ip);
            $targets[$ip] = $known[$ip] ?? (($lease['hostname'] ?? '') ?: (($lease['mac_info'] ?? '') ?: $ip));
        }

        return $targets;
    }

    /** The LAN's /24 prefix, from the firewall's own LAN address. */
    private function lanPrefix(): string
    {
        $ip = (string) config('services.opnsense.ip', '192.168.2.251');

        return substr($ip, 0, strrpos($ip, '.') + 1);
    }

    private function overall(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        return in_array('fail', $statuses, true) ? 'fail' : (in_array('warn', $statuses, true) ? 'warn' : 'ok');
    }

    private function check(string $label, string $status, string $summary, array $details): array
    {
        return ['label' => $label, 'status' => $status, 'summary' => $summary, 'details' => $details];
    }

    /**
     * Ping every host at once — sequential pings to ~12 hosts would take
     * several seconds.
     *
     * @param  array<string, int>  $hosts  host => packet count
     * @return array<string, array{loss: float, avg: ?float}>
     */
    public function pingInParallel(array $hosts): array
    {
        if ($hosts === []) {
            return [];
        }

        try {
            $results = Process::pool(function (Pool $pool) use ($hosts) {
                foreach ($hosts as $host => $count) {
                    $pool->as($host)->timeout(10)->command(['ping', '-c', (string) $count, '-i', '0.2', '-W', '1', '-q', $host]);
                }
            })->start()->wait();
        } catch (\Throwable $e) {
            Log::warning('Network health: ping failed to run: '.$e->getMessage());

            return array_map(fn () => ['loss' => 100.0, 'avg' => null], $hosts);
        }

        $out = [];
        foreach (array_keys($hosts) as $host) {
            $text = isset($results[$host]) ? $results[$host]->output() : '';
            $loss = preg_match('/([\d.]+)% packet loss/', $text, $m) ? (float) $m[1] : 100.0;
            $avg = preg_match('#= [\d.]+/([\d.]+)/#', $text, $m) ? (float) $m[1] : null;
            $out[$host] = ['loss' => $loss, 'avg' => $avg];
        }

        return $out;
    }
}
