<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\User;
use App\Services\Agent\PermissionResolver;
use App\Services\Agent\ToolRegistry;
use App\Services\Agent\Tools\GetSalesSummaryTool;
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

    public function test_excludes_cancelled_sales(): void
    {
        $sale = $this->makeSale('TRN-VOID', 999, 'cancelled');
        $sale->created_at = now()->subDay()->setTime(14, 0);
        $sale->save();

        $result = app(GetSalesSummaryTool::class)->execute(['period' => 'yesterday'], null);

        $this->assertSame(0.0, $result->data['total']);
        $this->assertSame(0, $result->data['order_count']);
    }

    public function test_counts_sales_still_pending_or_preparing_on_the_kds_board(): void
    {
        // Regression: checkout() always creates a sale as 'pending' — it only
        // becomes 'completed' once the KDS board clears every item. Payment
        // already happened at checkout, so it must count toward revenue
        // regardless of kitchen fulfillment status. See Sale::scopeRevenue().
        $pending = $this->makeSale('TRN-PEND', 89, 'pending');
        $pending->created_at = now()->subDay()->setTime(14, 0);
        $pending->save();

        $preparing = $this->makeSale('TRN-PREP', 150, 'preparing');
        $preparing->created_at = now()->subDay()->setTime(15, 0);
        $preparing->save();

        $result = app(GetSalesSummaryTool::class)->execute(['period' => 'yesterday'], null);

        $this->assertSame(239.0, $result->data['total']);
        $this->assertSame(2, $result->data['order_count']);
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

    public function test_description_is_audience_neutral(): void
    {
        // Regression: the description used to assume it was only reachable
        // from the admin prompt ("today's revenue is already in your system
        // prompt context") — wrong once staff (whose prompt has no such
        // baked-in figure) can reach this tool too. That guidance belongs in
        // the admin prompt's own guidelines, not this tool's description.
        $description = app(GetSalesSummaryTool::class)->description();

        $this->assertStringNotContainsString('system prompt', $description);
    }

    public function test_invalid_period_fails_gracefully(): void
    {
        $result = app(GetSalesSummaryTool::class)->execute(['period' => 'next_century'], null);

        $this->assertFalse($result->success);
    }

    public function test_registered_for_admin_and_staff_but_not_guest(): void
    {
        // Staff previously had zero shop-wide sales tool at all, which was
        // the structural reason staff chat kept misusing shiftHandoffSummary
        // (a single shift's numbers) for general sales questions — see
        // AIServiceStaffPromptTest for the matching disambiguation-prompt fix.
        $registry = app(ToolRegistry::class);

        $this->assertArrayHasKey('getSalesSummary', $registry->forAudience(ToolRegistry::AUDIENCE_ADMIN));
        $this->assertArrayHasKey('getSalesSummary', $registry->forAudience(ToolRegistry::AUDIENCE_STAFF));
        $this->assertArrayNotHasKey('getSalesSummary', $registry->forAudience(ToolRegistry::AUDIENCE_GUEST));
    }

    public function test_resolves_to_confirm_tier_for_staff_despite_auto_default(): void
    {
        // Same role-floor architecture every other read-only staff tool
        // already goes through (checkStockLevels, getActiveSessions, etc.) —
        // not a new inconsistency introduced by widening this tool's audience.
        $resolver = app(PermissionResolver::class);
        $registry = app(ToolRegistry::class);
        $tool = $registry->forAudience(ToolRegistry::AUDIENCE_STAFF)['getSalesSummary'];
        $staff = User::factory()->create(['role' => 'staff']);

        $this->assertSame(PermissionResolver::TIER_CONFIRM, $resolver->tierFor($tool, $staff));
    }
}
