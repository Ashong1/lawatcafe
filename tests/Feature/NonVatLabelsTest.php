<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The shop is non-VAT (owner, Oct 30): no 12% VAT line, no placeholder TIN or address. */
class NonVatLabelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_register_and_receipt_say_non_vat(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        Shift::create(['user_id' => $staff->id, 'starting_cash' => 0, 'status' => 'open', 'opened_at' => now()]);
        $this->actingAs($staff)->get(route('pos'))->assertDontSee('VAT (12% inc)')->assertSee('VAT-exempt');

        Setting::set('pos_receipt_printing_enabled', '1');
        $sale = Sale::create(['transaction_number' => 'TRN-1', 'user_id' => $staff->id, 'total_amount' => 112, 'payment_method' => 'Cash', 'status' => 'completed']);
        $this->actingAs($staff)->get(route('pos.receipt', $sale))->assertOk()
            ->assertDontSee('VAT (12% inc)')->assertDontSee('123-456-789-000')->assertDontSee('Brew City')->assertSee('NON-VAT');
    }
}
