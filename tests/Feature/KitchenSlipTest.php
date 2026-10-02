<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The kitchen order slip prints while customer receipts are off (pending BIR
 * registration): it carries no prices, so it can't serve as a receipt.
 */
class KitchenSlipTest extends TestCase
{
    use RefreshDatabase;

    private function sale(User $staff): Sale
    {
        $shift = Shift::create(['user_id' => $staff->id, 'starting_cash' => 0, 'status' => 'open', 'opened_at' => now()]);
        $sale = Sale::create(['transaction_number' => 'TRN-ABCD1234', 'total_amount' => 239, 'amount_received' => 239, 'status' => 'pending',
            'payment_method' => 'Cash', 'order_type' => 'takeaway', 'user_id' => $staff->id, 'shift_id' => $shift->id]);
        SaleItem::create(['sale_id' => $sale->id, 'type' => 'product', 'item_name' => 'Spanish Latte (Iced)', 'quantity' => 2, 'price' => 109.50, 'kds_status' => 'pending', 'note' => 'less sugar']);
        SaleItem::create(['sale_id' => $sale->id, 'type' => 'wifi', 'item_name' => '1 Hour Wi-Fi', 'quantity' => 1, 'price' => 20, 'kds_status' => 'pending']);

        return $sale;
    }

    public function test_slip_lists_items_and_notes_without_prices_while_receipts_are_off(): void
    {
        Setting::set('pos_receipt_printing_enabled', '0');
        $staff = User::factory()->create(['role' => 'staff']);
        $sale = $this->sale($staff);

        $this->actingAs($staff)->get(route('pos.receipt', $sale))->assertRedirect(route('pos.history'));

        $this->actingAs($staff)->get(route('pos.kitchen-slip', $sale))->assertOk()
            ->assertSee('KITCHEN COPY — NOT A RECEIPT')
            ->assertSee('#1234')
            ->assertSee('TAKE AWAY')
            ->assertSee('Spanish Latte (Iced)')
            ->assertSee('less sugar')
            ->assertDontSee('1 Hour Wi-Fi')
            ->assertDontSee('₱')
            ->assertDontSee('109.5')
            ->assertDontSee('239');
    }

    public function test_orders_page_offers_the_slip(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $sale = $this->sale($staff);

        $this->actingAs($staff)->get(route('pos.history'))->assertOk()
            ->assertSee(route('pos.kitchen-slip', $sale), false);
    }

    public function test_guests_cannot_open_a_slip(): void
    {
        $sale = $this->sale(User::factory()->create(['role' => 'staff']));

        $this->get(route('pos.kitchen-slip', $sale))->assertRedirect(route('login'));
    }
}
