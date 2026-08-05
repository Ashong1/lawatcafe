<?php

namespace Tests\Feature;

use App\Models\Voucher;
use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrafficShapingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.opnsense.url' => 'https://opnsense.test',
            'services.opnsense.key' => 'test-key',
            'services.opnsense.secret' => 'test-secret',
            'services.opnsense.tier_alias_free' => 'lawatcafe_free_tier',
            'services.opnsense.tier_alias_premium' => 'lawatcafe_premium_tier',
        ]);
    }

    private array $limits = [
        'bw_free_up' => 1, 'bw_free_down' => 2,
        'bw_premium_up' => 5, 'bw_premium_down' => 10,
    ];

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fakeGreenfieldOpnsense(array $overrides = []): void
    {
        Http::fake(array_merge([
            // Nothing provisioned yet, and neither tier alias exists.
            'opnsense.test/api/trafficshaper/settings/get' => Http::response([
                'ts' => ['pipes' => ['pipe' => []], 'rules' => ['rule' => []]],
            ], 200),
            'opnsense.test/api/firewall/alias/search_item' => Http::response(['rows' => [
                ['name' => 'bogons', 'type' => 'external'],
            ]], 200),
            'opnsense.test/api/firewall/alias/addItem' => Http::response(['result' => 'saved', 'uuid' => 'alias-uuid'], 200),
            'opnsense.test/api/firewall/alias/reconfigure' => Http::response(['status' => 'ok'], 200),
            'opnsense.test/api/trafficshaper/settings/addPipe' => Http::response(['result' => 'saved', 'uuid' => 'pipe-uuid'], 200),
            'opnsense.test/api/trafficshaper/settings/addRule' => Http::response(['result' => 'saved', 'uuid' => 'rule-uuid'], 200),
            'opnsense.test/api/trafficshaper/service/reconfigure' => Http::response(['status' => 'ok'], 200),
        ], $overrides));
    }

    /**
     * The whole point of the v1.0.0.81 fix: a pipe alone shapes nothing. A
     * guest's packets only enter it if a rule binds the tier's alias to it,
     * and the alias has to exist for membership changes to stick.
     */
    public function test_apply_limits_provisions_aliases_pipes_and_rules_for_both_tiers(): void
    {
        $this->fakeGreenfieldOpnsense();

        $applied = app(TrafficShapingService::class)->applyLimits($this->limits, app(OpnSenseService::class));

        $this->assertTrue($applied);

        foreach (['lawatcafe_free_tier', 'lawatcafe_premium_tier'] as $alias) {
            Http::assertSent(fn ($request) => str_contains($request->url(), '/firewall/alias/addItem')
                && $request['alias']['name'] === $alias);
        }

        foreach (['free_down', 'free_up', 'premium_down', 'premium_up'] as $name) {
            Http::assertSent(fn ($request) => str_contains($request->url(), '/settings/addPipe')
                && $request['pipe']['description'] === "lawatcafe_{$name}");
            Http::assertSent(fn ($request) => str_contains($request->url(), '/settings/addRule')
                && $request['rule']['description'] === "lawatcafe_{$name}");
        }

        Http::assertSent(fn ($request) => str_contains($request->url(), '/trafficshaper/service/reconfigure'));
    }

    /**
     * A tier is asymmetric. The old code posted one pipe per tier at
     * max(down, up), so free guests would have got 2 Mbit upload as well.
     */
    public function test_each_direction_gets_its_own_pipe_at_its_own_speed(): void
    {
        $this->fakeGreenfieldOpnsense();

        app(TrafficShapingService::class)->applyLimits($this->limits, app(OpnSenseService::class));

        $expected = [
            'lawatcafe_free_down' => ['2', 'dst-ip'],
            'lawatcafe_free_up' => ['1', 'src-ip'],
            'lawatcafe_premium_down' => ['10', 'dst-ip'],
            'lawatcafe_premium_up' => ['5', 'src-ip'],
        ];

        foreach ($expected as $description => [$bandwidth, $mask]) {
            Http::assertSent(function ($request) use ($description, $bandwidth, $mask) {
                if (! str_contains($request->url(), '/settings/addPipe') || $request['pipe']['description'] !== $description) {
                    return false;
                }

                // 'Mbit/s' is not a valid bandwidthMetric option — OPNsense
                // accepts only bit/Kbit/Mbit/Gbit, and the old value was
                // rejected outright.
                $this->assertSame('Mbit', $request['pipe']['bandwidthMetric']);
                // Per-client, not one shared bucket split between all guests.
                $this->assertSame($mask, $request['pipe']['mask']);

                return $request['pipe']['bandwidth'] === $bandwidth;
            });
        }
    }

    /**
     * Direction is from the LAN interface's point of view: a download leaves
     * it (matched on destination), an upload enters it (matched on source).
     */
    public function test_rules_match_the_tier_alias_on_the_correct_side_and_direction(): void
    {
        $this->fakeGreenfieldOpnsense();

        app(TrafficShapingService::class)->applyLimits($this->limits, app(OpnSenseService::class));

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/settings/addRule') || $request['rule']['description'] !== 'lawatcafe_free_down') {
                return false;
            }

            return $request['rule']['direction'] === 'out'
                && $request['rule']['destination'] === 'lawatcafe_free_tier'
                && $request['rule']['source'] === 'any'
                && $request['rule']['target'] === 'pipe-uuid'
                && $request['rule']['interface'] === 'lan';
        });

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/settings/addRule') || $request['rule']['description'] !== 'lawatcafe_free_up') {
                return false;
            }

            return $request['rule']['direction'] === 'in'
                && $request['rule']['source'] === 'lawatcafe_free_tier'
                && $request['rule']['destination'] === 'any';
        });
    }

    /**
     * Identity comes from OPNsense's own descriptions, so a second save
     * updates in place instead of stacking a duplicate set of pipes.
     */
    public function test_existing_objects_are_updated_in_place_not_duplicated(): void
    {
        $this->fakeGreenfieldOpnsense([
            'opnsense.test/api/trafficshaper/settings/get' => Http::response([
                'ts' => [
                    'pipes' => ['pipe' => [
                        'pipe-free-down' => ['description' => 'lawatcafe_free_down'],
                        'pipe-free-up' => ['description' => 'lawatcafe_free_up'],
                    ]],
                    'rules' => ['rule' => [
                        'rule-free-down' => ['description' => 'lawatcafe_free_down'],
                    ]],
                ],
            ], 200),
            'opnsense.test/api/trafficshaper/settings/setPipe/*' => Http::response(['result' => 'saved'], 200),
            'opnsense.test/api/trafficshaper/settings/setRule/*' => Http::response(['result' => 'saved'], 200),
        ]);

        $this->assertTrue(app(TrafficShapingService::class)->applyLimits($this->limits, app(OpnSenseService::class)));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/settings/setPipe/pipe-free-down'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/settings/setRule/rule-free-down'));
        // The premium tier had nothing yet, so it is still created.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/settings/addPipe')
            && $request['pipe']['description'] === 'lawatcafe_premium_down');
    }

    public function test_an_existing_alias_is_not_recreated(): void
    {
        $this->fakeGreenfieldOpnsense([
            'opnsense.test/api/firewall/alias/search_item' => Http::response(['rows' => [
                ['name' => 'lawatcafe_free_tier', 'type' => 'host'],
                ['name' => 'lawatcafe_premium_tier', 'type' => 'host'],
            ]], 200),
        ]);

        app(TrafficShapingService::class)->applyLimits($this->limits, app(OpnSenseService::class));

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/firewall/alias/addItem'));
    }

    public function test_apply_limits_fails_if_a_pipe_cannot_be_written(): void
    {
        $this->fakeGreenfieldOpnsense([
            'opnsense.test/api/trafficshaper/settings/addPipe' => Http::response([], 500),
        ]);

        $this->assertFalse(app(TrafficShapingService::class)->applyLimits($this->limits, app(OpnSenseService::class)));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/trafficshaper/service/reconfigure'));
    }

    public function test_apply_limits_fails_if_the_tier_alias_cannot_be_created(): void
    {
        $this->fakeGreenfieldOpnsense([
            'opnsense.test/api/firewall/alias/addItem' => Http::response(['result' => 'failed'], 200),
        ]);

        $this->assertFalse(app(TrafficShapingService::class)->applyLimits($this->limits, app(OpnSenseService::class)));
    }

    /**
     * alias_util answers HTTP 200 with {"status":"failed"} when the alias does
     * not exist. Reporting that as success is what hid this outage: the log
     * recorded every guest being added to a tier that had never been created.
     */
    public function test_adding_to_a_missing_alias_is_reported_as_failure(): void
    {
        Http::fake([
            'opnsense.test/api/firewall/alias_util/add/*' => Http::response(['status' => 'failed'], 200),
        ]);

        $this->assertFalse(app(OpnSenseService::class)->addIpToTierAlias('free', '192.168.2.77'));
    }

    public function test_assign_tier_adds_ip_to_the_matching_alias(): void
    {
        Http::fake([
            'opnsense.test/api/firewall/alias_util/add/lawatcafe_premium_tier' => Http::response(['status' => 'done'], 200),
        ]);

        $voucher = Voucher::create([
            'code' => 'LAWA-TEST', 'duration_minutes' => 60, 'tier' => 'premium',
            'is_used' => true, 'used_at' => now(), 'ip_address' => '192.168.2.77',
        ]);

        app(TrafficShapingService::class)->assignTier($voucher, '192.168.2.77', app(OpnSenseService::class));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/alias_util/add/lawatcafe_premium_tier')
            && $request['address'] === '192.168.2.77');
    }

    public function test_release_ip_removes_from_both_tier_aliases(): void
    {
        Http::fake([
            'opnsense.test/api/firewall/alias_util/delete/*' => Http::response(['status' => 'done'], 200),
        ]);

        app(TrafficShapingService::class)->releaseIp('192.168.2.77', app(OpnSenseService::class));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/alias_util/delete/lawatcafe_free_tier'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/alias_util/delete/lawatcafe_premium_tier'));
    }
}
