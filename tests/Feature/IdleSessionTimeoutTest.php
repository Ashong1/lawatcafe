<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Agent\ToolCallOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdleSessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_ajax_request_after_idle_timeout_gets_a_401_instead_of_a_redirect(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->mock(ToolCallOrchestrator::class, function ($mock) {
            $mock->shouldReceive('run')->never();
        });

        $response = $this->actingAs($admin)
            ->withSession(['last_activity_at' => now()->subMinutes(30)])
            ->withHeaders([
                'Accept' => 'text/event-stream',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(route('admin.ai.chat'), ['message' => 'hello']);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Your session expired due to inactivity.']);
        $this->assertGuest();
    }

    public function test_plain_navigation_after_idle_timeout_still_redirects_to_login(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->withSession(['last_activity_at' => now()->subMinutes(30)])
            ->get('/dashboard');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * This is the scenario actually observed live: a genuinely expired session
     * (no auth at all, not just the idle-timeout guard) hitting an auth-gated
     * fetch() endpoint. Laravel's own `auth` middleware wraps the whole
     * admin/staff route group and fires before RoleMiddleware/IdleSessionTimeout
     * ever run, so its default AuthenticationException handling is what
     * actually decides redirect-vs-401 here.
     */
    public function test_ajax_request_with_no_session_at_all_gets_a_401_not_a_redirect(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'text/event-stream',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post(route('admin.ai.chat'), ['message' => 'hello']);

        $response->assertStatus(401);
    }
}
