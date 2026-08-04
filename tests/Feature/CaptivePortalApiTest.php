<?php

namespace Tests\Feature;

use App\Models\BannedDevice;
use App\Models\Voucher;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RFC 8908 Captive Portal API. Advertised via DHCP option 114 (RFC 8910);
 * iOS 14+/Android 11+ poll it to show remaining session time natively in
 * Wi-Fi settings — the only way a guest sees their time tick down without
 * keeping a browser tab open, since the OS dismisses the captive-network
 * assistant (and the portal's own countdown with it) once authorized.
 */
class CaptivePortalApiTest extends TestCase
{
    use RefreshDatabase;

    private function mockIdentity(?string $mac = 'AA:BB:CC:DD:EE:FF'): void
    {
        $this->mock(OpnSenseService::class, function ($mock) use ($mac) {
            $mock->shouldReceive('resolveMacForIp')->andReturn($mac);
        });
    }

    private function callApi(string $ip = '192.168.2.50')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->get(route('captive-portal-api'));
    }

    private function makeRedeemedVoucher(int $durationMinutes, $usedAt, string $ip = '192.168.2.50'): Voucher
    {
        return Voucher::create([
            'code' => 'LAWA-'.substr(md5((string) mt_rand()), 0, 4),
            'duration_minutes' => $durationMinutes,
            'tier' => 'premium',
            'is_used' => true,
            'used_at' => $usedAt,
            'ip_address' => $ip,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ]);
    }

    public function test_unauthenticated_device_is_reported_as_captive(): void
    {
        $this->mockIdentity();

        $response = $this->callApi();

        $response->assertOk();
        $response->assertJson(['captive' => true]);
        $response->assertJsonMissingPath('seconds-remaining');
    }

    public function test_response_uses_the_rfc_mandated_media_type_and_is_not_cacheable(): void
    {
        // A plain application/json response is silently ignored by the OS
        // captive-portal agents — the media type is load-bearing, not cosmetic.
        $this->mockIdentity();

        $response = $this->callApi();

        $this->assertStringContainsString('application/captive+json', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_authorized_device_reports_remaining_seconds(): void
    {
        $this->mockIdentity();
        $this->makeRedeemedVoucher(180, now()->subMinutes(30));

        $response = $this->callApi();

        $response->assertOk();
        $response->assertJson(['captive' => false, 'can-extend-session' => true]);

        // 180 - 30 = 150 minutes left; allow a couple of seconds of clock drift.
        $remaining = $response->json('seconds-remaining');
        $this->assertEqualsWithDelta(150 * 60, $remaining, 5);
    }

    public function test_expired_voucher_makes_the_device_captive_again_with_no_negative_time(): void
    {
        $this->mockIdentity();
        $this->makeRedeemedVoucher(60, now()->subMinutes(90));

        $response = $this->callApi();

        $response->assertJson(['captive' => true]);
        $response->assertJsonMissingPath('seconds-remaining');
    }

    public function test_banned_device_is_captive_even_holding_a_valid_voucher(): void
    {
        $this->mockIdentity();
        $this->makeRedeemedVoucher(180, now()->subMinutes(5));
        BannedDevice::create(['mac_address' => 'AA:BB:CC:DD:EE:FF', 'reason' => 'abuse']);

        $response = $this->callApi();

        $response->assertJson(['captive' => true]);
        $response->assertJsonMissingPath('seconds-remaining');
    }

    public function test_device_is_matched_by_mac_even_when_its_ip_has_changed(): void
    {
        // DHCP can hand the same device a different lease mid-session; the MAC
        // blind index is what actually identifies it.
        $this->mockIdentity();
        $this->makeRedeemedVoucher(120, now()->subMinutes(10), '192.168.2.77');

        $response = $this->callApi('192.168.2.50');

        $response->assertJson(['captive' => false]);
        $this->assertEqualsWithDelta(110 * 60, $response->json('seconds-remaining'), 5);
    }

    public function test_self_referencing_urls_follow_the_request_host(): void
    {
        // The portal is served on its own hostname (plain HTTP, no forced TLS)
        // separate from the admin app's APP_URL — so these must come from the
        // request, not from config, or the OS is handed an unreachable URL.
        $this->mockIdentity();

        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.2.50'])
            ->get('http://wifi.lawatkape.lab/captive-portal-api');

        $response->assertJson(['user-portal-url' => 'http://wifi.lawatkape.lab/portal']);
    }

    public function test_endpoint_is_reachable_without_authentication(): void
    {
        // Reachable pre-auth by definition — a guest has no account at all.
        $this->mockIdentity();

        $this->callApi()->assertOk();
    }
}
