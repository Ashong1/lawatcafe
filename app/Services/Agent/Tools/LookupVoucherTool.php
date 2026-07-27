<?php

namespace App\Services\Agent\Tools;

use App\Models\User;
use App\Models\Voucher;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;

/**
 * Safe for the guest audience: only ever looks up a single voucher by the
 * exact code the caller supplies, never lists or enumerates vouchers.
 */
class LookupVoucherTool implements AgentTool
{
    public function name(): string
    {
        return 'lookupVoucher';
    }

    public function description(): string
    {
        return "Check whether a specific Wi-Fi voucher code is valid, used, or expired. Requires the caller to already know the exact code.";
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'code' => ['type' => 'string', 'description' => 'The exact voucher code to look up'],
            ],
            'required' => ['code'],
        ];
    }

    public function permissionTier(): string
    {
        return 'auto';
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        $code = trim((string) ($arguments['code'] ?? ''));
        if ($code === '') {
            return ToolResult::fail('No voucher code provided.');
        }

        $voucher = Voucher::where('code', $code)->first();
        if (!$voucher) {
            return ToolResult::fail("No voucher found with code {$code}.");
        }

        if (!$voucher->is_used) {
            return ToolResult::ok("Voucher {$code} is valid and unused ({$voucher->duration_minutes} min).", [
                'status' => 'unused',
                'duration_minutes' => $voucher->duration_minutes,
            ]);
        }

        $expiresAt = $voucher->used_at?->copy()->addMinutes($voucher->duration_minutes);
        $expired = $expiresAt && now()->greaterThan($expiresAt);

        return ToolResult::ok(
            $expired ? "Voucher {$code} has expired." : "Voucher {$code} is currently active until {$expiresAt?->toDayDateTimeString()}.",
            ['status' => $expired ? 'expired' : 'active', 'expires_at' => $expiresAt?->toIso8601String()],
        );
    }
}
