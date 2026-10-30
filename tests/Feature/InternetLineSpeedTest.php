<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\InternetSpeedTest;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Evaluators (Oct 28, R1 02:54): show the real speed the ISP delivers next to
 * the plan speeds, and how much each guest can be given.
 */
class InternetLineSpeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_recommendation_divides_what_is_left_after_the_shop_reserve(): void
    {
        $r = InternetSpeedTest::recommend(down: 50, reserve: 5, busyGuests: 10, plans: ['free' => 2, 'premium' => 10, 'turbo' => 60]);

        $this->assertEquals(45, $r['usable']);
        $this->assertEquals(4.5, $r['per_guest']);
        $this->assertSame(22, $r['plans']['free']['guests']);
        $this->assertSame(4, $r['plans']['premium']['guests']);
        $this->assertTrue($r['plans']['turbo']['too_fast']);
        $this->assertFalse($r['plans']['premium']['too_fast']);
    }

    public function test_a_run_measures_and_keeps_the_result(): void
    {
        Carbon::setTestNow('2026-10-30 09:00:00');
        Http::fake([
            'speed.cloudflare.com/__down*' => Http::response(str_repeat('x', 1000)),
            'speed.cloudflare.com/__up' => Http::response('ok'),
        ]);

        $result = app(InternetSpeedTest::class)->run();

        $this->assertGreaterThan(0, $result['down']);
        $this->assertGreaterThan(0, $result['up']);
        $this->assertEquals($result, app(InternetSpeedTest::class)->latest());
        $this->assertTrue(app(InternetSpeedTest::class)->testedToday());
    }

    public function test_a_failed_run_keeps_nothing(): void
    {
        Http::fake(['speed.cloudflare.com/*' => Http::response('down', 500)]);

        $this->assertNull(app(InternetSpeedTest::class)->run());
        $this->assertNull(app(InternetSpeedTest::class)->latest());
    }

    public function test_the_wifi_speed_page_shows_the_line_and_the_advice(): void
    {
        Http::fake(['*' => Http::response([], 500)]); // the gateway is not reachable from tests
        Setting::set('internet_speed_tests', json_encode([['down' => 48.2, 'up' => 19.6, 'at' => now()->subMinutes(5)->toIso8601String()]]));
        Setting::set('bw_premium_down', '60');

        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('network.traffic'))->assertOk()
            ->assertSee('Your internet line')
            ->assertSee('48.2')->assertSee('19.6')
            ->assertSee('Test now')
            ->assertSee('per guest at the busiest time')
            ->assertSee('faster than your internet');
    }

    public function test_test_now_runs_one_and_reports_it(): void
    {
        $this->mock(InternetSpeedTest::class, fn ($m) => $m->shouldReceive('run')->once()->andReturn(['down' => 30.0, 'up' => 10.0, 'at' => now()->toIso8601String()]));

        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('network.traffic.speed-test'))
            ->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, '30'));
    }

    public function test_the_daily_test_is_scheduled_and_runs_once_a_day_after_opening(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command ?? '', 'network:speed-test'));
        $this->assertNotNull($event);

        Setting::set('store_open_time', '08:00');
        Carbon::setTestNow('2026-10-30 07:30:00');
        $this->mock(InternetSpeedTest::class, function ($m) {
            $m->shouldReceive('testedToday')->andReturn(false, true);
            $m->shouldReceive('run')->once()->andReturn(['down' => 30.0, 'up' => 10.0, 'at' => now()->toIso8601String()]);
            $m->shouldReceive('history')->andReturn([]);
        });

        $this->artisan('network:speed-test')->assertSuccessful();   // before opening: skipped
        Carbon::setTestNow('2026-10-30 08:15:00');
        $this->artisan('network:speed-test')->assertSuccessful();   // runs
        $this->artisan('network:speed-test')->assertSuccessful();   // already done today
    }
}
