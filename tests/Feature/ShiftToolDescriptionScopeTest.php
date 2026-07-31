<?php

namespace Tests\Feature;

use App\Services\Agent\Tools\ShiftHandoffSummaryTool;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression for a live report: asking the admin chat "what's the total
 * sales for today?" and "what about expected sales for today and the whole
 * week?" both got the same shiftHandoffSummary tool result — the model
 * grabbed that tool for any "sales"-shaped question even though (a) today's
 * revenue is already in buildAdminSystemPrompt()'s CURRENT SHOP STATUS block
 * with no tool call needed, and (b) the tool only ever describes the
 * caller's own single shift, not shop-wide sales. This can't be asserted via
 * an actual model call without flakiness/cost — verifying the disambiguating
 * text is present in both places is the deterministic part of the fix.
 */
class ShiftToolDescriptionScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_shift_tool_description_disambiguates_from_shop_wide_sales(): void
    {
        $description = app(ShiftHandoffSummaryTool::class)->description();

        $this->assertStringContainsString('OWN', $description);
        $this->assertStringContainsString('not shop-wide sales', $description);
    }

    public function test_admin_system_prompt_tells_the_model_to_answer_from_context_first(): void
    {
        $prompt = app(AIService::class)->buildAdminSystemPrompt();

        $this->assertStringContainsString('no tool call needed', $prompt);
        $this->assertStringContainsString('never for a general "today\'s sales" or forecast question', $prompt);
    }
}
