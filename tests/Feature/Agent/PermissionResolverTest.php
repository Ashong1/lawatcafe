<?php

namespace Tests\Feature\Agent;

use App\Models\User;
use App\Services\Agent\PermissionResolver;
use App\Services\Agent\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_never_resolves_to_auto_even_when_setting_says_so(): void
    {
        $resolver = app(PermissionResolver::class);
        $tool = app(ToolRegistry::class)->forAudience(ToolRegistry::AUDIENCE_ADMIN)['restockIngredient'];
        $staff = User::factory()->create(['role' => 'staff']);

        $resolver->saveOverrides(['restockIngredient' => PermissionResolver::TIER_AUTO]);

        $this->assertSame(PermissionResolver::TIER_CONFIRM, $resolver->tierFor($tool, $staff));
    }

    public function test_admin_gets_the_configured_tier_unmodified(): void
    {
        $resolver = app(PermissionResolver::class);
        $tool = app(ToolRegistry::class)->forAudience(ToolRegistry::AUDIENCE_ADMIN)['restockIngredient'];
        $admin = User::factory()->create(['role' => 'admin']);

        $resolver->saveOverrides(['restockIngredient' => PermissionResolver::TIER_AUTO]);

        $this->assertSame(PermissionResolver::TIER_AUTO, $resolver->tierFor($tool, $admin));
    }

    public function test_falls_back_to_tool_default_tier_when_unconfigured(): void
    {
        $resolver = app(PermissionResolver::class);
        $tool = app(ToolRegistry::class)->forAudience(ToolRegistry::AUDIENCE_ADMIN)['restockIngredient'];
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertSame(PermissionResolver::TIER_CONFIRM, $resolver->tierFor($tool, $admin));
    }

    public function test_admin_only_default_tool_cannot_be_loosened_by_settings_for_anyone(): void
    {
        $resolver = app(PermissionResolver::class);
        $tool = app(ToolRegistry::class)->forAudience(ToolRegistry::AUDIENCE_ADMIN)['blockDevice'];
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);

        // Simulate an admin fat-fingering the settings UI and "loosening" blockDevice to auto.
        $resolver->saveOverrides(['blockDevice' => PermissionResolver::TIER_AUTO]);

        $this->assertSame(PermissionResolver::TIER_ADMIN_ONLY, $resolver->tierFor($tool, $admin));
        $this->assertSame(PermissionResolver::TIER_ADMIN_ONLY, $resolver->tierFor($tool, $staff));
    }

    public function test_is_configurable_reflects_admin_only_lock(): void
    {
        $resolver = app(PermissionResolver::class);
        $tools = app(ToolRegistry::class)->forAudience(ToolRegistry::AUDIENCE_ADMIN);

        $this->assertFalse($resolver->isConfigurable($tools['blockDevice']));
        $this->assertFalse($resolver->isConfigurable($tools['generateVoucherBatch']));
        $this->assertFalse($resolver->isConfigurable($tools['unblockDevice']));
        $this->assertFalse($resolver->isConfigurable($tools['setSessionBandwidthTier']));
        $this->assertTrue($resolver->isConfigurable($tools['restockIngredient']));
        $this->assertTrue($resolver->isConfigurable($tools['checkStockLevels']));
    }

    public function test_guest_actor_null_gets_no_extra_floor_beyond_configured_tier(): void
    {
        $resolver = app(PermissionResolver::class);
        $tool = app(ToolRegistry::class)->forAudience(ToolRegistry::AUDIENCE_GUEST)['lookupVoucher'];

        $this->assertSame(PermissionResolver::TIER_AUTO, $resolver->tierFor($tool, null));
    }
}
