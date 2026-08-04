<?php

namespace App\Services\Agent\Tools;

use App\Models\User;
use App\Models\Voucher;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;

/**
 * Guest-safe: only ever resolves the voucher tied to the requester's OWN
 * connection ($context['ip']/$context['mac'], set by the calling controller
 * from the actual request — never from model-supplied arguments), so a guest
 * cannot use this to probe another device's session.
 */
class CheckMySessionTool implements AgentTool
{
    public function name(): string
    {
        return 'checkMySession';
    }

    public function description(): string
    {
        return "Check the caller's own current Wi-Fi session status and remaining time.";
    }

    public function parametersSchema(): array
    {
        return ['type' => 'object', 'properties' => [], 'required' => []];
    }

    public function permissionTier(): string
    {
        return 'auto';
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        $ip = $context['ip'] ?? null;
        $mac = $context['mac'] ?? null;

        if (! $ip && ! $mac) {
            return ToolResult::fail('No session context available for this request.');
        }

        $voucher = Voucher::where('is_used', true)
            ->where(function ($q) use ($ip, $mac) {
                $q->where('ip_address', $ip);
                if ($mac) {
                    $q->orWhere('mac_address_hash', Voucher::hashMac($mac));
                }
            })
            ->latest('used_at')
            ->first();

        if (! $voucher) {
            return ToolResult::ok("You don't have an active Wi-Fi session right now.", ['active' => false]);
        }

        $expiresAt = $voucher->used_at->copy()->addMinutes($voucher->duration_minutes);
        if (now()->greaterThan($expiresAt)) {
            return ToolResult::ok('Your last Wi-Fi session has expired.', ['active' => false]);
        }

        $minutesLeft = (int) now()->diffInMinutes($expiresAt);

        return ToolResult::ok("You have about {$minutesLeft} minute(s) of Wi-Fi left.", [
            'active' => true,
            'minutes_left' => $minutesLeft,
        ]);
    }
}
