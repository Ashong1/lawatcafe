<?php

namespace App\Services;

use App\Models\BannedDevice;
use App\Models\Setting;
use App\Models\StaticIpAssignment;
use Illuminate\Support\Collection;

/**
 * Finds "ghosts": devices holding an IP/MAC on the LAN (ARP table + Kea
 * leases) that the captive portal has no session for and that aren't trusted
 * (allow-listed, a static/VIP reservation, infrastructure or ignored) — using
 * the network without the portal ever logging it.
 *
 * Covers what VoucherController::sessions() can't see: a device with a live
 * DHCP lease but an aged-out ARP entry, and the allow-list checked directly
 * rather than inferred from passthrough sessions.
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

        $arpEntries = collect(OpnSenseService::withoutWan($this->opnsense->getArpTable()));
        $dhcpLeases = collect($this->opnsense->getDhcpLeases());
        $sessions = collect($this->opnsense->listSessions());
        $allowed = $this->opnsense->getAllowedAddresses();

        // Any device the portal has logged at all — pending or authorized —
        // is not a ghost; it's already surfaced on the sessions page.
        $sessionMacs = $sessions->pluck('macAddress')->map($normalizeMac)->filter()->unique();
        $sessionIps = $sessions->map(fn ($s) => str_replace('/32', '', $s['ipAddress'] ?? ''))->filter()->unique();

        $staticAssignments = StaticIpAssignment::all();

        $trustedMacs = collect($allowed['macs'])->map($normalizeMac)
            ->merge($staticAssignments->pluck('mac_address')->map($normalizeMac))
            ->filter()->unique();

        $trustedIps = collect($allowed['ips'])
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
