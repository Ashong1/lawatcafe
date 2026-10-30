<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Evaluators (Oct 28): changes to inventory and Wi-Fi settings must ask
 * "are you sure?" first instead of saving on one click.
 */
class ConfirmBeforeSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_forms_ask_before_saving(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['inventory.ingredients.index', 'inventory.products.index', 'inventory.wastage.index'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk()->assertSee('data-confirm=', false);
        }
    }

    public function test_network_forms_ask_before_saving(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($super)->get(route('admin.settings.network'))->assertOk()->assertSee('data-confirm=', false);
        $this->actingAs($super)->get(route('network.traffic'))->assertOk()->assertSee('data-confirm=', false);
    }

    public function test_both_layouts_handle_data_confirm_forms(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('inventory.wastage.index'))->assertSee("form[data-confirm]", false);
        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get(route('pos'))->assertSee("form[data-confirm]", false);
    }
}
