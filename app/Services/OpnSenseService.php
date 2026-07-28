<?php

namespace App\Services;

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
        $this->zone = \App\Models\Setting::get('opnsense_zone', config('services.opnsense.zone', 0));
    }

    /**
     * Build the authenticated HTTP client used for every OPNsense call.
     * Centralizing this means the TLS verification tradeoff (see
     * services.opnsense.verify_tls) is applied consistently everywhere,
     * instead of each method deciding it individually.
     */
    protected function client()
    {
        return Http::withBasicAuth($this->apiKey, $this->apiSecret)
            ->withOptions(['verify' => config('services.opnsense.verify_tls', false)]);
    }

    /**
     * Authorize a device on the OPNsense Captive Portal.
     *
     * @param string $ip The client IP address.
     * @param string $username A identifier for the session (e.g. Voucher code).
     * @return bool
     */
    public function authorizeDevice($ip, $voucherCode = 'guest')
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            Log::warning("OPNsense: API credentials not configured.");
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
            
            Log::info("OPNsense Request URL: " . $url);
            Log::info("OPNsense API Key Length: " . strlen($this->apiKey));
            
            $response = $this->client()->post($url, [
                    'user' => config('services.opnsense.guest_user'), 
                    'password' => config('services.opnsense.guest_pass'),
                    'ip' => $ip,
                ]);

            $data = $response->json();

            if ($response->successful()) {
                // If we get a sessionId or a successful state, the device is authorized
                if (isset($data['sessionId']) || (isset($data['clientState']) && in_array($data['clientState'], ['AUTHORIZED', 'CONNECTED', 'ALREADY_AUTHORIZED']))) {
                    Log::info("OPNsense: Successfully authorized IP {$ip} via session/connect. Session: " . ($data['sessionId'] ?? 'N/A'));
                    $this->forgetSessionsCache();
                    return true;
                }
            }

            Log::error("OPNsense: Failed to authorize IP {$ip} via session/connect", [
                'status' => $response->status(),
                'response' => $data
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error("OPNsense: Exception during authorization: " . $e->getMessage());
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

        return Cache::remember('opnsense_arp_table', 5, function () {
            try {
                $url = "{$this->baseUrl}/api/diagnostics/interface/getArp";

                $response = $this->client()->get($url);

                if ($response->successful()) {
                    return $response->json();
                }

                return [];
            } catch (\Exception $e) {
                Log::error("OPNsense: Exception fetching ARP table: " . $e->getMessage());
                return [];
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

        return Cache::remember("opnsense_sessions_list_{$zone}", 5, function () use ($zone) {
            try {
                $url = "{$this->baseUrl}/api/captiveportal/session/list/{$zone}/";

                $response = $this->client()->get($url);

                if ($response->successful()) {
                    $data = $response->json();
                    $rows = $data['rows'] ?? $data['sessions'] ?? $data;

                    if (!is_array($rows)) return [];

                    return array_map(function($session) {
                        // Normalize byte keys
                        if (isset($session['bytes_in']) && !isset($session['bytes_received'])) {
                            $session['bytes_received'] = $session['bytes_in'];
                        }
                        if (isset($session['bytes_out']) && !isset($session['bytes_sent'])) {
                            $session['bytes_sent'] = $session['bytes_out'];
                        }
                        return $session;
                    }, $rows);
                }

                return [];
            } catch (\Exception $e) {
                Log::error("OPNsense: Exception fetching sessions: " . $e->getMessage());
                return [];
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
        Cache::forget('opnsense_sessions_list_' . session('zone', $this->zone));
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

        if (!is_array($entries)) {
            return null;
        }

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
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
     */
    public function getGatewayStatus()
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return [];
        }

        try {
            $url = "{$this->baseUrl}/api/diagnostics/gateway/status";
            
            $response = $this->client()->get($url);

            if ($response->successful()) {
                return $response->json();
            }

            return [];
        } catch (\Exception $e) {
            Log::error("OPNsense: Exception fetching gateway status: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get interface statistics from OPNsense.
     */
    public function getInterfaceStats()
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return [];
        }

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
                    if (!isset($normalized[$name]) || ($inBytes + $outBytes) > ($normalized[$name]['inbytes'] + $normalized[$name]['outbytes'])) {
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
            Log::error("OPNsense: Exception fetching interface stats: " . $e->getMessage());
            return [];
        }
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

            if ($response->successful()) {
                Log::info("OPNsense: {$action} {$value} on alias '{$alias}'.");
                return true;
            }

            Log::error("OPNsense: Failed to {$action} {$value} on alias '{$alias}'.", [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error("OPNsense: Exception during alias {$action} for {$value} on '{$alias}': " . $e->getMessage());
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

    protected function tierAliasName(string $tier): string
    {
        return config("services.opnsense.tier_alias_{$tier}", "lawatcafe_{$tier}_tier");
    }

    /**
     * Create or update the Dummynet pipe for a bandwidth tier ('free' or
     * 'premium'). The pipe's OPNsense UUID is cached in Setting so repeat
     * calls update the same pipe instead of creating duplicates. Does NOT
     * apply the change by itself — call reconfigureShaper() after.
     */
    public function upsertShaperPipe(string $tier, float $downMbps, float $upMbps): bool
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            Log::warning("OPNsense: API credentials not configured, cannot upsert shaper pipe for tier {$tier}.");
            return false;
        }

        $settingKey = "opnsense_pipe_uuid_{$tier}";
        $uuid = \App\Models\Setting::get($settingKey);

        $payload = [
            'pipe' => [
                'bandwidth' => (string) max($downMbps, $upMbps),
                'bandwidthMetric' => 'Mbit/s',
                'description' => "lawatcafe_{$tier}",
            ],
        ];

        try {
            $url = $uuid
                ? "{$this->baseUrl}/api/trafficshaper/settings/setPipe/{$uuid}"
                : "{$this->baseUrl}/api/trafficshaper/settings/addPipe";

            $response = $this->client()->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $newUuid = $data['uuid'] ?? $uuid;
                if ($newUuid && $newUuid !== $uuid) {
                    \App\Models\Setting::set($settingKey, $newUuid);
                }

                Log::info("OPNsense: upserted shaper pipe for tier {$tier}.", ['uuid' => $newUuid]);
                return true;
            }

            Log::error("OPNsense: Failed to upsert shaper pipe for tier {$tier}.", [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error("OPNsense: Exception upserting shaper pipe for tier {$tier}: " . $e->getMessage());
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
            Log::warning("OPNsense: API credentials not configured, cannot reconfigure shaper.");
            return false;
        }

        try {
            $url = "{$this->baseUrl}/api/trafficshaper/service/reconfigure";

            $response = $this->client()->post($url);

            if ($response->successful()) {
                Log::info("OPNsense: traffic shaper reconfigured.");
                return true;
            }

            Log::error("OPNsense: Failed to reconfigure traffic shaper.", [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error("OPNsense: Exception reconfiguring traffic shaper: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Terminate an active session on OPNsense.
     *
     * @param string $sessionId
     * @return bool
     */
    public function disconnectDevice($sessionId)
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            Log::warning("OPNsense Disconnect: API credentials missing.");
            return false;
        }

        try {
            $zone = session('zone', $this->zone);
            $url = "{$this->baseUrl}/api/captiveportal/session/disconnect/{$zone}/";
            
            // We send both camelCase and lowercase to be safe across OPNsense versions
            $response = $this->client()->post($url, [
                    'sessionId' => $sessionId,
                    'sessionid' => $sessionId 
                ]);

            $data = $response->json();

            if ($response->successful()) {
                Log::info("OPNsense: Disconnect request sent for session {$sessionId}. Response: " . json_encode($data));
                $this->forgetSessionsCache();

                // If the session was deleted or returned successfully
                return true;
            }

            Log::error("OPNsense: Failed to disconnect session {$sessionId}", [
                'status' => $response->status(),
                'response' => $data
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error("OPNsense: Exception disconnecting session {$sessionId}: " . $e->getMessage());
            return false;
        }
    }
}
