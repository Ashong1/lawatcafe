<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\SaleVoidRequest;
use App\Models\User;
use App\Notifications\SystemAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Staff can't void a sale outright — they submit a request with a reason,
 * and an admin/owner reviews it. Admin/owner acting on their own still voids
 * instantly (see SaleVoidTest). This is deliberately staff-submission-only,
 * mirroring the delivery-receiving auto-confirm/pending-review pattern.
 */
class SaleVoidRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSale(User $user, array $overrides = []): Sale
    {
        return Sale::create(array_merge([
            'transaction_number' => 'TRN-'.uniqid(),
            'total_amount' => 150,
            'status' => 'completed',
            'payment_method' => 'Cash',
            'order_type' => 'dine_in',
            'user_id' => $user->id,
        ], $overrides));
    }

    public function test_staff_void_request_requires_a_reason(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $sale = $this->makeSale($staff);

        $response = $this->actingAs($staff)->post(route('pos.history.void', $sale));

        $response->assertSessionHasErrors('reason');
        $sale->refresh();
        $this->assertSame('completed', $sale->status);
    }

    public function test_staff_void_request_notifies_admins(): void
    {
        Notification::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $sale = $this->makeSale($staff);

        $this->actingAs($staff)->post(route('pos.history.void', $sale), ['reason' => 'Wrong item rung up.']);

        Notification::assertSentTo($admin, SystemAlert::class);
    }

    public function test_cannot_submit_a_second_pending_request_for_the_same_sale(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $sale = $this->makeSale($staff);

        $this->actingAs($staff)->post(route('pos.history.void', $sale), ['reason' => 'First reason.']);
        $response = $this->actingAs($staff)->post(route('pos.history.void', $sale), ['reason' => 'Second reason.']);

        $response->assertSessionHas('error');
        $this->assertSame(1, SaleVoidRequest::where('sale_id', $sale->id)->count());
    }

    public function test_admin_approving_a_request_voids_the_sale(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $sale = $this->makeSale($staff);

        $this->actingAs($staff)->post(route('pos.history.void', $sale), ['reason' => 'Customer left.']);
        $voidRequest = SaleVoidRequest::firstOrFail();

        $response = $this->actingAs($admin)->post(route('pos.history.void-requests.approve', $voidRequest));

        $response->assertRedirect(route('pos.history'));
        $sale->refresh();
        $this->assertSame('cancelled', $sale->status);

        $voidRequest->refresh();
        $this->assertSame('approved', $voidRequest->status);
        $this->assertSame($admin->id, $voidRequest->reviewed_by);
        $this->assertNotNull($voidRequest->reviewed_at);
    }

    public function test_admin_rejecting_a_request_never_voids_the_sale(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $sale = $this->makeSale($staff);

        $this->actingAs($staff)->post(route('pos.history.void', $sale), ['reason' => 'Customer left.']);
        $voidRequest = SaleVoidRequest::firstOrFail();

        $this->actingAs($admin)->post(route('pos.history.void-requests.reject', $voidRequest))
            ->assertRedirect(route('pos.history'));

        $sale->refresh();
        $this->assertSame('completed', $sale->status);

        $voidRequest->refresh();
        $this->assertSame('rejected', $voidRequest->status);
    }

    public function test_staff_cannot_approve_or_reject_void_requests(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $sale = $this->makeSale($staff);

        $this->actingAs($staff)->post(route('pos.history.void', $sale), ['reason' => 'Customer left.']);
        $voidRequest = SaleVoidRequest::firstOrFail();

        $this->actingAs($staff)->post(route('pos.history.void-requests.approve', $voidRequest))
            ->assertRedirect(route('staff.dashboard'));

        $voidRequest->refresh();
        $this->assertSame('pending', $voidRequest->status);
    }

    public function test_pending_void_requests_are_shown_to_admin_on_the_history_page(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'name' => 'Jamie Staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $sale = $this->makeSale($staff);

        $this->actingAs($staff)->post(route('pos.history.void', $sale), ['reason' => 'Accidentally rung up twice.']);

        $response = $this->actingAs($admin)->get(route('pos.history'));

        $response->assertOk();
        $response->assertSee('Jamie Staff');
        $response->assertSee('Accidentally rung up twice.');
    }
}
