<?php

namespace Tests\Feature;

use App\Models\AiActionAudit;
use App\Models\User;
use App\Notifications\SystemAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderBadgeSeedingTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_bell_seeds_the_real_unread_count_server_side(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->notify(new SystemAlert('Low stock', 'Milk is low', 'alert-triangle'));
        $admin->notify(new SystemAlert('Low stock', 'Sugar is low', 'alert-triangle'));

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('notificationBell(2)', false);
    }

    public function test_agent_pending_badge_seeds_the_real_pending_count_server_side(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        AiActionAudit::create([
            'tool_name' => 'restockIngredient',
            'input_params' => ['ingredient_id' => 1],
            'status' => 'proposed',
            'actor_type' => 'ai',
        ]);
        AiActionAudit::create([
            'tool_name' => 'voidSale',
            'input_params' => ['sale_id' => 1],
            'status' => 'proposed',
            'actor_type' => 'ai',
        ]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('initialCount: 2', false);
    }
}
