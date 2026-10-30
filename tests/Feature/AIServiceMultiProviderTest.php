<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Owner (Oct 30): route across Gemini, Groq and OpenRouter like OmniRoute —
 * Gemini first, then Groq, then OpenRouter's free models.
 */
class AIServiceMultiProviderTest extends TestCase
{
    use RefreshDatabase;

    private function sse(string $text): string
    {
        return 'data: '.json_encode(['choices' => [['delta' => ['content' => $text]]]])."\n\n";
    }

    private function keys(array $providers): void
    {
        config(['services.openrouter.key' => null]);
        foreach ($providers as $p) {
            Setting::set("{$p}_api_key", Crypt::encryptString("test-{$p}-key"));
        }
    }

    private function stream(): ?array
    {
        return app(AIService::class)->chatWithToolsStreaming([['role' => 'user', 'content' => 'hi']], [], fn () => null);
    }

    public function test_gemini_answers_first_when_it_has_a_key(): void
    {
        $this->keys(['gemini', 'groq', 'openrouter']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->sse('From Gemini.')),
            '*' => Http::response($this->sse('Wrong provider.')),
        ]);

        $this->assertSame('From Gemini.', $this->stream()['choices'][0]['message']['content']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'generativelanguage.googleapis.com/v1beta/openai/chat/completions')
            && $r->hasHeader('Authorization', 'Bearer test-gemini-key'));
    }

    public function test_a_failing_provider_falls_through_to_the_next(): void
    {
        $this->keys(['gemini', 'groq', 'openrouter']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'quota'], 429),
            'api.groq.com/*' => Http::response($this->sse('From Groq.')),
            '*' => Http::response($this->sse('Wrong provider.')),
        ]);

        $this->assertSame('From Groq.', $this->stream()['choices'][0]['message']['content']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.groq.com/openai/v1/chat/completions'));
    }

    public function test_openrouter_is_the_last_fallback(): void
    {
        $this->keys(['gemini', 'groq', 'openrouter']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
            'api.groq.com/*' => Http::response([], 500),
            'openrouter.ai/api/v1/chat/*' => Http::response($this->sse('From OpenRouter.')),
            '*' => Http::response([], 404),
        ]);

        $this->assertSame('From OpenRouter.', $this->stream()['choices'][0]['message']['content']);
    }

    public function test_a_provider_without_a_key_is_skipped(): void
    {
        $this->keys(['groq']);
        Http::fake([
            'api.groq.com/*' => Http::response($this->sse('From Groq.')),
            '*' => Http::response([], 500),
        ]);

        $this->assertSame('From Groq.', $this->stream()['choices'][0]['message']['content']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'generativelanguage') || str_contains($r->url(), 'openrouter.ai/api/v1/chat'));
    }

    public function test_a_gemini_rate_limit_does_not_pause_openrouter(): void
    {
        $this->keys(['gemini', 'openrouter']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'free-models-per-day']], 429),
            'openrouter.ai/api/v1/chat/*' => Http::response($this->sse('From OpenRouter.')),
            '*' => Http::response([], 404),
        ]);

        $this->assertSame('From OpenRouter.', $this->stream()['choices'][0]['message']['content']);
        $this->assertNull(AIService::quotaExhaustedUntil());
    }

    public function test_the_owner_saves_keys_encrypted_and_they_are_never_shown_back(): void
    {
        $owner = User::factory()->create(['role' => 'admin']);

        $this->actingAs($owner)->post(route('admin.settings.ai-providers.update'), ['gemini_api_key' => 'AIza-secret-123', 'groq_api_key' => 'gsk_secret_456'])
            ->assertSessionHas('success');

        $stored = Setting::get('gemini_api_key');
        $this->assertNotSame('AIza-secret-123', $stored);
        $this->assertSame('AIza-secret-123', Crypt::decryptString($stored));
        $this->assertSame('AIza-secret-123', AIService::providerKey('gemini'));

        $page = $this->actingAs($owner)->get(route('admin.settings.ai-providers'))->assertOk();
        $page->assertDontSee('AIza-secret-123')->assertDontSee('gsk_secret_456')->assertSee('Google Gemini')->assertSee('Groq');

        // A blank field keeps the saved key.
        $this->actingAs($owner)->post(route('admin.settings.ai-providers.update'), ['gemini_api_key' => '', 'groq_api_key' => '']);
        $this->assertSame('AIza-secret-123', AIService::providerKey('gemini'));

        // With no keys at all, no provider shows a green "Healthy".
        config(['services.openrouter.key' => null]);
        Setting::set('gemini_api_key', null);
        Setting::set('groq_api_key', null);
        $this->actingAs($owner)->get(route('admin.settings.ai-providers'))->assertDontSee('Healthy');
    }

    /** OpenRouter's reserve only guards OpenRouter: with Gemini or Groq in front, learning needn't wait for it. */
    public function test_background_learning_is_not_held_back_by_openrouter_when_another_provider_has_a_key(): void
    {
        $budget = new class extends \App\Services\AiBudget
        {
            public function dailyRequestsRemaining(): ?int
            {
                return 3;
            }
        };

        $this->keys(['openrouter']);
        $this->assertFalse($budget->backgroundMaySpend());

        $this->keys(['gemini']);
        $this->assertTrue($budget->backgroundMaySpend());
    }
}
