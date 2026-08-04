<?php

namespace App\Services;

use App\Models\BannedDevice;
use App\Models\Setting;
use App\Models\StaticIpAssignment;
use Illuminate\Support\Collection;

/**
 * Cross-references OPNsense's raw Layer-2/3 view of the LAN (ARP table +
 * Kea DHCP leases) against what the captive portal itself knows about
 * (its session list) and its own allow-list. Anything holding an IP/MAC on
 * the LAN that the portal has zero session record for — pending or
 * authorized — and that isn't explicitly trusted (allow-listed, a static/VIP
 * reservation, infrastructure, or an ignored IP) is a "ghost": it's
 * consuming network access without the portal ever having logged it.
 *
 * This closes two real blind spots in VoucherController::sessions(), which
 * only ever unions ARP MACs with session MACs: a device whose DHCP lease is
 * still current but whose ARP cache entry has aged out is invisible there
 * entirely, and the allow-list is only ever inferred from passthrough
 * session rows rather than checked directly.
 */
class GhostDeviceDetectionService
{
    public function __construct(protected OpnSenseService $opnsense) {}

    /**
     * @return Collection<int, array{mac_address: string, ip_address: ?string, hostname: ?string, manufacturer: ?string, seen_via: string[], is_banned: bool}>
     */
    public function detect(): Collection
    {
        $normalizeMac = fn (?string $mac) => strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $mac ?? ''));

        $arpRaw = $this->opnsense->getArpTable();
        $arpEntries = collect($arpRaw['arp'] ?? $arpRaw)->filter(fn ($e) => is_array($e));
        $dhcpLeases = collect($this->opnsense->getDhcpLeases());
        $sessions = collect($this->opnsense->listSessions());
        $allowed = $this->opnsense->getAllowedAddresses();

        // Any device the portal has logged at all — pending or authorized —
        // is not a ghost; it's already surfaced on the sessions page.
        $sessionMacs = $sessions->pluck('macAddress')->map($normalizeMac)->filter()->unique();
        $sessionIps = $sessions->map(fn ($s) => str_replace('/32', '', $s['ipAddress'] ?? ''))->filter()->unique();

        $staticAssignments = StaticIpAssignment::all();

        $trustedMacs = collect($allowed['macs'] ?? [])->map($normalizeMac)
            ->merge($staticAssignments->pluck('mac_address')->map($normalizeMac))
            ->filter()->unique();

        $trustedIps = collect($allowed['ips'] ?? [])
            ->merge(explode(',', Setting::get('network_ignored_ips', '192.168.2.251,192.168.2.1')))
            ->merge(Setting::infrastructureIps())
            ->merge($staticAssignments->pluck('ip_address'))
            ->map(fn ($ip) => trim((string) $ip))
            ->filter()->unique();

        // Merge ARP + DHCP into one device-per-MAC view of "what's on the LAN".
        $byMac = collect();

        foreach ($arpEntries as $entry) {
            $mac = $normalizeMac($entry['mac'] ?? $entry['macaddr'] ?? $entry['mac_addr'] ?? null);
            if ($mac === '') {
                continue;
            }

            $byMac->put($mac, [
                'mac_address' => $mac,
                'ip_address' => $entry['ip'] ?? $entry['ipaddress'] ?? $entry['ip_address'] ?? null,
                'hostname' => $entry['hostname'] ?? null,
                'manufacturer' => $entry['manufacturer'] ?? null,
                'seen_via' => ['arp'],
            ]);
        }

        foreach ($dhcpLeases as $lease) {
            $mac = $normalizeMac($lease['hwaddr'] ?? null);
            if ($mac === '') {
                continue;
            }

            $row = $byMac->get($mac);
            if ($row) {
                $row['ip_address'] = $row['ip_address'] ?: ($lease['address'] ?? null);
                $row['hostname'] = ($lease['hostname'] ?? null) ?: $row['hostname'];
                $row['seen_via'][] = 'dhcp';
            } else {
                $row = [
                    'mac_address' => $mac,
                    'ip_address' => $lease['address'] ?? null,
                    'hostname' => $lease['hostname'] ?? null,
                    'manufacturer' => null,
                    'seen_via' => ['dhcp'],
                ];
            }

            $byMac->put($mac, $row);
        }

        $bannedMacs = BannedDevice::pluck('mac_address')->map($normalizeMac)->filter();

        return $byMac
            ->reject(function (array $device) use ($sessionMacs, $sessionIps, $trustedMacs, $trustedIps) {
                if ($sessionMacs->contains($device['mac_address'])) {
                    return true;
                }
                if ($device['ip_address'] && $sessionIps->contains($device['ip_address'])) {
                    return true;
                }
                if ($trustedMacs->contains($device['mac_address'])) {
                    return true;
                }

                return $device['ip_address'] && $trustedIps->contains($device['ip_address']);
            })
            ->map(function (array $device) use ($bannedMacs) {
                $device['is_banned'] = $bannedMacs->contains($device['mac_address']);

                return $device;
            })
            ->sortByDesc('is_banned')
            ->values();
    }
}
