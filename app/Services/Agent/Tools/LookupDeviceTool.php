<?php

namespace App\Services\Agent\Tools;

use App\Models\BannedDevice;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use App\Services\OpnSenseService;

/**
 * "What is 192.168.2.130?" — one device, from every source the network has:
 * DHCP lease (hostname, vendor), ARP (seen on which interface), the firewall's
 * live sessions, the voucher it signed in with, the ban list, and the
 * infrastructure/trusted lists.
 */
class LookupDeviceTool implements AgentTool
{
    public function __construct(protected OpnSenseService $opnsense) {}

    public function name(): string
    {
        return 'lookupDevice';
    }

    public function description(): string
    {
        return 'Identify one device on the shop network by IP address, MAC address, or part of its hostname: its name and maker, whether it is online and signed in, how much data it has used, which voucher it used, and whether it is infrastructure, trusted or banned. Use for "what is this device", "who is on 192.168.2.x", or before blocking anything. Read-only.';
    }

    public function parametersSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'query' => ['type' => 'string', 'description' => 'An IP address (192.168.2.130), a MAC address (aa:bb:cc:dd:ee:ff), or part of a hostname.'],
        ], 'required' => ['query']];
    }

    public function permissionTier(): string
    {
        return 'auto';
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        $query = strtolower(trim((string) ($arguments['query'] ?? '')));
        if ($query === '') {
            return ToolResult::fail('Give an IP address, MAC address or hostname to look up.');
        }

        $normMac = fn (?string $m) => strtolower(preg_replace('/[^a-fA-F0-9]/', '', (string) $m));
        $isMac = strlen($normMac($query)) === 12 && preg_match('/^[0-9a-f:.\-]+$/', $query);
        $matches = function (?string $ip, ?string $mac, ?string $host = null) use ($query, $isMac, $normMac) {
            if ($isMac) {
                return $mac && $normMac($mac) === $normMac($query);
            }

            return $ip === $query || ($host && str_contains(strtolower($host), $query));
        };

        $lease = collect($this->opnsense->getDhcpLeases())->first(fn ($l) => $matches($l['address'] ?? null, $l['hwaddr'] ?? null, $l['hostname'] ?? null));
        $arp = collect($this->opnsense->getArpTable())->first(fn ($a) => $matches($a['ip'] ?? null, $a['mac'] ?? null, $a['hostname'] ?? null));

        $ip = $lease['address'] ?? $arp['ip'] ?? (filter_var($query, FILTER_VALIDATE_IP) ? $query : null);
        $mac = $lease['hwaddr'] ?? $arp['mac'] ?? ($isMac ? $query : null);

        if (! $ip && ! $mac) {
            return ToolResult::ok("No device matching \"{$arguments['query']}\" is on the network right now (no DHCP lease or ARP entry).", ['found' => false]);
        }

        $session = collect($this->opnsense->listSessions())->first(fn ($s) => ($ip && str_replace('/32', '', (string) ($s['ipAddress'] ?? '')) === $ip) || ($mac && ($s['macAddress'] ?? '') && $normMac($s['macAddress']) === $normMac($mac)));
        $voucher = ($mac ? Voucher::findByMac($mac) : null) ?? ($ip ? Voucher::where('ip_address', $ip)->whereNotNull('used_at')->latest('used_at')->first() : null);
        $banned = $mac ? BannedDevice::findByMac($mac) : null;
        $allowed = $this->opnsense->getAllowedAddresses();
        $label = config("services.network.labels.{$ip}");

        $facts = [
            'ip' => $ip,
            'mac' => $mac,
            'name' => $label ?: (($lease['hostname'] ?? '') ?: null),
            'maker' => ($lease['mac_info'] ?? '') ?: ($arp['manufacturer'] ?? null),
            'interface' => $arp['intf_description'] ?? $lease['if_descr'] ?? null,
            'infrastructure' => $ip && in_array($ip, Setting::infrastructureIps(), true),
            'trusted' => ($ip && in_array($ip, $allowed['ips'] ?? [], true)) || ($mac && in_array($normMac($mac), array_map($normMac, $allowed['macs'] ?? []), true)),
            'banned' => (bool) $banned,
            'ban_reason' => $banned?->reason,
            'online' => (bool) $session,
            'data_mb' => $session ? round(((int) ($session['bytes_in'] ?? 0) + (int) ($session['bytes_out'] ?? 0)) / 1048576, 1) : null,
            'signed_in_since' => $session && isset($session['startTime']) ? date('M j, g:i A', (int) $session['startTime']) : null,
            'voucher' => $voucher?->code,
            'tier' => $voucher?->tier,
            'reserved_address' => ($lease['is_reserved'] ?? '0') === '1',
        ];

        $who = $facts['name'] ?: ($facts['maker'] ? "a {$facts['maker']} device" : 'an unnamed device');
        $parts = ["{$ip} ({$mac}) is {$who}".($facts['interface'] ? " on {$facts['interface']}" : '').'.'];
        if ($facts['banned']) {
            $parts[] = 'It is BANNED'.($facts['ban_reason'] ? " ({$facts['ban_reason']})" : '').'.';
        }
        if ($facts['infrastructure']) {
            $parts[] = 'It is shop infrastructure — never disconnect or block it.';
        } elseif ($facts['trusted']) {
            $parts[] = 'It is on the trusted list and skips the Wi-Fi login.';
        }
        $parts[] = $facts['online']
            ? "Signed in and online since {$facts['signed_in_since']}, {$facts['data_mb']} MB used."
            : 'Not signed in to the Wi-Fi right now.';
        if ($facts['voucher']) {
            $parts[] = "Last voucher: {$facts['voucher']} ({$facts['tier']}).";
        }

        return ToolResult::ok(implode(' ', $parts), ['found' => true] + $facts);
    }
}
