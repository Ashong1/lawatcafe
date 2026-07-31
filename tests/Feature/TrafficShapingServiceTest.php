<?php

namespace Tests\Feature;

use App\Models\Setting;
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

    public function test_apply_limits_creates_both_pipes_then_reconfigures(): void
    {
        Http::fake([
            'opnsense.test/api/trafficshaper/settings/addPipe' => Http::response(['uuid' => 'pipe-uuid-1'], 200),
            'opnsense.test/api/trafficshaper/service/reconfigure' => Http::response(['status' => 'ok'], 200),
        ]);

        $applied = app(TrafficShapingService::class)->applyLimits([
            'bw_free_up' => 1, 'bw_free_down' => 2,
            'bw_premium_up' => 5, 'bw_premium_down' => 10,
        ], app(OpnSenseService::class));

        $this->assertTrue($applied);
        Http::assertSentCount(3); // addPipe (free) + addPipe (premium) + reconfigure
        $this->assertSame('pipe-uuid-1', Setting::get('opnsense_pipe_uuid_free'));
        $this->assertSame('pipe-uuid-1', Setting::get('opnsense_pipe_uuid_premium'));
    }

    public function test_apply_limits_updates_existing_pipe_by_stored_uuid(): void
    {
        Setting::set('opnsense_pipe_uuid_free', 'existing-uuid');
        Setting::set('opnsense_pipe_uuid_premium', 'existing-uuid-2');

        Http::fake([
            'opnsense.test/api/trafficshaper/settings/setPipe/*' => Http::response(['result' => 'saved'], 200),
            'opnsense.test/api/trafficshaper/service/reconfigure' => Http::response(['status' => 'ok'], 200),
        ]);

        $applied = app(TrafficShapingService::class)->applyLimits([
            'bw_free_up' => 1, 'bw_free_down' => 2,
            'bw_premium_up' => 5, 'bw_premium_down' => 10,
        ], app(OpnSenseService::class));

        $this->assertTrue($applied);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/settings/setPipe/existing-uuid'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/settings/setPipe/existing-uuid-2'));
    }

    public function test_apply_limits_fails_if_any_pipe_upsert_fails(): void
    {
        Http::fake([
            'opnsense.test/api/trafficshaper/settings/addPipe' => Http::response([], 500),
        ]);

        $applied = app(TrafficShapingService::class)->applyLimits([
            'bw_free_up' => 1, 'bw_free_down' => 2,
            'bw_premium_up' => 5, 'bw_premium_down' => 10,
        ], app(OpnSenseService::class));

        $this->assertFalse($applied);
    }

    public function test_assign_tier_adds_ip_to_the_matching_alias(): void
    {
        Http::fake([
            'opnsense.test/api/firewall/alias_util/add/lawatcafe_premium_tier' => Http::response(['status' => 'ok'], 200),
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
            'opnsense.test/api/firewall/alias_util/delete/*' => Http::response(['status' => 'ok'], 200),
        ]);

        app(TrafficShapingService::class)->releaseIp('192.168.2.77', app(OpnSenseService::class));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/alias_util/delete/lawatcafe_free_tier'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/alias_util/delete/lawatcafe_premium_tier'));
    }
}
