<?php

namespace Tests\Feature;

use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OpnSenseServiceTest extends TestCase
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

    public function test_authorize_device_refuses_without_a_configured_guest_password(): void
    {
        config(['services.opnsense.guest_user' => 'laravel_guest', 'services.opnsense.guest_pass' => null]);

        Http::fake();

        $result = app(OpnSenseService::class)->authorizeDevice('192.168.2.50', 'LAWA-TEST');

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    public function test_authorize_device_refuses_without_a_configured_guest_user(): void
    {
        config(['services.opnsense.guest_user' => null, 'services.opnsense.guest_pass' => 'somepass']);

        Http::fake();

        $result = app(OpnSenseService::class)->authorizeDevice('192.168.2.50', 'LAWA-TEST');

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    public function test_authorize_device_proceeds_once_guest_credentials_are_configured(): void
    {
        config(['services.opnsense.guest_user' => 'laravel_guest', 'services.opnsense.guest_pass' => 'realpass']);

        Http::fake([
            'opnsense.test/api/captiveportal/session/connect/*' => Http::response(['sessionId' => 'sess-1'], 200),
        ]);

        $result = app(OpnSenseService::class)->authorizeDevice('192.168.2.50', 'LAWA-TEST');

        $this->assertTrue($result);
        Http::assertSentCount(1);
    }

    public function test_get_arp_table_is_cached_across_calls(): void
    {
        Http::fake([
            'opnsense.test/api/diagnostics/interface/getArp' => Http::response(['arp' => [['ip' => '10.0.0.5', 'mac' => 'aa:bb:cc:dd:ee:ff']]], 200),
        ]);

        $service = app(OpnSenseService::class);
        $first = $service->getArpTable();
        $second = $service->getArpTable();

        $this->assertEquals($first, $second);
        Http::assertSentCount(1);
    }

    public function test_list_sessions_is_cached_across_calls(): void
    {
        Http::fake([
            'opnsense.test/api/captiveportal/session/list/*' => Http::response(['rows' => [['sessionId' => 'sess-1']]], 200),
        ]);

        $service = app(OpnSenseService::class);
        $service->listSessions();
        $service->listSessions();

        Http::assertSentCount(1);
    }

    public function test_disconnecting_a_device_clears_the_cached_sessions_list(): void
    {
        Http::fake([
            'opnsense.test/api/captiveportal/session/list/*' => Http::response(['rows' => [['sessionId' => 'sess-1']]], 200),
            'opnsense.test/api/captiveportal/session/disconnect/*' => Http::response(['result' => 'ok'], 200),
        ]);

        $service = app(OpnSenseService::class);
        $service->listSessions();
        $this->assertTrue(Cache::has('opnsense_sessions_list_0'));

        $service->disconnectDevice('sess-1');

        $this->assertFalse(Cache::has('opnsense_sessions_list_0'));
    }
}
