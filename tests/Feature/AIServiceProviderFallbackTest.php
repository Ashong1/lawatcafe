<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * OpenRouter is now the only provider (v1.9.0 — one provider, per the
 * capstone adviser's revision), so "fallback" here means model-level
 * fallback within OpenRouter's own list, not provider-to-provider.
 */
class AIServiceProviderFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_falls_through_to_a_second_model_when_the_first_fails(): void
    {
        // OpenRouter's model name lives in the JSON body, not the URL, so —
        // unlike the old Gemini/Groq fallback tests this replaces — the fake
        // can't key on a per-model URL. Http::sequence() over the single
        // 'openrouter.ai/*' host works instead, since callOpenRouterLoop
        // tries the two configured models strictly in order (first is never
        // shuffled — see its own comment).
        Setting::set('ai_models_openrouter', json_encode(['model-a', 'model-b']));

        Http::fake([
            'openrouter.ai/*' => Http::sequence()
                ->push([], 500)
                ->push(['choices' => [['message' => ['content' => 'Model B answered instead.']]]], 200),
        ]);

        $reply = app(AIService::class)->chat('Hello there');

        $this->assertSame('Model B answered instead.', $reply);
    }

    public function test_uses_a_deterministic_local_fallback_when_every_model_fails(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([], 500),
        ]);

        $reply = app(AIService::class)->chat('hi there');

        $this->assertStringContainsString('Barista AI here', $reply);
    }

    public function test_circuit_breaker_opens_after_repeated_failures_and_skips_the_provider(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([], 500),
        ]);

        // Default threshold is 3 consecutive failures (ai_circuit_failure_threshold).
        app(AIService::class)->chat('one');
        app(AIService::class)->chat('two');
        app(AIService::class)->chat('three');

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => 'Should never be reached now that the circuit is open.']]],
            ], 200),
        ]);

        $reply = app(AIService::class)->chat('four');

        $this->assertStringContainsString('Serving guests', $reply, 'Circuit should be open, forcing the local fallback rather than a real request.');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'openrouter.ai'));
    }
}
