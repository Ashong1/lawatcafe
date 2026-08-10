<?php

namespace Tests\Feature;

use App\Models\BandwidthSample;
use App\Models\Setting;
use App\Models\User;
use App\Services\AdaptiveBandwidthService;
use App\Services\Agent\Tools\AdjustFairUseCeilingTool;
use App\Services\GuestSessionService;
use App\Services\LinkCapacityLearner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The adaptive fair-use loop.
 *
 * The shaper pipes are masked per IP, so each device gets its own queue at the
 * full ceiling — but they share one line. Ten guests at a 20 Mbps ceiling admit
 * 200 Mbps of demand into a ~60 Mbps connection, and TCP rather than policy
 * decides who wins: a guest with a dozen parallel connections takes far more
 * than a guest with one. Lowering the ceiling toward each guest's real share is
 * what makes that a rule again.
 *
 * These tests pin the parts that are dangerous to get wrong: that the estimate
 * cannot be poisoned by its own caps, that the agent can never leave the
 * owner's bounds, and that nothing reaches the firewall on a hold or a failure.
 */
class AdaptiveBandwidthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Frozen mid-hour. The peak/quiet split is derived from the hour a
        // sample was taken in, so a run that straddles the top of an hour would
        // seed samples into one hour and assess in the next — the same test
        // passing or failing on when it happened to run.
        $this->travelTo(now()->setTime(12, 30));
    }

    private function forgetSettings(): void
    {
        foreach ([
            'bw_fair_use_mbps', 'bw_adaptive_enabled', 'bw_adaptive_min',
            'bw_adaptive_max', 'bw_adaptive_last_change_at', 'bw_adaptive_last_decision',
        ] as $key) {
            Cache::forget("setting.{$key}");
        }
    }

    /** @param  array<int, array{down: float, guests: int, ceiling: float|null, hoursAgo?: int}>  $rows */
    private function seedSamples(array $rows): void
    {
        foreach ($rows as $row) {
            BandwidthSample::create([
                // Defaults into the current hour, so that hour is the busiest
                // one and assess() sees a peak. Tests that want the quiet
                // branch pass hoursAgo explicitly.
                'sampled_at' => now()->subHours($row['hoursAgo'] ?? 0),
                'down_mbps' => $row['down'],
                'up_mbps' => $row['down'] / 2,
                'active_guests' => $row['guests'],
                'ceiling_mbps' => $row['ceiling'],
            ]);
        }
    }

    private function fakeOpnsense(): void
    {
        Http::fake([
            '*/api/trafficshaper/settings/searchPipes*' => Http::response(['rows' => []], 200),
            '*/api/trafficshaper/settings/searchRules*' => Http::response(['rows' => []], 200),
            '*/api/trafficshaper/settings/get' => Http::response(['ts' => [
                'pipes' => ['pipe' => []], 'rules' => ['rule' => []],
            ]], 200),
            '*' => Http::response(['result' => 'saved', 'uuid' => 'obj-1'], 200),
        ]);
    }

    private function mockGuests(int $count): void
    {
        $this->mock(GuestSessionService::class, function ($mock) use ($count) {
            $mock->shouldReceive('activeGuestCount')->andReturn($count);
        });
    }

    // ---------------------------------------------------------------- learning

    /**
     * The trap this whole design turns on. Observed throughput is bounded by the
     * caps in force, so a quiet hour with two guests at a 20 Mbps ceiling can
     * never measure more than ~60 however fast the line is. Counting those as
     * capacity readings would ratchet the estimate down every night until the
     * ceiling collapsed.
     */
    public function test_samples_taken_while_pinned_against_the_caps_are_not_capacity_evidence(): void
    {
        // 1 guest + the shop's own devices at a 20 ceiling allows ~40; 8 Mbps is
        // nowhere near it, so the line was never the limit here.
        $this->seedSamples(array_fill(0, 30, ['down' => 8.0, 'guests' => 1, 'ceiling' => 20.0]));

        $estimate = app(LinkCapacityLearner::class)->estimate();

        $this->assertFalse($estimate['learned']);
        $this->assertSame(0, $estimate['informative']);
        $this->assertNull($estimate['down']);
    }

    /** An uncapped sample always counts — nothing was standing between it and the line. */
    public function test_uncapped_samples_are_always_capacity_evidence(): void
    {
        $this->seedSamples(array_fill(0, 20, ['down' => 58.0, 'guests' => 3, 'ceiling' => null]));

        $estimate = app(LinkCapacityLearner::class)->estimate();

        $this->assertTrue($estimate['learned']);
        $this->assertSame(58.0, $estimate['down']);
    }

    /**
     * One burst — a backup, a bad read, a counter rollover — must not be able to
     * redefine the connection, so the estimate takes the third-highest.
     */
    public function test_a_single_spike_does_not_become_the_capacity(): void
    {
        $rows = array_fill(0, 20, ['down' => 55.0, 'guests' => 3, 'ceiling' => null]);
        $rows[] = ['down' => 900.0, 'guests' => 3, 'ceiling' => null];
        $this->seedSamples($rows);

        $this->assertSame(55.0, app(LinkCapacityLearner::class)->estimate()['down']);
    }

    /** Too little evidence must read as "unknown", never as "slow". */
    public function test_a_thin_record_is_not_treated_as_a_low_capacity(): void
    {
        $this->seedSamples(array_fill(0, 5, ['down' => 55.0, 'guests' => 3, 'ceiling' => null]));

        $estimate = app(LinkCapacityLearner::class)->estimate();

        $this->assertFalse($estimate['learned']);
        $this->assertNull($estimate['down']);
    }

    /**
     * Peak is relative to the shop's own busiest hour. A café that peaks at six
     * guests has a rush as real as one that peaks at thirty, and an absolute
     * threshold would find no peak at all in the first.
     */
    public function test_peak_hours_are_relative_to_the_shops_own_busiest_hour(): void
    {
        $busy = now()->setTime(12, 0);
        $quiet = now()->setTime(3, 0);

        BandwidthSample::create(['sampled_at' => $busy, 'down_mbps' => 50, 'up_mbps' => 10, 'active_guests' => 6, 'ceiling_mbps' => 20]);
        BandwidthSample::create(['sampled_at' => $quiet, 'down_mbps' => 5, 'up_mbps' => 1, 'active_guests' => 1, 'ceiling_mbps' => 20]);

        $peak = app(LinkCapacityLearner::class)->peakHours();

        $this->assertContains(12, $peak);
        $this->assertNotContains(3, $peak);
    }

    /**
     * With no history every hour looks identical. Calling all 24 of them peak
     * would silently put the loop into its strictest mode forever.
     */
    public function test_an_empty_record_has_no_peak_hours(): void
    {
        $this->assertSame([], app(LinkCapacityLearner::class)->peakHours());
    }

    // ------------------------------------------------------------- the formula

    public function test_more_guests_means_a_lower_ceiling(): void
    {
        $this->seedSamples(array_fill(0, 20, ['down' => 60.0, 'guests' => 3, 'ceiling' => null]));
        Setting::set('bw_adaptive_min', '5');
        Setting::set('bw_adaptive_max', '20');
        Setting::set('bw_fair_use_mbps', '20');
        $this->forgetSettings();

        $this->mockGuests(9);
        $busy = app(AdaptiveBandwidthService::class)->assess();

        // 60 * 0.9 / 9 = 6 per guest.
        $this->assertSame(6.0, $busy['target']);
        $this->assertTrue($busy['should_change']);
    }

    public function test_a_quiet_shop_gets_the_ceiling_raised_back_to_the_maximum(): void
    {
        $this->seedSamples(array_fill(0, 20, ['down' => 60.0, 'guests' => 3, 'ceiling' => null]));
        Setting::set('bw_adaptive_min', '5');
        Setting::set('bw_adaptive_max', '20');
        Setting::set('bw_fair_use_mbps', '6');
        $this->forgetSettings();

        $this->mockGuests(1);
        $assessment = app(AdaptiveBandwidthService::class)->assess();

        $this->assertSame(20.0, $assessment['target']);
        $this->assertTrue($assessment['should_change']);
    }

    /** The computed share is never allowed out of the owner's envelope. */
    public function test_the_target_is_clamped_to_the_owners_bounds(): void
    {
        $this->seedSamples(array_fill(0, 20, ['down' => 60.0, 'guests' => 3, 'ceiling' => null]));
        Setting::set('bw_adaptive_min', '8');
        Setting::set('bw_adaptive_max', '20');
        Setting::set('bw_fair_use_mbps', '20');
        $this->forgetSettings();

        // 60 * 0.9 / 40 = 1.35, far below the floor.
        $this->mockGuests(40);

        $this->assertSame(8.0, app(AdaptiveBandwidthService::class)->assess()['target']);
    }

    /** Without a capacity figure there is no divisor, and guessing one throttles the shop. */
    public function test_nothing_is_proposed_before_the_line_speed_is_known(): void
    {
        Setting::set('bw_fair_use_mbps', '20');
        $this->forgetSettings();
        $this->mockGuests(10);

        $assessment = app(AdaptiveBandwidthService::class)->assess();

        $this->assertFalse($assessment['should_change']);
        $this->assertFalse($assessment['learned']);
        $this->assertStringContainsString('still learning', $assessment['blocked_by']);
    }

    /**
     * Every change rewrites two pipes and two rules and reloads dummynet, which
     * is a visible blip for everyone. A loop that chased small differences would
     * cost the shop more than the contention does.
     */
    public function test_a_small_difference_does_not_earn_a_shaper_reload(): void
    {
        $this->seedSamples(array_fill(0, 20, ['down' => 60.0, 'guests' => 3, 'ceiling' => null]));
        Setting::set('bw_adaptive_min', '5');
        Setting::set('bw_adaptive_max', '20');
        // 60 * 0.9 / 5 = 10.8 -> 11, which is 10% from 12. Inside the deadband.
        Setting::set('bw_fair_use_mbps', '12');
        $this->forgetSettings();

        $this->mockGuests(5);
        $assessment = app(AdaptiveBandwidthService::class)->assess();

        $this->assertFalse($assessment['should_change']);
        $this->assertStringContainsString('deadband', $assessment['blocked_by']);
    }

    public function test_a_recent_change_puts_the_loop_on_cooldown(): void
    {
        $this->seedSamples(array_fill(0, 20, ['down' => 60.0, 'guests' => 3, 'ceiling' => null]));
        Setting::set('bw_adaptive_min', '5');
        Setting::set('bw_adaptive_max', '20');
        Setting::set('bw_fair_use_mbps', '20');
        Setting::set('bw_adaptive_last_change_at', now()->subMinutes(2)->toIso8601String());
        $this->forgetSettings();

        $this->mockGuests(9);
        $assessment = app(AdaptiveBandwidthService::class)->assess();

        $this->assertFalse($assessment['should_change']);
        $this->assertStringContainsString('cooling down', $assessment['blocked_by']);
    }

    // ------------------------------------------------------------------ the tool

    /**
     * The structural safety. Whatever number arrives — from the loop, from an
     * admin in chat, or from a model that has misread the situation — it is
     * clamped before anything is written.
     */
    public function test_the_tool_clamps_a_wild_request_to_the_bounds(): void
    {
        $this->fakeOpnsense();
        Setting::set('bw_adaptive_min', '5');
        Setting::set('bw_adaptive_max', '20');
        Setting::set('bw_fair_use_mbps', '20');
        $this->forgetSettings();

        $result = app(AdjustFairUseCeilingTool::class)->execute(
            ['mbps' => 0.1, 'reason' => 'Model went haywire'],
            null
        );

        $this->assertTrue($result->success);
        $this->assertSame(5.0, $result->data['mbps']);
        $this->assertTrue($result->data['clamped']);

        Cache::forget('setting.bw_fair_use_mbps');
        $this->assertSame('5', Setting::get('bw_fair_use_mbps'));
    }

    /** Silent clamping would leave the loop proposing the same rejected figure forever. */
    public function test_the_clamp_is_reported_rather_than_hidden(): void
    {
        $this->fakeOpnsense();
        Setting::set('bw_adaptive_min', '5');
        Setting::set('bw_adaptive_max', '20');
        // Below the ceiling the clamp lands on, so the call is a real change
        // rather than the "already there" short-circuit.
        Setting::set('bw_fair_use_mbps', '10');
        $this->forgetSettings();

        $result = app(AdjustFairUseCeilingTool::class)->execute(
            ['mbps' => 500, 'reason' => 'Trying it on'],
            null
        );

        $this->assertStringContainsString('clamped', $result->message);
        $this->assertSame(500.0, $result->data['requested']);
    }

    /** A stored figure describing a cap the gateway is not running is worse than none. */
    public function test_a_rejected_write_leaves_the_recorded_ceiling_alone(): void
    {
        Http::fake([
            '*/api/trafficshaper/settings/searchPipes*' => Http::response(['rows' => []], 200),
            '*/api/trafficshaper/settings/searchRules*' => Http::response(['rows' => []], 200),
            '*/api/trafficshaper/settings/get' => Http::response(['ts' => [
                'pipes' => ['pipe' => []], 'rules' => ['rule' => []],
            ]], 200),
            '*/api/trafficshaper/settings/addRule' => Http::response(['result' => 'failed'], 200),
            '*' => Http::response(['result' => 'saved', 'uuid' => 'obj-1'], 200),
        ]);
        Setting::set('bw_adaptive_min', '5');
        Setting::set('bw_adaptive_max', '20');
        Setting::set('bw_fair_use_mbps', '20');
        $this->forgetSettings();

        $result = app(AdjustFairUseCeilingTool::class)->execute(
            ['mbps' => 8, 'reason' => 'Busy afternoon'],
            null
        );

        $this->assertFalse($result->success);
        Cache::forget('setting.bw_fair_use_mbps');
        $this->assertSame('20', Setting::get('bw_fair_use_mbps'));
    }

    /** A reload nobody needs is still a blip for everyone on the network. */
    public function test_setting_the_ceiling_to_what_it_already_is_touches_no_firewall(): void
    {
        Http::fake();
        Setting::set('bw_adaptive_min', '5');
        Setting::set('bw_adaptive_max', '20');
        Setting::set('bw_fair_use_mbps', '12');
        $this->forgetSettings();

        $result = app(AdjustFairUseCeilingTool::class)->execute(
            ['mbps' => 12, 'reason' => 'No change needed'],
            null
        );

        $this->assertTrue($result->success);
        $this->assertFalse($result->data['changed']);
        Http::assertNothingSent();
    }

    /** The owner reads this on the traffic page — a change with no stated cause is not acceptable. */
    public function test_the_tool_refuses_to_act_without_a_reason(): void
    {
        Http::fake();

        $result = app(AdjustFairUseCeilingTool::class)->execute(['mbps' => 8], null);

        $this->assertFalse($result->success);
        Http::assertNothingSent();
    }

    // -------------------------------------------------------------- the command

    /**
     * Sampling is the one part that must never be skipped: the capacity estimate
     * and the hour profile are both built from this history, so an owner who
     * switches the loop on next month should find it already knows the shop.
     */
    public function test_the_loop_keeps_sampling_while_adaptation_is_switched_off(): void
    {
        Setting::set('bw_adaptive_enabled', '0');
        $this->forgetSettings();
        $this->mockGuests(4);

        $this->mock(\App\Services\OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('getInterfaceStats')->andReturn(
                ['wan' => ['inbytes' => 0, 'outbytes' => 0]],
                ['wan' => ['inbytes' => 13_750_000, 'outbytes' => 1_375_000]],
            );
        });

        $this->artisan('shaper:adapt', ['--sample-only' => true])->assertSuccessful();

        $this->assertSame(1, BandwidthSample::count());
        $this->assertSame(4, BandwidthSample::first()->active_guests);
    }

    /**
     * A counter that went backwards means the interface was reset between reads.
     * Treating that wrap as a burst would poison the capacity estimate for a
     * month, so the sample is discarded rather than recorded.
     */
    public function test_a_counter_reset_is_discarded_rather_than_recorded_as_a_burst(): void
    {
        $this->mockGuests(2);
        $this->mock(\App\Services\OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('getInterfaceStats')->andReturn(
                ['wan' => ['inbytes' => 900_000_000, 'outbytes' => 900_000_000]],
                ['wan' => ['inbytes' => 12_000, 'outbytes' => 9_000]],
            );
        });

        $this->artisan('shaper:adapt', ['--sample-only' => true])->assertSuccessful();

        $this->assertSame(0, BandwidthSample::count());
    }

    /** No model may be woken, and no firewall touched, while the loop is off. */
    public function test_a_disabled_loop_never_reaches_the_firewall(): void
    {
        Http::fake();
        Setting::set('bw_adaptive_enabled', '0');
        $this->forgetSettings();
        $this->mockGuests(12);
        $this->seedSamples(array_fill(0, 20, ['down' => 60.0, 'guests' => 3, 'ceiling' => null]));

        $this->mock(\App\Services\OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('getInterfaceStats')->andReturn(
                ['wan' => ['inbytes' => 0, 'outbytes' => 0]],
                ['wan' => ['inbytes' => 1_000_000, 'outbytes' => 100_000]],
            );
        });

        $this->artisan('shaper:adapt')->assertSuccessful();

        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------- the page

    public function test_the_page_shows_the_bounds_and_what_has_been_learned(): void
    {
        $this->seedSamples(array_fill(0, 20, ['down' => 60.0, 'guests' => 3, 'ceiling' => null]));

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('network.traffic'))
            ->assertOk()
            ->assertSee('Adaptive Ceiling', false)
            ->assertSee('name="bw_adaptive_min"', false)
            ->assertSee('name="bw_adaptive_max"', false)
            ->assertSee('60 Mbps down', false);
    }

    public function test_the_page_says_it_is_still_measuring_before_it_knows(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('network.traffic'))
            ->assertOk()
            ->assertSee('Still measuring', false);
    }

    /** Saving the envelope must not itself write the firewall. */
    public function test_saving_the_adaptive_bounds_calls_no_firewall(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('network.traffic.adaptive'), [
                'bw_adaptive_enabled' => '1',
                'bw_adaptive_min' => 6,
                'bw_adaptive_max' => 18,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        Http::assertNothingSent();

        $this->forgetSettings();
        $this->assertSame('1', Setting::get('bw_adaptive_enabled'));
        $this->assertSame('6', Setting::get('bw_adaptive_min'));
    }

    /** An inverted envelope would let the clamp push the ceiling the wrong way. */
    public function test_a_maximum_below_the_minimum_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('network.traffic.adaptive'), [
                'bw_adaptive_min' => 15,
                'bw_adaptive_max' => 8,
            ])
            ->assertSessionHasErrors('bw_adaptive_max');
    }

    /** Unchecking the box has to actually switch it off — an absent checkbox is not "unchanged". */
    public function test_unchecking_the_box_disables_the_loop(): void
    {
        Setting::set('bw_adaptive_enabled', '1');
        $this->forgetSettings();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('network.traffic.adaptive'), [
                'bw_adaptive_min' => 5,
                'bw_adaptive_max' => 20,
            ])->assertSessionHas('success');

        $this->forgetSettings();
        $this->assertSame('0', Setting::get('bw_adaptive_enabled'));
    }

    /** Guest and staff assistants must not be able to move the whole shop's ceiling. */
    public function test_the_tool_is_out_of_reach_of_guest_and_staff_assistants(): void
    {
        $registry = app(\App\Services\Agent\ToolRegistry::class);

        $this->assertArrayNotHasKey('adjustFairUseCeiling', $registry->forAudience(\App\Services\Agent\ToolRegistry::AUDIENCE_GUEST));
        $this->assertArrayNotHasKey('adjustFairUseCeiling', $registry->forAudience(\App\Services\Agent\ToolRegistry::AUDIENCE_STAFF));
        $this->assertArrayHasKey('adjustFairUseCeiling', $registry->forAudience(\App\Services\Agent\ToolRegistry::AUDIENCE_ADMIN));
    }
}
