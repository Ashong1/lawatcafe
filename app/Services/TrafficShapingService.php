<?php

namespace App\Services;

use App\Models\Voucher;
use Illuminate\Support\Facades\Log;

class TrafficShapingService
{
    public const TIERS = ['free', 'premium'];

    public const DIRECTIONS = ['down', 'up'];

    /** Named so it is never confused with the per-tier objects beside it. */
    public const FAIR_USE_TIER = 'fairuse';

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
     * Provision the one cap this OPNsense build can actually enforce: a single
     * ceiling per device across the whole guest interface.
     *
     * The same objects `shaper:fair-use` builds, so the page and the command
     * cannot drift — see ProvisionFairUseCap for why a shop-wide rule is safe
     * here and why the figure must sit well above what the shop's own equipment
     * uses.
     */
    public function applyFairUseCap(float $mbps, OpnSenseService $opnsense): bool
    {
        $this->lastError = null;

        if ($mbps <= 0) {
            $this->lastError = 'The fair-use ceiling must be greater than zero.';

            return false;
        }

        $existing = $opnsense->readShaperConfig();
        $sequence = 0;

        foreach (self::DIRECTIONS as $direction) {
            $sequence++;
            $name = $opnsense->shaperObjectName(self::FAIR_USE_TIER, $direction);

            $pipeUuid = $opnsense->upsertShaperPipe(self::FAIR_USE_TIER, $direction, $mbps, $existing['pipes'][$name] ?? null);
            if (! $pipeUuid) {
                $this->lastError = "OPNsense rejected the bandwidth pipe '{$name}'.";

                return false;
            }

            // null alias = source/destination "any". The per-IP mask on the pipe
            // is what keeps this a ceiling per device rather than a shared total.
            $ruleUuid = $opnsense->upsertShaperRule(self::FAIR_USE_TIER, $direction, $pipeUuid, null, $sequence, $existing['rules'][$name] ?? null);
            if (! $ruleUuid) {
                $this->lastError = "OPNsense rejected the shaper rule '{$name}'.";

                return false;
            }
        }

        if (! $opnsense->reconfigureShaper()) {
            $this->lastError = 'The pipes and rules were written, but the shaper would not reload — they are staged, not live.';

            return false;
        }

        return true;
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
    /**
     * Drop an address from every tier alias.
     *
     * Returns whether every removal that mattered actually succeeded, rather
     * than swallowing the result as it used to. Once a filter rule PASSES
     * traffic for alias members, a silent failure here stops being cosmetic:
     * the address keeps its access after the portal has dropped the session.
     * reconcileTierMembership() is the backstop, but the caller should know.
     */
    public function releaseIp(string $ip, OpnSenseService $opnsense): bool
    {
        $ok = true;

        foreach (self::TIERS as $tier) {
            // Always attempt the removal — never gate it on first reading the
            // alias. A read that fails returns an empty list, which would look
            // exactly like "not a member" and skip the removal silently. That
            // is precisely the state this whole mechanism exists to prevent, so
            // it must not be reachable through an unrelated API hiccup.
            if (! $opnsense->removeIpFromTierAlias($tier, $ip)) {
                Log::error("Traffic shaping: could not remove {$ip} from the {$tier} tier alias — it keeps that tier's treatment until the next reconcile.");
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * Remove tier-alias members that no longer have a live session.
     *
     * The guarantee the filter rules rest on. Membership is written when a
     * guest activates and cleared when they disconnect or expire, but a failed
     * removal, an OPNsense restart mid-release, or a session reaped outside the
     * app all leave an address behind. While the alias only drove a shaper
     * pipe that was harmless; once a PASS rule matches on it, a stale member is
     * a guest who keeps working internet after their time is up.
     *
     * Authoritative source is the firewall's own session list, not the voucher
     * table — a voucher says what somebody paid for, only OPNsense knows who is
     * actually connected right now.
     *
     * @return array{checked:int, removed:int, failed:int}
     */
    public function reconcileTierMembership(OpnSenseService $opnsense): array
    {
        $liveIps = collect($opnsense->listSessions())
            ->map(fn ($s) => str_replace('/32', '', $s['ipAddress'] ?? ''))
            ->filter()
            ->unique()
            ->all();

        $checked = 0;
        $removed = 0;
        $failed = 0;

        foreach (self::TIERS as $tier) {
            $alias = $opnsense->tierAliasName($tier);

            foreach ($opnsense->listAliasMembers($alias) as $ip) {
                $checked++;

                if (in_array($ip, $liveIps, true)) {
                    continue;
                }

                if ($opnsense->removeIpFromTierAlias($tier, $ip)) {
                    Log::info("Traffic shaping: reconciled {$ip} out of the {$tier} tier alias — no live session.");
                    $removed++;
                } else {
                    Log::error("Traffic shaping: {$ip} has no live session but could not be removed from the {$tier} tier alias.");
                    $failed++;
                }
            }
        }

        return ['checked' => $checked, 'removed' => $removed, 'failed' => $failed];
    }
}
