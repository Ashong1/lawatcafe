<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\AIService;
use App\Services\AiUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Owner (Oct 30): usage was only tracked for OpenRouter; count every AI service. */
class AiUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_service_is_counted_with_its_reported_limits(): void
    {
        config(['services.openrouter.key' => null]);
        foreach (['gemini', 'groq'] as $p) {
            Setting::set("{$p}_api_key", Crypt::encryptString("k-{$p}"));
        }
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'quota'], 429),
            'api.groq.com/*' => Http::response('data: '.json_encode(['choices' => [['delta' => ['content' => 'Hi.']]]])."\n\n", 200, [
                'x-ratelimit-limit-requests' => '14400', 'x-ratelimit-remaining-requests' => '14390',
            ]),
        ]);

        app(AIService::class)->chatWithToolsStreaming([['role' => 'user', 'content' => 'hi']], [], fn () => null);

        $today = app(AiUsage::class)->today();
        $this->assertSame(['answered' => 0, 'failed' => 2], array_intersect_key($today['gemini'], ['answered' => 1, 'failed' => 1]));
        $this->assertSame(1, $today['groq']['answered']);
        $this->assertSame(['14,390 of 14,400 requests left'], $today['groq']['limits']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.settings.ai-providers'))
            ->assertSee('AI usage today')->assertSee('Google Gemini')->assertSee('14,390 left')->assertSee('10 of 14,400');
    }

    /** Owner (Oct 30): show total and remaining usage, not just counts. */
    public function test_used_remaining_and_the_week_are_shown(): void
    {
        config(['services.openrouter.key' => null]);
        Setting::set('gemini_api_key', Crypt::encryptString('k'));
        Setting::set('groq_api_key', Crypt::encryptString('k'));
        $usage = app(AiUsage::class);
        $ok = new \GuzzleHttp\Psr7\Response(200, ['x-ratelimit-limit-requests' => '1000', 'x-ratelimit-remaining-requests' => '990']);
        foreach (range(1, 3) as $i) {
            AiUsage::observe('https://api.groq.com/openai/v1/chat/completions', $ok);
        }
        AiUsage::observe('https://generativelanguage.googleapis.com/v1beta/openai/chat/completions', new \GuzzleHttp\Psr7\Response(200));
        \Illuminate\Support\Facades\Cache::put('ai_usage:'.now()->subDays(2)->toDateString().':groq:ok', 5, now()->addDay());

        // Gemini doesn't report what's left: the super admin enters its daily limit.
        $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->post(route('admin.settings.ai-providers.limit', 'gemini'), ['daily_limit' => 250])->assertSessionHas('success');

        $d = $usage->detailed();
        $this->assertSame(['used' => 10, 'limit' => 1000, 'remaining' => 990, 'source' => 'reported by Groq'], $d['groq']['quota']);
        $this->assertSame(8, $d['groq']['week']);
        $this->assertSame(['used' => 1, 'limit' => 250, 'remaining' => 249, 'source' => 'your daily limit'], $d['gemini']['quota']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.settings.ai-providers'))
            ->assertSee('990 left')->assertSee('249 left')->assertSee('Last 7 days')->assertSee('All services');
    }
}
