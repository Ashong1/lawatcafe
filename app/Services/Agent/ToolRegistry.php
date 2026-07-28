<?php

namespace App\Services\Agent;

use App\Services\Agent\Tools\BlockDeviceTool;
use App\Services\Agent\Tools\CheckMySessionTool;
use App\Services\Agent\Tools\CheckStockLevelsTool;
use App\Services\Agent\Tools\DraftSupplierPoTool;
use App\Services\Agent\Tools\GenerateVoucherBatchTool;
use App\Services\Agent\Tools\GetActiveSessionsTool;
use App\Services\Agent\Tools\LookupVoucherTool;
use App\Services\Agent\Tools\RestockIngredientTool;
use App\Services\Agent\Tools\SendSupplierPoTool;
use App\Services\Agent\Tools\ShiftHandoffSummaryTool;
use App\Services\Agent\Tools\VoidSaleTool;

/**
 * Per-audience tool allowlists. This is the PRIMARY guest-isolation
 * mechanism: the guest list below is a hardcoded, code-only allowlist of
 * read-only/self-scoped tools. It is intentionally NOT built from Setting
 * (admin-editable config), so guest safety never depends on an admin not
 * misconfiguring something. See ToolCallOrchestrator for the second,
 * execution-level backstop that re-checks this list independent of the
 * model's prompt.
 */
class ToolRegistry
{
    public const AUDIENCE_GUEST = 'guest';
    public const AUDIENCE_STAFF = 'staff';
    public const AUDIENCE_ADMIN = 'admin';

    /** @return class-string[] */
    protected function guestToolClasses(): array
    {
        return [
            LookupVoucherTool::class,
            CheckMySessionTool::class,
        ];
    }

    /** @return class-string[] */
    protected function staffToolClasses(): array
    {
        return [
            ...$this->guestToolClasses(),
            CheckStockLevelsTool::class,
            GetActiveSessionsTool::class,
            RestockIngredientTool::class,
            VoidSaleTool::class,
            DraftSupplierPoTool::class,
            SendSupplierPoTool::class,
            ShiftHandoffSummaryTool::class,
        ];
    }

    /** @return class-string[] */
    protected function adminToolClasses(): array
    {
        return [
            ...$this->staffToolClasses(),
            GenerateVoucherBatchTool::class,
            BlockDeviceTool::class,
        ];
    }

    /**
     * @return array<string, \App\Services\Agent\Contracts\AgentTool> keyed by tool name()
     */
    public function forAudience(string $audience): array
    {
        $classes = match ($audience) {
            self::AUDIENCE_GUEST => $this->guestToolClasses(),
            self::AUDIENCE_STAFF => $this->staffToolClasses(),
            self::AUDIENCE_ADMIN => $this->adminToolClasses(),
            default => [],
        };

        $tools = [];
        foreach ($classes as $class) {
            $tool = app($class);
            $tools[$tool->name()] = $tool;
        }

        return $tools;
    }

    /** All registered tool classes across every audience — used to keep the settings UI in sync. */
    public function allToolClasses(): array
    {
        return array_unique([...$this->adminToolClasses()]);
    }
}
