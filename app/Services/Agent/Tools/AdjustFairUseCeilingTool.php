<?php

namespace App\Services\Agent\Tools;

use App\Models\Setting;
use App\Models\User;
use App\Services\AdaptiveBandwidthService;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;

/**
 * The one action in the adaptive bandwidth loop, and the only tool in this
 * system that rewrites firewall rules affecting every device in the shop.
 *
 * The safety here is structural rather than a matter of prompting. Whatever
 * figure arrives — from the scheduled loop, from an admin in chat, or from a
 * model that has misread the situation entirely — it is clamped to the admin's
 * own min/max before anything is written. There is no argument that reaches
 * OPNsense unbounded, so the worst a confused model can do is move the ceiling
 * to one end of a range its owner already approved.
 *
 * The clamp is reported rather than applied silently: a loop told it set 6 when
 * it actually set the 5 Mbps floor would keep proposing 6 forever.
 *
 * Tiered auto_approved so the scheduled loop can act without a human awake at
 * 8pm. An owner who would rather approve each change can move it to
 * confirm_required on the Agent Permissions page — the tier is read from
 * settings at call time, so that switch needs no code change.
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
        return 'auto_approved';
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
