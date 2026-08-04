<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Voucher;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaptivePortalStatusPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression: portal.status renders an "Extend Session" tab that loops
     * over $durations and reads $qrCode unconditionally (Blade renders every
     * tab server-side; Alpine's x-show only hides them client-side). Those
     * were never passed alongside session/expirationTime/userName, so any
     * guest with an active session hit an undefined-variable error instead
     * of ever seeing their remaining time.
     */
    public function test_guest_with_an_active_session_sees_their_status_instead_of_a_crash(): void
    {
        $voucher = Voucher::create([
            'code' => 'LAWA-TEST',
            'duration_minutes' => 180,
            'tier' => 'premium',
            'is_used' => true,
            'used_at' => now(),
            'ip_address' => '192.168.2.50',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ]);

        $this->mock(OpnSenseService::class, function ($mock) use ($voucher) {
            $mock->shouldReceive('resolveMacForIp')->andReturn('AA:BB:CC:DD:EE:FF');
            $mock->shouldReceive('listSessions')->andReturn([
                [
                    'sessionId' => 'sess-1',
                    'ipAddress' => '192.168.2.50/32',
                    'macAddress' => 'AA:BB:CC:DD:EE:FF',
                    'startTime' => now()->subMinutes(5)->timestamp,
                    'bytes_received' => 1024,
                    'userName' => $voucher->code,
                ],
            ]);
        });

        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.2.50'])->get(route('portal.index'));

        $response->assertOk();
        $response->assertViewIs('portal.status');
        $response->assertSee('You\'re Online', false);
        // The live countdown reads its starting point from this timestamp.
        $response->assertSee((string) $voucher->used_at->copy()->addMinutes(180)->getTimestampMs());
    }

    /**
     * Regression: the success page used to auto-navigate to neverssl.com after
     * 5s (a captive-network-assistant dismissal trick), which dumped every
     * guest on a blank third-party page and threw away the countdown they'd
     * just been given. The automatic destination is now the portal's own
     * status page; reaching the open web is a deliberate secondary action.
     */
    public function test_success_page_lands_the_guest_on_their_own_session_not_a_third_party_site(): void
    {
        $response = $this->get(route('portal.success'));

        $response->assertOk();
        $response->assertSee(route('portal.index'), false);
        $this->assertStringNotContainsString(
            'window.location.href = "http://neverssl.com"',
            $response->getContent(),
            'The 5s auto-redirect must not send guests to a third-party site.'
        );
    }

    public function test_success_page_shows_the_portal_address_for_reopening_in_a_real_browser(): void
    {
        $response = $this->get(route('portal.success'));

        $response->assertSee('open this page in your normal browser', false);
        $response->assertSee(route('portal.index'), false);
    }

    public function test_browse_url_is_configurable_rather_than_a_hardcoded_domain(): void
    {
        Setting::set('portal_browse_url', 'http://example.test');

        $response = $this->get(route('portal.success'));

        $response->assertSee('http://example.test', false);
    }
}
