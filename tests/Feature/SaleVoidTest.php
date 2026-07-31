<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleVoidTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_service_void_marks_a_completed_sale_cancelled(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $sale = Sale::create([
            'transaction_number' => 'TRN-VOID001', 'total_amount' => 150, 'status' => 'completed',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $user->id,
        ]);

        $result = app(SaleService::class)->void($sale, actorUserId: null, actorType: 'human');

        $this->assertTrue($result['success']);
        $sale->refresh();
        $this->assertSame('cancelled', $sale->status);
    }

    public function test_sale_service_void_refuses_an_already_voided_sale(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $sale = Sale::create([
            'transaction_number' => 'TRN-VOID002', 'total_amount' => 150, 'status' => 'cancelled',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $user->id,
        ]);

        $result = app(SaleService::class)->void($sale);

        $this->assertFalse($result['success']);
        $this->assertSame('Order is already voided.', $result['message']);
    }

    public function test_sale_service_void_does_not_restore_ingredient_stock(): void
    {
        // Documents a known, deliberate limitation (see SaleService::void's
        // docblock): voiding a sale does not currently reverse the ingredient
        // stock deduction that happened at checkout. This test exists so that
        // if that behavior ever changes, it changes on purpose (updating this
        // test) rather than silently, and so it doesn't regress into being
        // "half-fixed" by accident.
        $user = User::factory()->create(['role' => 'staff']);
        $milk = Ingredient::create(['name' => 'Milk', 'current_stock' => 40, 'unit' => 'ml', 'low_stock_threshold' => 500, 'status' => 'Low Stock']);
        $sale = Sale::create([
            'transaction_number' => 'TRN-VOID003', 'total_amount' => 150, 'status' => 'completed',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $user->id,
        ]);

        app(SaleService::class)->void($sale);

        $milk->refresh();
        $this->assertEquals(40, $milk->current_stock, 'Stock must remain exactly as it was — void does not restock.');
    }

    public function test_order_history_void_endpoint_voids_the_sale_instantly_for_an_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sale = Sale::create([
            'transaction_number' => 'TRN-VOID004', 'total_amount' => 150, 'status' => 'completed',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('pos.history.void', $sale));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $sale->refresh();
        $this->assertSame('cancelled', $sale->status);
    }

    public function test_order_history_void_endpoint_flashes_error_for_an_already_voided_sale_as_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sale = Sale::create([
            'transaction_number' => 'TRN-VOID005', 'total_amount' => 150, 'status' => 'cancelled',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('pos.history.void', $sale));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_order_history_void_endpoint_creates_a_pending_request_for_staff_instead_of_voiding(): void
    {
        // Staff can no longer void instantly — see SaleVoidRequestTest for the
        // full pending-request/admin-approval flow.
        $staff = User::factory()->create(['role' => 'staff']);
        $sale = Sale::create([
            'transaction_number' => 'TRN-VOID007', 'total_amount' => 150, 'status' => 'completed',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $staff->id,
        ]);

        $response = $this->actingAs($staff)->post(route('pos.history.void', $sale), ['reason' => 'Customer changed their mind.']);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $sale->refresh();
        $this->assertSame('completed', $sale->status, 'Sale must not change status until an admin approves the request.');
        $this->assertDatabaseHas('sale_void_requests', ['sale_id' => $sale->id, 'status' => 'pending']);
    }

    public function test_guest_cannot_reach_the_void_endpoint(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $sale = Sale::create([
            'transaction_number' => 'TRN-VOID006', 'total_amount' => 150, 'status' => 'completed',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $user->id,
        ]);

        $response = $this->post(route('pos.history.void', $sale));

        $response->assertRedirect(route('login'));
        $sale->refresh();
        $this->assertSame('completed', $sale->status);
    }
}
