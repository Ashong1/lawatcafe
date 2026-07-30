<?php

namespace Tests\Feature;

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
}
