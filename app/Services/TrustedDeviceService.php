<?php

namespace App\Services;

use App\Models\StaticIpAssignment;

/**
 * The captive portal allow-list, shown as devices instead of raw addresses so
 * a non-technical admin can recognise "the owner's laptop" by name before
 * letting it skip the Wi-Fi login.
 *
 * Trusting a device allow-lists its MAC and, when the device has a fixed
 * address outside the guest DHCP pool, that IP as well. The IP entry is the
 * one OPNsense applies immediately and reliably; an allowed-MAC entry alone
 * did not follow a device to a new address. A pool address is never
 * allow-listed, because the next guest to lease it would skip the portal too.
 */
class TrustedDeviceService
{
    public function __construct(protected OpnSenseService $opnsense) {}

    /**
     * Every device the network knows about: active DHCP leases, the ARP
     * table, and fixed-address reservations (shown even while offline).
     *
     * @return array<int, array{mac: string, ip: ?string, name: ?string, maker: ?string, online: bool, fixed: bool, trusted: bool, system: bool}>
     */
    public function devices(): array
    {
        $allowed = $this->allowedSets();
        $devices = [];

        $merge = function (?string $mac, array $facts) use (&$devices) {
            $key = $this->normMac($mac);
            if (strlen($key) !== 12) {
                return;
            }
            $current = $devices[$key] ?? ['mac' => $this->formatMac($key), 'ip' => null, 'name' => null, 'maker' => null, 'online' => false, 'fixed' => false];
            foreach ($facts as $field => $value) {
                // First source to supply a value wins; sources are merged in
                // priority order below. online/fixed only ever turn on.
                if (in_array($field, ['online', 'fixed'], true)) {
                    $current[$field] = $current[$field] || $value;
                } elseif ($current[$field] === null && $value !== null && $value !== '') {
                    $current[$field] = $value;
                }
            }
            $devices[$key] = $current;
        };

        // A reservation's address is the one the device will get, so it beats
        // a stale lease or ARP entry left over from before it was reserved.
        foreach (StaticIpAssignment::all() as $assignment) {
            $merge($assignment->mac_address, ['ip' => $assignment->ip_address, 'name' => $assignment->hostname, 'fixed' => true]);
        }

        foreach ($this->opnsense->getArpTable() as $entry) {
            if (! ($entry['expired'] ?? false)) {
                $merge($entry['mac'] ?? null, ['ip' => $entry['ip'] ?? null, 'maker' => $entry['manufacturer'] ?? null, 'online' => true]);
            }
        }

        foreach ($this->opnsense->getDhcpLeases() as $lease) {
            // Kea state 0 is a live lease; 1/2 are declined/expired.
            if ((string) ($lease['state'] ?? '0') !== '0') {
                continue;
            }
            $merge($lease['hwaddr'] ?? null, [
                'ip' => $lease['address'] ?? null,
                'name' => $lease['hostname'] ?? null,
                'maker' => $lease['mac_info'] ?? null,
                'fixed' => ($lease['is_reserved'] ?? '0') === '1',
            ]);
        }

        $labels = config('services.network.labels', []);

        $rows = array_map(function (array $device) use ($allowed, $labels) {
            $ip = $device['ip'];
            if ($ip && isset($labels[$ip])) {
                $device['name'] = $labels[$ip];
            }
            if ($ip && ! $device['fixed']) {
                $device['fixed'] = $this->opnsense->dhcpPoolContaining($ip) === null;
            }
            $device['trusted'] = in_array($this->normMac($device['mac']), $allowed['macs'], true)
                || ($ip && $device['fixed'] && in_array($ip, $allowed['ips'], true));
            $device['system'] = $ip !== null && $this->isSystemIp($ip);

            return $device;
        }, array_values($devices));

        usort($rows, fn ($a, $b) => [$b['online'], $a['name'] === null, strtolower((string) $a['name']), $a['ip']]
            <=> [$a['online'], $b['name'] === null, strtolower((string) $b['name']), $b['ip']]);

        return $rows;
    }

    /**
     * What is allow-listed now, one row per device. A device's MAC entry and
     * its fixed-IP entry collapse into a single row; entries matching no known
     * device (a range, a device that's gone) are listed as-is.
     *
     * @return array<int, array{name: ?string, ip: ?string, mac: ?string, maker: ?string, online: bool, system: bool, entries: array{ips: string[], macs: string[]}}>
     */
    public function allowed(): array
    {
        $raw = $this->opnsense->getAllowedAddresses();
        $devices = $this->devices();
        $rows = [];
        $claimedIps = [];

        foreach ($raw['macs'] ?? [] as $mac) {
            $device = collect($devices)->first(fn ($d) => $this->normMac($d['mac']) === $this->normMac($mac));
            $ips = $device && $device['ip'] && in_array($device['ip'], $this->stripMasks($raw['ips'] ?? []), true) ? [$device['ip']] : [];
            $claimedIps = array_merge($claimedIps, $ips);
            $rows[] = [
                'name' => $device['name'] ?? null,
                'ip' => $device['ip'] ?? null,
                'mac' => $device['mac'] ?? $this->formatMac($this->normMac($mac)),
                'maker' => $device['maker'] ?? null,
                'online' => $device['online'] ?? false,
                'system' => $device['system'] ?? false,
                'entries' => ['ips' => $ips, 'macs' => [$mac]],
            ];
        }

        foreach ($this->stripMasks($raw['ips'] ?? []) as $ip) {
            if (in_array($ip, $claimedIps, true)) {
                continue;
            }
            $device = collect($devices)->first(fn ($d) => $d['ip'] === $ip);
            $rows[] = [
                'name' => $device['name'] ?? (config('services.network.labels')[$ip] ?? null),
                'ip' => $ip,
                'mac' => $device['mac'] ?? null,
                'maker' => $device['maker'] ?? null,
                'online' => $device['online'] ?? false,
                'system' => $this->isSystemIp($ip),
                'entries' => ['ips' => [$ip], 'macs' => []],
            ];
        }

        usort($rows, fn ($a, $b) => [$a['system'], strtolower((string) $a['name'])] <=> [$b['system'], strtolower((string) $b['name'])]);

        return $rows;
    }

