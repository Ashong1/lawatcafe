<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Agent\ConversationHistoryService;
use App\Services\Agent\ToolCallOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: the client sends its whole in-memory history unsliced on every
 * request, but the server validated it with a hard `max:30`/`max:20` — a
 * legitimately long conversation got a 422 instead of the server just
 * dropping the oldest turns. ConversationHistoryService::slidingWindow() now
 * does the truncation server-side so long conversations degrade gracefully.
 */
class ChatHistorySlidingWindowTest extends TestCase
{
    use RefreshDatabase;

    private function mockOrchestrator(): void
    {
        $this->mock(ToolCallOrchestrator::class, function ($mock) {
            $mock->shouldReceive('run')->andReturn(['reply' => 'Sure.', 'pending' => [], 'executed' => []]);
        });
    }

    private function longHistory(int $count): array
    {
        return collect(range(1, $count))
            ->map(fn ($i) => ['role' => $i % 2 === 0 ? 'assistant' : 'user', 'content' => "message {$i}"])
            ->all();
    }

    public function test_sliding_window_keeps_only_the_most_recent_entries(): void
    {
        $service = new ConversationHistoryService;
        $history = $this->longHistory(45);

        $windowed = $service->slidingWindow($history, 30);

        $this->assertCount(30, $windowed);
        $this->assertSame('message 16', $windowed[0]['content']);
        $this->assertSame('message 45', array_values($windowed)[29]['content']);
    }

    public function test_admin_chat_accepts_a_history_longer_than_the_old_hard_cap(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->mockOrchestrator();

        $response = $this->actingAs($admin)
            ->withHeader('Accept', 'text/event-stream')
            ->post(route('admin.ai.chat'), ['message' => 'hey', 'history' => $this->longHistory(45)]);

        $response->assertOk();
    }

    public function test_staff_chat_accepts_a_history_longer_than_the_old_hard_cap(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->mockOrchestrator();

        $response = $this->actingAs($staff)
            ->withHeader('Accept', 'text/event-stream')
            ->post(route('staff.ai.chat'), ['message' => 'hey', 'history' => $this->longHistory(45)]);

        $response->assertOk();
    }

    public function test_guest_portal_chat_accepts_a_history_longer_than_the_old_hard_cap(): void
    {
        $this->mockOrchestrator();

        $response = $this->withHeader('Accept', 'text/event-stream')
            ->post(route('portal.chat'), ['message' => 'hey', 'history' => $this->longHistory(25)]);

        $response->assertOk();
    }
}
