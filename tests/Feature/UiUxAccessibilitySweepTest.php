<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guards for the Phase 3.2/3.3 accessibility sweeps (see
 * /root/.claude/plans/misty-plotting-bird.md) — a representative sample per
 * batch, not exhaustive coverage of all ~110 instances. Checks the markup
 * mechanically; actual screen-reader/keyboard behavior is manual review only.
 */
class UiUxAccessibilitySweepTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_products_form_fields_are_labelled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Product::create(['name' => 'Spanish Latte', 'category' => 'Coffee', 'price' => 120, 'status' => 'Active']);

        $response = $this->actingAs($admin)->get(route('inventory.products.index'));

        $response->assertOk();
        foreach (['product-name', 'product-category', 'product-price', 'product-status'] as $id) {
            $response->assertSee("for=\"{$id}\"", false);
            $response->assertSee("id=\"{$id}\"", false);
        }
        $response->assertSee('aria-label="Edit"', false);
        $response->assertSee('aria-label="Delete"', false);
    }

    public function test_inventory_suppliers_form_fields_are_labelled_and_modal_close_has_a_label(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('inventory.suppliers.index'));

        $response->assertOk();
        foreach (['supplier-name', 'supplier-contact-person', 'supplier-phone'] as $id) {
            $response->assertSee("for=\"{$id}\"", false);
            $response->assertSee("id=\"{$id}\"", false);
        }
        // Was one of the ~10 zero-accessible-name buttons in the original audit.
        $response->assertSee('aria-label="Close"', false);
    }

    public function test_accounts_index_form_fields_are_labelled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($admin)->get(route('accounts.index'));

        $response->assertOk();
        foreach (['account-name', 'account-email', 'account-role', 'account-password'] as $id) {
            $response->assertSee("for=\"{$id}\"", false);
            $response->assertSee("id=\"{$id}\"", false);
        }
        $response->assertSee('aria-label="Edit Staff"', false);
        $response->assertSee('aria-label="Remove Access"', false);
    }

    public function test_network_vouchers_page_labels_fields_and_labels_bulk_delete(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('network.vouchers.index'));

        $response->assertOk();
        $response->assertSee('aria-label="Delete selected vouchers"', false);
    }

    public function test_network_blocklist_page_labels_its_form_and_unban_button(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('network.blocklist'));

        $response->assertOk();
        foreach (['mac_address', 'hostname', 'reason'] as $id) {
            $response->assertSee("for=\"{$id}\"", false);
            $response->assertSee("id=\"{$id}\"", false);
        }
    }

    public function test_pos_cart_partial_quantity_buttons_and_dismiss_are_labelled(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($staff)->get('/pos');

        $response->assertOk();
        $response->assertSee('aria-label="Decrease quantity"', false);
        $response->assertSee('aria-label="Increase quantity"', false);
        $response->assertSee('aria-label="Dismiss suggestion"', false);
    }

    public function test_pos_index_search_input_has_an_aria_label(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($staff)->get('/pos');

        $response->assertOk();
        $response->assertSee('aria-label="Search menu"', false);
    }

    public function test_portal_index_passcode_input_has_an_aria_label(): void
    {
        $response = $this->get(route('portal.index'));

        $response->assertOk();
        $response->assertSee('aria-label="Wi-Fi passcode"', false);
    }

    public function test_admin_settings_ai_providers_fields_are_labelled(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)->get(route('admin.settings.ai-providers'));

        $response->assertOk();
        $response->assertSee('for="gemini_api_key"', false);
        $response->assertSee('id="gemini_api_key"', false);
    }

    public function test_admin_settings_store_fields_are_labelled(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)->get(route('admin.settings.store'));

        $response->assertOk();
        foreach (['low-stock-threshold', 'store-open-time', 'store-close-time', 'receipt-header'] as $id) {
            $response->assertSee("for=\"{$id}\"", false);
            $response->assertSee("id=\"{$id}\"", false);
        }
    }
}
