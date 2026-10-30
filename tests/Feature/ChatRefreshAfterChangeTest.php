<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Agent\ToolCallOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Owner (Oct 30): after Barista AI changes something, the page behind the chat still showed old data. */
class ChatRefreshAfterChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_stream_marks_actions_that_change_data(): void
    {
        $this->mock(ToolCallOrchestrator::class, fn ($m) => $m->shouldReceive('run')->andReturn(['reply' => 'Done.', 'pending' => [], 'executed' => [
            ['tool' => 'getSalesSummary', 'result' => ['success' => true, 'data' => [], 'message' => 'x']],
            ['tool' => 'blockDevice', 'result' => ['success' => true, 'data' => [], 'message' => 'y']],
        ]]));

        $body = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.ai.chat'), ['message' => 'block the laptop on the wifi'], ['Accept' => 'text/event-stream', 'X-Requested-With' => 'XMLHttpRequest'])
            ->streamedContent();
        $meta = collect(explode("\n\n", $body))->map(fn ($l) => json_decode(substr($l, 6), true))->firstWhere('type', 'meta');

        $this->assertSame([false, true], array_column($meta['executed'], 'changes'));
    }

    public function test_the_chat_refreshes_the_page_but_never_the_register(): void
    {
        $js = file_get_contents(resource_path('js/agent-chat.js'));
        $this->assertStringContainsString('refreshAfterChange()', $js);
        $this->assertStringContainsString("document.querySelector('[data-chat-no-refresh]')", $js);
        $this->assertStringContainsString('data-chat-no-refresh', file_get_contents(resource_path('views/pos/index.blade.php')));
    }
}
