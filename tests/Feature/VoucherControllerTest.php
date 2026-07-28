<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Voucher;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_batch_defaults_to_free_tier_when_not_specified(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('network.vouchers.generate'), [
            'quantity' => 2,
            'duration_minutes' => 60,
        ])->assertRedirect();

        $this->assertDatabaseHas('vouchers', ['tier' => 'free']);
        $this->assertSame(2, Voucher::where('tier', 'free')->count());
    }

    public function test_generate_batch_forwards_the_requested_tier(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('network.vouchers.generate'), [
            'quantity' => 3,
            'duration_minutes' => 60,
            'tier' => 'premium',
        ])->assertRedirect();

        $this->assertSame(3, Voucher::where('tier', 'premium')->count());
    }

    public function test_set_tier_updates_voucher_and_syncs_opnsense_alias(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $voucher = Voucher::create([
            'code' => 'LAWA-SETT', 'duration_minutes' => 60, 'tier' => 'free',
            'is_used' => true, 'used_at' => now(), 'ip_address' => '192.168.2.60',
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('removeIpFromTierAlias')->andReturn(true);
            $mock->shouldReceive('addIpToTierAlias')->once()->with('premium', '192.168.2.60')->andReturn(true);
        });

        $this->actingAs($admin)->post(route('network.sessions.set-tier'), [
            'voucher_code' => 'LAWA-SETT',
            'tier' => 'premium',
        ])->assertRedirect();

        $voucher->refresh();
        $this->assertSame('premium', $voucher->tier);
    }

    public function test_set_tier_fails_for_unknown_voucher_code(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('network.sessions.set-tier'), [
            'voucher_code' => 'NOPE',
            'tier' => 'premium',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_set_tier_rejects_an_invalid_tier_value(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('network.sessions.set-tier'), [
            'voucher_code' => 'LAWA-XYZ',
            'tier' => 'gold',
        ])->assertSessionHasErrors('tier');
    }
}
