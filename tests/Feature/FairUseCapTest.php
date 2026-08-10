<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The shaping this OPNsense build can actually enforce.
 *
 * Per-tier caps cannot be provisioned here — the shaper's rule model offers
 * nothing but "any" for source and destination, so a rule matching a tier alias
 * is rejected (see docs/INFRASTRUCTURE.md). What works is one rule for the whole
 * interface, made safe by two things these tests pin:
 *
 *   - the pipe carries a per-IP mask, so the figure is a ceiling PER DEVICE and
 *     not a total shared between them;
 *   - the ceiling sits far above what the shop's own equipment uses, because the
 *     captive portal zone is bound to `lan` and that interface also carries the
 *     POS, this application server, Pi-hole and OPNsense itself.
 */
class FairUseCapTest extends TestCase
{
    use RefreshDatabase;

    private function fakeOpnsense(): void
    {
        Http::fake([
            '*/api/trafficshaper/settings/searchPipes*' => Http::response(['rows' => []], 200),
            '*/api/trafficshaper/settings/searchRules*' => Http::response(['rows' => []], 200),
            '*/api/firewall/alias/search_item*' => Http::response(['rows' => [
                ['name' => 'lawatcafe_free_tier'], ['name' => 'lawatcafe_premium_tier'],
            ]], 200),
            '*/api/trafficshaper/settings/get' => Http::response(['ts' => [
                'pipes' => ['pipe' => []], 'rules' => ['rule' => []],
            ]], 200),
            '*' => Http::response(['result' => 'saved', 'uuid' => 'obj-1'], 200),
        ]);
    }

    /** A total shared across every guest would be useless; each device gets its own. */
    public function test_the_pipes_are_masked_so_the_ceiling_is_per_device(): void
    {
        $this->fakeOpnsense();

        $this->artisan('shaper:fair-use', ['mbps' => 20, '--apply' => true])->assertSuccessful();

        $masks = [];
        Http::assertSent(function ($request) use (&$masks) {
            if (str_contains($request->url(), 'addPipe')) {
                $masks[] = $request['pipe']['mask'] ?? null;
            }

            return true;
        });

        // Download is masked on destination, upload on source — the direction a
        // guest's traffic crosses the interface.
        $this->assertContains('dst-ip', $masks);
        $this->assertContains('src-ip', $masks);
    }

