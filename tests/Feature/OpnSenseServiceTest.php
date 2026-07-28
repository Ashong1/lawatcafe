<?php

namespace Tests\Feature;

use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
}
