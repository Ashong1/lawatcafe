<?php

namespace App\Services;

use App\Models\Setting;
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

        $shaper = $opnsense->readShaperConfig();
        $existingPipes = $shaper['pipes'] ?? [];
        $existingShaperRules = $shaper['rules'] ?? [];
        $sequence = 10;

        foreach (self::DIRECTIONS as $direction) {
            $sequence++;
            $name = $opnsense->shaperObjectName(self::FAIR_USE_TIER, $direction);

            $pipeUuid = $opnsense->upsertShaperPipe(self::FAIR_USE_TIER, $direction, $mbps, $existingPipes[$name] ?? null);
            if (! $pipeUuid) {
                $this->lastError = "OPNsense rejected the bandwidth pipe '{$name}'.";

                return false;
            }

            // A SHAPER rule, deliberately back here after a failed experiment.
            // Moving this cap to the filter table looked right on paper — one
            // table, one sequence, tiers override the catch-all — but a filter
            // rule carrying shaper1 does not shape at all on this build:
            // measured 60/56 Mbit on a capped connection with the rules applied
            // and OPNsense reporting OK. The Shaper table is the only one whose
            // rules demonstrably take effect here, so the ceiling lives here and
            // the shop stays protected.
            $ruleUuid = $opnsense->upsertShaperRule(
                self::FAIR_USE_TIER,
                $direction,
                $pipeUuid,
                null,
                $sequence,
                $existingShaperRules[$name] ?? null
            );

            if (! $ruleUuid) {
                $this->lastError = "OPNsense rejected the fair-use rule '{$name}'.";

                return false;
            }
        }

        if (! $opnsense->reconfigureShaper()) {
            $this->lastError = 'The pipes were written but the shaper would not reload.';

            return false;
        }

        return true;
    }

    /**
     * Switch the fair-use rules off, leaving the pipes and the stored figure in
     * place so turning it back on is one click. Plan caps are separate rules
     * and keep working.
     */
    public function disableFairUseCap(OpnSenseService $opnsense): bool
    {
        $this->lastError = null;
        $config = $opnsense->readShaperConfig();
        $sequence = 10;

        foreach (self::DIRECTIONS as $direction) {
            $sequence++;
            $name = $opnsense->shaperObjectName(self::FAIR_USE_TIER, $direction);
            $pipe = $config['pipes'][$name] ?? null;
            $rule = $config['rules'][$name] ?? null;

            if (! $pipe || ! $rule) {
                continue; // never set up, so already off
            }

            if (! $opnsense->upsertShaperRule(self::FAIR_USE_TIER, $direction, $pipe, null, $sequence, $rule, false)) {
                $this->lastError = "OPNsense rejected switching off the rule '{$name}'.";

                return false;
            }
        }

        if (! $opnsense->reconfigureShaper()) {
            $this->lastError = 'The rules were switched off but the shaper would not reload.';

            return false;
        }

        return true;
    }

    /** The owner's on/off choice; Barista AI may only move the ceiling while it is on. */
    public static function fairUseSwitchedOn(): bool
    {
        return Setting::get('bw_fair_use_enabled', '1') === '1';
    }

    /**
     * The caps the gateway is enforcing right now, read from OPNsense rather
     * than from settings — what the Traffic page shows, so it agrees with a
     * speed test. Stored figures fill in only when OPNsense can't be read, and
     * 'reachable' says so.
     *
     * A plan counts as in force when both of its pipes and rules exist; its
     * rules are switched off while nobody is on the plan (see syncTierRules),
     * which is idle rather than broken.
     *
     * @param  array<string, string>  $stored  bw_{tier}_{direction} settings
     * @return array{reachable: bool, plans: array<string, array{down: float, up: float, provisioned: bool, guests: int}>, fair_use: array{enforced: bool, mbps: float|null}}
     */
    public function liveStatus(OpnSenseService $opnsense, array $stored): array
    {
        $live = $opnsense->readShaperStatus();
        $plans = [];

        foreach (self::TIERS as $tier) {
            $plan = ['provisioned' => $live !== null, 'guests' => 0];

            foreach (self::DIRECTIONS as $direction) {
                $name = $opnsense->shaperObjectName($tier, $direction);
                $pipe = $live['pipes'][$name] ?? null;
                $plan[$direction] = $pipe['mbps'] ?? (float) ($stored["bw_{$tier}_{$direction}"] ?? 0);

                if ($live !== null && (! $pipe || ! isset($live['rules'][$name]))) {
                    $plan['provisioned'] = false;
                }
            }

            $down = $live['rules'][$opnsense->shaperObjectName($tier, 'down')] ?? null;
            if ($down && $down['enabled']) {
                $plan['guests'] = count($down['ips']);
            }

            $plans[$tier] = $plan;
        }

        $fairName = $opnsense->shaperObjectName(self::FAIR_USE_TIER, 'down');
        $fairRule = $live['rules'][$fairName] ?? null;
        $fairPipe = $live['pipes'][$fairName] ?? null;

        return [
            'reachable' => $live !== null,
            'plans' => $plans,
            'fair_use' => [
                'enforced' => (bool) ($fairRule['enabled'] ?? false) && (bool) ($fairPipe['enabled'] ?? false),
                'mbps' => $fairPipe['mbps'] ?? null,
            ],
        ];
    }

    /**
     * Provision the complete shaping chain on OPNsense and apply it. A cap
     * needs all three, or a "2 Mbps" tier measures full line speed:
     *   1. a Dummynet pipe per tier per direction (the cap),
     *   2. a firewall alias per tier (who it applies to),
     *   3. a shaper rule per direction binding alias -> pipe (what steers
     *      packets into it).
     * Idempotent: matched by description and updated in place.
     *
     * $settings: validated bw_free_up/down, bw_premium_up/down from
     * TrafficController::updatePlans.
     */
    public function applyLimits(array $settings, OpnSenseService $opnsense): bool
    {
        $this->lastError = null;

        $existing = $opnsense->readShaperConfig();
        $aliasesChanged = false;
        $pipes = [];

        foreach (self::TIERS as $tier) {
            if (! $opnsense->ensureTierAlias($tier)) {
                $this->lastError = "OPNsense rejected the firewall alias for the {$tier} tier (".$opnsense->tierAliasName($tier).').';

                return false;
            }
            $aliasesChanged = true;

            foreach (self::DIRECTIONS as $direction) {
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
                $pipes[$name] = $pipeUuid;
            }
        }

        if ($aliasesChanged) {
            $opnsense->reconfigureAliases();
        }

        // The rules that steer each plan's guests into its pipes.
        return $this->syncTierRules($opnsense, $pipes, $existing['rules'] ?? []);
    }

    /**
     * Bind a newly-authorized session's IP to its voucher's bandwidth tier
     * alias, so the matching OPNsense shaper rule applies to its traffic.
     */
    public function assignTier(Voucher $voucher, string $ip, OpnSenseService $opnsense): void
    {
        if ($opnsense->isProtectedIp($ip)) {
            return; // shop equipment is never on a guest plan
        }

        $tier = $voucher->tier ?? 'free';
        // Off the other plan first: an upgrade/downgrade must not leave the
        // address capped by both.
        foreach (array_diff(self::TIERS, [$tier]) as $other) {
            $opnsense->removeIpFromTierAlias($other, $ip);
        }
        $opnsense->addIpToTierAlias($tier, $ip);
        $this->syncTierRules($opnsense);
    }

    /**
     * Write each plan's member addresses into its Shaper rules — the only
     * rules that actually limit speed on this gateway. The tier aliases stay
     * the record of who is on which plan; shaper rules can't name an alias,
     * but on 25.7 their source/destination take a list of addresses. A plan
     * with no members gets its rules switched off (the field can't be empty).
     *
     * Tier rules run before the shop-wide fair-use rule (sequence 11-12), so a
     * guest on a plan gets that plan's cap.
     */
    public function syncTierRules(OpnSenseService $opnsense, array $pipes = [], ?array $rules = null): bool
    {
        $this->lastError = null;
        // applyLimits() passes what it just wrote, saving a re-read.
        $config = $pipes && $rules !== null ? ['pipes' => $pipes, 'rules' => $rules] : $opnsense->readShaperConfig();
        $sequence = 0;

        foreach (self::TIERS as $tier) {
            $ips = collect($opnsense->listAliasMembers($opnsense->tierAliasName($tier)))
                ->reject(fn ($ip) => $opnsense->isProtectedIp($ip))
                ->values()
                ->all();

            foreach (self::DIRECTIONS as $direction) {
                $sequence++;
                $name = $opnsense->shaperObjectName($tier, $direction);
                $pipe = $config['pipes'][$name] ?? null;
                if (! $pipe) {
                    $this->lastError = "There is no '{$name}' speed limit on OPNsense yet — save the plan speeds on the Traffic page first.";

                    return false;
                }

                $ok = $opnsense->upsertShaperRule($tier, $direction, $pipe, $ips ? implode(',', $ips) : null, $sequence, $config['rules'][$name] ?? null, (bool) $ips);
                if (! $ok) {
                    $this->lastError = "OPNsense rejected the speed rule '{$name}'.";

                    return false;
                }
            }
        }

        return $opnsense->reconfigureShaper();
    }

    /**
     * Remove an IP from both tier aliases. Idempotent and safe to call even
     * if the IP was never added (e.g. a static/VIP session).
     */
    /**
     * Drop an address from every tier alias.
     *
     * Returns whether every removal that mattered succeeded. Once a filter rule PASSES
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

        $this->syncTierRules($opnsense);

        return $ok;
    }

    /**
     * Remove tier-alias members that no longer have a live session.
     *
     * A failed removal, an OPNsense restart or a session reaped outside the
     * app can leave an address behind, and once a PASS rule matches the alias
     * that's a guest with internet after their time is up. The firewall's
     * session list is the authority — a voucher says what was paid for, only
     * OPNsense knows who is connected.
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

                if (in_array($ip, $liveIps, true) && ! $opnsense->isProtectedIp($ip)) {
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

        // Always re-sync: it also repairs rules edited or lost on the OPNsense side.
        $this->syncTierRules($opnsense);

        return ['checked' => $checked, 'removed' => $removed, 'failed' => $failed];
    }
}
