<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Agent\ToolCallOrchestrator;
use App\Services\Agent\ToolRegistry;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Owner: "make sure that the portal ai features uses only all free ai
 * models". The model list is admin-editable, and once the account has credit
 * a paid model there would bill for anonymous guest chats. Two locks: the
 * guest cascade keeps only models the live catalog prices at $0, and every
 * guest request carries provider.max_price = 0.
 */
class GuestFreeModelsOnlyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openrouter.key' => 'test-key']);
        Cache::flush();
        // Admin has put a paid model first in the list.
        Setting::set('ai_models_openrouter', json_encode(['openai/gpt-4o', 'dots-studio/dots-3-note-preview:free']));
    }

    private function fakeOpenRouter(): void
    {
        Http::fake([
            'openrouter.ai/api/v1/models' => Http::response(['data' => [
                ['id' => 'openai/gpt-4o', 'pricing' => ['prompt' => '0.0000025', 'completion' => '0.00001']],
                ['id' => 'dots-studio/dots-3-note-preview:free', 'pricing' => ['prompt' => '0', 'completion' => '0']],
                ['id' => 'openrouter/free', 'pricing' => ['prompt' => '0', 'completion' => '0']],
            ]]),
            'openrouter.ai/api/v1/chat/completions' => Http::response(
                "data: {\"choices\":[{\"delta\":{\"content\":\"Hi!\"}}]}\n\ndata: [DONE]\n\n",
                200, ['Content-Type' => 'text/event-stream']
            ),
        ]);
    }

    private function completionRequests(): array
    {
        return Http::recorded(fn ($r) => str_contains($r->url(), '/chat/completions'))->map(fn ($pair) => $pair[0])->all();
    }

    public function test_guest_chat_never_tries_a_paid_model_and_caps_the_price_at_zero(): void
    {
        $this->fakeOpenRouter();

        $result = app(ToolCallOrchestrator::class)->run([['role' => 'user', 'content' => 'hi']], ToolRegistry::AUDIENCE_GUEST, null);

        $this->assertSame('Hi!', $result['reply']);
        foreach ($this->completionRequests() as $request) {
            $this->assertStringEndsWith(':free', $request['model'], 'a paid model was tried for a guest');
            $this->assertSame(['max_price' => ['prompt' => 0, 'completion' => 0]], $request['provider']);
        }
    }

    public function test_staff_and_admin_requests_are_not_price_capped(): void
    {
        $this->fakeOpenRouter();

        app(ToolCallOrchestrator::class)->run([['role' => 'user', 'content' => 'hi']], ToolRegistry::AUDIENCE_ADMIN, null);

        $first = $this->completionRequests()[0];
        $this->assertSame('openai/gpt-4o', $first['model'], 'admin keeps the admin-chosen order');
        $this->assertArrayNotHasKey('provider', $first->data());
    }

    public function test_free_filter_uses_real_prices_and_never_comes_back_empty(): void
    {
        $this->fakeOpenRouter();
        $ai = app(AIService::class);

        $this->assertSame(['dots-studio/dots-3-note-preview:free'], $ai->freeModelsOnly(['openai/gpt-4o', 'dots-studio/dots-3-note-preview:free']));
        $this->assertSame([AIService::FREE_ROUTER], $ai->freeModelsOnly(['openai/gpt-4o']));
    }

    public function test_without_the_catalog_only_free_variants_count(): void
    {
        Http::fake(['openrouter.ai/api/v1/models' => Http::response([], 500)]);

        $this->assertSame(
            ['dots-studio/dots-3-note-preview:free', 'openrouter/free'],
            app(AIService::class)->freeModelsOnly(['openai/gpt-4o', 'dots-studio/dots-3-note-preview:free', 'openrouter/free'])
        );
    }
}
