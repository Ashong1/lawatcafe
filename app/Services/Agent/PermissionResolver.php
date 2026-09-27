<?php

namespace App\Services\Agent;

use App\Models\Setting;
use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;

/**
 * The effective permission tier for a tool call:
 *  1. an admin-editable override (Setting 'agent_tool_permissions'), else the
 *     tool's own permissionTier();
 *  2. the actor's role as a FLOOR — settings can only make a tool stricter; a
 *     staff actor never gets 'auto';
 *  3. tools whose default is 'admin_only' (blockDevice, generateVoucherBatch —
 *     money or network access) are never configurable, or a loosened one could
 *     become staff-confirmable.
 */
class PermissionResolver
{
    public const TIER_AUTO = 'auto';

    public const TIER_CONFIRM = 'confirm';

    public const TIER_ADMIN_ONLY = 'admin_only';

    public const SETTING_KEY = 'agent_tool_permissions';

    private const LEVELS = [
        self::TIER_AUTO => 0,
        self::TIER_CONFIRM => 1,
        self::TIER_ADMIN_ONLY => 2,
    ];

    public function tierFor(AgentTool $tool, ?User $actor): string
    {
        $default = $tool->permissionTier();

        // Hard floor at the tool level: a tool whose own default is admin_only
        // can never be loosened by a Setting override, for anyone.
        if ($default === self::TIER_ADMIN_ONLY) {
            return self::TIER_ADMIN_ONLY;
        }

        $configured = $this->configuredTier($tool->name()) ?? $default;
        $floor = $this->roleFloor($actor);

        $effectiveLevel = max(self::LEVELS[$configured] ?? self::LEVELS[self::TIER_ADMIN_ONLY], $floor);

        return array_search($effectiveLevel, self::LEVELS, true) ?: self::TIER_ADMIN_ONLY;
    }

    /** Whether this tool's tier can be changed via the settings UI at all. */
    public function isConfigurable(AgentTool $tool): bool
    {
        return $tool->permissionTier() !== self::TIER_ADMIN_ONLY;
    }

    /**
     * @return array{auto_approved: string[], confirm_required: string[], admin_only: string[]}
     */
    public function currentOverrides(): array
    {
        $raw = json_decode(Setting::get(self::SETTING_KEY, '{}'), true) ?: [];

        return [
            'auto_approved' => $raw['auto_approved'] ?? [],
            'confirm_required' => $raw['confirm_required'] ?? [],
            'admin_only' => $raw['admin_only'] ?? [],
        ];
    }

    public function saveOverrides(array $tierByToolName): void
    {
        $buckets = ['auto_approved' => [], 'confirm_required' => [], 'admin_only' => []];
        $map = [
            self::TIER_AUTO => 'auto_approved',
            self::TIER_CONFIRM => 'confirm_required',
            self::TIER_ADMIN_ONLY => 'admin_only',
        ];

        foreach ($tierByToolName as $toolName => $tier) {
            if (isset($map[$tier])) {
                $buckets[$map[$tier]][] = $toolName;
            }
        }

        Setting::set(self::SETTING_KEY, json_encode($buckets));
    }

    private function configuredTier(string $toolName): ?string
    {
        $overrides = $this->currentOverrides();

        if (in_array($toolName, $overrides['admin_only'], true)) {
            return self::TIER_ADMIN_ONLY;
        }
        if (in_array($toolName, $overrides['confirm_required'], true)) {
            return self::TIER_CONFIRM;
        }
        if (in_array($toolName, $overrides['auto_approved'], true)) {
            return self::TIER_AUTO;
        }

        return null;
    }

    private function roleFloor(?User $actor): int
    {
        return match ($actor?->role) {
            'staff' => self::LEVELS[self::TIER_CONFIRM],
            default => self::LEVELS[self::TIER_AUTO],
        };
    }
}
