<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Voucher;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_a_custom_duration_is_used_instead_of_a_preset(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('network.vouchers.generate'), [
            'quantity' => 1,
            'duration_minutes' => 'custom',
            'custom_duration_minutes' => 90,
        ])->assertRedirect();

        $this->assertDatabaseHas('vouchers', ['duration_minutes' => 90]);
    }

    /**
     * The literal string "custom" must never reach the database as a duration —
     * it is a UI sentinel, and (int) 'custom' would silently become 0.
     */
    public function test_choosing_custom_without_a_value_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('network.vouchers.generate'), [
            'quantity' => 1,
            'duration_minutes' => 'custom',
        ])->assertSessionHasErrors('custom_duration_minutes');

        $this->assertSame(0, Voucher::count());
    }

    public static function badCustomDurationProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-30],
            'beyond thirty days' => [43201],
        ];
    }

    #[DataProvider('badCustomDurationProvider')]
    public function test_an_out_of_range_custom_duration_is_rejected(int $minutes): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('network.vouchers.generate'), [
            'quantity' => 1,
            'duration_minutes' => 'custom',
            'custom_duration_minutes' => $minutes,
        ])->assertSessionHasErrors('custom_duration_minutes');

        $this->assertSame(0, Voucher::count());
    }

    public static function badPresetDurationProvider(): array
    {
        return [
            'not a number' => ['forever'],
            // Regression: the custom branch replaced an `integer|min:1` rule with
            // a closure, and a first cut of it only checked "is an integer" —
            // which let a negative through and would have minted vouchers that
            // were expired the moment they were created.
            'negative' => [-1],
            'zero' => [0],
        ];
    }

    #[DataProvider('badPresetDurationProvider')]
    public function test_an_invalid_preset_duration_is_rejected(string|int $duration): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('network.vouchers.generate'), [
            'quantity' => 1,
            'duration_minutes' => $duration,
        ])->assertSessionHasErrors('duration_minutes');

        $this->assertSame(0, Voucher::count());
    }

    /** The presets must keep working exactly as before, including with no JS. */
    public function test_presets_still_generate_without_a_custom_value(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('network.vouchers.generate'), [
            'quantity' => 1,
            'duration_minutes' => 1440,
        ])->assertRedirect();

        $this->assertDatabaseHas('vouchers', ['duration_minutes' => 1440]);
    }

    /**
     * Assert the rendered values, not just that the field exists — a select
     * driven by x-model whose seed doesn't match any option renders blank, so
     * the seed itself is the thing worth pinning.
     */
    public function test_the_generation_modal_offers_a_custom_duration_seeded_to_a_real_preset(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('listSessions')->andReturn([]);
            $mock->shouldReceive('getArpTable')->andReturn([]);
            $mock->shouldReceive('getDhcpLeases')->andReturn([]);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
        });

        $response = $this->actingAs($admin)->get(route('network.vouchers.index'));

        $response->assertOk();
        $response->assertSee('name="custom_duration_minutes"', false);
        $response->assertSee('value="custom"', false);
        // The default preset, not an empty string that would blank the select.
        $response->assertSee("durationChoice: '60'", false);
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
