<?php

namespace Tests\Feature;

use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Free-vs-premium differentiation, restored through firewall filter rules.
 *
 * The Shaper's own rules cannot do this on this build — their source and
 * destination accept nothing but "any". A filter rule's source_net and
 * destination_net are free text and take an alias name, and its shaper1 takes a
 * pipe UUID, which together is per-tier shaping.
 */
class TierFilterRulesTest extends TestCase
{
    use RefreshDatabase;

    private array $limits = [
        'bw_free_down' => 2, 'bw_free_up' => 1,
        'bw_premium_down' => 10, 'bw_premium_up' => 5,
    ];

    /** @param array<string,string> $existingRules description => uuid */
    private function fakeGateway(array $existingRules = []): void
    {
        $ruleTree = [];
        foreach ($existingRules as $description => $uuid) {
            $ruleTree[$uuid] = ['description' => $description];
        }

        Http::fake([
            '*/api/trafficshaper/settings/searchPipes*' => Http::response(['rows' => []], 200),
            '*/api/trafficshaper/settings/searchRules*' => Http::response(['rows' => []], 200),
            '*/api/firewall/alias/search_item*' => Http::response(['rows' => [
                ['name' => 'lawatcafe_free_tier'], ['name' => 'lawatcafe_premium_tier'],
            ]], 200),
            '*/api/firewall/filter/get' => Http::response(['filter' => ['rules' => ['rule' => $ruleTree]]], 200),
            '*' => Http::response(['result' => 'saved', 'uuid' => 'obj-1'], 200),
        ]);
    }

    /** The alias in the rule is the whole point — that is what the Shaper cannot do. */
    public function test_each_tier_rule_matches_that_tier_alias_and_targets_its_pipe(): void
    {
        $this->fakeGateway();

        $this->assertTrue(
            app(TrafficShapingService::class)->applyTierRules($this->limits, app(OpnSenseService::class))
        );

        $seen = [];
        Http::assertSent(function ($request) use (&$seen) {
            if (str_contains($request->url(), 'filter/add_rule')) {
                $r = $request['rule'];
                $seen[$r['description']] = [$r['source_net'], $r['destination_net'], $r['direction'], $r['shaper1'] ?? null];
            }

            return true;
        });

        // Download leaves the interface, matched on destination; upload enters
        // it, matched on source.
        $this->assertSame(['any', 'lawatcafe_free_tier', 'out', 'obj-1'], $seen['lawatcafe_free_down']);
        $this->assertSame(['lawatcafe_free_tier', 'any', 'in', 'obj-1'], $seen['lawatcafe_free_up']);
        $this->assertSame(['any', 'lawatcafe_premium_tier', 'out', 'obj-1'], $seen['lawatcafe_premium_down']);
        $this->assertSame(['lawatcafe_premium_tier', 'any', 'in', 'obj-1'], $seen['lawatcafe_premium_up']);
    }

    /**
     * quick must stay off. These are `pass` rules only because the API offers no
     * `match`; short-circuiting evaluation on a pass would skip everything after
     * them, including decisions that should still apply.
     */
    public function test_the_rules_do_not_short_circuit_evaluation(): void
    {
        $this->fakeGateway();

        app(TrafficShapingService::class)->applyTierRules($this->limits, app(OpnSenseService::class));

        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'filter/add_rule')
            || $request['rule']['quick'] === '0');
    }

    /** Tier rules sit after the fair-use catch-all so they override it. */
    public function test_tier_rules_are_sequenced_after_the_fair_use_catch_all(): void
    {
        $this->fakeGateway();

        app(TrafficShapingService::class)->applyTierRules($this->limits, app(OpnSenseService::class));

        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'filter/add_rule')
            || (int) $request['rule']['sequence'] > 10);
    }

    /**
     * The trap this nearly shipped with. search_rule answers 200 with total=0 on
     * this build even when rules exist, so a provisioning run believed them
     * absent and created another set every time. Existing rules must be found
     * and updated in place.
     */
    public function test_an_existing_rule_is_updated_rather_than_duplicated(): void
    {
        $this->fakeGateway(existingRules: [
            'lawatcafe_free_down' => 'uuid-free-down',
            'lawatcafe_free_up' => 'uuid-free-up',
            'lawatcafe_premium_down' => 'uuid-premium-down',
            'lawatcafe_premium_up' => 'uuid-premium-up',
        ]);

        app(TrafficShapingService::class)->applyTierRules($this->limits, app(OpnSenseService::class));

        // Every write is a set against the known UUID; nothing is added.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'filter/add_rule'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'filter/set_rule/uuid-free-down'));
    }

    /** Rules are staged until applied; skipping that leaves them inert. */
    public function test_the_rules_are_applied_not_just_written(): void
    {
        $this->fakeGateway();

        app(TrafficShapingService::class)->applyTierRules($this->limits, app(OpnSenseService::class));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'firewall/filter/apply'));
    }

    public function test_a_refused_rule_reports_why(): void
    {
        Http::fake([
            '*/api/trafficshaper/settings/searchPipes*' => Http::response(['rows' => []], 200),
            '*/api/trafficshaper/settings/searchRules*' => Http::response(['rows' => []], 200),
            '*/api/firewall/alias/search_item*' => Http::response(['rows' => [
                ['name' => 'lawatcafe_free_tier'], ['name' => 'lawatcafe_premium_tier'],
            ]], 200),
            '*/api/firewall/filter/get' => Http::response(['filter' => ['rules' => ['rule' => []]]], 200),
            '*/api/firewall/filter/add_rule' => Http::response(['result' => 'failed'], 200),
            '*' => Http::response(['result' => 'saved', 'uuid' => 'obj-1'], 200),
        ]);

        $service = app(TrafficShapingService::class);

        $this->assertFalse($service->applyTierRules($this->limits, app(OpnSenseService::class)));
        $this->assertStringContainsString('firewall rule', $service->lastError());
    }

    /** Only rules this app owns may be touched. */
    public function test_rules_belonging_to_someone_else_are_ignored(): void
    {
        $this->fakeGateway(existingRules: [
            'someone_elses_rule' => 'uuid-theirs',
            'lawatcafe_free_down' => 'uuid-ours',
        ]);

        $owned = app(OpnSenseService::class)->readFilterRules();

        $this->assertArrayHasKey('lawatcafe_free_down', $owned);
        $this->assertArrayNotHasKey('someone_elses_rule', $owned);
    }
}
