<?php

namespace App\Services;

use App\Models\Voucher;

class TrafficShapingService
{
    public const TIERS = ['free', 'premium'];

    /**
     * Push the admin-configured bandwidth caps to OPNsense as the free/premium
     * Dummynet pipes, then apply the change. $settings is the validated array
     * from TrafficController::update (bw_free_up/down, bw_premium_up/down).
     */
    public function applyLimits(array $settings, OpnSenseService $opnsense): bool
    {
        $free = $opnsense->upsertShaperPipe('free', (float) $settings['bw_free_down'], (float) $settings['bw_free_up']);
        $premium = $opnsense->upsertShaperPipe('premium', (float) $settings['bw_premium_down'], (float) $settings['bw_premium_up']);

        if (! $free || ! $premium) {
            return false;
        }

        return $opnsense->reconfigureShaper();
    }

    /**
     * Bind a newly-authorized session's IP to its voucher's bandwidth tier
     * alias, so the matching OPNsense shaper rule applies to its traffic.
     */
    public function assignTier(Voucher $voucher, string $ip, OpnSenseService $opnsense): void
    {
        $opnsense->addIpToTierAlias($voucher->tier ?? 'free', $ip);
    }

    /**
     * Remove an IP from both tier aliases. Idempotent and safe to call even
     * if the IP was never added (e.g. a static/VIP session).
     */
    public function releaseIp(string $ip, OpnSenseService $opnsense): void
    {
        foreach (self::TIERS as $tier) {
            $opnsense->removeIpFromTierAlias($tier, $ip);
        }
    }
}
