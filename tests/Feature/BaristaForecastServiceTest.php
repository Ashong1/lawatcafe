<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\User;
use App\Services\AIService;
use App\Services\BaristaForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class BaristaForecastServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_ai_insights_and_analytics_page_return_the_same_calibrating_shape(): void
    {
        // Zero sales seeded — both consumers must hit the "less than 1 day of
        // data" gate, which needs no AI call and is fully deterministic. This
        // is also the regression check for the shared-cache-key bug: before
        // extracting a single BaristaForecastService, these two endpoints ran
        // independently-drifted copies of this logic under the same cache
        // key, so whichever ran first silently decided what shape the other
        // got back. Now both call the same method, so they must always agree.
        $admin = User::factory()->create(['role' => 'admin']);

        $insightsResponse = $this->actingAs($admin)->getJson(route('admin.ai.insights'));
        $insightsResponse->assertOk();
        $insightsResponse->assertJson([
            'is_calibrating' => true,
            'meta' => [
                'confidence_label' => 'Awaiting First Sale',
                'confidence_max' => 7,
            ],
        ]);

        $analyticsResponse = $this->actingAs($admin)->get(route('admin.analytics'));
        $analyticsResponse->assertOk();
        $analyticsResponse->assertSee('Awaiting First Sale');
        // Zero sales also means both breakdown tables are empty — assert
        // their empty states render instead of a silently blank tbody.
        $analyticsResponse->assertSee('No category sales yet');
        $analyticsResponse->assertSee('No transactions this week');
    }

    /** One sale is enough to clear the "less than 1 day of data" gate. */
    private function seedOneSale(): void
    {
        Sale::create([
            'transaction_number' => 'TRN-FORECAST-1',
            'total_amount' => 250,
            'status' => 'completed',
            'payment_method' => 'Cash',
            'order_type' => 'dine_in',
            'user_id' => User::factory()->create()->id,
        ]);
    }

    private function fakeForecastPayload(): array
    {
        return [
            'forecast_total' => 5000.0,
            'daily_forecast' => [],
            'trend_analysis' => 'Steady.',
            'predicted_top_products' => ['Latte'],
            'predicted_low_products' => [],
            'demand_risk_alerts' => [],
            'strategic_advice' => 'Push the weekend promo.',
            'context_tags' => ['steady'],
        ];
    }

    /**
     * The reason this service exists in this shape: the AI cascade takes ~9s,
     * so it must never run inside a web request. Once a good forecast has been
     * generated, an expired freshness window has to serve the last known good
     * copy instantly rather than regenerating inline.
     */
    public function test_an_expired_forecast_serves_the_stale_copy_without_calling_the_ai(): void
    {
        $this->seedOneSale();

        $generating = Mockery::mock(AIService::class);
        $generating->shouldReceive('analyzeSalesTrends')->once()->andReturn($this->fakeForecastPayload());

        $service = app(BaristaForecastService::class);
        $service->getForecast($generating);

        // Exactly what happens an hour later, when the next admin logs in.
        Cache::forget('barista_forecast_deep');

        $mustNotBeCalled = Mockery::mock(AIService::class);
        $mustNotBeCalled->shouldNotReceive('analyzeSalesTrends');

        $result = $service->getForecast($mustNotBeCalled);

        $this->assertSame(5000.0, $result['forecast_total']);
        $this->assertSame('Push the weekend promo.', $result['strategic_advice']);
    }

    public function test_the_scheduled_warmer_regenerates_even_when_the_cache_is_fresh(): void
    {
        $this->seedOneSale();

        $ai = Mockery::mock(AIService::class);
        // Once for the initial fill, once more because the warmer forces it.
        $ai->shouldReceive('analyzeSalesTrends')->twice()->andReturn($this->fakeForecastPayload());
        $this->app->instance(AIService::class, $ai);

        app(BaristaForecastService::class)->getForecast($ai);
        $this->assertNotNull(Cache::get('barista_forecast_deep'));

        $this->artisan('ai:warm-forecast')->assertSuccessful();
    }

    /**
     * A total AI outage must not become the stale fallback — otherwise a single
     * bad minute would pin "AI unavailable" on the dashboard for a whole day.
     */
    public function test_an_ai_outage_is_never_written_to_either_cache(): void
    {
        $this->seedOneSale();

        $down = Mockery::mock(AIService::class);
        $down->shouldReceive('analyzeSalesTrends')->andReturn(null);
        $this->app->instance(AIService::class, $down);

        $result = app(BaristaForecastService::class)->getForecast($down);

        $this->assertSame(['AI Unavailable'], $result['context_tags']);
        $this->assertNull(Cache::get('barista_forecast_deep'));
        $this->assertNull(Cache::get('barista_forecast_deep_stale'));
        $this->artisan('ai:warm-forecast')->assertFailed();
    }

    public function test_staff_cannot_reach_admin_analytics_or_ai_insights(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get(route('admin.analytics'))->assertRedirect(route('staff.dashboard'));
        // A JSON-expecting request now gets a real 403 instead of a redirect it
        // has no way to detect (see RoleMiddleware's ajax()/expectsJson() check).
        $this->actingAs($staff)->getJson(route('admin.ai.insights'))->assertStatus(403);
    }
}
