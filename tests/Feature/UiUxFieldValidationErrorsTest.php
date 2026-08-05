<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for the Phase 4.1 per-field validation error rollout (see
 * /root/.claude/plans/misty-plotting-bird.md) — before this, every custom
 * form relied on a single generic toast showing only $errors->first(), so a
 * form with two invalid fields told the user about exactly one of them.
 * These tests submit invalid data with >=2 invalid fields and assert BOTH
 * fields' specific error text is now visible on the follow-up page render —
 * that's the actual bug this closes, not just "error markup exists somewhere".
 *
 * Also guards the less obvious half of the fix: these modals are
 * Alpine-driven (x-show="isModalOpen", default false) with no server logic
 * to reopen them — the field-error markup would otherwise render into the
 * DOM but stay invisible behind a closed modal. Each form's Alpine root now
 * sets its open flag from $errors->any() so a validation failure actually
 * reopens the modal the errors belong to.
 */
class UiUxFieldValidationErrorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_form_shows_both_invalid_fields_and_reopens_the_modal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->from(route('inventory.products.index'))
            ->post(route('inventory.products.store'), ['price' => -5])
            ->assertRedirect(route('inventory.products.index'))
            ->assertSessionHasErrors(['name', 'category', 'price', 'status']);

        $response = $this->actingAs($admin)->get(route('inventory.products.index'));

        $response->assertOk();
        $response->assertSee('The name field is required.', false);
        $response->assertSee('The price field must be at least 0.', false);
        $response->assertSee('isModalOpen: true', false);
    }

    public function test_account_form_shows_both_invalid_fields_and_reopens_the_modal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->from(route('accounts.index'))
            ->post(route('accounts.store'), ['email' => 'not-an-email'])
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHasErrors(['name', 'email', 'password']);

        $response = $this->actingAs($admin)->get(route('accounts.index'));

        $response->assertOk();
        $response->assertSee('The name field is required.', false);
        $response->assertSee('The email field must be a valid email address.', false);
        $response->assertSee('isModalOpen: true', false);
    }

    public function test_voucher_generation_form_shows_invalid_field_and_reopens_the_modal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->from(route('network.vouchers.index'))
            ->post(route('network.vouchers.generate'), ['quantity' => 0, 'duration_minutes' => -1])
            ->assertRedirect(route('network.vouchers.index'))
            ->assertSessionHasErrors(['quantity', 'duration_minutes']);

        $response = $this->actingAs($admin)->get(route('network.vouchers.index'));

        $response->assertOk();
        $response->assertSee('isModalOpen: true', false);
    }

    public function test_network_settings_reservation_form_shows_invalid_field(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
            $mock->shouldReceive('getDhcpPools')->andReturn([]);
        });

        $this->actingAs($superAdmin)->from(route('admin.settings.network'))
            ->post(route('network.static-ips.store'), ['mac_address' => 'not-a-mac'])
            ->assertRedirect(route('admin.settings.network'))
            ->assertSessionHasErrors(['mac_address', 'ip_address']);

        $response = $this->actingAs($superAdmin)->get(route('admin.settings.network'));

        $response->assertOk();
        $response->assertSee('The ip address field is required.', false);
    }

    public function test_supplier_form_shows_invalid_field_and_reopens_the_modal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->from(route('inventory.suppliers.index'))
            ->post(route('inventory.suppliers.store'), ['contact_person' => 'Jane'])
            ->assertRedirect(route('inventory.suppliers.index'))
            ->assertSessionHasErrors(['name']);

        $response = $this->actingAs($admin)->get(route('inventory.suppliers.index'));

        $response->assertOk();
        $response->assertSee('The name field is required.', false);
        $response->assertSee('isModalOpen: true', false);
    }

    public function test_store_settings_form_shows_invalid_field(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)->from(route('admin.settings.store'))
            ->post(route('admin.settings.store.update'), ['low_stock_threshold' => 'not-a-number'])
            ->assertRedirect(route('admin.settings.store'))
            ->assertSessionHasErrors(['low_stock_threshold']);

        $response = $this->actingAs($superAdmin)->get(route('admin.settings.store'));

        $response->assertOk();
        $response->assertSee('The low stock threshold field must be a number.', false);
    }
}
