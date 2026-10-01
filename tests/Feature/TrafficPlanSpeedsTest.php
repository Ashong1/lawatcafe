<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Traffic page's plan speeds: shown as OPNsense is running them, so the
 * page agrees with a guest's speed test, and applied before they are saved.
 */
class TrafficPlanSpeedsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.opnsense.url' => 'https://opnsense.test',
            'services.opnsense.key' => 'test-key',
            'services.opnsense.secret' => 'test-secret',
        ]);
    }

    private function option(string $value): array
    {
        return [$value => ['value' => $value, 'selected' => 1]];
    }

    private function pipe(string $name, string $bandwidth, string $metric = 'Mbit', string $enabled = '1'): array
    {
        return ['description' => $name, 'bandwidth' => $bandwidth, 'bandwidthMetric' => $this->option($metric), 'enabled' => $enabled];
    }

    private function rule(string $name, array $ips, string $enabled = '1'): array
    {
        $match = $ips ? collect($ips)->mapWithKeys(fn ($ip) => $this->option($ip))->all() : $this->option('any');
        $down = str_ends_with($name, '_down');

        return [
            'description' => $name,
            'enabled' => $enabled,
            'source' => $down ? $this->option('any') : $match,
            'destination' => $down ? $match : $this->option('any'),
        ];
    }

    /** The live state from 2026-09-28: plans at 5/3 and 15/6, fair-use switched off. */
    private function fakeLiveGateway(): void
    {
        Http::fake([
            'opnsense.test/api/trafficshaper/settings/get' => Http::response(['ts' => [
                'pipes' => ['pipe' => [
                    'p1' => $this->pipe('lawatcafe_fairuse_down', '200', 'Mbit', '0'),
                    'p2' => $this->pipe('lawatcafe_fairuse_up', '200', 'Mbit', '0'),
                    'p3' => $this->pipe('lawatcafe_free_down', '5'),
                    'p4' => $this->pipe('lawatcafe_free_up', '3000', 'Kbit'),
                    'p5' => $this->pipe('lawatcafe_premium_down', '15'),
                    'p6' => $this->pipe('lawatcafe_premium_up', '6'),
                ]],
                'rules' => ['rule' => [
                    'r1' => $this->rule('lawatcafe_fairuse_down', [], '0'),
                    'r2' => $this->rule('lawatcafe_fairuse_up', [], '0'),
                    'r3' => $this->rule('lawatcafe_free_down', ['192.168.2.110']),
                    'r4' => $this->rule('lawatcafe_free_up', ['192.168.2.110']),
                    'r5' => $this->rule('lawatcafe_premium_down', ['192.168.2.115', '192.168.2.120']),
                    'r6' => $this->rule('lawatcafe_premium_up', ['192.168.2.115', '192.168.2.120']),
                ]],
            ]], 200),
            '*' => Http::response(['result' => 'saved', 'uuid' => 'obj-1', 'rows' => []], 200),
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /** The reported bug: the page showed none of the speeds a speed test measured. */
    public function test_the_page_shows_the_plan_speeds_the_gateway_is_running(): void
    {
        // Stale settings must lose to what OPNsense reports.
        Setting::set('bw_free_down', '2');
        Setting::set('bw_premium_down', '10');
        $this->fakeLiveGateway();

        $response = $this->actingAs($this->admin())->get(route('network.traffic'));

        $response->assertOk();
        $response->assertSee('Plan Speeds', false);
        $response->assertSee('id="bw_free_down" name="bw_free_down"', false);
        $response->assertSeeInOrder(['bw_free_down', 'value="5"', 'bw_free_up', 'value="3"'], false);
        $response->assertSeeInOrder(['bw_premium_down', 'value="15"', 'bw_premium_up', 'value="6"'], false);
        $response->assertSee('1 guest on it', false);
        $response->assertSee('2 guests on it', false);
    }

    /** A disabled fair-use rule must not be labelled as in force. */
    public function test_a_switched_off_fair_use_ceiling_is_not_called_in_force(): void
    {
        $this->fakeLiveGateway();

        $this->actingAs($this->admin())->get(route('network.traffic'))
            ->assertSee('<span class="text-[#795548]">Off</span>', false)
            ->assertSee('It is off right now', false)
            ->assertDontSee('Update Ceiling', false);
    }

    public function test_an_unreachable_gateway_shows_the_saved_speeds_as_unconfirmed(): void
    {
        Setting::set('bw_free_down', '4');
        Http::fake(['*' => Http::response([], 500)]);

        $this->actingAs($this->admin())->get(route('network.traffic'))
            ->assertOk()
            ->assertSee('last saved speeds', false)
            ->assertSeeInOrder(['bw_free_down', 'value="4"'], false);
    }

    public function test_saving_plan_speeds_applies_them_then_records_them(): void
    {
        $this->fakeLiveGateway();

        $this->actingAs($this->admin())
            ->post(route('network.traffic.plans'), [
                'bw_free_down' => 5, 'bw_free_up' => 3, 'bw_premium_down' => 20, 'bw_premium_up' => 8,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'setPipe/p5')
            && $request['pipe']['bandwidth'] === '20');

        Cache::forget('setting.bw_premium_down');
        $this->assertSame('20', Setting::get('bw_premium_down'));
    }

    public function test_a_rejected_plan_speed_is_not_recorded(): void
    {
        Setting::set('bw_premium_down', '15');
        Http::fake([
            'opnsense.test/api/trafficshaper/settings/get' => Http::response(['ts' => ['pipes' => ['pipe' => []], 'rules' => ['rule' => []]]], 200),
            'opnsense.test/api/trafficshaper/settings/addPipe' => Http::response(['result' => 'failed'], 200),
            '*' => Http::response(['result' => 'saved', 'uuid' => 'obj-1', 'rows' => []], 200),
        ]);

        $this->actingAs($this->admin())
            ->post(route('network.traffic.plans'), [
                'bw_free_down' => 5, 'bw_free_up' => 3, 'bw_premium_down' => 20, 'bw_premium_up' => 8,
            ])
            ->assertSessionHas('error');

        Cache::forget('setting.bw_premium_down');
        $this->assertSame('15', Setting::get('bw_premium_down'));
    }

    public function test_a_missing_or_zero_speed_is_refused_before_reaching_the_gateway(): void
    {
        Http::fake();

        $this->actingAs($this->admin())
            ->post(route('network.traffic.plans'), ['bw_free_down' => 0, 'bw_free_up' => 3, 'bw_premium_down' => 20])
            ->assertSessionHasErrors(['bw_free_down', 'bw_premium_up']);

        Http::assertNothingSent();
    }

    public function test_when_the_ceiling_is_off_the_page_offers_to_turn_it_on(): void
    {
        $this->fakeLiveGateway();

        $this->actingAs($this->admin())->get(route('network.traffic'))
            ->assertSee('Turn On Speed Limit', false)
            ->assertDontSee('fair-use-off-form', false);
    }

    public function test_turning_the_ceiling_on_records_the_switch(): void
    {
        Setting::set('bw_fair_use_enabled', '0');
        $this->fakeLiveGateway();

        $this->actingAs($this->admin())
            ->post(route('network.traffic.update'), ['bw_fair_use_mbps' => 50])
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'setRule/r1')
            && $request['rule']['enabled'] === '1');
        Cache::forget('setting.bw_fair_use_enabled');
        $this->assertSame('1', Setting::get('bw_fair_use_enabled'));
    }

    public function test_turning_the_ceiling_off_disables_only_its_rules(): void
    {
        $this->fakeLiveGateway();

        $this->actingAs($this->admin())
            ->post(route('network.traffic.fair-use.off'))
            ->assertSessionHas('success');

        foreach (['r1', 'r2'] as $uuid) {
            Http::assertSent(fn ($request) => str_contains($request->url(), "setRule/{$uuid}")
                && $request['rule']['enabled'] === '0');
        }
        foreach (['r3', 'r4', 'r5', 'r6'] as $uuid) {
            Http::assertNotSent(fn ($request) => str_contains($request->url(), "setRule/{$uuid}"));
        }
        Cache::forget('setting.bw_fair_use_enabled');
        $this->assertSame('0', Setting::get('bw_fair_use_enabled'));
    }

    /** Switched off by the owner means Barista AI can't quietly switch it back on. */
    public function test_the_ai_cannot_move_a_switched_off_ceiling(): void
    {
        Setting::set('bw_fair_use_enabled', '0');
        Http::fake();

        $result = app(\App\Services\Agent\Tools\AdjustFairUseCeilingTool::class)
            ->execute(['mbps' => 30, 'reason' => 'busy'], null);

        $this->assertFalse($result->success);
        Http::assertNothingSent();
    }
}
