<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
        $this->actingAs($owner)->get(route('admin.settings.ai-providers'))->assertDontSee('w-1.5 h-1.5 bg-green-500 rounded-full', false);
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

    /** Owner (Oct 30): test each model in the background with a live spinner, without tying up the page. */
    public function test_one_model_is_tested_per_request_and_answers_json(): void
    {
        $this->keys(['groq']);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => 'OK']]]])]);
        $owner = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($owner)->postJson(route('admin.settings.ai-providers.models.test', 'groq'), ['model' => 'openai/gpt-oss-120b'])
            ->assertOk()->assertJson(['ok' => true, 'reason' => null])->assertJsonStructure(['ok', 'ms', 'reason']);

        // Only models in the provider's list; nothing arbitrary.
        $this->actingAs($owner)->postJson(route('admin.settings.ai-providers.models.test', 'groq'), ['model' => 'someone/else'])
            ->assertUnprocessable();

        $page = $this->actingAs($owner)->get(route('admin.settings.ai-providers'))->assertOk();
        $page->assertSee('Test all models')->assertSee('Test models')->assertSee('lkModelTests', false);
    }

    public function test_a_failed_model_reports_why(): void
    {
        $this->keys(['groq']);
        Http::fake(['api.groq.com/*' => Http::response(['error' => 'gone'], 404)]);

        $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->postJson(route('admin.settings.ai-providers.models.test', 'groq'), ['model' => 'openai/gpt-oss-120b'])
            ->assertOk()->assertJson(['ok' => false, 'reason' => 'http_404']);
    }

    /** Owner (Oct 30): scan a provider for more models, qualify each, add the good ones. */
    public function test_scan_lists_chat_models_not_already_in_the_route(): void
    {
        $this->keys(['gemini']);
        Http::fake(['generativelanguage.googleapis.com/v1beta/openai/models' => Http::response(['data' => [
            ['id' => 'models/gemini-3.5-flash'], ['id' => 'models/gemini-3.6-flash'], ['id' => 'models/gemini-3.8-flash-tts'],
            ['id' => 'models/gemini-3.1-flash-image'], ['id' => 'models/gemini-3.7-flash'],
        ]])]);

        $this->actingAs(User::factory()->create(['role' => 'super_admin']))->getJson(route('admin.settings.ai-providers.models.scan', 'gemini'))
            ->assertOk()->assertExactJson(['models' => ['gemini-3.6-flash', 'gemini-3.7-flash']]);
    }

    public function test_a_candidate_qualifies_only_by_calling_the_tool(): void
    {
        $this->keys(['groq']);
        Cache::put('ai_scan_groq', ['qwen/new-model', 'meta/chatty'], 3600);
        Http::fake(function ($request) {
            return $request->data()['model'] === 'qwen/new-model'
                ? Http::response(['choices' => [['message' => ['content' => null, 'tool_calls' => [['id' => 'c1', 'type' => 'function', 'function' => ['name' => 'get_shop_time', 'arguments' => '{}']]]]]]])
                : Http::response(['choices' => [['message' => ['content' => 'It is 3 PM.']]]]);
        });
        $owner = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($owner)->postJson(route('admin.settings.ai-providers.models.check', 'groq'), ['model' => 'qwen/new-model'])
            ->assertOk()->assertJson(['ok' => true, 'reason' => null]);
        $this->actingAs($owner)->postJson(route('admin.settings.ai-providers.models.check', 'groq'), ['model' => 'meta/chatty'])
            ->assertOk()->assertJson(['ok' => false, 'reason' => 'no_tool_call']);
        // Not from the scan: refused.
        $this->actingAs($owner)->postJson(route('admin.settings.ai-providers.models.check', 'groq'), ['model' => 'x/unknown'])
            ->assertUnprocessable();
    }

    public function test_adding_a_scanned_model_puts_it_in_the_route(): void
    {
        $this->keys(['groq']);
        Cache::put('ai_scan_groq', ['qwen/new-model'], 3600);
        $owner = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($owner)->postJson(route('admin.settings.ai-providers.models.add', 'groq'), ['model' => 'qwen/new-model'])->assertOk();

        $this->assertContains('qwen/new-model', app(AIService::class)->activeModels('groq'));
        // Owner (Oct 30): an added model shows in the provider's list at once, no refresh.
        $this->actingAs($owner)->get(route('admin.settings.ai-providers'))->assertSee('x-for="m in (added[', false);
        $this->actingAs($owner)->get(route('admin.settings.ai-providers'))->assertSee('Scan for more models');
    }

    /** Oct 30: a chat that used a tool sent our internal call format back; Groq and Gemini answered 400. */
    private function afterAToolCall(array $call): array
    {
        return [['role' => 'user', 'content' => 'How many guests?'],
            ['role' => 'assistant', 'content' => null, 'tool_calls' => [$call]],
            ['role' => 'tool', 'name' => 'getActiveSessions', 'tool_call_id' => 'c1', 'content' => '{"count":0}']];
    }

    public function test_tool_calls_go_back_in_the_standard_format(): void
    {
        $this->keys(['groq']);
        Http::fake(['api.groq.com/*' => Http::response($this->sse('None online.'))]);

        app(AIService::class)->chatWithToolsStreaming($this->afterAToolCall(['id' => 'c1', 'name' => 'getActiveSessions', 'arguments' => ['tier' => 'free']]), [], fn () => null);

        Http::assertSent(function ($r) {
            $call = $r->data()['messages'][1]['tool_calls'][0];

            return $call === ['id' => 'c1', 'type' => 'function', 'function' => ['name' => 'getActiveSessions', 'arguments' => '{"tier":"free"}']];
        });
    }

    public function test_gemini_gets_its_thought_signature_back(): void
    {
        $this->keys(['gemini']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push('data: '.json_encode(['choices' => [['delta' => ['tool_calls' => [['id' => 'c1', 'type' => 'function', 'function' => ['name' => 'getActiveSessions', 'arguments' => '{}'], 'extra_content' => ['google' => ['thought_signature' => 'SIG123']]]]]]]])."\n\n")
            ->push($this->sse('None online.'))]);
        $ai = app(AIService::class);

        $first = $ai->chatWithToolsStreaming([['role' => 'user', 'content' => 'How many guests?']], [], fn () => null);
        $call = $first['choices'][0]['message']['tool_calls'][0];
        $ai->chatWithToolsStreaming($this->afterAToolCall($call), [], fn () => null);

        Http::assertSent(fn ($r) => ($r->data()['messages'][1]['tool_calls'][0]['extra_content']['google']['thought_signature'] ?? null) === 'SIG123');
    }

    public function test_gemini_gets_a_placeholder_signature_for_another_providers_call(): void
    {
        $this->keys(['gemini']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->sse('None online.'))]);

        app(AIService::class)->chatWithToolsStreaming($this->afterAToolCall(['id' => 'c1', 'name' => 'getActiveSessions', 'arguments' => []]), [], fn () => null);

        Http::assertSent(fn ($r) => ($r->data()['messages'][1]['tool_calls'][0]['extra_content']['google']['thought_signature'] ?? null) === 'skip_thought_signature_validator'
            && $r->data()['messages'][1]['tool_calls'][0]['function']['arguments'] === '{}');
    }

    /** Owner (Oct 30): add Cloudflare Workers AI the same way, set up from the settings page. */
    public function test_cloudflare_is_set_up_from_the_page_and_used_before_openrouter(): void
    {
        config(['services.openrouter.key' => null]);
        $owner = User::factory()->create(['role' => 'admin']);
        $this->actingAs($owner)->get(route('admin.settings.ai-providers'))->assertSee('Cloudflare Workers AI API Key')->assertSee('Cloudflare Account ID');

        $this->actingAs($owner)->post(route('admin.settings.ai-providers.update'), ['cloudflare_api_key' => 'cf-token', 'cloudflare_account_id' => 'abc123', 'openrouter_api_key' => 'or-key'])
            ->assertSessionHas('success');
        $this->assertSame('cf-token', AIService::providerKey('cloudflare'));

        Http::fake([
            'api.cloudflare.com/client/v4/accounts/abc123/ai/v1/chat/completions' => Http::response($this->sse('From Cloudflare.')),
            '*' => Http::response($this->sse('Wrong provider.')),
        ]);
        $reply = app(AIService::class)->chatWithToolsStreaming([
            ['role' => 'system', 'content' => 'Rule one.'], ['role' => 'system', 'content' => 'Rule two.'], ['role' => 'user', 'content' => 'hi'],
        ], [], fn () => null);

        $this->assertSame('From Cloudflare.', $reply['choices'][0]['message']['content']);
        Http::assertSent(function ($r) {
            $m = $r->data()['messages'];

            // One system message: Workers AI's chat templates reject several.
            return $r->hasHeader('Authorization', 'Bearer cf-token') && $r->data()['model'] === '@cf/openai/gpt-oss-20b'
                && count($m) === 2 && $m[0] === ['role' => 'system', 'content' => "Rule one.\n\nRule two."];
        });
    }

    public function test_cloudflare_without_an_account_id_is_not_configured(): void
    {
        Setting::set('cloudflare_api_key', Crypt::encryptString('cf-token'));

        $this->assertFalse(app(AIService::class)->getProviderStatuses()['cloudflare']['configured']);
    }

    public function test_cloudflare_scan_reads_its_text_generation_models(): void
    {
        $this->keys(['cloudflare']);
        Setting::set('cloudflare_account_id', 'abc123');
        Http::fake(['api.cloudflare.com/client/v4/accounts/abc123/ai/models/search*' => Http::response(['result' => [
            ['name' => '@cf/openai/gpt-oss-20b'], ['name' => '@cf/qwen/qwen3-30b-a3b-fp8'], ['name' => '@cf/meta/llama-3.1-8b-instruct-fp8-fast'],
        ]])]);

        $this->actingAs(User::factory()->create(['role' => 'super_admin']))->getJson(route('admin.settings.ai-providers.models.scan', 'cloudflare'))
            ->assertOk()->assertExactJson(['models' => ['@cf/meta/llama-3.1-8b-instruct-fp8-fast']]);
    }

    /** Owner (Oct 30): Mistral, connected from the settings page like the others. */
    public function test_mistral_is_set_up_from_the_page_and_tried_before_openrouter(): void
    {
        config(['services.openrouter.key' => null]);
        $owner = User::factory()->create(['role' => 'admin']);
        $this->actingAs($owner)->get(route('admin.settings.ai-providers'))->assertSee('Mistral AI API Key');
        $this->actingAs($owner)->post(route('admin.settings.ai-providers.update'), ['mistral_api_key' => 'ms-key', 'openrouter_api_key' => 'or-key']);

        Http::fake([
            'api.mistral.ai/v1/chat/completions' => Http::response($this->sse('From Mistral.')),
            '*' => Http::response($this->sse('Wrong provider.')),
        ]);
        $reply = app(AIService::class)->chatWithToolsStreaming([['role' => 'user', 'content' => 'hi']], [], fn () => null);

        $this->assertSame('From Mistral.', $reply['choices'][0]['message']['content']);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer ms-key') && $r->data()['model'] === 'mistral-small-latest');
    }

    /** Mistral only takes 9-character tool-call ids; another provider's ids are mapped. */
    public function test_mistral_gets_nine_character_tool_call_ids(): void
    {
        $this->keys(['mistral']);
        Http::fake(['api.mistral.ai/*' => Http::response($this->sse('None online.'))]);

        app(AIService::class)->chatWithToolsStreaming([
            ['role' => 'user', 'content' => 'How many guests?'],
            ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => 'call_from_groq_123', 'name' => 'getActiveSessions', 'arguments' => []]]],
            ['role' => 'tool', 'name' => 'getActiveSessions', 'tool_call_id' => 'call_from_groq_123', 'content' => '{"count":0}'],
        ], [], fn () => null);

        Http::assertSent(function ($r) {
            $m = $r->data()['messages'];
            $id = $m[1]['tool_calls'][0]['id'];

            return preg_match('/^[a-zA-Z0-9]{9}$/', $id) && $m[2]['tool_call_id'] === $id;
        });
    }
}
