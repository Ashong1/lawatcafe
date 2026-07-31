<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\SaleVoidRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderHistoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeSale(User $user, array $overrides = []): Sale
    {
        return Sale::create(array_merge([
            'transaction_number' => 'TRN-'.uniqid(),
            'total_amount' => 100,
            'status' => 'completed',
            'payment_method' => 'Cash',
            'order_type' => 'dine_in',
            'user_id' => $user->id,
        ], $overrides));
    }

    public function test_index_defaults_to_todays_sales(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $today = $this->makeSale($admin, ['transaction_number' => 'TRN-TODAY']);
        $yesterday = $this->makeSale($admin, ['transaction_number' => 'TRN-YESTERDAY']);
        $yesterday->created_at = now()->subDay();
        $yesterday->save();

        $response = $this->actingAs($admin)->get(route('pos.history'));

        $response->assertOk();
        $ids = $response->viewData('sales')->pluck('id')->all();
        $this->assertContains($today->id, $ids);
        $this->assertNotContains($yesterday->id, $ids);
    }

    public function test_index_can_search_by_transaction_number(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $match = $this->makeSale($admin, ['transaction_number' => 'TRN-FINDME']);
        $other = $this->makeSale($admin, ['transaction_number' => 'TRN-OTHER']);

        $response = $this->actingAs($admin)->get(route('pos.history', ['search' => 'FINDME']));

        $ids = $response->viewData('sales')->pluck('id')->all();
        $this->assertContains($match->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_index_can_filter_by_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $completed = $this->makeSale($admin, ['transaction_number' => 'TRN-COMPLETED', 'status' => 'completed']);
        $cancelled = $this->makeSale($admin, ['transaction_number' => 'TRN-CANCELLED', 'status' => 'cancelled']);

        $response = $this->actingAs($admin)->get(route('pos.history', ['status' => 'cancelled']));

        $ids = $response->viewData('sales')->pluck('id')->all();
        $this->assertContains($cancelled->id, $ids);
        $this->assertNotContains($completed->id, $ids);
    }

    public function test_index_can_filter_by_an_explicit_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sale = $this->makeSale($admin, ['transaction_number' => 'TRN-OLD']);
        $sale->created_at = '2026-01-01 10:00:00';
        $sale->save();

        $response = $this->actingAs($admin)->get(route('pos.history', ['date' => '2026-01-01']));

        $ids = $response->viewData('sales')->pluck('id')->all();
        $this->assertContains($sale->id, $ids);
    }

    public function test_pending_void_requests_are_hidden_from_staff(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $sale = $this->makeSale($staff);
        SaleVoidRequest::create([
            'sale_id' => $sale->id,
            'requested_by' => $staff->id,
            'reason' => 'Test',
            'status' => 'pending',
        ]);

        $adminResponse = $this->actingAs($admin)->get(route('pos.history'));
        $this->assertCount(1, $adminResponse->viewData('pendingVoidRequests'));

        $staffResponse = $this->actingAs($staff)->get(route('pos.history'));
        $this->assertCount(0, $staffResponse->viewData('pendingVoidRequests'));
    }
}
