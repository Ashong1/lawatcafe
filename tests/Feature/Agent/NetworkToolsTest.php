<?php

namespace Tests\Feature\Agent;

use App\Models\BannedDevice;
use App\Models\Voucher;
use App\Services\Agent\ToolRegistry;
use App\Services\Agent\Tools\GetTrafficStatsTool;
use App\Services\Agent\Tools\SetSessionBandwidthTierTool;
use App\Services\Agent\Tools\UnblockDeviceTool;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworkToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unblock_device_tool_is_admin_only(): void
    {
        $this->assertSame('admin_only', app(UnblockDeviceTool::class)->permissionTier());
    }

    public function test_get_traffic_stats_tool_is_auto_tier(): void
    {
        $this->assertSame('auto', app(GetTrafficStatsTool::class)->permissionTier());
    }

    public function test_set_session_bandwidth_tier_tool_is_admin_only(): void
    {
        $this->assertSame('admin_only', app(SetSessionBandwidthTierTool::class)->permissionTier());
    }

    public function test_new_network_tools_are_reachable_by_the_right_audiences(): void
    {
        $registry = app(ToolRegistry::class);

        $staffTools = $registry->forAudience(ToolRegistry::AUDIENCE_STAFF);
        $adminTools = $registry->forAudience(ToolRegistry::AUDIENCE_ADMIN);
        $guestTools = $registry->forAudience(ToolRegistry::AUDIENCE_GUEST);

        $this->assertArrayHasKey('getTrafficStats', $staffTools);
        $this->assertArrayHasKey('getTrafficStats', $adminTools);
        $this->assertArrayNotHasKey('getTrafficStats', $guestTools);

        $this->assertArrayNotHasKey('unblockDevice', $staffTools);
        $this->assertArrayHasKey('unblockDevice', $adminTools);
        $this->assertArrayNotHasKey('unblockDevice', $guestTools);

        $this->assertArrayNotHasKey('setSessionBandwidthTier', $staffTools);
        $this->assertArrayHasKey('setSessionBandwidthTier', $adminTools);
        $this->assertArrayNotHasKey('setSessionBandwidthTier', $guestTools);
    }

    public function test_unblock_device_removes_the_banned_device_and_alias_entry(): void
    {
        $device = BannedDevice::create(['mac_address' => 'AA:BB:CC:DD:EE:FF', 'reason' => 'test']);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('removeMacFromBlockAlias')->once()->with('AA:BB:CC:DD:EE:FF')->andReturn(true);
        });

        $result = app(UnblockDeviceTool::class)->execute(['mac_address' => 'AA:BB:CC:DD:EE:FF'], null);

        $this->assertTrue($result->success);
        $this->assertDatabaseMissing('banned_devices', ['id' => $device->id]);
    }

    public function test_unblock_device_fails_for_unknown_mac(): void
    {
        $result = app(UnblockDeviceTool::class)->execute(['mac_address' => 'AA:BB:CC:DD:EE:FF'], null);

        $this->assertFalse($result->success);
    }

    public function test_unblock_device_rejects_an_invalid_mac(): void
    {
        $result = app(UnblockDeviceTool::class)->execute(['mac_address' => 'not-a-mac'], null);

        $this->assertFalse($result->success);
    }

    public function test_get_traffic_stats_returns_interface_data(): void
    {
        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('getInterfaceStats')->once()->andReturn(['wan' => ['inbytes' => 100, 'outbytes' => 200]]);
        });

        $result = app(GetTrafficStatsTool::class)->execute([], null);

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('wan', $result->data['interfaces']);
    }

    public function test_set_session_bandwidth_tier_updates_voucher_and_syncs_alias(): void
    {
        $voucher = Voucher::create([
            'code' => 'LAWA-ABCD', 'duration_minutes' => 60, 'tier' => 'premium',
            'is_used' => true, 'used_at' => now(), 'ip_address' => '192.168.2.50',
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('removeIpFromTierAlias')->andReturn(true);
            $mock->shouldReceive('addIpToTierAlias')->once()->with('free', '192.168.2.50')->andReturn(true);
        });

        $result = app(SetSessionBandwidthTierTool::class)->execute(['voucher_code' => 'LAWA-ABCD', 'tier' => 'free'], null);

        $this->assertTrue($result->success);
        $voucher->refresh();
        $this->assertSame('free', $voucher->tier);
    }

    public function test_set_session_bandwidth_tier_fails_for_unknown_voucher(): void
    {
        $result = app(SetSessionBandwidthTierTool::class)->execute(['voucher_code' => 'NOPE', 'tier' => 'free'], null);

        $this->assertFalse($result->success);
    }

    public function test_set_session_bandwidth_tier_rejects_an_invalid_tier(): void
    {
        $result = app(SetSessionBandwidthTierTool::class)->execute(['voucher_code' => 'LAWA-ABCD', 'tier' => 'gold'], null);

        $this->assertFalse($result->success);
    }
}
