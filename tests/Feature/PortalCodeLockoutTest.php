<?php

namespace Tests\Feature;

use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Evaluators (Oct 28): a device that types a wrong Wi-Fi code 3 times is
 * blocked automatically instead of only alerting the staff. The "already
 * used" message stands in for "the code was looked up": redeeming for real
 * needs a live firewall.
 */
class PortalCodeLockoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Voucher::create(['code' => 'FREE-USED', 'duration_minutes' => 60, 'tier' => 'free', 'is_used' => true, 'used_at' => now()]);
    }

    private function tryCode(string $code)
    {
        return $this->post(route('portal.authenticate'), ['passcode' => $code]);
    }

    public function test_three_wrong_codes_block_the_device_even_for_a_real_code(): void
    {
        foreach (['NOPE-0001', 'NOPE-0002', 'NOPE-0003'] as $wrong) {
            $this->tryCode($wrong);
        }

        $this->tryCode('FREE-USED')->assertSessionHas('error', fn ($m) => str_contains($m, 'Too many wrong codes'));
    }

    public function test_two_wrong_codes_still_let_the_third_try_through(): void
    {
        $this->tryCode('NOPE-0001');
        $this->tryCode('NOPE-0002');

        $this->tryCode('FREE-USED')->assertSessionHas('error', 'This code has already been used.');
    }

    public function test_the_block_lifts_after_five_minutes(): void
    {
        foreach (['NOPE-0001', 'NOPE-0002', 'NOPE-0003'] as $wrong) {
            $this->tryCode($wrong);
        }

        $this->travel(301)->seconds();

        $this->tryCode('FREE-USED')->assertSessionHas('error', 'This code has already been used.');
    }

    public function test_a_code_that_exists_resets_the_count(): void
    {
        $this->tryCode('NOPE-0001');
        $this->tryCode('NOPE-0002');
        $this->tryCode('FREE-USED');
        $this->tryCode('NOPE-0003');

        $this->tryCode('FREE-USED')->assertSessionHas('error', 'This code has already been used.');
    }

    public function test_a_faked_forwarded_address_does_not_reset_the_count(): void
    {
        foreach (['NOPE-0001', 'NOPE-0002', 'NOPE-0003'] as $i => $wrong) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.168.2.150'])
                ->withHeader('X-Forwarded-For', "10.0.0.{$i}")
                ->post(route('portal.authenticate'), ['passcode' => $wrong]);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.2.150'])->withHeader('X-Forwarded-For', '10.0.0.9')
            ->post(route('portal.authenticate'), ['passcode' => 'FREE-USED'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Too many wrong codes'));
    }

    public function test_the_shop_proxy_still_passes_on_the_real_address(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.2.5'])->withHeader('X-Forwarded-For', '192.168.2.150')
            ->get(route('portal.index'));

        $this->assertSame('192.168.2.150', request()->ip());
    }
}
