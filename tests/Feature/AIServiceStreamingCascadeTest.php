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
 * expected" reports: chatWithToolsStreaming() used to give every model a
 * fresh STREAM_TIMEOUT (18s), but fast_path_model_limit (default 2) means a
 * single provider could burn up to 2x that before falling through — well
 * past the widget's fixed 20s fetch() abort. Fixed by threading one shared
 * deadline through the whole cascade (see AIService::chatWithToolsStreaming()/
 * secondsUntil()). These tests cover the functional cascade still working
 * correctly with that deadline threaded through, now scoped to OpenRouter's
 * own model-level fallback since Gemini/Groq were removed entirely (v1.9.0 —
 * one provider only, per the capstone adviser's revision).
 */
class AIServiceStreamingCascadeTest extends TestCase
{
    use RefreshDatabase;

    private function sse(array $lines): string
    {
        return collect($lines)->map(fn ($e) => 'data: '.json_encode($e)."\n\n")->implode('');
    }

    public function test_streams_an_openrouter_reply_when_openrouter_succeeds(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response(
                $this->sse([
                    ['choices' => [['delta' => ['content' => 'Today\'s sales are PHP 500.']]]],
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

    /**
     * The tool-calling streaming path used to shuffle its model list before
     * trying it — a strong tool-reliable model and a weak one were equally
     * likely to be tried first on every call, regardless of the order an
     * admin configured via the ai_models_openrouter Setting. This path no
     * longer shuffles, so the configured order should now be followed
     * exactly and deterministically (not "usually" — this would be flaky
     * under the old shuffled behavior, which is the point).
     */
    public function test_tool_calling_streaming_tries_models_in_configured_order_not_shuffled(): void
    {
        Setting::set('ai_models_openrouter', json_encode(['model-a-first', 'model-b-second']));

        // OpenRouter's model name lives in the JSON body, not the URL — a
        // Gemini-style per-model URL fake doesn't work here — so this fakes
        // by inspecting the request body instead.
        Http::fake(function ($request) {
            $model = $request->data()['model'] ?? null;

            if ($model === 'model-a-first') {
                return Http::response([], 500);
            }

            return Http::response($this->sse([['choices' => [['delta' => ['content' => 'From model B.']]]]]), 200);
        });

        $result = app(AIService::class)->chatWithToolsStreaming(
            [['role' => 'user', 'content' => 'hi']],
            [],
            function () {}
        );

        $this->assertSame('From model B.', $result['choices'][0]['message']['content']);

        $requestedModels = collect(Http::recorded())
            ->map(fn ($pair) => ($pair[0]->data()['model'] ?? null) === 'model-a-first' ? 'a' : 'b')
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
        Setting::set('ai_models_openrouter', json_encode(['model-a-first', 'model-b-second']));
        Setting::set('fast_path_model_limit', 1);

        Cache::put('ai_model_status_openrouter_model_a_first', [
            'status' => 'failed',
            'reason' => 'http_500',
            'at' => now()->timestamp,
        ], now()->addMinutes(5));

        Http::fake(function ($request) {
            $model = $request->data()['model'] ?? null;

            if ($model === 'model-a-first') {
                return Http::response([], 500);
            }

            return Http::response($this->sse([['choices' => [['delta' => ['content' => 'From healthy model B.']]]]]), 200);
        });

        $result = app(AIService::class)->chatWithToolsStreaming(
            [['role' => 'user', 'content' => 'hi']],
            [],
            function () {}
        );

        $this->assertSame('From healthy model B.', $result['choices'][0]['message']['content']);
        $this->assertCount(1, Http::recorded(), 'Only one model should have been tried given fast_path_model_limit=1 — it should be the healthy one, not the recently-failed one.');
    }

    public function test_returns_null_when_every_model_fails_so_the_caller_can_send_a_graceful_fallback(): void
    {
        Http::fake([
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
