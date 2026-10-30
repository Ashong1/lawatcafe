<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AiBudget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The owner asked to see the OpenRouter balance and the daily free-request limit. */
class OpenRouterUsageCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openrouter.key' => 'sk-test']);
        $this->app->instance(AiBudget::class, new AiBudget); // the real one, against faked endpoints
    }

    private function page()
    {
        return $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.settings.ai-providers'));
    }

    public function test_it_shows_the_balance_and_todays_free_requests(): void
    {
        Http::fake([
            'openrouter.ai/api/v1/credits' => Http::response(['data' => ['total_credits' => 5, 'total_usage' => 5.201682747]]),
            'openrouter.ai/api/v1/key' => Http::response(['data' => ['free_model_daily_requests' => ['used' => 13, 'limit' => 50, 'remaining' => 37]]]),
        ]);

        $this->page()->assertOk()
            ->assertSee('-$0.20')
            ->assertSee('13 of 50')
            ->assertSee('below zero');
    }

    public function test_a_positive_balance_has_no_warning(): void
    {
        Http::fake([
            'openrouter.ai/api/v1/credits' => Http::response(['data' => ['total_credits' => 10, 'total_usage' => 2.5]]),
            'openrouter.ai/api/v1/key' => Http::response(['data' => ['free_model_daily_requests' => ['used' => 3, 'limit' => 1000, 'remaining' => 997]]]),
        ]);

        $this->page()->assertOk()->assertSee('$7.50')->assertSee('3 of 1,000')->assertDontSee('below zero');
    }

    public function test_the_page_still_loads_when_openrouter_is_unreachable(): void
    {
        Http::fake(['openrouter.ai/*' => Http::response('down', 500)]);

        $this->page()->assertOk()->assertSee("Couldn't reach OpenRouter", false);
    }
}
