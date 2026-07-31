<?php

namespace Tests\Feature;

use App\Mail\PurchaseOrderRequest;
use App\Models\Ingredient;
use App\Models\PurchaseOrderDraft;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PurchaseOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function makeDraft(array $overrides = []): PurchaseOrderDraft
    {
        $ingredient = Ingredient::create(['name' => 'Milk', 'current_stock' => 50, 'unit' => 'ml', 'low_stock_threshold' => 500, 'status' => 'Low Stock']);

        return PurchaseOrderDraft::create(array_merge([
            'ingredient_id' => $ingredient->id,
            'suggested_quantity' => 1000,
            'status' => 'draft',
            'notes' => 'Auto-drafted.',
            'created_by_actor_type' => 'ai',
        ], $overrides));
    }

    public function test_index_lists_purchase_order_drafts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->makeDraft();

        $response = $this->actingAs($admin)->get(route('inventory.purchase-orders.index'));

        $response->assertOk();
        $response->assertSee('Milk');
    }

    public function test_send_marks_draft_sent_and_emails_supplier(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = Supplier::create(['name' => 'Acme Dairy', 'email' => 'orders@acmedairy.test']);
        $draft = $this->makeDraft(['supplier_id' => $supplier->id]);

        $response = $this->actingAs($admin)->post(route('inventory.purchase-orders.send', $draft->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $draft->refresh();
        $this->assertSame('sent', $draft->status);
        Mail::assertSent(PurchaseOrderRequest::class);
    }

    public function test_send_flashes_error_when_already_sent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $draft = $this->makeDraft(['status' => 'sent']);

        $response = $this->actingAs($admin)->post(route('inventory.purchase-orders.send', $draft->id));

        $response->assertSessionHas('error');
    }

    public function test_destroy_deletes_the_draft(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $draft = $this->makeDraft();

        $this->actingAs($admin)->delete(route('inventory.purchase-orders.destroy', $draft->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('purchase_order_drafts', ['id' => $draft->id]);
    }
}
