<?php

namespace App\Services;

use App\Models\Voucher;

class TrafficShapingService
{
    public const TIERS = ['free', 'premium'];

    public const DIRECTIONS = ['down', 'up'];

    /**
     * Provision the complete shaping chain on OPNsense and apply it.
     *
     * A working setup needs all three of these, and the app previously built
     * only the first — which is why a "2 Mbps" free tier measured 8 Mbps:
     *
     *   1. a Dummynet pipe per tier per direction (the bandwidth cap itself),
     *   2. a firewall alias per tier (who the cap applies to), and
     *   3. a shaper rule binding alias -> pipe per direction (what steers a
     *      guest's packets into the pipe at all).
     *
     * Idempotent: existing pipes/rules are matched by description and updated
     * in place, so repeated saves never accumulate duplicates.
     *
     * $settings is the validated array from TrafficController::update
     * (bw_free_up/down, bw_premium_up/down).
     */
    public function applyLimits(array $settings, OpnSenseService $opnsense): bool
    {
        $existing = $opnsense->readShaperConfig();
        $sequence = 0;
        $aliasesChanged = false;

        foreach (self::TIERS as $tier) {
            if (! $opnsense->ensureTierAlias($tier)) {
                return false;
            }
            $aliasesChanged = true;

            foreach (self::DIRECTIONS as $direction) {
                $sequence++;
                $name = $opnsense->shaperObjectName($tier, $direction);
                $mbps = (float) ($settings["bw_{$tier}_{$direction}"] ?? 0);

                if ($mbps <= 0) {
                    return false;
                }

                $pipeUuid = $opnsense->upsertShaperPipe($tier, $direction, $mbps, $existing['pipes'][$name] ?? null);
                if (! $pipeUuid) {
                    return false;
                }

                $ruleUuid = $opnsense->upsertShaperRule(
                    $tier,
                    $direction,
                    $pipeUuid,
                    $opnsense->tierAliasName($tier),
                    $sequence,
                    $existing['rules'][$name] ?? null
                );

                if (! $ruleUuid) {
                    return false;
                }
            }
        }

        if ($aliasesChanged) {
            $opnsense->reconfigureAliases();
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
