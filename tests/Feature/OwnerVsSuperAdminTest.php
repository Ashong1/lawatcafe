<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owner (Oct 30): the admin (shop owner) isn't technical, so complicated
 * configuration belongs to the super admin — blocked on the server, not just
 * hidden. The owner confirms what Barista AI suggests instead.
 */
class OwnerVsSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_technical_actions_are_super_admin_only(): void
    {
        $owner = $this->owner();
        $posts = [
            route('network.traffic.plans'), route('network.traffic.update'), route('network.traffic.line'),
            route('network.traffic.adaptive'), route('network.traffic.fair-use.off'), route('network.blocklist.store'),
            route('network.allowed-addresses.macs.store'), route('network.allowed-addresses.ips.store'),
            route('admin.settings.ai-providers.models.test', 'groq'), route('admin.settings.ai-providers.models.check', 'groq'),
            route('admin.settings.ai-providers.models.add', 'groq'), route('backups.restore', 'lawat_db_2026-10-30_010000.sql.gz'),
        ];
        foreach ($posts as $url) {
            $this->actingAs($owner)->post($url)->assertRedirect(route('dashboard'));
        }
        $this->actingAs($owner)->get(route('admin.settings.ai-providers.models.scan', 'groq'))->assertRedirect(route('dashboard'));
        $this->actingAs($owner)->post(route('system-power.act', 'shutdown-server'), ['password' => 'password', 'confirm' => 'SHUT DOWN'])->assertForbidden();
    }

    public function test_the_owner_sees_plain_pages(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('admin.settings.ai-providers'))->assertOk()
            ->assertSee('Barista AI is connected')->assertDontSee('Test all models')->assertDontSee('Scan for more models')->assertDontSee('gemini-3.5-flash');
        $this->actingAs($owner)->get(route('network.traffic'))->assertOk()
            ->assertDontSee('Keep for the shop (Mbps)')->assertDontSee('Speed limit per device')->assertDontSee('id="plan-speeds-form"', false);
        $this->actingAs($owner)->get(route('dashboard'))->assertSee('id="power-restart-app"', false)->assertDontSee('id="power-shutdown-server"', false);
        $this->actingAs($owner)->get(route('network.trusted-devices'))->assertDontSee('Add by address');
        $this->actingAs($owner)->get(route('network.blocklist'))->assertOk()->assertDontSee('Block a device by hand');
        $this->actingAs($owner)->get(route('security'))->assertOk()->assertDontSee('What Restore does')->assertSee('Remote access');

        $super = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($super)->get(route('admin.settings.ai-providers'))->assertSee('Scan for more models');
        $this->actingAs($super)->get(route('network.traffic'))->assertSee('Keep for the shop (Mbps)');
    }

    public function test_the_owner_confirms_the_suggested_plan_speeds(): void
    {
        Setting::set('bw_free_down', '15');
        Setting::set('bw_premium_down', '30');
        Setting::set('bw_shop_reserve_mbps', '5');
        // Two slow days in a row: 15 Mbps line, 10 usable after the shop's share.
        Setting::set('internet_speed_tests', json_encode([['down' => 15.0, 'up' => 5.0, 'at' => now()->toIso8601String()], ['down' => 14.0, 'up' => 5.0, 'at' => now()->subDay()->toIso8601String()]]));
        $this->mock(TrafficShapingService::class, function ($m) {
            $m->shouldReceive('liveStatus')->andReturn([]);
            $m->shouldReceive('savePlanSpeeds')->once()->with(['bw_free_down' => 10.0], \Mockery::any())->andReturn(true);
        });
        $this->mock(OpnSenseService::class)->shouldIgnoreMissing();

        $this->actingAs($this->owner())->get(route('network.traffic'))->assertSee('Barista AI suggests')->assertSee('Confirm');
        $this->actingAs($this->owner())->post(route('network.traffic.plans.accept'))->assertSessionHas('success');
    }

    public function test_nothing_to_confirm_when_the_plans_fit(): void
    {
        Setting::set('internet_speed_tests', json_encode([['down' => 300.0, 'up' => 100.0, 'at' => now()->toIso8601String()], ['down' => 290.0, 'up' => 100.0, 'at' => now()->subDay()->toIso8601String()]]));
        $this->mock(TrafficShapingService::class, fn ($m) => $m->shouldNotReceive('savePlanSpeeds')->shouldReceive('liveStatus')->andReturn([]));
        $this->mock(OpnSenseService::class)->shouldIgnoreMissing();

        $this->actingAs($this->owner())->post(route('network.traffic.plans.accept'))->assertSessionHas('success', fn ($m) => str_contains($m, 'already fit'));
    }

    public function test_raw_device_addresses_are_for_the_super_admin(): void
    {
        \App\Models\BannedDevice::create(['mac_address' => 'aa:bb:cc:dd:ee:01', 'hostname' => 'Noisy laptop', 'reason' => 'test']);

        $this->actingAs($this->owner())->get(route('network.blocklist'))->assertOk()
            ->assertSee('Noisy laptop')->assertDontSee('AA:BB:CC:DD:EE:01')->assertDontSee('MAC address');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']))->get(route('network.blocklist'))
            ->assertSee('AA:BB:CC:DD:EE:01');
    }
}
