<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: agent-chat.blade.php has two separate x-data="agentChat({...})"
 * blocks (embedded vs floating mode) with slightly different indentation. An
 * earlier replace_all edit silently matched only one of them, leaving the
 * floating widget (used by both admin and staff layouts) with
 * historyEnabled always undefined — the History button never rendered, with
 * no error anywhere, because x-show="historyEnabled" just quietly hid it.
 * Asserting the actual JS config value (not just that the function exists
 * somewhere on the page) is what would have caught this originally.
 */
class AgentChatHistoryUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_actually_enables_history_in_the_floating_widget_config(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('historyEnabled: true,', false);
    }

    public function test_staff_dashboard_actually_enables_history_in_the_floating_widget_config(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $response = $this->actingAs($staff)->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertSee('historyEnabled: true,', false);
    }

    public function test_guest_portal_leaves_history_disabled_in_its_embedded_widget_config(): void
    {
        $response = $this->get(route('portal.index'));

        $response->assertOk();
        $response->assertSee('historyEnabled: false,', false);
    }
}
