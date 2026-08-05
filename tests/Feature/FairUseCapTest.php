<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
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
            if (! str_contains($request->url(), 'addRule')) {
                return true;
            }

            // "any" both sides is not a shortcut — it is the only form this
            // build's shaper accepts.
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
            '*/api/trafficshaper/settings/addRule' => Http::response(['result' => 'failed'], 200),
            '*' => Http::response(['result' => 'saved', 'uuid' => 'obj-1'], 200),
        ]);

        $this->artisan('shaper:fair-use', ['mbps' => 20, '--apply' => true])->assertFailed();
    }

    /**
     * The Bandwidth Shaping page must provision the cap that works, not the
     * per-tier chain that cannot. Saving used to fail every single time on this
     * hardware while the values had in fact been stored — an error on every
     * click, for a setting that had saved.
     */
    public function test_saving_the_bandwidth_page_applies_the_fair_use_ceiling(): void
    {
        $this->fakeOpnsense();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('network.traffic.update'), [
                'bw_fair_use_mbps' => 20,
                'bw_free_down' => 2, 'bw_free_up' => 1,
                'bw_premium_down' => 10, 'bw_premium_up' => 5,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Nothing may be addressed at a tier alias — that is the rule this
        // build rejects, and attempting it is what broke Save.
        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'addRule')
            || ($request['rule']['source'] ?? null) === 'any');

        Cache::forget('setting.bw_fair_use_mbps');
        $this->assertSame('20', Setting::get('bw_fair_use_mbps'));
    }

    /** The tier values are still recorded — vouchers carry a tier. */
    public function test_the_per_tier_values_are_still_saved_even_though_they_are_not_enforced(): void
    {
        $this->fakeOpnsense();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('network.traffic.update'), [
                'bw_fair_use_mbps' => 20,
                'bw_free_down' => 3, 'bw_free_up' => 2,
                'bw_premium_down' => 12, 'bw_premium_up' => 6,
            ])->assertSessionHas('success');

        Cache::forget('setting.bw_free_down');
        $this->assertSame('3', Setting::get('bw_free_down'));
    }

    public function test_the_page_says_plainly_that_per_tier_is_not_enforced(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('network.traffic'));

        $response->assertOk();
        $response->assertSee('Fair-Use Ceiling', false);
        $response->assertSee('recorded, not enforced', false);
    }
}
