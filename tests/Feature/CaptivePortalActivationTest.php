<?php

namespace Tests\Feature;

use App\Models\Voucher;
use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Redeeming a voucher and being let onto the internet used to be one step, and
 * that is what made the portal look like it closed itself: a phone's captive
 * assistant is destroyed by the OS the instant its connectivity probe
 * succeeds, so opening the firewall inside authenticate() killed the window
 * before the success page — the only place the guest is told how to watch
 * their remaining time — could render.
 *
 * They are now two steps. These tests pin the seam between them.
 */
class CaptivePortalActivationTest extends TestCase
{
    use RefreshDatabase;

    private const IP = '192.168.2.50';

    private const MAC = 'AA:BB:CC:DD:EE:FF';

    private function unusedVoucher(string $code = 'LAWA-NEW', int $minutes = 60): Voucher
    {
        return Voucher::create([
            'code' => $code,
            'duration_minutes' => $minutes,
            'tier' => 'free',
            'is_used' => false,
        ]);
    }

    private function fromGuestDevice()
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::IP]);
    }

    public function test_redeeming_a_code_claims_the_voucher_without_opening_the_firewall(): void
    {
        $voucher = $this->unusedVoucher();

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('resolveMacForIp')->andReturn(self::MAC);
            $mock->shouldReceive('listSessions')->andReturn([]);
            // The whole point: nothing touches the firewall at redemption time.
            $mock->shouldNotReceive('authorizeDevice');
        });

        $response = $this->fromGuestDevice()->post(route('portal.authenticate'), ['passcode' => $voucher->code]);

        $response->assertRedirect(route('portal.success'));

        $voucher->refresh();
        $this->assertTrue($voucher->is_used);
        $this->assertNotNull($voucher->used_at);
        $this->assertNull($voucher->activated_at, 'Redemption must not mark the voucher activated.');
        $this->assertSame(self::IP, $voucher->ip_address);
    }

    public function test_tapping_start_browsing_authorizes_the_device_and_applies_its_tier(): void
    {
        $voucher = Voucher::create([
            'code' => 'LAWA-PEND',
            'duration_minutes' => 60,
            'tier' => 'premium',
            'is_used' => true,
            'used_at' => now(),
            'activated_at' => null,
            'ip_address' => self::IP,
            'mac_address' => self::MAC,
        ]);

        $this->mock(OpnSenseService::class, function ($mock) use ($voucher) {
            $mock->shouldReceive('resolveMacForIp')->andReturn(self::MAC);
            $mock->shouldReceive('listSessions')->andReturn([]);
            $mock->shouldReceive('authorizeDevice')->once()->with(self::IP, $voucher->code)->andReturn(true);
        });

        $this->mock(TrafficShapingService::class, function ($mock) {
            $mock->shouldReceive('assignTier')->once();
        });

        $this->fromGuestDevice()->post(route('portal.activate'))->assertRedirect();

        $this->assertNotNull($voucher->fresh()->activated_at);
    }

    /**
     * The safety net for the window dying anyway. Without it a guest whose
     * assistant was torn down before they tapped through would be holding a
     * spent code and no internet.
     */
    public function test_portal_recovers_a_redemption_that_was_never_activated(): void
    {
        $voucher = Voucher::create([
            'code' => 'LAWA-ABAN',
            'duration_minutes' => 60,
            'tier' => 'free',
            'is_used' => true,
            'used_at' => now()->subMinutes(2),
            'activated_at' => null,
            'ip_address' => self::IP,
            'mac_address' => self::MAC,
        ]);

        $this->mock(OpnSenseService::class, function ($mock) use ($voucher) {
            $mock->shouldReceive('resolveMacForIp')->andReturn(self::MAC);
            $mock->shouldReceive('authorizeDevice')->once()->with(self::IP, $voucher->code)->andReturn(true);
            // Empty before authorization, live after — the portal re-reads the
            // session list so it can render the status page in the same request.
            $mock->shouldReceive('listSessions')->andReturn([], [], [[
                'sessionId' => 'sess-1',
                'ipAddress' => self::IP.'/32',
                'macAddress' => self::MAC,
                'startTime' => now()->timestamp,
                'userName' => $voucher->code,
            ]]);
        });

        $this->mock(TrafficShapingService::class, function ($mock) {
            $mock->shouldReceive('assignTier')->once();
        });

        $this->fromGuestDevice()->get(route('portal.index'))->assertOk();

        $this->assertNotNull($voucher->fresh()->activated_at);
    }

    /**
     * The reason activated_at exists rather than just "is_used and no live
     * session". A guest who deliberately hits Disconnect still has paid-for
     * minutes left, and auto-recovery must not drag them straight back online.
     */
    public function test_recovery_never_reconnects_a_guest_who_deliberately_disconnected(): void
    {
        Voucher::create([
            'code' => 'LAWA-DISC',
            'duration_minutes' => 60,
            'tier' => 'free',
            'is_used' => true,
            'used_at' => now()->subMinutes(5),
            // Already been online once — that is what disconnecting implies.
            'activated_at' => now()->subMinutes(5),
            'ip_address' => self::IP,
            'mac_address' => self::MAC,
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('resolveMacForIp')->andReturn(self::MAC);
            $mock->shouldReceive('listSessions')->andReturn([]);
            $mock->shouldNotReceive('authorizeDevice');
        });

        $this->fromGuestDevice()->get(route('portal.index'))->assertViewIs('portal.index');
    }

    public function test_activating_an_expired_voucher_is_refused(): void
    {
        Voucher::create([
            'code' => 'LAWA-OLD',
            'duration_minutes' => 60,
            'tier' => 'free',
            'is_used' => true,
            'used_at' => now()->subHours(3),
            'activated_at' => null,
            'ip_address' => self::IP,
            'mac_address' => self::MAC,
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('resolveMacForIp')->andReturn(self::MAC);
            $mock->shouldReceive('listSessions')->andReturn([]);
            $mock->shouldNotReceive('authorizeDevice');
        });

        $this->fromGuestDevice()->post(route('portal.activate'))
            ->assertRedirect(route('portal.index'))
            ->assertSessionHas('error', 'Your session has expired. Please enter a new voucher.');
    }

    /**
     * The handoff is the whole reason a guest ends up in their own browser.
     *
     * A sign-in window only closes when the OS's connectivity probe succeeds,
     * and the OS will not accept a page served by the portal itself as proof of
     * internet. browseUrl()'s default IS the portal, so activate() used to
     * redirect the captive window to a local address and leave it sitting there
     * — "it never opens my browser".
     */
    public static function handoffProvider(): array
    {
        return [
            'android' => [
                'Mozilla/5.0 (Linux; Android 13; Pixel 7 Build/TQ3A; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/117 Mobile Safari/537.36',
                'http://connectivitycheck.gstatic.com/generate_204',
            ],
            'ios' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148',
                'http://captive.apple.com/hotspot-detect.html',
            ],
            'anything else' => [
                'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120 Safari/537.36',
                'http://connectivitycheck.gstatic.com/generate_204',
            ],
        ];
    }

    #[DataProvider('handoffProvider')]
    public function test_activation_hands_the_device_off_to_the_open_internet(string $userAgent, string $expected): void
    {
        Voucher::create([
            'code' => 'LAWA-HAND',
            'duration_minutes' => 60,
            'tier' => 'free',
            'is_used' => true,
            'used_at' => now(),
            'activated_at' => null,
            'ip_address' => self::IP,
            'mac_address' => self::MAC,
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('resolveMacForIp')->andReturn(self::MAC);
            $mock->shouldReceive('listSessions')->andReturn([]);
            $mock->shouldReceive('authorizeDevice')->andReturn(true);
        });
        $this->mock(TrafficShapingService::class, fn ($mock) => $mock->shouldReceive('assignTier'));

        $this->fromGuestDevice()
            ->withHeaders(['User-Agent' => $userAgent])
            ->post(route('portal.activate'))
            ->assertRedirect($expected);
    }

    /** Plain HTTP is not incidental — these probes are defined as HTTP. */
    public function test_the_handoff_target_is_never_https(): void
    {
        foreach (self::handoffProvider() as [$userAgent, $expected]) {
            $this->assertStringStartsWith('http://', $expected);
        }
    }

    /**
     * A redeemed voucher is not a spent one. The guest bought a span of time,
     * not one connection, and the tab holding their session is trivially lost —
     * the phone sleeps, the captive window closes, they switch to mobile data
     * and back. Re-entry is safe precisely because the voucher is MAC-bound.
     */
    public function test_the_same_device_can_re_enter_its_code_while_time_remains(): void
    {
        $voucher = Voucher::create([
            'code' => 'LAWA-BACK',
            'duration_minutes' => 120,
            'tier' => 'free',
            'is_used' => true,
            'used_at' => now()->subMinutes(30),
            'activated_at' => now()->subMinutes(30),
            'ip_address' => self::IP,
            'mac_address' => self::MAC,
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('resolveMacForIp')->andReturn(self::MAC);
            $mock->shouldReceive('listSessions')->andReturn([]);
        });

        $this->fromGuestDevice()
            ->post(route('portal.authenticate'), ['passcode' => $voucher->code])
            ->assertRedirect(route('portal.success'))
            ->assertSessionHasNoErrors();

        // Still one redemption — re-entry must not restart the clock, or a
        // guest could hold the code and refresh their way to unlimited Wi-Fi.
        $this->assertTrue($voucher->fresh()->used_at->eq($voucher->used_at));
    }

    /**
     * Re-entry follows the device, not the address: DHCP moves guests around,
     * and every downstream check matches on the recorded IP.
     */
    public function test_re_entry_updates_the_recorded_ip_when_dhcp_has_moved_the_device(): void
    {
        $voucher = Voucher::create([
            'code' => 'LAWA-MOVED',
            'duration_minutes' => 120,
            'tier' => 'free',
            'is_used' => true,
            'used_at' => now()->subMinutes(10),
            'activated_at' => now()->subMinutes(10),
            'ip_address' => '192.168.2.140',
            'mac_address' => self::MAC,
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('resolveMacForIp')->andReturn(self::MAC);
            $mock->shouldReceive('listSessions')->andReturn([]);
        });

        $this->fromGuestDevice()
            ->post(route('portal.authenticate'), ['passcode' => $voucher->code])
            ->assertRedirect(route('portal.success'));

        $this->assertSame(self::IP, $voucher->fresh()->ip_address);
    }

    /** The binding has to actually bind — a different device gets nothing. */
    public function test_another_device_cannot_reuse_a_bound_code(): void
    {
        Voucher::create([
            'code' => 'LAWA-BOUND',
            'duration_minutes' => 120,
            'tier' => 'free',
            'is_used' => true,
            'used_at' => now()->subMinutes(10),
            'activated_at' => now()->subMinutes(10),
            'ip_address' => '192.168.2.140',
            'mac_address' => 'AA:AA:AA:AA:AA:AA',
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            // A different MAC entirely.
            $mock->shouldReceive('resolveMacForIp')->andReturn('BB:BB:BB:BB:BB:BB');
            $mock->shouldReceive('listSessions')->andReturn([]);
        });

        $this->fromGuestDevice()
            ->post(route('portal.authenticate'), ['passcode' => 'LAWA-BOUND'])
            ->assertSessionHas('error', 'This code is already in use on another device.');
    }

    public function test_a_code_whose_time_has_run_out_is_still_refused(): void
    {
        Voucher::create([
            'code' => 'LAWA-SPENT',
            'duration_minutes' => 60,
            'tier' => 'free',
            'is_used' => true,
            'used_at' => now()->subHours(3),
            'activated_at' => now()->subHours(3),
            'ip_address' => self::IP,
            'mac_address' => self::MAC,
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('resolveMacForIp')->andReturn(self::MAC);
            $mock->shouldReceive('listSessions')->andReturn([]);
        });

        $this->fromGuestDevice()
            ->post(route('portal.authenticate'), ['passcode' => 'LAWA-SPENT'])
            ->assertSessionHas('error', 'This code has already been used and its time has run out.');
    }

    /**
     * Vouchers redeemed when the ARP lookup came back empty carry no MAC, so
     * the IP is the only binding available — refusing them outright would
     * strand a guest who genuinely paid.
     */
    public function test_a_voucher_with_no_recorded_mac_falls_back_to_matching_on_ip(): void
    {
        Voucher::create([
            'code' => 'LAWA-NOMAC',
            'duration_minutes' => 120,
            'tier' => 'free',
            'is_used' => true,
            'used_at' => now()->subMinutes(10),
            'activated_at' => now()->subMinutes(10),
            'ip_address' => self::IP,
            'mac_address' => null,
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('resolveMacForIp')->andReturn(null);
            $mock->shouldReceive('listSessions')->andReturn([]);
        });

        $this->fromGuestDevice()
            ->post(route('portal.authenticate'), ['passcode' => 'LAWA-NOMAC'])
            ->assertRedirect(route('portal.success'));
    }

    /**
     * A firewall that cannot be reached must leave the voucher unactivated, so
     * the guest can retry and the recovery path above still applies.
     */
    public function test_a_firewall_failure_leaves_the_voucher_activatable(): void
    {
        $voucher = Voucher::create([
            'code' => 'LAWA-FAIL',
            'duration_minutes' => 60,
            'tier' => 'free',
            'is_used' => true,
            'used_at' => now(),
            'activated_at' => null,
            'ip_address' => self::IP,
            'mac_address' => self::MAC,
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('resolveMacForIp')->andReturn(self::MAC);
            $mock->shouldReceive('listSessions')->andReturn([]);
            $mock->shouldReceive('authorizeDevice')->andReturn(false);
        });

        $this->fromGuestDevice()->post(route('portal.activate'))
            ->assertRedirect(route('portal.success'))
            ->assertSessionHas('error');

        $this->assertNull($voucher->fresh()->activated_at);
    }
}
