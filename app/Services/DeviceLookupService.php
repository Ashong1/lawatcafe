<?php

namespace App\Services;

use App\Models\BannedDevice;
use App\Models\Setting;
use App\Models\Voucher;

/**
 * One device, from every source the network has: DHCP lease (hostname,
 * vendor), ARP (interface), the firewall's live sessions, its voucher, the ban
 * list, and the infrastructure/trusted lists. Shared by the AI's lookupDevice
 * tool and the "Find a device" box, so both give the same answer.
 */
class DeviceLookupService
{
    public function __construct(protected OpnSenseService $opnsense) {}

    /**
     * @param  string  $query  an IP, a MAC, or part of a hostname
     * @return array|null facts, or null when nothing on the network matches
     */
    public function find(string $query): ?array
    {
        $query = strtolower(trim($query));
        if ($query === '') {
            return null;
        }

        // A voucher code ("which device is on FREE-IFMW?") → that voucher's device.
        if (preg_match('/^[a-z]+-[a-z0-9]{3,}$/', $query) && ($voucher = Voucher::whereRaw('LOWER(code) = ?', [$query])->first())) {
            if (! $voucher->ip_address) {
                return null;
            }
            $query = $voucher->ip_address;
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
            return null;
        }

        $session = collect($this->opnsense->listSessions())->first(fn ($s) => ($ip && str_replace('/32', '', (string) ($s['ipAddress'] ?? '')) === $ip) || ($mac && ($s['macAddress'] ?? '') && $normMac($s['macAddress']) === $normMac($mac)));
        $voucher = ($mac ? Voucher::findByMac($mac) : null) ?? ($ip ? Voucher::where('ip_address', $ip)->whereNotNull('used_at')->latest('used_at')->first() : null);
        $banned = $mac ? BannedDevice::findByMac($mac) : null;
        $allowed = $this->opnsense->getAllowedAddresses();
        // Not config("...labels.{$ip}"): config keys split on dots, and IPs are full of them.
        $label = $ip ? (config('services.network.labels')[$ip] ?? null) : null;

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
            'session_id' => $session['sessionId'] ?? null,
        ];
        $facts['summary'] = $this->describe($facts);

        return $facts;
    }

    /** The facts as one plain paragraph. */
    public function describe(array $facts): string
    {
        $ip = $facts['ip'];
        $mac = $facts['mac'];

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

        return implode(' ', $parts);
    }
}
