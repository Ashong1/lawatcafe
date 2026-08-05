<?php

namespace Tests\Feature\Agent;

use App\Services\Agent\ToolRegistry;
use App\Services\Agent\Tools\BlockDeviceTool;
use App\Services\Agent\Tools\DraftSupplierPoTool;
use App\Services\Agent\Tools\GenerateVoucherBatchTool;
use App\Services\Agent\Tools\RestockIngredientTool;
use App\Services\Agent\Tools\SendSupplierPoTool;
use App\Services\Agent\Tools\SetSessionBandwidthTierTool;
use App\Services\Agent\Tools\UnblockDeviceTool;
use App\Services\Agent\Tools\VoidSaleTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolRegistryIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const WRITE_TOOL_CLASSES = [
        GenerateVoucherBatchTool::class,
        BlockDeviceTool::class,
        RestockIngredientTool::class,
        VoidSaleTool::class,
        DraftSupplierPoTool::class,
        SendSupplierPoTool::class,
        UnblockDeviceTool::class,
        SetSessionBandwidthTierTool::class,
    ];

    public function test_guest_registry_never_contains_a_write_tool(): void
    {
        $registry = app(ToolRegistry::class);
        $guestTools = $registry->forAudience(ToolRegistry::AUDIENCE_GUEST);

        foreach ($guestTools as $tool) {
            $this->assertNotContains(
                get_class($tool),
                self::WRITE_TOOL_CLASSES,
                get_class($tool).' must never be reachable by the guest audience.'
            );
        }
    }

    public function test_guest_registry_is_exactly_the_expected_read_only_self_scoped_set(): void
    {
        $registry = app(ToolRegistry::class);
        $names = array_keys($registry->forAudience(ToolRegistry::AUDIENCE_GUEST));
        sort($names);

        $this->assertSame(['checkMySession', 'lookupVoucher'], $names);
    }

    public function test_staff_registry_never_contains_admin_only_default_tools(): void
    {
        $registry = app(ToolRegistry::class);
        $staffTools = $registry->forAudience(ToolRegistry::AUDIENCE_STAFF);

        $this->assertArrayNotHasKey('generateVoucherBatch', $staffTools);
        $this->assertArrayNotHasKey('blockDevice', $staffTools);
        $this->assertArrayNotHasKey('unblockDevice', $staffTools);
        $this->assertArrayNotHasKey('setSessionBandwidthTier', $staffTools);
    }

    /**
     * super_admin is the superset now, not admin. The point of this assertion
     * is unchanged: no tool may be registered yet unreachable from every
     * audience, which would leave it silently absent from the settings UI too
     * (SettingController reads allToolClasses()).
     */
    public function test_the_super_admin_registry_contains_every_registered_tool(): void
    {
        $registry = app(ToolRegistry::class);
        $superAdminNames = array_keys($registry->forAudience(ToolRegistry::AUDIENCE_SUPER_ADMIN));
        $allNames = array_map(fn ($c) => app($c)->name(), $registry->allToolClasses());

        sort($superAdminNames);
        sort($allNames);

        $this->assertSame($allNames, $superAdminNames);
    }

    /**
     * The estate-level tools exist for the account responsible for the
     * deployment. An ordinary admin runs the shop and has no business reading
     * the application error log or the server's disk usage from a chat bubble.
     */
    public function test_system_tools_are_reachable_only_by_super_admin(): void
    {
        $registry = app(ToolRegistry::class);

        $systemOnly = [
            'getSystemHealth',
            'getScheduledJobHealth',
            'getAiStackStatus',
            'getPortalPosture',
            'getRecentSystemErrors',
            'listUserAccounts',
        ];

        $superAdmin = $registry->forAudience(ToolRegistry::AUDIENCE_SUPER_ADMIN);

        foreach ([ToolRegistry::AUDIENCE_GUEST, ToolRegistry::AUDIENCE_STAFF, ToolRegistry::AUDIENCE_ADMIN] as $audience) {
            $tools = $registry->forAudience($audience);
            foreach ($systemOnly as $name) {
                $this->assertArrayNotHasKey($name, $tools, "{$name} must not be reachable by {$audience}.");
            }
        }

        foreach ($systemOnly as $name) {
            $this->assertArrayHasKey($name, $superAdmin);
        }
    }

    /** Everything an admin can do, super_admin can do too — a superset, not a swap. */
    public function test_super_admin_keeps_every_admin_tool(): void
    {
        $registry = app(ToolRegistry::class);

        $missing = array_diff(
            array_keys($registry->forAudience(ToolRegistry::AUDIENCE_ADMIN)),
            array_keys($registry->forAudience(ToolRegistry::AUDIENCE_SUPER_ADMIN))
        );

        $this->assertSame([], $missing);
    }

    /**
     * Every estate tool is read-only by design: the assistant should be able to
     * say what is wrong with the system, but changing infrastructure from a chat
     * bubble on the strength of a model's reading of a log is a different risk
     * entirely. A future tool that mutates must be a deliberate decision, not
     * something that slips in.
     */
    public function test_system_tools_are_all_read_only(): void
    {
        $registry = app(ToolRegistry::class);

        $adminNames = array_keys($registry->forAudience(ToolRegistry::AUDIENCE_ADMIN));

        foreach ($registry->forAudience(ToolRegistry::AUDIENCE_SUPER_ADMIN) as $name => $tool) {
            if (in_array($name, $adminNames, true)) {
                continue; // inherited admin tools keep their own tiers
            }

            $this->assertStringContainsString(
                'Read-only.',
                $tool->description(),
                "{$name} is a system tool, so its description must state it is read-only."
            );
        }
    }

    public function test_unknown_audience_returns_no_tools(): void
    {
        $registry = app(ToolRegistry::class);

        $this->assertSame([], $registry->forAudience('anonymous_hacker'));
    }
}
