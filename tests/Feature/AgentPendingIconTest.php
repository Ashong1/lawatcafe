<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Evaluators (Oct 28): the plain robot icon for pending AI actions looked like
 * the chat button. It carries a cog and says what it is.
 */
class AgentPendingIconTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_pending_actions_button_is_a_robot_with_a_cog(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dashboard'))
            ->assertSee('title="AI actions waiting for approval"', false)
            ->assertSee('data-icon="bot-cog"', false);
    }
}
