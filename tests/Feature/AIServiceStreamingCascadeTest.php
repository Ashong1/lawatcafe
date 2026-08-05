<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
            function (string $delta) use (&$deltas) {
                $deltas[] = $delta;
            }
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

    /**
     * The tool-calling streaming path used to shuffle its model list before
     * trying it — a strong tool-reliable model and a weak one were equally
     * likely to be tried first on every call, regardless of the order an
     * admin configured via the ai_models_gemini Setting. This path no longer
     * shuffles, so the configured order should now be followed exactly and
     * deterministically (not "usually" — this would be flaky under the old
     * shuffled behavior, which is the point).
     */
    public function test_tool_calling_streaming_tries_models_in_configured_order_not_shuffled(): void
    {
        Setting::set('ai_models_gemini', json_encode(['model-a-first', 'model-b-second']));

        Http::fake([
            '*model-a-first*' => Http::response([], 500),
            '*model-b-second*' => Http::response(
                $this->sse([['candidates' => [['content' => ['parts' => [['text' => 'From model B.']]]]]]]),
                200
            ),
        ]);

        $result = app(AIService::class)->chatWithToolsStreaming(
            [['role' => 'user', 'content' => 'hi']],
            [],
            function () {}
        );

        $this->assertSame('From model B.', $result['choices'][0]['message']['content']);

        $requestedModels = collect(Http::recorded())
            ->map(fn ($pair) => str_contains($pair[0]->url(), 'model-a-first') ? 'a' : 'b')
            ->values();

        $this->assertSame(['a', 'b'], $requestedModels->all(), 'model-a-first must always be tried before model-b-second, not randomly ordered.');
    }

    /**
     * healthyModelsFirst() keeps the admin's configured order as the primary
     * sort key (see the test above) but deprioritizes a model that failed
     * within the last few minutes — otherwise fast_path_model_limit's
     * truncation could waste its one or two slots retrying a model that's
     * currently broken instead of a healthy one further down the list.
     */
    public function test_tool_calling_streaming_skips_a_recently_failed_model_within_the_fast_path_limit(): void
    {
        Setting::set('ai_models_gemini', json_encode(['model-a-first', 'model-b-second']));
        Setting::set('fast_path_model_limit', 1);

        Cache::put('ai_model_status_gemini_model_a_first', [
            'status' => 'failed',
            'reason' => 'http_500',
            'at' => now()->timestamp,
        ], now()->addMinutes(5));

        Http::fake([
            '*model-a-first*' => Http::response([], 500),
            '*model-b-second*' => Http::response(
                $this->sse([['candidates' => [['content' => ['parts' => [['text' => 'From healthy model B.']]]]]]]),
                200
            ),
        ]);

        $result = app(AIService::class)->chatWithToolsStreaming(
            [['role' => 'user', 'content' => 'hi']],
            [],
            function () {}
        );

        $this->assertSame('From healthy model B.', $result['choices'][0]['message']['content']);
        $this->assertCount(1, Http::recorded(), 'Only one model should have been tried given fast_path_model_limit=1 — it should be the healthy one, not the recently-failed one.');
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