    public function test_the_rules_match_the_whole_interface(): void
    {
        $this->fakeOpnsense();

        $this->artisan('shaper:fair-use', ['mbps' => 20, '--apply' => true])->assertSuccessful();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'trafficshaper/settings/addRule')) {
                return true;
            }

            // any/any is not a simplification, it is the only thing the field
            // accepts here: getRule offers exactly one option for source and
            // for destination, and it is "any". That is what makes per-tier
            // shaping impossible on this build.
            return ($request['rule']['source'] ?? null) === 'any'
                && ($request['rule']['destination'] ?? null) === 'any';
        });
    }

    public function test_the_ceiling_is_written_at_the_requested_rate(): void
    {
        $this->fakeOpnsense();

        $this->artisan('shaper:fair-use', ['mbps' => 20, '--apply' => true])->assertSuccessful();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'addPipe')) {
                return true;
            }

            return ($request['pipe']['bandwidth'] ?? null) === '20'
                && ($request['pipe']['bandwidthMetric'] ?? null) === 'Mbit';
        });
    }

    /** So the applied value survives a restart and the next run has a default. */
    public function test_the_applied_ceiling_is_remembered(): void
    {
        $this->fakeOpnsense();

        $this->artisan('shaper:fair-use', ['mbps' => 20, '--apply' => true])->assertSuccessful();

        Cache::forget('setting.bw_fair_use_mbps');
        $this->assertSame('20', Setting::get('bw_fair_use_mbps'));
    }

    /** Nothing may reach OPNsense without --apply. */
    public function test_a_dry_run_writes_nothing(): void
    {
        $this->fakeOpnsense();

        $this->artisan('shaper:fair-use', ['mbps' => 20])->assertSuccessful();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'addPipe')
            || str_contains($request->url(), 'addRule'));
    }

    public function test_a_zero_ceiling_is_refused(): void
    {
        $this->fakeOpnsense();

        $this->artisan('shaper:fair-use', ['mbps' => 0, '--apply' => true])->assertFailed();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'addPipe'));
    }

    /**
     * A rule that OPNsense refuses leaves a pipe nothing steers into — silent
     * and indistinguishable from a working cap, so it has to fail loudly.
     */
    public function test_a_rejected_rule_fails_the_command(): void
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

        $this->artisan('shaper:fair-use', ['mbps' => 20, '--apply' => true])->assertFailed();
    }

    /**
     * The exact report: saving with a free upload of 1.5 Mbps failed with
     * "OPNsense rejected the bandwidth pipe 'lawatcafe_free_up'". The pipe's
     * bandwidth field is an integer, and OPNsense answers "Bandwidth out of
     * range" for 1.5 — verified live. Rounding somebody's 1.5 down to 1 would
     * silently change what they asked for, so the value moves to the next unit
     * down instead: 1.5 Mbit and 1500 Kbit are the same cap.
     */
    public function test_a_fractional_rate_is_written_in_kbit_rather_than_rounded(): void
    {
        $this->fakeOpnsense();

        app(OpnSenseService::class)->upsertShaperPipe('free', 'up', 1.5);

        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'addPipe')
            || ($request['pipe']['bandwidth'] === '1500' && $request['pipe']['bandwidthMetric'] === 'Kbit'));
    }

    /** A whole number stays in Mbit — no need to inflate every value. */
    public function test_a_whole_rate_stays_in_mbit(): void
    {
        $this->fakeOpnsense();

        app(OpnSenseService::class)->upsertShaperPipe('free', 'down', 3.0);

        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'addPipe')
            || ($request['pipe']['bandwidth'] === '3' && $request['pipe']['bandwidthMetric'] === 'Mbit'));
    }

    /** Whatever the unit, the field must never carry a decimal point. */
    public function test_the_bandwidth_written_is_always_a_whole_number(): void
    {
        $this->fakeOpnsense();

        $service = app(OpnSenseService::class);
        foreach ([0.5, 1.5, 2, 2.25, 10, 20.75] as $mbps) {
            $service->upsertShaperPipe('free', 'down', $mbps);
        }

        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'addPipe')
            || ctype_digit($request['pipe']['bandwidth']));
    }

    /**
     * The Bandwidth Shaping page reports; it does not configure. Its QoS form
     * offered four per-tier fields this gateway cannot enforce and a burst
     * toggle nothing reads, alongside the one control that worked — so most of
     * what an admin could set there silently did nothing. The ceiling now
     * belongs to `shaper:fair-use` alone.
     */
    public function test_the_page_is_read_only_and_states_the_ceiling(): void
    {
        Setting::set('bw_fair_use_mbps', '20');
        Cache::forget('setting.bw_fair_use_mbps');

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('network.traffic'));

        $response->assertOk();
        $response->assertSee('Fair-Use Ceiling', false);
        $response->assertSee('20 Mbps', false);

        $response->assertDontSee('QoS Configuration', false);
        $response->assertDontSee('name="bw_fair_use_mbps"', false);
        $response->assertDontSee('name="bw_free_down"', false);
        $response->assertDontSee('name="bw_premium_down"', false);
        $response->assertDontSee('name="bw_burst_enabled"', false);
    }

    /** And nothing may POST to it any more. */
    public function test_the_page_accepts_no_post(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post('/network/traffic', ['bw_fair_use_mbps' => 99])
            ->assertStatus(405);
    }

    /**
     * The regression this pins. The ceiling was moved to the filter table to
     * let per-tier rules override it by sequence; the move was accepted by
     * OPNsense and left the network completely unshaped — 60 down / 56 up on a
     * connection that had been capped at 20. Shaper rules are the only ones
     * that take effect on this gateway.
     */
    public function test_the_fair_use_cap_is_a_shaper_rule(): void
    {
        $this->fakeOpnsense();

        $this->artisan('shaper:fair-use', ['mbps' => 20, '--apply' => true])->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'trafficshaper/settings/addRule'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'firewall/filter'));
    }
}
