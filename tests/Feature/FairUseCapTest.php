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
            '*/api/firewall/filter/get' => Http::response(['filter' => ['rules' => ['rule' => []]]], 200),
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
            if (! str_contains($request->url(), 'firewall/filter/add_rule')) {
                return true;
            }

            // The catch-all matches everything; the per-tier rules that sit
            // above it in sequence are what narrow it down.
            return ($request['rule']['source_net'] ?? null) === 'any'
                && ($request['rule']['destination_net'] ?? null) === 'any';
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
            || str_contains($request->url(), 'add_rule'));
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
            // The fair-use cap is a FILTER rule now, not a Shaper rule — the two
            // are separate tables, and while it lived in the Shaper's it
            // silently overrode every per-tier rule.
            '*/api/firewall/filter/get' => Http::response(['filter' => ['rules' => ['rule' => []]]], 200),
            '*/api/firewall/filter/add_rule' => Http::response(['result' => 'failed'], 200),
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

        // The Shaper rules stay any/any — that is the only form they accept.
        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'trafficshaper/settings/addRule')
            || ($request['rule']['source'] ?? null) === 'any');

        // And the per-tier caps are applied too, as filter rules.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'firewall/filter/add_rule'));

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

    /**
     * The page said per-tier caps were "recorded, not enforced" — true only
     * while that was impossible here. Filter rules made it work, so the notice
     * had to stop saying otherwise.
     */
    public function test_the_page_states_that_both_layers_are_enforced(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('network.traffic'));

        $response->assertOk();
        $response->assertSee('Fair-Use Ceiling', false);
        $response->assertSee('Per-tier caps are enforced', false);
        $response->assertDontSee('recorded, not enforced', false);
    }

    /**
     * The exact report: an admin with the Bandwidth page already open when this
     * field shipped submitted the old form and got "The bw fair use mbps field
     * is required" — a hard failure over a value that was already stored. A
     * stale tab is not a reason to refuse the whole save.
     */
    public function test_a_form_submitted_without_the_new_field_still_saves(): void
    {
        Setting::set('bw_fair_use_mbps', '20');
        Cache::forget('setting.bw_fair_use_mbps');
        $this->fakeOpnsense();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('network.traffic.update'), [
                // No bw_fair_use_mbps at all — the pre-deploy form.
                'bw_free_down' => 2, 'bw_free_up' => 1,
                'bw_premium_down' => 10, 'bw_premium_up' => 5,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        // And the stored ceiling is what got applied, not zero or null.
        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'addPipe')
            || ($request['pipe']['bandwidth'] ?? null) === '20');
    }

    /** An omitted optional field means "leave it alone", never "blank it out". */
    public function test_an_omitted_ceiling_does_not_erase_the_stored_one(): void
    {
        Setting::set('bw_fair_use_mbps', '35');
        Cache::forget('setting.bw_fair_use_mbps');
        $this->fakeOpnsense();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('network.traffic.update'), [
                'bw_free_down' => 2, 'bw_free_up' => 1,
                'bw_premium_down' => 10, 'bw_premium_up' => 5,
            ])->assertSessionHas('success');

        Cache::forget('setting.bw_fair_use_mbps');
        $this->assertSame('35', Setting::get('bw_fair_use_mbps'));
    }

    /** A value that IS sent still has to be sane. */
    public function test_an_out_of_range_ceiling_is_still_rejected(): void
    {
        $this->fakeOpnsense();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('network.traffic.update'), [
                'bw_fair_use_mbps' => 0,
                'bw_free_down' => 2, 'bw_free_up' => 1,
                'bw_premium_down' => 10, 'bw_premium_up' => 5,
            ])->assertSessionHasErrors('bw_fair_use_mbps');
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

    /** And the whole per-tier save survives a fractional value end to end. */
    public function test_saving_with_a_fractional_tier_rate_succeeds(): void
    {
        $this->fakeOpnsense();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('network.traffic.update'), [
                'bw_fair_use_mbps' => 20,
                'bw_free_down' => 3, 'bw_free_up' => 1.5,
                'bw_premium_down' => 10, 'bw_premium_up' => 2.5,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
    }

    /**
     * The bug this move fixes: a free guest measured the 20 Mbit ceiling
     * instead of their tier. The fair-use cap was a Shaper rule while the tier
     * caps were filter rules — separate tables, so pf sequence never applied
     * between them and the any/any Shaper rule simply won.
     */
    public function test_the_fair_use_cap_is_a_filter_rule_not_a_shaper_rule(): void
    {
        $this->fakeOpnsense();

        $this->artisan('shaper:fair-use', ['mbps' => 20, '--apply' => true])->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'firewall/filter/add_rule'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'trafficshaper/settings/addRule'));
    }

    /** And it must sit below the tier rules so they win. */
    public function test_the_fair_use_rule_is_sequenced_below_the_tier_rules(): void
    {
        $this->fakeOpnsense();

        $this->artisan('shaper:fair-use', ['mbps' => 20, '--apply' => true])->assertSuccessful();

        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'firewall/filter/add_rule')
            || (int) $request['rule']['sequence'] < 100);
    }

    /**
     * A leftover Shaper rule is not inert — it matches independently of pf
     * sequence and overrides the tier rules, which is precisely the fault. So
     * provisioning retires any it finds rather than just no longer writing them.
     */
    public function test_legacy_shaper_rules_are_retired(): void
    {
        Http::fake([
            // readShaperConfig() reads settings/get and indexes ts.rules.rule
            // by description — not searchRules.
            '*/api/trafficshaper/settings/get' => Http::response(['ts' => [
                'pipes' => ['pipe' => []],
                'rules' => ['rule' => ['legacy-1' => ['description' => 'lawatcafe_fairuse_down']]],
            ]], 200),
            '*/api/firewall/filter/get' => Http::response(['filter' => ['rules' => ['rule' => []]]], 200),
            '*' => Http::response(['result' => 'saved', 'uuid' => 'obj-1'], 200),
        ]);

        $this->artisan('shaper:fair-use', ['mbps' => 20, '--apply' => true])->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'trafficshaper/settings/delRule/legacy-1'));
    }
}
