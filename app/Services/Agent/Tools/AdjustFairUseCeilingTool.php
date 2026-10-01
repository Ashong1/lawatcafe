<?php

namespace App\Services\Agent\Tools;

use App\Models\Setting;
use App\Models\User;
use App\Services\AdaptiveBandwidthService;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\PermissionResolver;
use App\Services\Agent\ToolResult;
use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;

/**
 * The adaptive bandwidth loop's one action, and the only tool that rewrites
 * firewall rules for every device in the shop.
 *
 * Safety is structural: any figure — from the loop, admin chat or a confused
 * model — is clamped to the admin's own min/max before anything is written.
 * The clamp is reported, not silent, or a loop told it set 6 when it set the
 * 5 Mbps floor keeps proposing 6.
 *
 * Auto tier so the scheduled loop can act unattended; set it to
 * "requires confirmation" on Agent Permissions to approve each change (read at
 * call time, no code change).
 */
class AdjustFairUseCeilingTool implements AgentTool
{
    public function __construct(
        protected TrafficShapingService $shaping,
        protected OpnSenseService $opnsense,
        protected AdaptiveBandwidthService $adaptive,
    ) {}

    public function name(): string
    {
        return 'adjustFairUseCeiling';
    }

    public function description(): string
    {
        return 'Change the per-device fair-use bandwidth ceiling on the guest network, in Mbps. '
            .'This is the cap every device gets its own share of, and it applies to the whole guest '
            .'interface including the POS and the kitchen display. Lower it when many guests are '
            .'sharing the line so no one device can crowd the others out; raise it when the network '
            .'is quiet. The value is clamped to the owner-configured bounds, so a request outside '
            .'them is applied at the nearest bound and reported as such.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'mbps' => [
                    'type' => 'number',
                    'description' => 'The new per-device ceiling in Mbps, each direction.',
                ],
                'reason' => [
                    'type' => 'string',
                    'description' => 'Why this change is warranted right now, in one sentence. Recorded and shown to the owner.',
                ],
            ],
            'required' => ['mbps', 'reason'],
        ];
    }

    public function permissionTier(): string
    {
        return PermissionResolver::TIER_AUTO;
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        $requested = $arguments['mbps'] ?? null;

        if (! is_numeric($requested)) {
            return ToolResult::fail('mbps must be a number.');
        }

        $requested = (float) $requested;
        $reason = trim((string) ($arguments['reason'] ?? ''));

        if ($reason === '') {
            return ToolResult::fail('A reason is required — the owner sees it on the traffic page.');
        }

        if (! TrafficShapingService::fairUseSwitchedOn()) {
            return ToolResult::fail('The owner has switched the fair-use ceiling off, so it cannot be adjusted. '
                .'Plan speeds still apply to guests. It can be switched back on from the Traffic page.');
        }

        $bounds = $this->adaptive->bounds();
        $applied = max($bounds['min'], min($bounds['max'], $requested));
        $clamped = abs($applied - $requested) > 0.001;

        $current = (float) Setting::get('bw_fair_use_mbps', '20');

        // Nothing to do, and a shaper reload is not free — say so instead.
        if (abs($applied - $current) < 0.001) {
            return ToolResult::ok(
                sprintf('The ceiling is already %s Mbps — no change made.', $this->format($applied)),
                ['mbps' => $applied, 'changed' => false]
            );
        }

        if (! $this->shaping->applyFairUseCap($applied, $this->opnsense)) {
            // Not recorded. A stored figure describing a cap the gateway is not
            // running is worse than none — see TrafficController::update().
            return ToolResult::fail(
                ($this->shaping->lastError() ?? 'OPNsense rejected the configuration.')
                .' The ceiling was not changed.'
            );
        }

        Setting::set('bw_fair_use_mbps', (string) $applied);
        $this->adaptive->recordChange($applied);

        return ToolResult::ok(
            sprintf(
                'Fair-use ceiling moved from %s to %s Mbps per device.%s Reason: %s',
                $this->format($current),
                $this->format($applied),
                $clamped
                    ? sprintf(' (%s Mbps was requested; clamped to the %s-%s Mbps bounds.)',
                        $this->format($requested), $this->format($bounds['min']), $this->format($bounds['max']))
                    : '',
                $reason
            ),
            [
                'mbps' => $applied,
                'previous' => $current,
                'requested' => $requested,
                'clamped' => $clamped,
                'changed' => true,
                'reason' => $reason,
            ]
        );
    }

    private function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
