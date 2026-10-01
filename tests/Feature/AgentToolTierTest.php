<?php

namespace Tests\Feature;

use App\Services\Agent\PermissionResolver;
use App\Services\Agent\ToolRegistry;
use App\Services\Agent\Tools\AdjustFairUseCeilingTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A tool that declares a tier PermissionResolver doesn't know is silently
 * treated as admin_only. That is how the adaptive bandwidth loop, meant to act
 * unattended, ended up only ever proposing changes.
 */
class AgentToolTierTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_tool_declares_a_tier_the_resolver_knows(): void
    {
        $valid = [PermissionResolver::TIER_AUTO, PermissionResolver::TIER_CONFIRM, PermissionResolver::TIER_ADMIN_ONLY];

        foreach (app(ToolRegistry::class)->forAudience(ToolRegistry::AUDIENCE_SUPER_ADMIN) as $name => $tool) {
            $this->assertContains($tool->permissionTier(), $valid, "{$name} declares an unknown tier");
        }
    }

    public function test_the_adaptive_ceiling_tool_runs_unattended_by_default(): void
    {
        $tier = app(PermissionResolver::class)->tierFor(app(AdjustFairUseCeilingTool::class), null);

        $this->assertSame(PermissionResolver::TIER_AUTO, $tier);
    }
}
