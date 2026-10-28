<?php

namespace App\Services\Agent\Tools;

use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\PermissionResolver;
use App\Services\Agent\ToolResult;
use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;

/**
 * The Traffic page's "Plan Speeds" form, from chat. Without it the model only
 * had the fair-use ceiling and offered that instead when asked for a plan's
 * upload speed.
 */
class SetPlanSpeedsTool implements AgentTool
{
    private const FIELDS = [
        'free_download_mbps' => 'bw_free_down',
        'free_upload_mbps' => 'bw_free_up',
        'premium_download_mbps' => 'bw_premium_down',
        'premium_upload_mbps' => 'bw_premium_up',
    ];

    public function __construct(
        protected TrafficShapingService $shaping,
        protected OpnSenseService $opnsense,
    ) {}

    public function name(): string
    {
        return 'setPlanSpeeds';
    }

    public function description(): string
    {
        return 'Change the download and/or upload speed of a Wi-Fi plan (free or premium), in Mbps per device. '
            .'Use this whenever the owner asks to change a plan\'s or tier\'s speed. Pass only the figures being '
            .'changed; the others keep their current value. This is not the fair-use ceiling.';
    }

    public function parametersSchema(): array
    {
        $field = fn (string $what) => ['type' => 'number', 'description' => "New {$what} speed in Mbps (0.5-1000)."];

        return [
            'type' => 'object',
            'properties' => [
                'free_download_mbps' => $field('free plan download'),
                'free_upload_mbps' => $field('free plan upload'),
                'premium_download_mbps' => $field('premium plan download'),
                'premium_upload_mbps' => $field('premium plan upload'),
            ],
        ];
    }

    public function permissionTier(): string
    {
        return PermissionResolver::TIER_CONFIRM;
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        $speeds = [];
        foreach (self::FIELDS as $argument => $key) {
            if (! array_key_exists($argument, $arguments) || $arguments[$argument] === null) {
                continue;
            }
            $value = $arguments[$argument];
            if (! is_numeric($value) || $value < 0.5 || $value > 1000) {
                return ToolResult::fail("{$argument} must be a number of Mbps between 0.5 and 1000.");
            }
            $speeds[$key] = (float) $value;
        }

        if ($speeds === []) {
            return ToolResult::fail('Say which plan speed to change.');
        }

        if (! $this->shaping->savePlanSpeeds($speeds, $this->opnsense)) {
            return ToolResult::fail(($this->shaping->lastError() ?? 'The router refused the plan speeds.').' Nothing was changed.');
        }

        $now = $this->shaping->storedPlanSpeeds();

        return ToolResult::ok(sprintf(
            'Plan speeds saved and working. Free: %s download / %s upload. Premium: %s download / %s upload (Mbps, per device).',
            $now['bw_free_down'], $now['bw_free_up'], $now['bw_premium_down'], $now['bw_premium_up']
        ), $now);
    }
}
