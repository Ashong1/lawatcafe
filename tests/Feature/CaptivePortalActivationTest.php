<?php

namespace Tests\Feature;

use App\Models\Voucher;
use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
