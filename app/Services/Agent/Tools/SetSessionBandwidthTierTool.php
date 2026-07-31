<?php

namespace App\Services\Agent\Tools;

use App\Models\User;
use App\Models\Voucher;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;

/**
 * A graduated alternative to blockDevice: throttle a session down to the
 * free tier (or restore it to premium) instead of banning it outright.
 */
class SetSessionBandwidthTierTool implements AgentTool
{
    public function __construct(protected TrafficShapingService $shaping, protected OpnSenseService $opnsense) {}

    public function name(): string
    {
        return 'setSessionBandwidthTier';
    }

    public function description(): string
    {
        return "Change a Wi-Fi session's bandwidth tier (free or premium) by voucher code or IP address — e.g. to throttle a device suspected of abuse instead of outright blocking it.";
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'voucher_code' => ['type' => 'string', 'description' => 'The voucher code identifying the session'],
                'ip_address' => ['type' => 'string', 'description' => 'The session IP address, if the voucher code is not known'],
                'tier' => ['type' => 'string', 'enum' => ['free', 'premium'], 'description' => 'The bandwidth tier to move the session to'],
            ],
            'required' => ['tier'],
        ];
    }

    public function permissionTier(): string
    {
        return 'admin_only';
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        $tier = $arguments['tier'] ?? null;
        if (! in_array($tier, ['free', 'premium'], true)) {
            return ToolResult::fail("tier must be 'free' or 'premium'.");
        }

        $code = trim((string) ($arguments['voucher_code'] ?? ''));
        $ip = trim((string) ($arguments['ip_address'] ?? ''));

        if ($code === '' && $ip === '') {
            return ToolResult::fail('Provide either voucher_code or ip_address to identify the session.');
        }

        $voucher = $code !== ''
            ? Voucher::where('code', $code)->first()
            : Voucher::where('is_used', true)->where('ip_address', $ip)->orderBy('used_at', 'desc')->first();

        if (! $voucher) {
            return ToolResult::fail('No matching voucher/session found.');
        }

        $voucher->tier = $tier;
        $voucher->save();

        if (! empty($voucher->ip_address)) {
            $this->shaping->releaseIp($voucher->ip_address, $this->opnsense);
            $this->shaping->assignTier($voucher, $voucher->ip_address, $this->opnsense);
        }

        return ToolResult::ok("Voucher {$voucher->code} moved to the {$tier} tier.", ['voucher_code' => $voucher->code, 'tier' => $tier]);
    }
}
