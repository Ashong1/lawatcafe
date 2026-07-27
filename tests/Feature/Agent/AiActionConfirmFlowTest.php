<?php

namespace Tests\Feature\Agent;

use App\Models\AiActionAudit;
use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiActionConfirmFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_confirm_a_proposed_confirm_tier_action(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ingredient = Ingredient::create(['name' => 'Milk', 'current_stock' => 50, 'unit' => 'ml', 'low_stock_threshold' => 500, 'status' => 'Low Stock']);

        $audit = AiActionAudit::create([
            'tool_name' => 'restockIngredient',
            'input_params' => ['ingredient_id' => $ingredient->id, 'added_amount' => 10],
            'result' => [],
            'actor_type' => 'ai',
            'status' => 'proposed',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.ai.actions.confirm', $audit));

        $response->assertRedirect();
        $audit->refresh();
        $this->assertSame('executed', $audit->status);
        $ingredient->refresh();
        $this->assertEquals(60, $ingredient->current_stock);
    }

    public function test_staff_cannot_confirm_an_admin_only_action(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $audit = AiActionAudit::create([
            'tool_name' => 'blockDevice',
            'input_params' => ['mac_address' => 'AA:BB:CC:DD:EE:FF'],
            'result' => [],
            'actor_type' => 'ai',
            'status' => 'proposed',
        ]);

        $response = $this->actingAs($staff)->post(route('admin.ai.actions.confirm', $audit));

        $response->assertRedirect();
        $audit->refresh();
        $this->assertSame('proposed', $audit->status, 'The action must remain pending — staff cannot approve admin_only tools.');
    }

    public function test_reject_endpoint_marks_action_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ingredient = Ingredient::create(['name' => 'Milk', 'current_stock' => 50, 'unit' => 'ml', 'low_stock_threshold' => 500, 'status' => 'Low Stock']);

        $audit = AiActionAudit::create([
            'tool_name' => 'restockIngredient',
            'input_params' => ['ingredient_id' => $ingredient->id, 'added_amount' => 10],
            'result' => [],
            'actor_type' => 'ai',
            'status' => 'proposed',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.ai.actions.reject', $audit));

        $response->assertRedirect();
        $audit->refresh();
        $this->assertSame('rejected', $audit->status);
        $ingredient->refresh();
        $this->assertEquals(50, $ingredient->current_stock);
    }

    public function test_guest_cannot_reach_confirm_endpoint(): void
    {
        $ingredient = Ingredient::create(['name' => 'Milk', 'current_stock' => 50, 'unit' => 'ml', 'low_stock_threshold' => 500, 'status' => 'Low Stock']);
        $audit = AiActionAudit::create([
            'tool_name' => 'restockIngredient',
            'input_params' => ['ingredient_id' => $ingredient->id, 'added_amount' => 10],
            'result' => [],
            'actor_type' => 'ai',
            'status' => 'proposed',
        ]);

        $response = $this->post(route('admin.ai.actions.confirm', $audit));

        $response->assertRedirect(route('login'));
        $audit->refresh();
        $this->assertSame('proposed', $audit->status);
    }
}
