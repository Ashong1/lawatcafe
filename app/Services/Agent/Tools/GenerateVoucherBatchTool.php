<?php

namespace App\Services\Agent\Tools;

use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use App\Services\VoucherService;

class GenerateVoucherBatchTool implements AgentTool
{
    public function __construct(protected VoucherService $vouchers) {}

    public function name(): string
    {
        return 'generateVoucherBatch';
    }

    public function description(): string
    {
        return 'Generate a batch of new Wi-Fi voucher codes with a given quantity and duration in minutes.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'quantity' => ['type' => 'integer', 'description' => 'How many vouchers to generate (1-100)'],
                'duration_minutes' => ['type' => 'integer', 'description' => 'Voucher validity duration in minutes'],
                'tier' => ['type' => 'string', 'enum' => ['free', 'premium'], 'description' => 'Bandwidth tier for the batch. Defaults to free.'],
            ],
            'required' => ['quantity', 'duration_minutes'],
        ];
    }

    public function permissionTier(): string
    {
        return 'admin_only';
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        $quantity = max(1, min(100, (int) ($arguments['quantity'] ?? 0)));
        $duration = max(1, (int) ($arguments['duration_minutes'] ?? 0));

        $tier = in_array($arguments['tier'] ?? null, ['free', 'premium'], true) ? $arguments['tier'] : 'free';

        $result = $this->vouchers->generateBatch($quantity, $duration, $actor?->id, 'ai', $tier);

        return ToolResult::ok(
            "Generated {$result['count']} voucher(s): ".implode(', ', $result['codes']),
            $result,
        );
    }
}
