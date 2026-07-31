<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Agent\ToolCallOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatHistoryNullContentTest extends TestCase
{
    use RefreshDatabase;

    private function mockOrchestrator(): void
    {
        $this->mock(ToolCallOrchestrator::class, function ($mock) {
            $mock->shouldReceive('run')->andReturn(['reply' => 'Sure.', 'pending' => [], 'executed' => []]);
        });
    }

    /**
     * A tool-only turn (or a stale sessionStorage cache from before this
     * class of bug was fixed) can hand back a history entry with content:
     * null — this used to 422 every subsequent message in that conversation
     * because history.*.content required a string.
     */
    public function test_admin_chat_tolerates_a_null_content_history_entry(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->mockOrchestrator();

        $response = $this->actingAs($admin)
            ->withHeader('Accept', 'text/event-stream')
            ->post(route('admin.ai.chat'), [
                'message' => 'hey',
                'history' => [
                    ['role' => 'user', 'content' => 'add 16 cans of condensed milk'],
                    ['role' => 'assistant', 'content' => null],
                ],
            ]);

        $response->assertOk();
    }

    public function test_staff_chat_tolerates_a_null_content_history_entry(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->mockOrchestrator();

        $response = $this->actingAs($staff)
            ->withHeader('Accept', 'text/event-stream')
            ->post(route('staff.ai.chat'), [
                'message' => 'hey',
                'history' => [
                    ['role' => 'assistant', 'content' => null],
                ],
            ]);

        $response->assertOk();
    }

    public function test_guest_portal_chat_tolerates_a_null_content_history_entry(): void
    {
        $this->mockOrchestrator();

        $response = $this->withHeader('Accept', 'text/event-stream')
            ->post(route('portal.chat'), [
                'message' => 'hey',
                'history' => [
                    ['role' => 'assistant', 'content' => null],
                ],
            ]);

        $response->assertOk();
    }
}
