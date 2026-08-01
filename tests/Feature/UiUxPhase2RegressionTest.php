<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Server-rendered regression guards for the Phase 2 UI/UX fixes (see
 * /root/.claude/plans/misty-plotting-bird.md) — modal focus management and
 * keyboard access for non-native clickable elements. Actual focus/keydown
 * behavior has no browser tooling this session and is verified by manual
 * code review instead; these tests only guard the markup itself.
 */
class UiUxPhase2RegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_modal_shell_watches_show_prop_for_focus_management(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('inventory.ingredients.index'));

        $response->assertOk();
        $response->assertSee('lastFocused', false);
        $response->assertSee('firstFocusable()', false);
    }

    public function test_pos_cart_partial_note_edit_is_keyboard_reachable(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($staff)->get('/pos');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, substr_count($response->getContent(), '@keydown.enter="editNote(index)"'));
    }

    public function test_notification_bell_row_is_keyboard_reachable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('@keydown.enter="markAsRead(notif)"', false);
    }

    public function test_agent_pending_badge_row_is_conditionally_keyboard_reachable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertSee(':tabindex="isAdmin ? \'0\' : \'-1\'"', false);
    }

    public function test_agent_chat_conversation_row_is_keyboard_reachable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('@keydown.enter="openConversation(conv.id)"', false);
    }
}
