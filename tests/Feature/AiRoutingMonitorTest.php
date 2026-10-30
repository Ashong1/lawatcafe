<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\AIService;
use App\Services\AiRouteLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Owner (Oct 30): watch the orchestration live, like OmniRoute's monitor. */
class AiRoutingMonitorTest extends TestCase
{
    use RefreshDatabase;

    private function keys(array $providers): void
    {
        config(['services.openrouter.key' => null]);
        foreach ($providers as $p) {
            Setting::set("{$p}_api_key", Crypt::encryptString("test-{$p}-key"));
        }
    }

    public function test_each_chat_is_logged_as_a_chain_of_attempts(): void
    {
        $this->keys(['gemini', 'groq']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'quota'], 429),
            'api.groq.com/*' => Http::response('data: '.json_encode(['choices' => [['delta' => ['content' => 'Hi.']]]])."\n\n"),
        ]);

        app(AIService::class)->chatWithToolsStreaming([['role' => 'user', 'content' => 'hi']], [], fn () => null);

        $request = app(AiRouteLog::class)->requests()[0];
        $this->assertSame('chat', $request['kind']);
        $this->assertSame(['gemini', 'gemini', 'groq'], array_column($request['attempts'], 'provider'));
        $this->assertSame([false, false, true], array_column($request['attempts'], 'ok'));
        $this->assertSame('http_429', $request['attempts'][0]['reason']);
        $this->assertSame('groq', $request['answered_by']);
    }

    public function test_model_tests_from_the_settings_page_are_not_logged(): void
    {
        $this->keys(['groq']);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => 'OK']]]])]);

        app(AIService::class)->testModel('groq', 'openai/gpt-oss-120b');

        $this->assertSame([], app(AiRouteLog::class)->requests());
    }

    public function test_the_live_page_is_for_the_super_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.settings.ai-routing'))->assertRedirect(route('dashboard'));

        $super = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($super)->get(route('admin.settings.ai-routing'))->assertOk()->assertSee('AI Routing')->assertSee('lkRouting', false);
        $this->actingAs($super)->getJson(route('admin.settings.ai-routing.feed'))->assertOk()
            ->assertJsonStructure(['providers' => [['key', 'label', 'configured', 'resting', 'answered', 'failed', 'avg_ms']], 'requests']);
    }
}