    /**
     * Let a device skip the Wi-Fi login.
     *
     * @return array{success: bool, message: string}
     */
    public function trust(string $mac): array
    {
        $device = collect($this->devices())->first(fn ($d) => $this->normMac($d['mac']) === $this->normMac($mac));
        $who = ($device['name'] ?? null) ?: 'This device';

        $result = $this->opnsense->addAllowedMac($mac);
        if (! $result['success']) {
            return ['success' => false, 'message' => $result['message'] ?? 'Could not update the firewall.'];
        }

        // Already on the list: adding again changes nothing on the firewall, so
        // reload the portal, which is what makes OPNsense open the device's
        // session again (it does so only on a reload).
        if ($device['trusted'] ?? false) {
            $this->opnsense->reconfigureCaptivePortal();
        }

        if ($device && $device['ip'] && $device['fixed'] && $this->opnsense->dhcpPoolContaining($device['ip']) === null) {
            $ipResult = $this->opnsense->addAllowedIp($device['ip']);
            if (! $ipResult['success']) {
                return ['success' => false, 'message' => "{$who} was added, but its address {$device['ip']} could not be: ".($ipResult['message'] ?? 'the firewall refused it.')];
            }

            return ['success' => true, 'message' => "{$who} now connects without a voucher."];
        }

        return ['success' => true, 'message' => "{$who} now connects without a voucher. Its address can change, so if it ever asks for a voucher again, give it a fixed address and trust it once more."];
    }

    /**
     * Make a device use vouchers again: removes its MAC entry and its IP entry.
     * Shop systems (servers, router, firewall) are refused unless $allowSystem.
     *
     * @param  string[]  $ips
     * @param  string[]  $macs
     * @return array{success: bool, message: string}
     */
    public function untrust(array $ips, array $macs, bool $allowSystem = false): array
    {
        if (! $allowSystem && collect($ips)->contains(fn ($ip) => $this->isSystemIp($ip))) {
            return ['success' => false, 'message' => "That's shop equipment — it has to stay on the list or the shop's own systems go offline."];
        }

        foreach ($macs as $mac) {
            $result = $this->opnsense->removeAllowedMac($mac);
            if (! $result['success']) {
                return ['success' => false, 'message' => $result['message'] ?? 'Could not update the firewall.'];
            }
        }
        foreach ($ips as $ip) {
            $result = $this->opnsense->removeAllowedIp($ip);
            if (! $result['success']) {
                return ['success' => false, 'message' => $result['message'] ?? 'Could not update the firewall.'];
            }
        }

        return ['success' => true, 'message' => 'Removed. That device needs a voucher again.'];
    }

    /** Servers, router and firewall: the shop stops working without them. */
    public function isSystemIp(string $ip): bool
    {
        $ip = str_replace('/32', '', $ip);

        return in_array($ip, array_merge(
            array_diff(config('services.opnsense.protected_ips', []), $this->staffDeviceIps()),
            array_keys(config('services.network.labels', [])),
            [config('services.opnsense.ip')],
        ), true);
    }

    /** Fixed addresses reserved for a person's device rather than a server. */
    protected function staffDeviceIps(): array
    {
        $labelled = array_keys(config('services.network.labels', []));

        return StaticIpAssignment::pluck('ip_address')->reject(fn ($ip) => in_array($ip, $labelled, true))->all();
    }

    /** @return array{ips: string[], macs: string[]} */
    protected function allowedSets(): array
    {
        $raw = $this->opnsense->getAllowedAddresses();

        return [
            'ips' => $this->stripMasks($raw['ips'] ?? []),
            'macs' => array_map(fn ($m) => $this->normMac($m), $raw['macs'] ?? []),
        ];
    }

    protected function stripMasks(array $ips): array
    {
        return array_map(fn ($ip) => str_ends_with($ip, '/32') ? substr($ip, 0, -3) : $ip, $ips);
    }

    protected function normMac(?string $mac): string
    {
        return strtolower(preg_replace('/[^a-fA-F0-9]/', '', (string) $mac));
    }

    protected function formatMac(string $norm): string
    {
        return strtoupper(implode(':', str_split($norm, 2)));
    }
}
