<?php

namespace Tests\Feature;

use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression for the "AI chatbot just doesn't reply" / "taking longer than
 * expected" reports: chatWithToolsStreaming() used to give every model of
 * every provider a fresh STREAM_TIMEOUT (18s), but fast_path_model_limit
 * (default 2) means a single provider could burn up to 2x that before
 * falling through — well past the widget's fixed 20s fetch() abort. Fixed by
 * threading one shared deadline through the whole cascade (see AIService::
 * chatWithToolsStreaming()/secondsUntil()). These tests cover the functional
 * cascade still working correctly with that deadline threaded through; the
 * live incident (both groq and openrouter circuit breakers tripping within
 * seconds of each other) is what surfaced the bug in the first place.
 */
class AIServiceStreamingCascadeTest extends TestCase
{
    use RefreshDatabase;

    private function sse(array $lines): string
    {
        return collect($lines)->map(fn ($e) => 'data: '.json_encode($e)."\n\n")->implode('');
    }

    public function test_streams_a_gemini_reply_when_gemini_succeeds(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(
                $this->sse([
                    ['candidates' => [['content' => ['parts' => [['text' => 'Today\'s sales are PHP 500.']]]]]],
                ]),
                200
            ),
        ]);

        $deltas = [];
        $result = app(AIService::class)->chatWithToolsStreaming(
            [['role' => 'user', 'content' => 'What are total sales today?']],
            [],
            function (string $delta) use (&$deltas) { $deltas[] = $delta; }
        );

        $this->assertSame("Today's sales are PHP 500.", $result['choices'][0]['message']['content']);
        $this->assertNotEmpty($deltas);
    }

    public function test_falls_through_to_groq_streaming_when_gemini_fails(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
            'api.groq.com/*' => Http::response(
                $this->sse([
                    ['choices' => [['delta' => ['content' => 'Groq streamed instead.']]]],
                ]),
                200
            ),
        ]);

        $result = app(AIService::class)->chatWithToolsStreaming(
            [['role' => 'user', 'content' => 'hi']],
            [],
            function () {}
        );

        $this->assertSame('Groq streamed instead.', $result['choices'][0]['message']['content']);
    }

    public function test_falls_through_to_openrouter_streaming_when_gemini_and_groq_fail(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
            'api.groq.com/*' => Http::response([], 500),
            'openrouter.ai/*' => Http::response(
                $this->sse([
                    ['choices' => [['delta' => ['content' => 'OpenRouter streamed instead.']]]],
                ]),
                200
            ),
        ]);

        $result = app(AIService::class)->chatWithToolsStreaming(
            [['role' => 'user', 'content' => 'hi']],
            [],
            function () {}
        );

        $this->assertSame('OpenRouter streamed instead.', $result['choices'][0]['message']['content']);
    }

    public function test_returns_null_when_every_provider_fails_so_the_caller_can_send_a_graceful_fallback(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
            'api.groq.com/*' => Http::response([], 500),
            'openrouter.ai/*' => Http::response([], 500),
        ]);

        $result = app(AIService::class)->chatWithToolsStreaming(
            [['role' => 'user', 'content' => 'hi']],
            [],
            function () {}
        );

        $this->assertNull($result);
    }
}
