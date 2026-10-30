<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\SystemAlert;
use App\Services\AdaptiveBandwidthService;
use App\Services\Agent\PermissionResolver;
use App\Services\Agent\Tools\SetPlanSpeedsTool;
use App\Services\InternetSpeedTest;
use App\Services\PiholeService;
use App\Services\TrafficShapingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

/**
 * Owner (Oct 30): the AI runs Wi-Fi speeds and blocks adult sites on its own
 * by default. Every change notifies the admins, and each has an off switch.
 */
class AiWifiAutonomyTest extends TestCase
{
    use RefreshDatabase;

    public function test_speeds_are_ai_managed_by_default(): void
    {
        $this->assertSame('1', AdaptiveBandwidthService::DEFAULTS['bw_adaptive_enabled']);
        $this->assertSame(PermissionResolver::TIER_AUTO, app(SetPlanSpeedsTool::class)->permissionTier());
    }

    private function speedTestReturns(float $down): void
    {
        Setting::set('store_open_time', '08:00');
        Carbon::setTestNow('2026-10-30 09:00:00');
        $this->mock(InternetSpeedTest::class, function ($m) use ($down) {
            $m->shouldReceive('testedToday')->andReturn(false);
            $m->shouldReceive('run')->andReturn(['down' => $down, 'up' => 5.0, 'at' => now()->toIso8601String()]);
            // Two slow days in a row: the plans are only lowered on a repeat.
            $m->shouldReceive('history')->andReturn([['down' => $down, 'up' => 5.0, 'at' => now()->toIso8601String()], ['down' => $down, 'up' => 5.0, 'at' => now()->subDay()->toIso8601String()]]);
        });
    }

    public function test_a_plan_faster_than_the_line_is_lowered_after_the_daily_test(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        Setting::set('bw_premium_down', '20');
        Setting::set('bw_free_down', '2');
        $this->speedTestReturns(15.0);   // 15 − 5 kept for the shop = 10 for guests

        $this->mock(TrafficShapingService::class, fn ($m) => $m->shouldReceive('savePlanSpeeds')->once()
            ->with(Mockery::on(fn ($speeds) => $speeds == ['bw_premium_down' => 10.0]), Mockery::any())->andReturn(true));

        $this->artisan('network:speed-test')->assertSuccessful();

        Notification::assertSentTo($admin, SystemAlert::class, fn ($n) => str_contains($n->toArray($admin)['message'], 'Premium'));
    }

    public function test_the_owner_can_switch_plan_fitting_off(): void
    {
        Setting::set('bw_auto_fit_plans', '0');
        Setting::set('bw_premium_down', '20');
        $this->speedTestReturns(15.0);
        $this->mock(TrafficShapingService::class, fn ($m) => $m->shouldNotReceive('savePlanSpeeds'));

        $this->artisan('network:speed-test')->assertSuccessful();
    }

    public function test_an_unblocked_adult_site_is_blocked_automatically(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->mock(PiholeService::class, function ($m) {
            $m->shouldReceive('queriesSince')->andReturn([['time' => time(), 'domain' => 'www.pinayflix.tv', 'client' => '192.168.2.111', 'status' => 'FORWARDED']]);
            $m->shouldReceive('blockDomain')->once()->with('pinayflix.tv', Mockery::type('string'))->andReturn(true);
        });

        $this->artisan('network:watch-adult-sites')->assertSuccessful();

        Notification::assertSentTo($admin, SystemAlert::class, fn ($n) => str_contains($n->toArray($admin)['title'], 'blocked automatically'));
    }

    public function test_both_automatic_jobs_have_an_off_switch(): void
    {
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response([], 500)]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('network.site-blocking.auto-adult'), ['enabled' => '0'])->assertRedirect();
        $this->assertSame('0', Setting::get('ai_auto_block_adult_sites'));

        $this->actingAs($admin)->post(route('network.traffic.line'), ['bw_shop_reserve_mbps' => 5, 'bw_busy_guests' => 10])->assertRedirect();
        $this->assertSame('0', Setting::get('bw_auto_fit_plans'));
        $this->actingAs($admin)->post(route('network.traffic.line'), ['bw_shop_reserve_mbps' => 5, 'bw_busy_guests' => 10, 'bw_auto_fit_plans' => '1']);
        $this->assertSame('1', Setting::get('bw_auto_fit_plans'));

        $this->actingAs($admin)->get(route('network.traffic'))->assertSee('Let Barista AI lower a plan');
    }
}
