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

    /** Owner (Oct 30): every action that changes business data asks first. */
    public function test_the_remaining_one_click_actions_now_ask_first(): void
    {
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response([], 500)]);
        $super = User::factory()->create(['role' => 'super_admin']);
        $checks = [
            'network.vouchers.index' => 'Make these Wi-Fi codes?', 'network.blocklist' => 'Block this device?',
            'network.site-blocking' => 'Block this site?', 'network.trusted-devices' => 'Trust this device?',
            'inventory.deliveries.index' => 'Record this delivery?', 'inventory.categories.index' => 'Save this category?',
            'inventory.suppliers.index' => 'Save this supplier?', 'admin.settings.store' => 'Save store settings?', 'network.plans' => 'Save Wi-Fi prices?',
            'admin.settings.agent' => 'Save Barista AI permissions?', 'admin.settings.ai-providers' => 'Save the AI keys?',
            'accounts.index' => 'Save this account?', 'staff.deliveries.index' => 'Record this delivery?',
        ];
        foreach ($checks as $route => $question) {
            $this->actingAs($super)->get(route($route))->assertOk()->assertSee('data-confirm="'.$question.'"', false);
        }
    }
}
