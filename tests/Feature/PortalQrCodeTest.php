<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Voucher;
use App\Services\OpnSenseService;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A guest must be able to get back to their remaining time without typing a URL.
 *
 * The two routes that would normally provide that are both closed here. A
 * phone's sign-in window is destroyed by the OS the instant the device goes
 * online, so it cannot hand a URL to the real browser; and this OPNsense build's
 * Kea model exposes only a fixed list of DHCP options with no v4-captive-portal
 * (option 114), so RFC 8908's native remaining-time display is never advertised
 * — see docs/INFRASTRUCTURE.md.
 *
 * What is left is a code the guest can scan, printed on the slip they keep.
 */
class PortalQrCodeTest extends TestCase
{
    use RefreshDatabase;

    private const IP = '192.168.2.50';

    private const MAC = 'AA:BB:CC:DD:EE:FF';

    /**
     * Everything must be inline. A pre-auth guest has no internet and a printed
     * slip has no network at all, so an externally-fetched QR image would be a
     * blank box exactly when it is needed.
     */
    public function test_the_qr_is_self_contained_svg_with_no_external_request(): void
    {
        $svg = app(QrCodeService::class)->svg('http://wifi.lawatkape.lab/portal');

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringNotContainsString('<image', $svg);
        $this->assertStringNotContainsString('http://', str_replace('http://www.w3.org/2000/svg', '', $svg));
        // A real matrix, not an empty frame.
        $this->assertGreaterThan(50, substr_count($svg, '<rect'));
    }

    /** Below two modules of quiet zone many scanners simply fail to see it. */
    public function test_the_qr_keeps_a_quiet_zone(): void
    {
        $service = app(QrCodeService::class);
        $value = 'http://example.test';

        // Asserted as a relationship rather than a fixed viewBox: the module
        // count depends on the QR version the encoder picks for the payload,
        // which is not something this test should be pinning.
        $bare = $this->viewBoxSize($service->svg($value, 160, 0));
        $padded = $this->viewBoxSize($service->svg($value, 160, 2));

        $this->assertSame($bare + 4, $padded, 'A margin of 2 must add 2 modules on each side.');
    }

    private function viewBoxSize(string $svg): int
    {
        preg_match('/viewBox="0 0 (\d+) \d+"/', $svg, $m);

        return (int) ($m[1] ?? 0);
    }

    /** A QR is always a shortcut to something reachable another way. */
    public function test_an_unencodable_value_returns_empty_rather_than_throwing(): void
    {
        // Far beyond what any QR version can hold.
        $svg = app(QrCodeService::class)->svg(str_repeat('x', 10000));

        $this->assertSame('', $svg);
    }

    public function test_the_printed_voucher_slip_carries_a_scannable_portal_code(): void
    {
        $voucher = Voucher::create([
            'code' => 'LAWA-QR01',
            'duration_minutes' => 60,
            'tier' => 'free',
            'is_used' => false,
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('network.vouchers.print', $voucher));

        $response->assertOk();
        $response->assertSee('Check your remaining time', false);
        $response->assertSee('<svg', false);
        // The address itself stays too — a scanner is not always to hand.
        $response->assertSee(route('portal.index'), false);
    }

    public function test_batch_printed_slips_carry_the_code_too(): void
    {
        $ids = collect(range(1, 3))->map(fn ($i) => Voucher::create([
            'code' => "LAWA-QR0{$i}",
            'duration_minutes' => 60,
            'tier' => 'free',
            'is_used' => false,
        ])->id)->all();

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('network.vouchers.batch-print', ['ids' => $ids]));

        $response->assertOk();
        // One per slip, all encoding the same status page.
        $this->assertSame(3, substr_count($response->getContent(), 'Check your remaining time'));
    }

    public function test_the_success_page_shows_the_same_code(): void
    {
        Voucher::create([
            'code' => 'LAWA-QR99',
            'duration_minutes' => 120,
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
        });

        $response = $this->withServerVariables(['REMOTE_ADDR' => self::IP])
            ->get(route('portal.success'));

        $response->assertOk();
        $response->assertSee('<svg', false);
        $response->assertSee('scan the code on your voucher slip', false);
    }
}
