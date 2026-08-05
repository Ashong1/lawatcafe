<?php

namespace App\Services\Agent;

use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\Tools\BlockDeviceTool;
use App\Services\Agent\Tools\CheckMySessionTool;
use App\Services\Agent\Tools\CheckStockLevelsTool;
use App\Services\Agent\Tools\DraftSupplierPoTool;
use App\Services\Agent\Tools\GenerateVoucherBatchTool;
use App\Services\Agent\Tools\GetActiveSessionsTool;
use App\Services\Agent\Tools\GetAiStackStatusTool;
use App\Services\Agent\Tools\GetAnomalySignalsTool;
use App\Services\Agent\Tools\GetPortalPostureTool;
use App\Services\Agent\Tools\GetRecentSystemErrorsTool;
use App\Services\Agent\Tools\GetSalesSummaryTool;
use App\Services\Agent\Tools\GetScheduledJobHealthTool;
use App\Services\Agent\Tools\GetSystemHealthTool;
use App\Services\Agent\Tools\GetTrafficStatsTool;
use App\Services\Agent\Tools\ListSupplierPoDraftsTool;
use App\Services\Agent\Tools\ListUserAccountsTool;
use App\Services\Agent\Tools\LookupVoucherTool;
use App\Services\Agent\Tools\RestockIngredientTool;
use App\Services\Agent\Tools\SendSupplierPoTool;
use App\Services\Agent\Tools\SetSessionBandwidthTierTool;
use App\Services\Agent\Tools\ShiftHandoffSummaryTool;
use App\Services\Agent\Tools\SuggestCategoryContentTool;
use App\Services\Agent\Tools\UnblockDeviceTool;
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

    /**
     * The developer/system account. Everything an admin can do, plus tools that
     * answer for the estate rather than the shop — see systemToolClasses().
     */
    public const AUDIENCE_SUPER_ADMIN = 'super_admin';

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
            GetTrafficStatsTool::class,
            RestockIngredientTool::class,
            VoidSaleTool::class,
            DraftSupplierPoTool::class,
            SendSupplierPoTool::class,
            ListSupplierPoDraftsTool::class,
            ShiftHandoffSummaryTool::class,
            GetSalesSummaryTool::class,
        ];
    }

    /** @return class-string[] */
    protected function adminToolClasses(): array
    {
        return [
            ...$this->staffToolClasses(),
            GenerateVoucherBatchTool::class,
            BlockDeviceTool::class,
            UnblockDeviceTool::class,
            SetSessionBandwidthTierTool::class,
            SuggestCategoryContentTool::class,
            GetAnomalySignalsTool::class,
        ];
    }

    /**
     * Estate-level tools, super_admin only.
     *
     * Every one of these is read-only. That is a deliberate line, not an
     * oversight: the assistant should be able to tell the owner what is wrong
     * with the system, but changing infrastructure is not something to do from
     * a chat bubble on the strength of a model's reading of a log. The existing
     * admin tools already cover the actions that ARE safe to take that way, and
     * each of those carries its own permission tier.
     *
     * @return class-string[]
     */
    protected function systemToolClasses(): array
    {
        return [
            ...$this->adminToolClasses(),
            GetSystemHealthTool::class,
            GetScheduledJobHealthTool::class,
            GetAiStackStatusTool::class,
            GetPortalPostureTool::class,
            GetRecentSystemErrorsTool::class,
            ListUserAccountsTool::class,
        ];
    }

    /**
     * @return array<string, AgentTool> keyed by tool name()
     */
    public function forAudience(string $audience): array
    {
        $classes = match ($audience) {
            self::AUDIENCE_GUEST => $this->guestToolClasses(),
            self::AUDIENCE_STAFF => $this->staffToolClasses(),
            self::AUDIENCE_ADMIN => $this->adminToolClasses(),
            self::AUDIENCE_SUPER_ADMIN => $this->systemToolClasses(),
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
        // super_admin's list is the superset, so the settings UI stays complete
        // as system tools are added — it used to read adminToolClasses(), which
        // would have silently omitted every one of them.
        return array_unique([...$this->systemToolClasses()]);
    }
}
