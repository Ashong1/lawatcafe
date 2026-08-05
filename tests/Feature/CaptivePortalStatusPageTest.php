<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Voucher;
use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
     * A redeemed-but-not-yet-activated voucher, i.e. exactly the state
     * authenticate() now leaves the guest in.
     */
    private function redeemPendingVoucher(string $code = 'LAWA-PEND', int $minutes = 120): Voucher
    {
        return Voucher::create([
            'code' => $code,
            'duration_minutes' => $minutes,
            'tier' => 'free',
            'is_used' => true,
            'used_at' => now(),
            'activated_at' => null,
            'ip_address' => '192.168.2.50',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ]);
    }

    private function mockIdentityOnly(): void
    {
        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('resolveMacForIp')->andReturn('AA:BB:CC:DD:EE:FF');
            $mock->shouldReceive('listSessions')->andReturn([]);
        });
    }

    private function getSuccessPage()
    {
        return $this->withServerVariables(['REMOTE_ADDR' => '192.168.2.50'])->get(route('portal.success'));
    }

    /**
     * The success page is now the whole point of the redemption split: it is
     * the last moment the sign-in window is guaranteed to be alive, because the
     * firewall has not been opened yet. It must state the time bought and the
     * address to come back to.
     */
    public function test_success_page_states_the_time_bought_and_where_to_check_it(): void
    {
        $this->redeemPendingVoucher(minutes: 120);
        $this->mockIdentityOnly();

        $response = $this->getSuccessPage();

        $response->assertOk();
        $response->assertSee('2', false);          // 120 minutes rendered as hours
        $response->assertSee('Voucher Accepted', false);
        $response->assertSee('To check your remaining time later', false);
        $response->assertSee(route('portal.index'), false);
    }

    /**
     * Nothing on this page may connect or navigate on its own. The old 5s
     * auto-redirect fired after the firewall was already open, which is the
     * exact race that destroyed the window before the guest could read it.
     */
    public function test_success_page_never_navigates_or_connects_by_itself(): void
    {
        $this->redeemPendingVoucher();
        $this->mockIdentityOnly();

        $content = $this->getSuccessPage()->getContent();

        $this->assertStringNotContainsString('window.location.href', $content);
        $this->assertStringNotContainsString('setInterval', $content);
        $this->assertStringNotContainsString('neverssl', $content);
        // Going online is a form the guest submits, never something automatic.
        $this->assertStringContainsString(route('portal.activate'), $content);
    }

    /**
     * Reached without a redeemed voucher — a stale bookmark, or the back button
     * after expiry. Showing a success page there would simply be false.
     */
    public function test_success_page_redirects_when_there_is_no_redeemed_voucher(): void
    {
        $this->mockIdentityOnly();

        $this->getSuccessPage()->assertRedirect(route('portal.index'));
    }

    public function test_browse_url_is_configurable_rather_than_a_hardcoded_domain(): void
    {
        Setting::set('portal_browse_url', 'http://example.test');
        $this->redeemPendingVoucher();

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('resolveMacForIp')->andReturn('AA:BB:CC:DD:EE:FF');
            $mock->shouldReceive('listSessions')->andReturn([]);
            $mock->shouldReceive('authorizeDevice')->andReturn(true);
        });
        // Tier provisioning is TrafficShapingServiceTest's subject, not this
        // test's — left real it reaches back into the OPNsense mock above.
        $this->mock(TrafficShapingService::class, fn ($mock) => $mock->shouldReceive('assignTier'));

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.2.50'])
            ->post(route('portal.activate'))
            ->assertRedirect('http://example.test');
    }

    /**
     * Regression: with portal_browse_url unset, the "browse the web" buttons
     * fell back to neverssl.com — a captive-portal-triggering trick from
     * before the shop had a real portal hostname. A paying customer tapping a
     * button and landing on an unbranded stranger's page reads as the Wi-Fi
     * being broken, which is exactly how it was reported.
     */
    public function test_no_third_party_fallback_when_browse_url_is_unset(): void
    {
        // Setting::get caches rememberForever, and the cache outlives
        // RefreshDatabase — without this the value another test wrote is still
        // sitting there and the "unset" half of this test is meaningless.
        Cache::forget('setting.portal_browse_url');
        $this->redeemPendingVoucher();
        $this->mockIdentityOnly();

        $this->assertStringNotContainsString('neverssl', $this->getSuccessPage()->getContent());
    }
}
