<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One person runs each shift (evaluators, Oct 28; owner): taking and making
 * orders happen on the register itself, not on a separate Kitchen Display page.
 */
class RegisterOrdersPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->create(['role' => 'staff']);
    }

    private function order(string $number, string $status): Sale
    {
        $sale = Sale::create(['transaction_number' => $number, 'total_amount' => 100, 'status' => $status, 'user_id' => $this->staff->id, 'order_type' => 'dine_in']);
        $sale->items()->create(['item_name' => 'Iced Latte', 'quantity' => 2, 'price' => 50, 'type' => 'product', 'category' => 'Coffee']);

        return $sale;
    }

    public function test_the_register_has_the_orders_to_make_panel_with_a_count(): void
    {
        Shift::create(['user_id' => $this->staff->id, 'starting_cash' => 500, 'status' => 'open', 'opened_at' => now()]);
        $this->order('TRN-00000041', 'pending');
        $this->order('TRN-00000042', 'completed');

        $this->actingAs($this->staff)->get(route('pos'))->assertOk()
            ->assertSee('Orders to make')
            ->assertSee('data-orders-count="1"', false)
            ->assertSee('view=panel', false)
            ->assertSee('#0041');
    }

    public function test_the_panel_feed_lists_waiting_orders_with_start_and_done(): void
    {
        $this->order('TRN-00000041', 'pending');
        $this->order('TRN-00000043', 'preparing');

        $this->actingAs($this->staff)->getJson(route('kds.data', ['view' => 'panel']))
            ->assertOk()->assertJson(['count' => 2])
            ->assertJsonPath('html', fn ($html) => str_contains($html, '#0041') && str_contains($html, '2×') && str_contains($html, 'Iced Latte')
                && str_contains($html, 'Start') && str_contains($html, 'Done') && str_contains($html, 'data-orders-panel'));
    }

    public function test_marking_done_from_the_register_returns_the_updated_panel(): void
    {
        $sale = $this->order('TRN-00000041', 'pending');

        $this->actingAs($this->staff)->postJson(route('kds.update', $sale), ['status' => 'completed', 'view' => 'panel'])
            ->assertOk()->assertJson(['count' => 0])
            ->assertJsonPath('html', fn ($html) => str_contains($html, 'data-orders-panel') && ! str_contains($html, '#0041'));
        $this->assertSame('completed', $sale->fresh()->status);
    }
}
