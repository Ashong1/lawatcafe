<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Shift;
use App\Models\User;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: buildStaffSystemPrompt() used to bake in nothing but the menu —
 * no shop-status data at all, unlike the admin prompt — which was the
 * structural root cause of staff chat misusing shiftHandoffSummary for
 * general sales questions (faf46a8 patched the symptom, not this gap).
 */
class AIServiceStaffPromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_prompt_disambiguates_shift_handoff_from_general_sales_questions(): void
    {
        $prompt = app(AIService::class)->buildStaffSystemPrompt();

        $this->assertStringContainsString('getSalesSummary', $prompt);
        $this->assertStringContainsString('shiftHandoffSummary', $prompt);
    }

    public function test_prompt_reports_low_stock_ingredients_by_name(): void
    {
        Ingredient::create(['name' => 'Milk', 'current_stock' => 5, 'unit' => 'L', 'low_stock_threshold' => 500]);

        $prompt = app(AIService::class)->buildStaffSystemPrompt();

        $this->assertStringContainsString('Milk', $prompt);
    }

    public function test_prompt_reports_the_actors_own_open_shift(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        Shift::create(['user_id' => $staff->id, 'status' => 'open', 'opened_at' => now(), 'starting_cash' => 1000]);

        $prompt = app(AIService::class)->buildStaffSystemPrompt($staff);

        $this->assertStringContainsString('Open since', $prompt);
    }

    public function test_prompt_reports_no_open_shift_when_none_exists(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $prompt = app(AIService::class)->buildStaffSystemPrompt($staff);

        $this->assertStringContainsString('No shift currently open', $prompt);
    }

    public function test_prompt_works_with_no_actor_for_the_legacy_caller(): void
    {
        $prompt = app(AIService::class)->buildStaffSystemPrompt();

        $this->assertStringContainsString('No shift currently open', $prompt);
    }
}
