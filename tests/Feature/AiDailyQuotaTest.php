<?php

namespace Tests\Feature;

use App\Console\Commands\RunAgentAnalysis;
use App\Models\User;
use App\Services\Agent\CrossDomainCorrelationService;
use App\Services\Agent\ToolCallOrchestrator;
use App\Services\AiBudget;
use App\Services\AIService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * 2026-09-28: the OpenRouter account hit its 50-a-day free-model cap. Every
 * model returned 429, each chat still tried all of them, and the owner saw
 * "trouble connecting". The scheduled jobs had used the whole allowance.
 */
class AiDailyQuotaTest extends TestCase
{
    use RefreshDatabase;

    private const RESET_MS = 1790553600000; // 2026-09-28 00:00 UTC = 8:00 AM Manila

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openrouter.key' => 'test-key']);
        Cache::flush();
    }

    private function dailyCap429()
    {
        return Http::response(['error' => [
            'message' => 'Rate limit exceeded: free-models-per-day. Add 5 credits to unlock 1000 free model requests per day',
            'code' => 429,
            'metadata' => ['headers' => ['X-RateLimit-Limit' => '50', 'X-RateLimit-Remaining' => '0', 'X-RateLimit-Reset' => (string) self::RESET_MS]],
        ]], 429);
    }

    public function test_the_daily_cap_stops_the_cascade_and_pauses_until_reset(): void
    {
        $this->travelTo(Carbon::createFromTimestamp(1790530698));
        Http::fake(['openrouter.ai/api/v1/chat/completions' => $this->dailyCap429()]);

        $this->assertNull(app(AIService::class)->chatWithToolsStreaming([['role' => 'user', 'content' => 'hi']], [], fn () => null));

        // One model tried, not the whole list: the rest would fail identically.
        Http::assertSentCount(1);
        $this->assertSame(1790553600, AIService::quotaExhaustedUntil()?->timestamp);

        // Paused: the next call doesn't even reach OpenRouter.
        app(AIService::class)->chatWithToolsStreaming([['role' => 'user', 'content' => 'again']], [], fn () => null);
        Http::assertSentCount(1);
    }

    public function test_an_ordinary_429_does_not_pause_ai(): void
    {
        Http::fake(['openrouter.ai/api/v1/chat/completions' => Http::response(['error' => ['message' => 'temporarily rate-limited upstream']], 429)]);

        app(AIService::class)->chatWithToolsStreaming([['role' => 'user', 'content' => 'hi']], [], fn () => null);

        $this->assertNull(AIService::quotaExhaustedUntil());
    }

    private function chatReply(string $role, string $route): string
    {
        Cache::put(AIService::QUOTA_CACHE_KEY, now()->addHours(6)->timestamp, now()->addHours(6));
        $this->mock(ToolCallOrchestrator::class, fn ($m) => $m->shouldReceive('run')->andReturn(['reply' => null, 'pending' => [], 'executed' => []]));

        $request = $role === 'guest' ? $this : $this->actingAs(User::factory()->create(['role' => $role]));

        return $request->withHeader('Accept', 'text/event-stream')->post(route($route), ['message' => 'hello'])->streamedContent();
    }

    public function test_staff_and_admins_are_told_why_and_when_it_comes_back(): void
    {
        $body = $this->chatReply('admin', 'admin.ai.chat');

        $this->assertStringContainsString("used up today's free AI allowance", $body);
        $this->assertStringContainsString('comes back at', $body);
        $this->assertStringContainsString('1,000 requests', $body);
        $this->assertStringNotContainsString('trouble connecting', $body);
    }

    public function test_guests_get_a_friendly_note_without_billing_talk(): void
    {
        $body = $this->chatReply('guest', 'portal.chat');

        $this->assertStringContainsString('taking a break until', $body);
        $this->assertStringNotContainsString('credit', $body);
    }

    public function test_background_jobs_keep_a_reserve_for_people(): void
    {
        Http::fake(['openrouter.ai/api/v1/key' => Http::sequence()
            ->push(['data' => ['free_model_daily_requests' => ['used' => 40, 'limit' => 50, 'remaining' => 10]]])
            ->push(['data' => ['free_model_daily_requests' => ['used' => 5, 'limit' => 50, 'remaining' => 45]]]),
        ]);

        $budget = new AiBudget; // the real one — TestCase binds an offline copy

        $this->assertFalse($budget->backgroundMaySpend(), '10 left is inside the reserve');

        Cache::forget('openrouter_daily_remaining');
        $this->assertTrue($budget->backgroundMaySpend());

        Cache::put(AIService::QUOTA_CACHE_KEY, now()->addHour()->timestamp, now()->addHour());
        $this->assertFalse($budget->backgroundMaySpend());
    }

    public function test_agent_analysis_does_not_re_review_unchanged_signals(): void
    {
        $signal = fn (int $pct) => ['type' => 'voucher_revenue_divergence', 'severity' => 'warning', 'summary' => "Wi-Fi voucher redemptions are up {$pct}% day-over-day", 'data' => []];
        $this->mock(CrossDomainCorrelationService::class, fn ($m) => $m->shouldReceive('run')->andReturn(
            ['signals' => [$signal(100)]], ['signals' => [$signal(120)]]
        ));
        $this->mock(AIService::class, fn ($m) => $m->shouldReceive('interpretSignals')->once()->andReturn(['narrative' => 'Reviewed.']));
        $this->mock(ToolCallOrchestrator::class, fn ($m) => $m->shouldReceive('run')->once()->andReturn(['reply' => 'ok', 'pending' => [], 'executed' => []]));

        $this->artisan('agent:analyze')->assertSuccessful();
        // Same warning, different number: no second review.
        $this->artisan('agent:analyze')->expectsOutputToContain('unchanged since the last review')->assertSuccessful();

        $this->assertNotNull(Cache::get(RunAgentAnalysis::FINGERPRINT_KEY));
    }

    public function test_the_forecast_warmer_skips_when_the_allowance_is_low(): void
    {
        $this->mock(AiBudget::class, fn ($m) => $m->shouldReceive('backgroundMaySpend')->andReturn(false));

        $this->artisan('ai:warm-forecast')->expectsOutputToContain('Skipped')->assertSuccessful();
    }
}
