<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\User;
use App\Services\Agent\Tools\GetSalesSummaryTool;
use App\Services\Agent\ToolRegistry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: "provide sales yesterday" got back an honest "I do not have
 * access to yesterday's specific sales" — buildAdminSystemPrompt() only ever
 * bakes in *today's* revenue, and no tool existed for any other period. Same
 * class of gap as the shiftHandoffSummary/checkStockLevels fixes earlier
 * this session.
 */
class GetSalesSummaryToolTest extends TestCase
{
    use RefreshDatabase;

    private function makeSale(string $transactionNumber, float $amount, string $status = 'completed'): Sale
    {
        $user = User::factory()->create();

        return Sale::create([
            'transaction_number' => $transactionNumber,
            'total_amount' => $amount,
            'status' => $status,
            'payment_method' => 'Cash',
            'order_type' => 'dine_in',
            'user_id' => $user->id,
        ]);
    }

    public function test_yesterday_sums_only_sales_from_yesterday(): void
    {
        $yesterday = $this->makeSale('TRN-YEST', 150);
        $yesterday->created_at = now()->subDay()->setTime(14, 0);
        $yesterday->save();

        $today = $this->makeSale('TRN-TODAY', 500);
        $today->created_at = now();
        $today->save();

        $result = app(GetSalesSummaryTool::class)->execute(['period' => 'yesterday'], null);

        $this->assertTrue($result->success);
        $this->assertSame(150.0, $result->data['total']);
        $this->assertSame(1, $result->data['order_count']);
        $this->assertStringContainsString("Yesterday's sales", $result->message);
        $this->assertStringContainsString('150.00', $result->message);
    }

    public function test_excludes_non_completed_sales(): void
    {
        $sale = $this->makeSale('TRN-VOID', 999, 'cancelled');
        $sale->created_at = now()->subDay()->setTime(14, 0);
        $sale->save();

        $result = app(GetSalesSummaryTool::class)->execute(['period' => 'yesterday'], null);

        $this->assertSame(0.0, $result->data['total']);
        $this->assertSame(0, $result->data['order_count']);
    }

    public function test_this_week_and_this_month_periods_work(): void
    {
        // Pinned mid-month/mid-week so startOfWeek() and startOfMonth() can't
        // land in different calendar months depending on when the suite runs
        // (a real boundary week — e.g. today, Sat Aug 1 2026 — otherwise makes
        // this flaky since startOfWeek() would fall in the prior month).
        Carbon::setTestNow(Carbon::create(2026, 1, 15, 10, 0));

        $sale = $this->makeSale('TRN-WEEK', 300);
        $sale->created_at = now()->startOfWeek()->addHours(2);
        $sale->save();

        $week = app(GetSalesSummaryTool::class)->execute(['period' => 'this_week'], null);
        $month = app(GetSalesSummaryTool::class)->execute(['period' => 'this_month'], null);

        Carbon::setTestNow();

        $this->assertSame(300.0, $week->data['total']);
        $this->assertSame(300.0, $month->data['total']);
    }

    public function test_invalid_period_fails_gracefully(): void
    {
        $result = app(GetSalesSummaryTool::class)->execute(['period' => 'next_century'], null);

        $this->assertFalse($result->success);
    }

    public function test_registered_for_admin_but_not_staff(): void
    {
        $registry = app(ToolRegistry::class);

        $this->assertArrayHasKey('getSalesSummary', $registry->forAudience(ToolRegistry::AUDIENCE_ADMIN));
        $this->assertArrayNotHasKey('getSalesSummary', $registry->forAudience(ToolRegistry::AUDIENCE_STAFF));
    }
}
