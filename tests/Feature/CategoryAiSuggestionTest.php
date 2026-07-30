<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryAiSuggestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_gets_a_description_and_icon_suggestion(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->mock(AIService::class, function ($mock) {
            $mock->shouldReceive('suggestCategoryContent')
                ->once()
                ->with('Cold Brews')
                ->andReturn(['description' => 'Smooth, slow-steeped iced coffee.', 'icon' => 'cup-soda']);
        });

        $response = $this->actingAs($admin)->postJson(route('inventory.categories.suggest-ai'), ['name' => 'Cold Brews']);

        $response->assertOk();
        $response->assertJson(['description' => 'Smooth, slow-steeped iced coffee.', 'icon' => 'cup-soda']);
    }

    public function test_it_returns_a_422_when_ai_cannot_generate_a_suggestion(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->mock(AIService::class, function ($mock) {
            $mock->shouldReceive('suggestCategoryContent')->once()->andReturn(null);
        });

        $response = $this->actingAs($admin)->postJson(route('inventory.categories.suggest-ai'), ['name' => 'Cold Brews']);

        $response->assertStatus(422);
    }

    public function test_name_is_required(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson(route('inventory.categories.suggest-ai'), []);

        $response->assertJsonValidationErrors('name');
    }

    public function test_staff_cannot_reach_this_endpoint(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($staff)->post(route('inventory.categories.suggest-ai'), ['name' => 'Cold Brews']);

        $response->assertRedirect(route('staff.dashboard'));
    }
}
