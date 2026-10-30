<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Agent\ScopeGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The guard in the real chat endpoint: before the AI, and after it. */
class ScopeGuardChatTest extends TestCase
{
    use RefreshDatabase;

    private function chat(string $message): string
    {
        config(['services.openrouter.key' => 'test-key']);
        return $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.ai.chat'), ['message' => $message], ['Accept' => 'text/event-stream', 'X-Requested-With' => 'XMLHttpRequest'])
            ->streamedContent();
    }

    public function test_bare_math_is_declined_without_asking_the_ai(): void
    {
        Http::fake();

        $body = $this->chat('what is 3 plus 3');

        $this->assertStringContainsString(json_encode(ScopeGuard::DECLINE), $body);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'chat/completions'));
    }

    public function test_an_off_topic_answer_from_the_ai_is_replaced(): void
    {
        // The model ignores the prompt rule and answers anyway.
        $this->mock(\App\Services\Agent\ToolCallOrchestrator::class, fn ($m) => $m->shouldReceive('run')
            ->andReturn(['reply' => 'Leonardo da Vinci painted it.', 'executed' => [], 'pending' => []]));

        $body = $this->chat('who painted the mona lisa');

        $meta = collect(explode("\n\n", $body))->map(fn ($l) => json_decode(substr($l, 6), true))->firstWhere('type', 'meta');
        $this->assertSame(ScopeGuard::DECLINE, $meta['reply']);
    }
}
