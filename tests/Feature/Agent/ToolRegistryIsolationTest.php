<?php

namespace Tests\Feature\Agent;

use App\Services\Agent\ToolRegistry;
use App\Services\Agent\Tools\BlockDeviceTool;
use App\Services\Agent\Tools\DraftSupplierPoTool;
use App\Services\Agent\Tools\GenerateVoucherBatchTool;
use App\Services\Agent\Tools\RestockIngredientTool;
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
    ];

    public function test_guest_registry_never_contains_a_write_tool(): void
    {
        $registry = app(ToolRegistry::class);
        $guestTools = $registry->forAudience(ToolRegistry::AUDIENCE_GUEST);

        foreach ($guestTools as $tool) {
            $this->assertNotContains(
                get_class($tool),
                self::WRITE_TOOL_CLASSES,
                get_class($tool) . ' must never be reachable by the guest audience.'
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
    }

    public function test_admin_registry_contains_every_registered_tool(): void
    {
        $registry = app(ToolRegistry::class);
        $adminNames = array_keys($registry->forAudience(ToolRegistry::AUDIENCE_ADMIN));
        $allNames = array_map(fn ($c) => app($c)->name(), $registry->allToolClasses());

        sort($adminNames);
        sort($allNames);

        $this->assertSame($allNames, $adminNames);
    }

    public function test_unknown_audience_returns_no_tools(): void
    {
        $registry = app(ToolRegistry::class);

        $this->assertSame([], $registry->forAudience('anonymous_hacker'));
    }
}
