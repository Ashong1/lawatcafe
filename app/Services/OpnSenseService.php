<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpnSenseService
{
    protected $baseUrl;

    protected $apiKey;

    protected $apiSecret;

    protected $zone;

    public function __construct()
    {
        $this->baseUrl = config('services.opnsense.url');
        $this->apiKey = config('services.opnsense.key');
        $this->apiSecret = config('services.opnsense.secret');
        // Prioritize database setting, fallback to config
        $this->zone = Setting::get('opnsense_zone', config('services.opnsense.zone', 0));
    }

    /**
     * Build the authenticated HTTP client used for every OPNsense call.
     * Centralizing this means the TLS verification tradeoff (see
     * services.opnsense.verify_tls) is applied consistently everywhere,
     * instead of each method deciding it individually.
     *
     * The default 1s/2s budget is deliberately tight: several call sites here
     * (getArpTable, listSessions, getGatewayStatus, getInterfaceStats) run
     * synchronously in a page's render/poll path and exceeding Chrome's
     * ~500ms paint-holding budget was previously causing a blank-page flash
     * on navigation (see .gemini/memory/LEARNINGS.md, 2026-07-28). Those
     * methods are all cached with a stale-value fallback on failure, so
     * timing out costs nothing worse than a few extra seconds of staleness.
     *
     * authorizeDevice() is the one call that can't take that tradeoff: it's a
     * one-shot write triggered directly by a customer redeeming a voucher,
     * with no cached fallback — a timeout there is a flatly rejected
     * voucher, not stale data. It passes its own longer budget explicitly
     * rather than sharing this default.
     */
    protected function client(?int $connectTimeout = null, ?int $timeoutSeconds = null)
    {
        return Http::withBasicAuth($this->apiKey, $this->apiSecret)
            ->withOptions(['verify' => config('services.opnsense.verify_tls', false)])
            ->connectTimeout($connectTimeout ?? 1)
            ->timeout($timeoutSeconds ?? 2);
    }

    /**
     * Authorize a device on the OPNsense Captive Portal.
     *
     * @param  string  $ip  The client IP address.
     * @param  string  $voucherCode  An identifier for the session (e.g. Voucher code).
     * @return bool
     */
    public function authorizeDevice($ip, $voucherCode = 'guest')
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            Log::warning('OPNsense: API credentials not configured.');

            return false;
        }

        if (empty(config('services.opnsense.guest_user')) || empty(config('services.opnsense.guest_pass'))) {
            Log::error("OPNsense: guest_user/guest_pass not configured — refusing to authorize {$ip} rather than fall back to a default credential.");

            return false;
        }

        try {
            // Use zone from session if available (captured from redirect), otherwise use config default
            $zone = session('zone', $this->zone);

            // Following the 'Direct Post' approach via the session/connect API
            // This endpoint is used to manually authorize an IP session in a specific zone.
            $url = "{$this->baseUrl}/api/captiveportal/session/connect/{$zone}/";

            Log::info('OPNsense Request URL: '.$url);
            Log::info('OPNsense API Key Length: '.strlen($this->apiKey));

            // Longer budget than the shared render-path default (see client()
            // docblock) — this redeems a customer's voucher with no fallback
            // on failure, so it's worth waiting out a slow-but-working router
            // rather than fast-failing a legitimate request.
            $response = $this->client(4, 8)->post($url, [
                'user' => config('services.opnsense.guest_user'),
                'password' => config('services.opnsense.guest_pass'),
                'ip' => $ip,
            ]);

            $data = $response->json();

            if ($response->successful()) {
                // If we get a sessionId or a successful state, the device is authorized
                if (isset($data['sessionId']) || (isset($data['clientState']) && in_array($data['clientState'], ['AUTHORIZED', 'CONNECTED', 'ALREADY_AUTHORIZED']))) {
                    Log::info("OPNsense: Successfully authorized IP {$ip} via session/connect. Session: ".($data['sessionId'] ?? 'N/A'));
                    $this->forgetSessionsCache();

                    return true;
                }
            }

            Log::error("OPNsense: Failed to authorize IP {$ip} via session/connect", [
                'status' => $response->status(),
                'response' => $data,
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('OPNsense: Exception during authorization: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Get the ARP table from OPNsense.
     *
     * Cached for a few seconds: this is called on nearly every captive-portal
     * page load (directly, and indirectly via resolveMacForIp()), so without
     * caching a burst of guest requests turns into a burst of OPNsense API
     * calls for data that's already good for a few seconds.
     *
     * @return array
     */
    public function getArpTable()
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return [];
        }

        return Cache::remember('opnsense_arp_table', 15, function () {
            try {
                $url = "{$this->baseUrl}/api/diagnostics/interface/getArp";

                $response = $this->client()->get($url);

                if ($response->successful()) {
                    return $response->json();
                }

                return Cache::get('opnsense_arp_table') ?? [];
            } catch (\Exception $e) {
                Log::error('OPNsense: Exception fetching ARP table: '.$e->getMessage());

                return Cache::get('opnsense_arp_table') ?? [];
            }
        });
    }

    /**
     * The Kea DHCPv4 dynamic pool ranges, as integer start/end pairs.
     *
     * An address inside one of these ranges belongs to whichever guest Kea
     * handed it to today and to somebody else tomorrow, so it can never
     * identify a fixed device. Treating one as permanent (infrastructure /
     * ignored) silently erases a real customer from the sessions page and the
     * dashboard counts the moment the lease rotates onto their phone — see
     * the .117 incident fixed in v1.0.0.78.
     *
     * Fails open: an unreachable OPNsense returns an empty list, which makes
     * every caller skip the check rather than block an admin from saving.
     *
     * @return array<int, array{start: int, end: int, label: string}>
     */
    public function getDhcpPools(): array
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return [];
        }

        return Cache::remember('opnsense_dhcp_pools', 600, function () {
            try {
                $response = $this->client()->get("{$this->baseUrl}/api/kea/dhcpv4/searchSubnet");

                if (! $response->successful()) {
                    return [];
                }

                $pools = [];

                foreach ($response->json('rows') ?? [] as $subnet) {
                    // One subnet's "pools" field holds newline- or
                    // comma-separated ranges, each either "start-end" or CIDR.
                    foreach (preg_split('/[\r\n,]+/', (string) ($subnet['pools'] ?? '')) as $pool) {
                        $pool = trim($pool);
                        if ($pool === '') {
                            continue;
                        }

                        $range = $this->parsePoolRange($pool);
                        if ($range) {
                            $pools[] = $range + ['label' => $pool];
                        }
                    }
                }

                return $pools;
            } catch (\Exception $e) {
                Log::error('OPNsense: Exception fetching DHCP pools: '.$e->getMessage());

                return [];
            }
        });
    }

    /**
     * The dynamic pool an address falls inside, or null if it is safely
     * outside every pool (a reservation, a manually-configured static, or
     * another subnet entirely).
     *
     * @return array{start: int, end: int, label: string}|null
     */
    public function dhcpPoolContaining(string $ip): ?array
    {
        $long = ip2long(trim($ip));

        if ($long === false) {
            return null;
        }

        foreach ($this->getDhcpPools() as $pool) {
            if ($long >= $pool['start'] && $long <= $pool['end']) {
                return $pool;
            }
        }

        return null;
    }

    /**
     * @return array{start: int, end: int}|null
     */
    protected function parsePoolRange(string $pool): ?array
    {
        if (str_contains($pool, '-')) {
            [$start, $end] = array_map('trim', explode('-', $pool, 2));
            $start = ip2long($start);
            $end = ip2long($end);

            return ($start !== false && $end !== false && $end >= $start)
                ? ['start' => $start, 'end' => $end]
                : null;
        }

        if (str_contains($pool, '/')) {
            [$base, $bits] = explode('/', $pool, 2);
            $base = ip2long(trim($base));
            $bits = (int) $bits;

            if ($base === false || $bits < 0 || $bits > 32) {
                return null;
            }

            $mask = $bits === 0 ? 0 : (-1 << (32 - $bits)) & 0xFFFFFFFF;

            return ['start' => $base & $mask, 'end' => ($base & $mask) | (~$mask & 0xFFFFFFFF)];
        }

        $single = ip2long($pool);

        return $single !== false ? ['start' => $single, 'end' => $single] : null;
    }

    /**
     * Get current Kea DHCPv4 leases from OPNsense — the authoritative
     * source for a device's self-reported hostname (whatever the client
     * sent in its DHCP request), which in practice is far more often
     * populated than the ARP diagnostic endpoint's own 'hostname' field
     * (ARP is just a Layer-2/3 lookup table; it has no naming data of its
     * own beyond whatever OPNsense happens to enrich it with). Some devices
     * genuinely send no hostname at all — that's a real client limitation,
     * not something either source can paper over.
     *
     * Cached like getArpTable() — hit on every sessions-page load.
     *
     * @return array
     */
    public function getDhcpLeases()
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return [];
        }

        return Cache::remember('opnsense_dhcp_leases', 15, function () {
            try {
                $response = $this->client()->get("{$this->baseUrl}/api/kea/leases4/search");

                if ($response->successful()) {
                    return $response->json('rows') ?? [];
                }

                return Cache::get('opnsense_dhcp_leases') ?? [];
            } catch (\Exception $e) {
                Log::error('OPNsense: Exception fetching DHCP leases: '.$e->getMessage());

                return Cache::get('opnsense_dhcp_leases') ?? [];
            }
        });
    }

    /**
     * Get the list of active sessions from OPNsense.
     *
     * Cached for a few seconds per zone — hit on every portal page load and
     * repeatedly within the same admin sessions-page request (session list +
     * ARP lookups), so a short TTL avoids redundant round-trips without
     * meaningfully staling the "who's connected" view.
     *
     * @return array
     */
    public function listSessions()
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return [];
        }

        $zone = session('zone', $this->zone);

        return Cache::remember("opnsense_sessions_list_{$zone}", 15, function () use ($zone) {
            try {
                $url = "{$this->baseUrl}/api/captiveportal/session/list/{$zone}/";

                $response = $this->client()->get($url);

                if ($response->successful()) {
                    $data = $response->json();
                    $rows = $data['rows'] ?? $data['sessions'] ?? $data;

                    if (! is_array($rows)) {
                        return [];
                    }

                    return array_map(function ($session) {
                        // Normalize byte keys
                        if (isset($session['bytes_in']) && ! isset($session['bytes_received'])) {
                            $session['bytes_received'] = $session['bytes_in'];
                        }
                        if (isset($session['bytes_out']) && ! isset($session['bytes_sent'])) {
                            $session['bytes_sent'] = $session['bytes_out'];
                        }

                        return $session;
                    }, $rows);
                }

                return Cache::get("opnsense_sessions_list_{$zone}") ?? [];
            } catch (\Exception $e) {
                Log::error('OPNsense: Exception fetching sessions: '.$e->getMessage());

                return Cache::get("opnsense_sessions_list_{$zone}") ?? [];
            }
        });
    }

    /**
     * Drop the cached session list for the current zone — call after any
     * action that changes who's connected (authorize/disconnect) so the
     * admin sessions page and the next portal check reflect it immediately
     * instead of waiting out the cache TTL.
     */
    protected function forgetSessionsCache(): void
    {
        Cache::forget('opnsense_sessions_list_'.session('zone', $this->zone));
    }

    /**
     * Resolve the MAC address OPNsense's own ARP table has on file for an IP.
     *
     * This is the authoritative source for "which device is this IP" — unlike
     * a clientMac value handed to us by the guest's browser (redirect query
     * string, form field, etc.), the ARP table reflects what the gateway
     * itself observed on the wire and cannot be forged by the client.
     *
     * @return string|null Uppercase MAC address, or null if unresolvable.
     */
    public function resolveMacForIp(string $ip): ?string
    {
        $arp = $this->getArpTable();
        $entries = $arp['arp'] ?? $arp;

        if (! is_array($entries)) {
            return null;
        }

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $entryIp = $entry['ip'] ?? $entry['ipaddress'] ?? $entry['ip_address'] ?? null;
            if ($entryIp !== $ip) {
                continue;
            }

            $mac = $entry['mac'] ?? $entry['macaddr'] ?? $entry['mac_addr'] ?? null;

            return $mac ? strtoupper($mac) : null;
        }

        return null;
    }

    /**
     * Get the gateway status from OPNsense.
     *
     * Cached for a few seconds like getArpTable() — DashboardController's
     * admin.live-stats endpoint polls interface/gateway data every 3 seconds
     * while a dashboard is open, so without a cache every poll from every
     * open admin session was its own uncached OPNsense round-trip.
     */
    public function getGatewayStatus()
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return [];
        }

        return Cache::remember('opnsense_gateway_status', 15, function () {
            try {
                $url = "{$this->baseUrl}/api/diagnostics/gateway/status";

                $response = $this->client()->get($url);

                if ($response->successful()) {
                    return $response->json();
                }

                return Cache::get('opnsense_gateway_status') ?? [];
            } catch (\Exception $e) {
                Log::error('OPNsense: Exception fetching gateway status: '.$e->getMessage());

                return Cache::get('opnsense_gateway_status') ?? [];
            }
        });
    }

    /**
     * Get interface statistics from OPNsense.
     *
     * Cached for a few seconds — this is called every 3s by
     * DashboardController::liveStats() (the admin.live-stats poll, live for
     * as long as any admin dashboard is open), and previously had no cache
     * at all, so every poll from every concurrently open dashboard was its
     * own uncached OPNsense HTTP round-trip. The raw byte counters returned
     * here are cumulative anyway (the client computes a bandwidth rate from
     * the delta between polls), so a few seconds of staleness costs nothing
     * observable.
     */
    public function getInterfaceStats()
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return [];
        }

        return Cache::remember('opnsense_interface_stats', 10, function () {
            try {
                $url = "{$this->baseUrl}/api/diagnostics/interface/getInterfaceStatistics";

                $response = $this->client()->get($url);

                if ($response->successful()) {
                    $data = $response->json();
                    $stats = $data['statistics'] ?? [];

                    $normalized = [];
                    foreach ($stats as $key => $values) {
                        // Extract the human name (WAN, LAN) or the interface name (vtnet0)
                        $name = 'unknown';

                        // Improved regex to handle nested brackets [[WAN]] -> wan
                        if (preg_match('/\[+([a-zA-Z0-9]+)\]+/', $key, $matches)) {
                            $name = strtolower($matches[1]);
                        } elseif (preg_match('/\(([^\)]+)\)/', $key, $matches)) {
                            $name = strtolower($matches[1]);
                        }

                        $inBytes = (int) ($values['received-bytes'] ?? 0);
                        $outBytes = (int) ($values['sent-bytes'] ?? 0);

                        // We want the aggregate entry for each interface.
                        // Usually, this is the one with the highest traffic count.
                        if (! isset($normalized[$name]) || ($inBytes + $outBytes) > ($normalized[$name]['inbytes'] + $normalized[$name]['outbytes'])) {
                            $normalized[$name] = [
                                'inbytes' => $inBytes,
                                'outbytes' => $outBytes,
                                'inpackets' => (int) ($values['received-packets'] ?? 0),
                                'outpackets' => (int) ($values['sent-packets'] ?? 0),
                                'errors' => (int) (($values['received-errors'] ?? 0) + ($values['send-errors'] ?? 0)),
                            ];
                        }
                    }

                    return $normalized;
                }

                return [];
            } catch (\Exception $e) {
                Log::error('OPNsense: Exception fetching interface stats: '.$e->getMessage());

                return [];
            }
        });
    }

    /**
     * Add a MAC address to the guest-blocklist firewall alias so it's
     * dropped at the pf layer, not just kicked from its current session.
     *
     * Requires a MAC-type alias (named per `services.opnsense.block_alias`)
     * and a block rule on the guest interface referencing it to already
     * exist in OPNsense — this only manages the alias's membership.
     */
    public function addMacToBlockAlias(string $macAddress): bool
    {
        return $this->alterBlockAlias('add', $macAddress);
    }

    /**
     * Remove a MAC address from the guest-blocklist firewall alias.
     */
    public function removeMacFromBlockAlias(string $macAddress): bool
    {
        return $this->alterBlockAlias('delete', $macAddress);
    }

    protected function alterBlockAlias(string $action, string $macAddress): bool
    {
        $alias = config('services.opnsense.block_alias', 'guest_blocklist');

        return $this->alterAlias($alias, $action, $macAddress);
    }

    /**
     * Add or remove a value (MAC or IP, depending on the alias's own type)
     * from a named OPNsense firewall alias. This only manages alias
     * membership — a firewall rule referencing the alias (block, or a
     * traffic-shaper pipe assignment) must already exist in OPNsense.
     */
    protected function alterAlias(string $alias, string $action, string $value): bool
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            Log::warning("OPNsense: API credentials not configured, cannot {$action} {$value} on alias '{$alias}'.");

            return false;
        }

        try {
            $url = "{$this->baseUrl}/api/firewall/alias_util/{$action}/{$alias}";

            $response = $this->client()->post($url, ['address' => $value]);

            // alias_util answers HTTP 200 even when it did nothing — a missing
            // alias comes back as {"status":"failed"}. Trusting the status code
            // alone made the app log "added 192.168.2.116 to lawatcafe_free_tier"
            // every time a guest connected, for an alias that did not exist,
            // which is why nobody noticed traffic shaping had never worked.
            $status = strtolower((string) ($response->json('status') ?? ''));

            if ($response->successful() && $status !== 'failed') {
                Log::info("OPNsense: {$action} {$value} on alias '{$alias}'.");

                return true;
            }

            Log::error("OPNsense: Failed to {$action} {$value} on alias '{$alias}'.", [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error("OPNsense: Exception during alias {$action} for {$value} on '{$alias}': ".$e->getMessage());

            return false;
        }
    }

    /**
     * Add/remove an IP address to the bandwidth-tier alias ('free' or
     * 'premium') so a matching OPNsense firewall+shaper rule applies the
     * corresponding pipe to its traffic. See upsertShaperPipe() for the pipe
     * side of this — the firewall rule binding alias -> pipe is a one-time
     * manual OPNsense setup step, not managed by this app.
     */
    public function addIpToTierAlias(string $tier, string $ip): bool
    {
        return $this->alterAlias($this->tierAliasName($tier), 'add', $ip);
    }

    public function removeIpFromTierAlias(string $tier, string $ip): bool
    {
        return $this->alterAlias($this->tierAliasName($tier), 'delete', $ip);
    }

    public function tierAliasName(string $tier): string
    {
        return config("services.opnsense.tier_alias_{$tier}", "lawatcafe_{$tier}_tier");
    }

    /**
     * The object name shared by a tier+direction's pipe and its rule, e.g.
     * "lawatcafe_free_down". Descriptions are the only stable identity these
     * objects have from the app's side — see readShaperConfig().
     */
    public function shaperObjectName(string $tier, string $direction): string
    {
        return "lawatcafe_{$tier}_{$direction}";
    }

    /**
     * The traffic shaper's whole configuration, indexed by description:
     * ['pipes' => [description => uuid], 'rules' => [description => uuid]].
     *
     * Identity comes from OPNsense itself on every call rather than from a
     * UUID the app stored earlier. The previous version cached each pipe's
     * UUID in a Setting, and a test run that shared this deployment's cache
     * store wrote its fixture strings ("existing-uuid") into it — after which
     * every write targeted setPipe/existing-uuid, got a 500, and no pipe was
     * ever created. Looking the objects up by description cannot desync.
     *
     * @return array{pipes: array<string, string>, rules: array<string, string>}
     */
    public function readShaperConfig(): array
    {
        $empty = ['pipes' => [], 'rules' => []];

        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return $empty;
        }

        try {
            $response = $this->client()->get("{$this->baseUrl}/api/trafficshaper/settings/get");

            if (! $response->successful()) {
                return $empty;
            }

            $ts = $response->json('ts') ?? [];

            $index = function (array $items): array {
                $map = [];
                foreach ($items as $uuid => $item) {
                    $description = $item['description'] ?? '';
                    if ($description !== '') {
                        $map[$description] = $uuid;
                    }
                }

                return $map;
            };

            return [
                'pipes' => $index($ts['pipes']['pipe'] ?? []),
                'rules' => $index($ts['rules']['rule'] ?? []),
            ];
        } catch (\Exception $e) {
            Log::error('OPNsense: Exception reading traffic shaper config: '.$e->getMessage());

            return $empty;
        }
    }

    /**
     * Create or update one direction's Dummynet pipe for a bandwidth tier.
     *
     * One pipe per direction, not one per tier: a tier is asymmetric (free is
     * 2 Mbit down / 1 Mbit up) and a single pipe can only express one number.
     * The old code collapsed both into max(down, up), so even a working setup
     * would have handed every free guest 2 Mbit in both directions.
     *
     * mask src-ip/dst-ip makes the cap PER CLIENT. Without it the pipe is one
     * shared bucket and ten guests would split a single 2 Mbit link between
     * them, which is not what "2 Mbps per guest" means.
     *
     * Does NOT apply the change by itself — call reconfigureShaper() after.
     *
     * @return string|null the pipe's UUID, or null on failure
     */
    public function upsertShaperPipe(string $tier, string $direction, float $mbps, ?string $uuid = null): ?string
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            Log::warning("OPNsense: API credentials not configured, cannot upsert shaper pipe for tier {$tier}.");

            return null;
        }

        $name = $this->shaperObjectName($tier, $direction);

        $payload = [
            'pipe' => [
                'enabled' => '1',
                'bandwidth' => (string) $mbps,
                // 'Mbit', never 'Mbit/s' — bandwidthMetric is an OptionField
                // whose only valid values are bit/Kbit/Mbit/Gbit. The old
                // 'Mbit/s' was not one of them.
                'bandwidthMetric' => 'Mbit',
                'mask' => $direction === 'down' ? 'dst-ip' : 'src-ip',
                'description' => $name,
            ],
        ];

        try {
            $url = $uuid
                ? "{$this->baseUrl}/api/trafficshaper/settings/setPipe/{$uuid}"
                : "{$this->baseUrl}/api/trafficshaper/settings/addPipe";

            $response = $this->client()->post($url, $payload);
            $data = $response->json();

            if ($response->successful() && ($data['result'] ?? null) !== 'failed') {
                $resolved = $data['uuid'] ?? $uuid;

                Log::info("OPNsense: upserted shaper pipe {$name} at {$mbps} Mbit.", ['uuid' => $resolved]);

                return $resolved;
            }

            Log::error("OPNsense: Failed to upsert shaper pipe {$name}.", [
                'status' => $response->status(),
                'response' => $data,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error("OPNsense: Exception upserting shaper pipe {$name}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Create or update the shaper rule that actually binds a tier's alias to
     * its pipe. Without this rule the pipe exists but nothing is ever steered
     * into it — which is precisely why guests measured full line speed while
     * the admin page showed a 2 Mbit cap. This step used to be documented as
     * "a one-time manual OPNsense setup step, not managed by this app", and
     * it had never been performed.
     *
     * Direction is from the LAN interface's point of view: a guest's download
     * leaves that interface ('out', matched on destination), their upload
     * enters it ('in', matched on source).
     *
     * @return string|null the rule's UUID, or null on failure
     */
    /**
     * @param  string|null  $aliasName  The alias whose members the rule applies to,
     *                                  or null for every host on the interface.
     *                                  Null is not a fallback — it is the only
     *                                  thing this OPNsense build's shaper will
     *                                  accept: source and destination are option
     *                                  fields offering nothing but "any" (see
     *                                  docs/INFRASTRUCTURE.md), so an alias-based
     *                                  rule is rejected outright and a
     *                                  shop-wide rule is the only one that lands.
     */
    public function upsertShaperRule(string $tier, string $direction, string $pipeUuid, ?string $aliasName, int $sequence, ?string $uuid = null): ?string
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return null;
        }

        $name = $this->shaperObjectName($tier, $direction);
        $isDownload = $direction === 'down';
        $match = $aliasName ?? 'any';

        $payload = [
            'rule' => [
                'enabled' => '1',
                'sequence' => (string) $sequence,
                'interface' => config('services.opnsense.shaper_interface', 'lan'),
                'proto' => 'ip',
                'source' => $isDownload ? 'any' : $match,
                'destination' => $isDownload ? $match : 'any',
                'direction' => $isDownload ? 'out' : 'in',
                'target' => $pipeUuid,
                'description' => $name,
            ],
        ];

        try {
            $url = $uuid
                ? "{$this->baseUrl}/api/trafficshaper/settings/setRule/{$uuid}"
                : "{$this->baseUrl}/api/trafficshaper/settings/addRule";

            $response = $this->client()->post($url, $payload);
            $data = $response->json();

            if ($response->successful() && ($data['result'] ?? null) !== 'failed') {
                $resolved = $data['uuid'] ?? $uuid;

                Log::info("OPNsense: upserted shaper rule {$name}.", ['uuid' => $resolved]);

                return $resolved;
            }

            Log::error("OPNsense: Failed to upsert shaper rule {$name}.", [
                'status' => $response->status(),
                'response' => $data,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error("OPNsense: Exception upserting shaper rule {$name}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Every firewall alias defined on OPNsense, as returned by search_item.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listAliases(): array
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return [];
        }

        try {
            return $this->client()->get("{$this->baseUrl}/api/firewall/alias/search_item")->json('rows') ?? [];
        } catch (\Exception $e) {
            Log::error('OPNsense: Exception listing firewall aliases: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Make sure a tier's firewall alias exists, creating an empty host alias
     * if it does not. Guest IPs are added to it on authorization
     * (addIpToTierAlias) and the shaper rule matches on it — but alias_util
     * cannot create the alias itself, so an absent alias silently swallowed
     * every membership change.
     */
    public function ensureTierAlias(string $tier): bool
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return false;
        }

        $name = $this->tierAliasName($tier);

        try {
            foreach ($this->listAliases() as $alias) {
                if (($alias['name'] ?? null) === $name) {
                    return true;
                }
            }

            $response = $this->client()->post("{$this->baseUrl}/api/firewall/alias/addItem", [
                'alias' => [
                    'enabled' => '1',
                    'name' => $name,
                    'type' => 'host',
                    'content' => '',
                    'description' => "Lawa't Kape {$tier} tier members",
                ],
            ]);

            if ($response->successful() && ($response->json('result') ?? null) !== 'failed') {
                Log::info("OPNsense: created tier alias '{$name}'.");

                return true;
            }

            Log::error("OPNsense: Failed to create tier alias '{$name}'.", [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error("OPNsense: Exception creating tier alias '{$name}': ".$e->getMessage());

            return false;
        }
    }

    /**
     * Apply pending firewall alias changes. Creating an alias through the API
     * stages it; without this it is not live for rules to match on.
     */
    public function reconfigureAliases(): bool
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return false;
        }

        try {
            return $this->client()->post("{$this->baseUrl}/api/firewall/alias/reconfigure")->successful();
        } catch (\Exception $e) {
            Log::error('OPNsense: Exception reconfiguring aliases: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Apply pending traffic-shaper configuration changes (pipe add/update).
     * Unlike alias_util, the trafficshaper module requires this explicit
     * reconfigure call for pipe changes to take effect.
     */
    public function reconfigureShaper(): bool
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            Log::warning('OPNsense: API credentials not configured, cannot reconfigure shaper.');

            return false;
        }

        try {
            $url = "{$this->baseUrl}/api/trafficshaper/service/reconfigure";

            $response = $this->client()->post($url);

            if ($response->successful()) {
                Log::info('OPNsense: traffic shaper reconfigured.');

                return true;
            }

            Log::error('OPNsense: Failed to reconfigure traffic shaper.', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('OPNsense: Exception reconfiguring traffic shaper: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Find the Kea DHCPv4 subnet (by UUID) that a given IP falls inside.
     * A reservation always belongs to a specific subnet object in Kea, so
     * this has to run before add/updateKeaReservation can build a payload.
     */
    protected function findKeaSubnetUuidForIp(string $ip): ?string
    {
        try {
            $response = $this->client()->get("{$this->baseUrl}/api/kea/dhcpv4/searchSubnet");

            if (! $response->successful()) {
                return null;
            }

            foreach ($response->json('rows') ?? [] as $row) {
                if ($this->cidrContainsIp($row['subnet'] ?? '', $ip)) {
                    return $row['uuid'] ?? null;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('OPNsense: Exception searching Kea subnets: '.$e->getMessage());

            return null;
        }
    }

    protected function cidrContainsIp(string $cidr, string $ip): bool
    {
        if (! str_contains($cidr, '/')) {
            return false;
        }

        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;
        if ($bits < 0 || $bits > 32) {
            return false;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = $bits === 0 ? 0 : (~0 << (32 - $bits));

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    /**
     * Create a permanent DHCP reservation in Kea binding $ip to $mac, so the
     * device keeps that IP forever regardless of lease renewals — unlike the
     * old app-only "VIP IP" whitelist, this is enforced by the DHCP server
     * itself.
     *
     * @return array{success: bool, uuid: ?string, subnet_uuid: ?string, message: ?string}
     */
    public function addKeaReservation(string $mac, string $ip, ?string $hostname): array
    {
        return $this->writeKeaReservation(null, $mac, $ip, $hostname);
    }

    /**
     * Update an existing reservation in place (e.g. the device's IP or
     * hostname changed). The subnet is re-resolved from $ip rather than
     * reused from the original assignment, so this also works correctly if
     * the new IP falls under a different Kea subnet.
     */
    public function updateKeaReservation(string $reservationUuid, string $mac, string $ip, ?string $hostname): array
    {
        return $this->writeKeaReservation($reservationUuid, $mac, $ip, $hostname);
    }

    protected function writeKeaReservation(?string $reservationUuid, string $mac, string $ip, ?string $hostname): array
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return ['success' => false, 'uuid' => null, 'subnet_uuid' => null, 'message' => 'OPNsense API credentials are not configured.'];
        }

        $subnetUuid = $this->findKeaSubnetUuidForIp($ip);
        if (! $subnetUuid) {
            return [
                'success' => false,
                'uuid' => null,
                'subnet_uuid' => null,
                'message' => "No Kea DHCPv4 subnet on OPNsense covers {$ip} — set one up under Services > Kea DHCP first.",
            ];
        }

        $payload = ['reservation' => [
            'subnet' => $subnetUuid,
            'ip_address' => $ip,
            'hw_address' => $mac,
            'hostname' => $hostname ?? '',
            'description' => $hostname ?? '',
        ]];

        try {
            $endpoint = $reservationUuid
                ? "{$this->baseUrl}/api/kea/dhcpv4/set_reservation/{$reservationUuid}"
                : "{$this->baseUrl}/api/kea/dhcpv4/add_reservation";

            $response = $this->client()->post($endpoint, $payload);
            $data = $response->json();

            if ($response->successful() && ($data['result'] ?? null) === 'saved') {
                $this->reconfigureKeaDhcp();

                return [
                    'success' => true,
                    'uuid' => $data['uuid'] ?? $reservationUuid,
                    'subnet_uuid' => $subnetUuid,
                    'message' => null,
                ];
            }

            Log::error("OPNsense: Failed to write Kea reservation for {$mac} -> {$ip}.", [
                'status' => $response->status(),
                'response' => $data,
            ]);

            return [
                'success' => false,
                'uuid' => null,
                'subnet_uuid' => $subnetUuid,
                'message' => 'OPNsense rejected the reservation: '.json_encode($data['validations'] ?? $data),
            ];
        } catch (\Exception $e) {
            Log::error("OPNsense: Exception writing Kea reservation for {$mac} -> {$ip}: ".$e->getMessage());

            return ['success' => false, 'uuid' => null, 'subnet_uuid' => $subnetUuid, 'message' => 'Could not reach OPNsense.'];
        }
    }

    /**
     * Delete a Kea reservation by its OPNsense UUID.
     */
    public function deleteKeaReservation(string $reservationUuid): bool
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            Log::warning("OPNsense: API credentials not configured, cannot delete Kea reservation {$reservationUuid}.");

            return false;
        }

        try {
            $response = $this->client()->post("{$this->baseUrl}/api/kea/dhcpv4/del_reservation/{$reservationUuid}");
            $data = $response->json();

            if ($response->successful() && ($data['result'] ?? null) === 'deleted') {
                $this->reconfigureKeaDhcp();

                return true;
            }

            Log::error("OPNsense: Failed to delete Kea reservation {$reservationUuid}.", [
                'status' => $response->status(),
                'response' => $data,
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error("OPNsense: Exception deleting Kea reservation {$reservationUuid}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Apply pending Kea DHCP configuration changes. Like the traffic shaper,
     * Kea reservation add/set/delete calls don't take effect until this runs.
     */
    public function reconfigureKeaDhcp(): bool
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return false;
        }

        try {
            $response = $this->client()->post("{$this->baseUrl}/api/kea/service/reconfigure");

            if ($response->successful()) {
                Log::info('OPNsense: Kea DHCP reconfigured.');

                return true;
            }

            Log::error('OPNsense: Failed to reconfigure Kea DHCP.', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('OPNsense: Exception reconfiguring Kea DHCP: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Look up the captive portal zone's model UUID from the numeric zone id
     * this app is configured against (Setting::opnsense_zone). The
     * session/access endpoints address a zone by that number; the zone
     * *settings* endpoints (used below to edit its Allowed IP/MAC
     * passthrough lists) only accept the model UUID, so this bridges the
     * two identifiers.
     */
    protected function resolveCaptivePortalZoneUuid(): ?string
    {
        try {
            $response = $this->client()->post("{$this->baseUrl}/api/captiveportal/settings/search_zones");

            if (! $response->successful()) {
                return null;
            }

            foreach ($response->json('rows') ?? [] as $row) {
                if ((string) ($row['zoneid'] ?? '') === (string) $this->zone) {
                    return $row['uuid'] ?? null;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('OPNsense: Exception resolving captive portal zone UUID: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Split an OPNsense "AsList" field's raw value into a clean array.
     *
     * get_zone returns these fields as a {value: {value, selected: 0|1}} map,
     * which is the shape actually seen in production — only entries with
     * selected=1 are part of the list. A plain string is still handled, and is
     * split on commas as well as newlines: comma is the separator OPNsense
     * itself uses for list fields (see modifyZoneListField), so treating a
     * comma-joined string as a single entry would silently produce a one-item
     * list and drop every other allow-listed address on the next write.
     */
    protected function splitZoneListField($raw): array
    {
        if (is_array($raw)) {
            $raw = implode(',', array_keys(array_filter($raw, function ($v) {
                return is_array($v) ? (($v['selected'] ?? 0) == 1) : (bool) $v;
            })));
        }

        return array_values(array_filter(array_map('trim', preg_split('/[,\r\n]+/', (string) $raw))));
    }

    /**
     * Read the captive portal zone's "Allowed IP addresses" and "Allowed MAC
     * addresses" passthrough lists. Devices on these lists skip the portal
     * entirely — no voucher, ever — which is a different, stronger guarantee
     * than a Kea static IP reservation (see addKeaReservation): that only
     * pins the device's IP, it still has to authenticate at the portal.
     *
     * Cached like getArpTable()/getDhcpLeases()/listSessions() — this is now
     * also read on every Network > Sessions page load (ghost-device
     * cross-reference), which polls every 5s, and unlike those it wasn't
     * previously cached at all (a live zone-config fetch every call).
     *
     * @return array{ips: string[], macs: string[]}
     */
    public function getAllowedAddresses(): array
    {
        $empty = ['ips' => [], 'macs' => []];

        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return $empty;
        }

        return Cache::remember('opnsense_allowed_addresses', 15, function () use ($empty) {
            $uuid = $this->resolveCaptivePortalZoneUuid();
            if (! $uuid) {
                return $empty;
            }

            try {
                $response = $this->client()->get("{$this->baseUrl}/api/captiveportal/settings/get_zone/{$uuid}");

                if (! $response->successful()) {
                    return Cache::get('opnsense_allowed_addresses') ?? $empty;
                }

                $zone = $response->json('zone') ?? [];

                return [
                    'ips' => $this->splitZoneListField($zone['allowedAddresses'] ?? ''),
                    'macs' => $this->splitZoneListField($zone['allowedMACAddresses'] ?? ''),
                ];
            } catch (\Exception $e) {
                Log::error('OPNsense: Exception fetching captive portal allowed addresses: '.$e->getMessage());

                return Cache::get('opnsense_allowed_addresses') ?? $empty;
            }
        });
    }

    public function addAllowedIp(string $address): array
    {
        return $this->modifyZoneListField('allowedAddresses', $address, true);
    }

    public function removeAllowedIp(string $address): array
    {
        return $this->modifyZoneListField('allowedAddresses', $address, false);
    }

    public function addAllowedMac(string $mac): array
    {
        return $this->modifyZoneListField('allowedMACAddresses', strtoupper($mac), true);
    }

    public function removeAllowedMac(string $mac): array
    {
        return $this->modifyZoneListField('allowedMACAddresses', strtoupper($mac), false);
    }

    /**
     * Add or remove one entry from the zone's allowedAddresses /
     * allowedMACAddresses list. Only the changed field is posted back to
     * set_zone — OPNsense's model layer (setNodes) leaves every field absent
     * from the payload untouched (interfaces, auth servers, template, ...),
     * the same partial-update approach writeKeaReservation() relies on — so
     * this can't clobber the rest of the zone's configuration.
     *
     * @return array{success: bool, message: ?string}
     */
    protected function modifyZoneListField(string $field, string $value, bool $add): array
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return ['success' => false, 'message' => 'OPNsense API credentials are not configured.'];
        }

        $uuid = $this->resolveCaptivePortalZoneUuid();
        if (! $uuid) {
            return ['success' => false, 'message' => "Could not find captive portal zone {$this->zone} on OPNsense."];
        }

        try {
            $getResponse = $this->client()->get("{$this->baseUrl}/api/captiveportal/settings/get_zone/{$uuid}");
            $zone = $getResponse->json('zone') ?? [];
            $current = $this->splitZoneListField($zone[$field] ?? '');

            // OPNsense stores a bare host address as /32, so a plain
            // "192.168.2.50" and the stored "192.168.2.50/32" are the same
            // entry. Comparing them raw would re-add one that is already
            // allow-listed, and would fail to remove one the caller named
            // without its mask.
            //
            // Case matters too: OPNsense normalises a stored MAC to lower case,
            // but addAllowedMac()/removeAllowedMac() hand this method the upper
            // case form. Under a case-sensitive compare the Remove button could
            // never match the stored entry — it filtered nothing, wrote the
            // list back unchanged, and still reported success.
            $canonical = function (string $v) {
                $v = strtolower(trim($v));

                return preg_match('/^(\d{1,3}\.){3}\d{1,3}$/', $v) ? "{$v}/32" : $v;
            };
            $target = $canonical($value);

            if ($add) {
                if (in_array($target, array_map($canonical, $current), true)) {
                    return ['success' => true, 'message' => null];
                }
                $current[] = $value;
            } else {
                $filtered = array_values(array_filter($current, fn ($v) => $canonical($v) !== $target));

                // Nothing matched: say so instead of reporting a successful
                // removal that never happened — silent success is what hid the
                // case-sensitivity bug above in the first place.
                if (count($filtered) === count($current)) {
                    return ['success' => false, 'message' => "{$value} is not on the captive portal allow-list."];
                }

                $current = $filtered;
            }

            // Comma, never a newline. OPNsense reads an AsList field back with
            // BaseField::setValue(), which splits the posted string on commas
            // only — a newline-joined list arrives as ONE value containing
            // newlines and fails the field's own validator with
            // {"zone.allowedAddresses":"Please specify a valid network segment
            // or IP address."}. That made every write of two-or-more entries
            // impossible: the only writes that ever succeeded were an add to
            // an empty list, which has no separator in it.
            $response = $this->client()->post("{$this->baseUrl}/api/captiveportal/settings/set_zone/{$uuid}", [
                'zone' => [$field => implode(',', $current)],
            ]);
            $data = $response->json();

            if ($response->successful() && ($data['result'] ?? null) === 'saved') {
                $this->reconfigureCaptivePortal();
                $this->forgetSessionsCache();
                Cache::forget('opnsense_allowed_addresses');

                return ['success' => true, 'message' => null];
            }

            Log::error("OPNsense: Failed to update captive portal {$field}.", [
                'status' => $response->status(),
                'response' => $data,
            ]);

            return ['success' => false, 'message' => 'OPNsense rejected the change: '.json_encode($data['validations'] ?? $data)];
        } catch (\Exception $e) {
            Log::error("OPNsense: Exception updating captive portal {$field}: ".$e->getMessage());

            return ['success' => false, 'message' => 'Could not reach OPNsense.'];
        }
    }

    /**
     * Apply pending captive portal zone configuration changes — allowed
     * address list edits don't take effect until this runs.
     */
    public function reconfigureCaptivePortal(): bool
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return false;
        }

        try {
            $response = $this->client()->post("{$this->baseUrl}/api/captiveportal/service/reconfigure");

            if ($response->successful()) {
                Log::info('OPNsense: captive portal reconfigured.');

                return true;
            }

            Log::error('OPNsense: Failed to reconfigure captive portal.', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('OPNsense: Exception reconfiguring captive portal: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Terminate an active session on OPNsense.
     *
     * @param  string  $sessionId
     * @return bool
     */
    public function disconnectDevice($sessionId)
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            Log::warning('OPNsense Disconnect: API credentials missing.');

            return false;
        }

        try {
            $zone = session('zone', $this->zone);
            $url = "{$this->baseUrl}/api/captiveportal/session/disconnect/{$zone}/";

            // We send both camelCase and lowercase to be safe across OPNsense versions
            $response = $this->client()->post($url, [
                'sessionId' => $sessionId,
                'sessionid' => $sessionId,
            ]);

            $data = $response->json();

            if ($response->successful()) {
                Log::info("OPNsense: Disconnect request sent for session {$sessionId}. Response: ".json_encode($data));
                $this->forgetSessionsCache();

                // If the session was deleted or returned successfully
                return true;
            }

            Log::error("OPNsense: Failed to disconnect session {$sessionId}", [
                'status' => $response->status(),
                'response' => $data,
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error("OPNsense: Exception disconnecting session {$sessionId}: ".$e->getMessage());

            return false;
        }
    }
}
