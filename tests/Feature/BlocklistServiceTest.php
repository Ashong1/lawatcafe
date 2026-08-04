<?php

namespace Tests\Feature;

use App\Models\BannedDevice;
use App\Services\BlocklistService;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlocklistServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ban_device_creates_a_record_and_syncs_the_opnsense_alias(): void
    {
        $opnsense = $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('addMacToBlockAlias')->once()->with('AA:BB:CC:DD:EE:FF');
        });

        $device = app(BlocklistService::class)->banDevice('AA:BB:CC:DD:EE:FF', 'Abuse', 'phone-1', $opnsense);

        $this->assertDatabaseHas('banned_devices', [
            'id' => $device->id,
            'mac_address_hash' => BannedDevice::hashMac('AA:BB:CC:DD:EE:FF'),
            'reason' => 'Abuse',
            'hostname' => 'phone-1',
        ]);
    }

    public function test_unban_device_removes_the_record_and_the_opnsense_alias(): void
    {
        $device = BannedDevice::create(['mac_address' => 'AA:BB:CC:DD:EE:FF']);

        $opnsense = $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('removeMacFromBlockAlias')->once()->with('AA:BB:CC:DD:EE:FF');
        });

        app(BlocklistService::class)->unbanDevice($device, $opnsense);

        $this->assertDatabaseMissing('banned_devices', ['id' => $device->id]);
    }

    public function test_block_and_kick_bans_a_new_device_and_disconnects_its_session(): void
    {
        $opnsense = $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('addMacToBlockAlias')->once()->with('AA:BB:CC:DD:EE:FF');
            $mock->shouldReceive('disconnectDevice')->once()->with('session-123')->andReturn(true);
        });

        $result = app(BlocklistService::class)->blockAndKick('AA:BB:CC:DD:EE:FF', 'session-123', 'AI-flagged abuse', $opnsense);

        $this->assertTrue($result['banned']);
        $this->assertTrue($result['kicked']);
        $this->assertDatabaseHas('banned_devices', ['mac_address_hash' => BannedDevice::hashMac('AA:BB:CC:DD:EE:FF')]);
    }

    public function test_block_and_kick_reports_already_banned_without_creating_a_duplicate(): void
    {
        BannedDevice::create(['mac_address' => 'AA:BB:CC:DD:EE:FF']);

        $opnsense = $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('disconnectDevice')->once()->andReturn(false);
        });

        $result = app(BlocklistService::class)->blockAndKick('AA:BB:CC:DD:EE:FF', 'session-123', null, $opnsense);

        $this->assertFalse($result['banned']);
        $this->assertFalse($result['kicked']);
        $this->assertSame('Device was already banned, but the live session could not be disconnected.', $result['message']);
        $this->assertDatabaseCount('banned_devices', 1);
    }

    public function test_block_and_kick_without_a_session_id_never_attempts_a_disconnect(): void
    {
        $opnsense = $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('addMacToBlockAlias')->once();
            $mock->shouldNotReceive('disconnectDevice');
        });

        $result = app(BlocklistService::class)->blockAndKick('AA:BB:CC:DD:EE:FF', null, null, $opnsense);

        $this->assertTrue($result['banned']);
        $this->assertFalse($result['kicked']);
        $this->assertSame('Device banned.', $result['message']);
    }
}
