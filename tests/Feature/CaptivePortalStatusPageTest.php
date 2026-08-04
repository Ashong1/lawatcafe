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

        $response->assertSee('open this address in your normal browser', false);
        $response->assertSee(route('portal.index'), false);
    }

    /**
     * The success page serves two audiences from one response: an ordinary
     * browser, where the tab survives and the 5s hand-off to the status page is
     * right; and the phone's captive-network assistant, where the OS destroys
     * the window the moment its connectivity probe succeeds, so auto-navigating
     * only flashes a page the guest can never get back to. Both variants must
     * ship in the markup — which one shows is decided client-side by the
     * html.is-cna class, since the user agent is the only signal available.
     */
    public function test_success_page_ships_both_the_browser_and_assistant_variants(): void
    {
        $response = $this->get(route('portal.success'));

        $response->assertOk();
        $response->assertSee('browser-only', false);
        $response->assertSee('cna-only', false);
        $response->assertSee('isCaptiveAssistant', false);
    }

    /**
     * The auto-redirect must stay gated on the assistant check. Without the
     * gate the assistant navigates mid-teardown, which is the failure this
     * whole split exists to prevent.
     */
    public function test_success_page_auto_redirect_is_gated_on_the_assistant_check(): void
    {
        $content = $this->get(route('portal.success'))->getContent();

        $this->assertStringContainsString(
            'reducedMotion || window.isCaptiveAssistant()',
            $content,
            'The countdown redirect must be skipped inside a captive-network assistant.'
        );
    }

    public function test_browse_url_is_configurable_rather_than_a_hardcoded_domain(): void
    {
        Setting::set('portal_browse_url', 'http://example.test');

        $response = $this->get(route('portal.success'));

        $response->assertSee('http://example.test', false);
    }
}
