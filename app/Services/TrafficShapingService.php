<?php

namespace App\Services;

use App\Models\Voucher;

class TrafficShapingService
{
    public const TIERS = ['free', 'premium'];

    public const DIRECTIONS = ['down', 'up'];

    /**
     * Why the last applyLimits() failed, in words a person can act on.
     *
     * applyLimits() still returns a bool so no caller changes, but "false" on
     * its own produced the least useful message this app has shipped:
     * "OPNsense could not be reached. Check the connection." OPNsense was
     * reachable the entire time — it was answering, and rejecting what we sent.
     * Telling someone to check a working connection sends them to look at the
     * one thing that is fine.
     */
    protected ?string $lastError = null;

    public function lastError(): ?string
    {
        return $this->lastError;
    }

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
        $this->lastError = null;

        $existing = $opnsense->readShaperConfig();
        $sequence = 0;
        $aliasesChanged = false;

        foreach (self::TIERS as $tier) {
            if (! $opnsense->ensureTierAlias($tier)) {
                $this->lastError = "OPNsense rejected the firewall alias for the {$tier} tier (".$opnsense->tierAliasName($tier).').';

                return false;
            }
            $aliasesChanged = true;

            foreach (self::DIRECTIONS as $direction) {
                $sequence++;
                $name = $opnsense->shaperObjectName($tier, $direction);
                $mbps = (float) ($settings["bw_{$tier}_{$direction}"] ?? 0);

                if ($mbps <= 0) {
                    $this->lastError = "No bandwidth is set for the {$tier} tier's {$direction} direction.";

                    return false;
                }

                $pipeUuid = $opnsense->upsertShaperPipe($tier, $direction, $mbps, $existing['pipes'][$name] ?? null);
                if (! $pipeUuid) {
                    $this->lastError = "OPNsense rejected the bandwidth pipe '{$name}'.";

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
                    // By far the most likely failure, and the one that produced
                    // the misleading "could not be reached" report. On this
                    // OPNsense build the shaper rule's source and destination
                    // fields accept only the literal value 'any' — verified
                    // against /api/trafficshaper/settings/getRule, which offers
                    // no alias among their options — so a rule that matches a
                    // tier's alias cannot be created through the API at all.
                    $this->lastError = "OPNsense rejected the shaper rule '{$name}'. "
                        .'This build\'s shaper rules only accept "any" for source and destination, so they cannot match a tier alias. '
                        .'See docs/INFRASTRUCTURE.md — per-tier shaping needs either a manual rule in Firewall > Rules or a newer OPNsense.';

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
