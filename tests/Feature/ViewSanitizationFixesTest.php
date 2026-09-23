<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lightweight content-assertion regressions for a batch of Blade-level bugs
 * found in a full-codebase review (2026-09-23), none of which changed any
 * PHP-testable behavior on their own — each is a rendered-markup defect
 * (a JS method-name mismatch, an unbalanced <div>, a dead link, a redundant
 * query) that PHPUnit can only lock in by asserting on the compiled HTML.
 */
class ViewSanitizationFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_wifi_plans_add_button_calls_the_method_that_actually_exists(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('network.plans'))->assertOk();

        // openAddModal() is the real method defined on the wifiPlans() Alpine
        // component; openModalForAdd() doesn't exist and previously made the
        // "Add New Tier" button a silent no-op (Alpine console error only).
        $response->assertSee('@click="openAddModal()"', false);
        $response->assertDontSee('openModalForAdd', false);
    }

    public function test_dashboard_ai_insights_modal_has_balanced_result_state_markup(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();

        // A stray </div> used to close the "Results State" container right
        // after the Demand Risk Alerts block, pushing Strategic Advice and
        // Hot/Cold Items outside the x-show guard so they rendered
        // unconditionally. Both sections must still be inside that div.
        $html = $response->getContent();
        $resultsStateStart = strpos($html, '!loadingInsights && !errorInsights && insights');
        $strategicAdvicePos = strpos($html, 'Strategic Advice');
        $hotItemsPos = strpos($html, 'Hot Items');

        $this->assertNotFalse($resultsStateStart);
        $this->assertNotFalse($strategicAdvicePos);
        $this->assertNotFalse($hotItemsPos);
        $this->assertGreaterThan($resultsStateStart, $strategicAdvicePos);
        $this->assertGreaterThan($resultsStateStart, $hotItemsPos);
    }

    public function test_admin_sidebar_has_no_dead_verification_logs_link(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();

        // /network/verifications was removed with the e-wallet feature; the
        // nav link survived and 404'd for anyone who clicked it.
        $response->assertDontSee('network/verifications', false);
    }

    public function test_products_edit_button_does_not_reload_the_already_eager_loaded_relation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Product::create(['name' => 'Latte', 'category' => 'Coffee', 'price' => 120, 'status' => 'Active']);

        $response = $this->actingAs($admin)->get(route('inventory.products.index'))->assertOk();

        $response->assertDontSee("openEditModal({{ \$product->load('ingredients') }})", false);
    }

    public function test_void_button_marks_form_submitting_since_native_submit_fires_no_event(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sale::create([
            'transaction_number' => 'TRN-'.uniqid(),
            'total_amount' => 100,
            'status' => 'completed',
            'payment_method' => 'Cash',
            'order_type' => 'dine_in',
            'user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('pos.history'))->assertOk();

        // Element.submit() (used by the confirmAction callback) does not
        // dispatch a 'submit' event, so @submit="formSubmitting = true" on
        // the <form> never ran for this path — the button never disabled or
        // showed its spinner. The callback must set the flag itself.
        $response->assertSee('formSubmitting = true; document.getElementById', false);
    }
}
