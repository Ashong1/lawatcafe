<?php

namespace Tests\Feature;

use App\Models\EwalletPayment;
use App\Models\Voucher;
use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * portal.verify-payment supports two response modes from the same handler:
 * a JSON mode (added so the guest-facing form can submit via fetch and keep
 * its loading state through the whole OPNsense round trip, instead of a
 * native form POST blanking the tab mid-wait) and the original
 * redirect+session-flash mode, kept as a fallback for a plain <form> with
 * no JS. Both are covered here — this endpoint had zero test coverage
 * before this pass despite being unauthenticated and payment-adjacent.
 */
class CaptivePortalVerifyPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_reference_number_flashes_an_error_for_the_redirect_fallback(): void
    {
        $response = $this->post(route('portal.verify-payment'), ['reference_number' => 'GC-NOPE']);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_unknown_reference_number_returns_a_json_error_for_the_fetch_path(): void
    {
        $response = $this->postJson(route('portal.verify-payment'), ['reference_number' => 'GC-NOPE']);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $response->assertJsonStructure(['success', 'message']);
    }

    public function test_amount_below_the_minimum_tier_returns_a_json_error(): void
    {
        EwalletPayment::create([
            'reference_number' => 'GC-TOOLOW',
            'amount' => 5,
            'is_used' => false,
        ]);

        $response = $this->postJson(route('portal.verify-payment'), ['reference_number' => 'GC-TOOLOW']);

        $response->assertStatus(422);
        $response->assertJson(['success' => false, 'message' => 'Insufficient amount for a Wi-Fi voucher. Minimum is ₱20.00.']);

        // Must not have been consumed on a rejected attempt.
        $this->assertDatabaseHas('ewallet_payments', ['reference_number' => 'GC-TOOLOW', 'is_used' => false]);
    }

    public function test_an_already_used_payment_is_not_matched_again(): void
    {
        EwalletPayment::create([
            'reference_number' => 'GC-USED',
            'amount' => 100,
            'is_used' => true,
        ]);

        $response = $this->postJson(route('portal.verify-payment'), ['reference_number' => 'GC-USED']);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_a_valid_payment_creates_a_voucher_and_returns_a_json_redirect(): void
    {
        EwalletPayment::create([
            'reference_number' => 'GC-VALID',
            'amount' => 100,
            'is_used' => false,
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('resolveMacForIp')->andReturn('AA:BB:CC:DD:EE:FF');
            $mock->shouldReceive('authorizeDevice')->once()->andReturn(true);
        });
        $this->mock(TrafficShapingService::class, function ($mock) {
            $mock->shouldReceive('assignTier')->once();
        });

        $response = $this->postJson(route('portal.verify-payment'), ['reference_number' => 'GC-VALID']);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure(['success', 'redirect']);

        $this->assertDatabaseHas('ewallet_payments', ['reference_number' => 'GC-VALID', 'is_used' => true]);

        $voucher = Voucher::where('tier', 'premium')->where('duration_minutes', 1440)->first();
        $this->assertNotNull($voucher, 'A premium voucher matching the ₱100 tier should have been created.');
        $this->assertTrue((bool) $voucher->is_used);
    }

    public function test_a_valid_payment_still_supports_the_redirect_fallback(): void
    {
        EwalletPayment::create([
            'reference_number' => 'GC-VALID2',
            'amount' => 50,
            'is_used' => false,
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('resolveMacForIp')->andReturn('AA:BB:CC:DD:EE:00');
            $mock->shouldReceive('authorizeDevice')->once()->andReturn(true);
        });
        $this->mock(TrafficShapingService::class, function ($mock) {
            $mock->shouldReceive('assignTier')->once();
        });

        $response = $this->post(route('portal.verify-payment'), ['reference_number' => 'GC-VALID2']);

        $response->assertRedirect(route('portal.success'));
        $response->assertSessionHas('passcode');
    }

    public function test_a_banned_device_is_rejected_even_with_a_valid_payment(): void
    {
        EwalletPayment::create([
            'reference_number' => 'GC-BANNED',
            'amount' => 100,
            'is_used' => false,
        ]);

        \App\Models\BannedDevice::create(['mac_address' => 'AA:BB:CC:DD:EE:FF']);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('resolveMacForIp')->andReturn('AA:BB:CC:DD:EE:FF');
        });

        $response = $this->postJson(route('portal.verify-payment'), ['reference_number' => 'GC-BANNED']);

        $response->assertStatus(422);
        $response->assertJson(['success' => false, 'message' => 'This device has been blocked from network access. Please see staff for assistance.']);

        // The payment must not be consumed on a rejected/banned attempt.
        $this->assertDatabaseHas('ewallet_payments', ['reference_number' => 'GC-BANNED', 'is_used' => false]);
    }
}
