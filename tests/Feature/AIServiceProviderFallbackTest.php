<?php

namespace Tests\Feature;

use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AIServiceProviderFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_falls_through_to_groq_when_every_gemini_model_fails(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Groq answered instead.']]],
            ], 200),
        ]);

        $reply = app(AIService::class)->chat('Hello there');

        $this->assertSame('Groq answered instead.', $reply);
    }

    public function test_falls_through_to_openrouter_when_gemini_and_groq_both_fail(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
            'api.groq.com/*' => Http::response([], 500),
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => 'OpenRouter answered instead.']]],
            ], 200),
        ]);

        $reply = app(AIService::class)->chat('Hello there');

        $this->assertSame('OpenRouter answered instead.', $reply);
    }

    public function test_uses_a_deterministic_local_fallback_when_every_provider_fails(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
            'api.groq.com/*' => Http::response([], 500),
            'openrouter.ai/*' => Http::response([], 500),
        ]);

        $reply = app(AIService::class)->chat('hi there');

        $this->assertStringContainsString('Barista AI here', $reply);
    }

    public function test_circuit_breaker_opens_after_repeated_failures_and_skips_the_provider(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Groq answered instead.']]],
            ], 200),
        ]);

        // Default threshold is 3 consecutive failures (ai_circuit_failure_threshold).
        app(AIService::class)->chat('one');
        app(AIService::class)->chat('two');
        app(AIService::class)->chat('three');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Groq answered instead.']]],
            ], 200),
        ]);

        app(AIService::class)->chat('four');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'generativelanguage.googleapis.com'));
    }
}
