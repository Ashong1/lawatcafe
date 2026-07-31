<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_staff_cannot_reach_admin_analytics_or_ai_insights(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get(route('admin.analytics'))->assertRedirect(route('staff.dashboard'));
        // A JSON-expecting request now gets a real 403 instead of a redirect it
        // has no way to detect (see RoleMiddleware's ajax()/expectsJson() check).
        $this->actingAs($staff)->getJson(route('admin.ai.insights'))->assertStatus(403);
    }
}
