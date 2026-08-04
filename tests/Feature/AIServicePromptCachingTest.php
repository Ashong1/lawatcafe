<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Sale;
use App\Models\User;
use App\Models\Voucher;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * buildAdminSystemPrompt()/buildStaffSystemPrompt() used to run their
 * low-stock/today's-revenue/active-voucher-count queries fresh on every
 * single chat message (unlike the menu/pricing/best-sellers context, which
 * was already cached) — a 15-message conversation meant 45 avoidable
 * identical queries. These prove the shared cached helpers actually cache
 * (same value survives an underlying data change within the TTL) rather
 * than just asserting the prompt still contains the right data.
 */
class AIServicePromptCachingTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_stock_context_is_cached_across_prompt_builds(): void
    {
        Ingredient::create(['name' => 'Milk', 'current_stock' => 5, 'unit' => 'L', 'low_stock_threshold' => 500]);

        $first = app(AIService::class)->buildStaffSystemPrompt();
        $this->assertStringContainsString('Milk', $first);

        // A new low-stock ingredient appears, but within the cache TTL the
        // prompt should still reflect the cached snapshot, not requery.
        Ingredient::create(['name' => 'Sugar', 'current_stock' => 1, 'unit' => 'kg', 'low_stock_threshold' => 500]);

        $second = app(AIService::class)->buildStaffSystemPrompt();
        $this->assertStringNotContainsString('Sugar', $second, 'Low-stock context should be cached, not requeried on every prompt build.');

        Cache::forget('ai_ctx_low_stock');
        $third = app(AIService::class)->buildStaffSystemPrompt();
        $this->assertStringContainsString('Sugar', $third);
    }

    public function test_todays_sales_and_active_voucher_count_are_cached(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        Sale::create(['transaction_number' => 'TRN-A', 'total_amount' => 150, 'status' => 'completed', 'order_type' => 'dine_in', 'user_id' => $staff->id]);
        Voucher::create(['code' => 'LAWA-UNUSED', 'duration_minutes' => 60, 'is_used' => false]);

        $first = app(AIService::class)->buildAdminSystemPrompt();
        $this->assertStringContainsString('150.00', $first);
        $this->assertStringContainsString('Active Wi-Fi Vouchers: 1', $first);

        Sale::create(['transaction_number' => 'TRN-B', 'total_amount' => 500, 'status' => 'completed', 'order_type' => 'dine_in', 'user_id' => $staff->id]);
        Voucher::create(['code' => 'LAWA-UNUSED-2', 'duration_minutes' => 60, 'is_used' => false]);

        $second = app(AIService::class)->buildAdminSystemPrompt();
        $this->assertStringContainsString('150.00', $second, 'Revenue should still reflect the cached snapshot within the TTL.');
        $this->assertStringContainsString('Active Wi-Fi Vouchers: 1', $second);

        Cache::forget('ai_ctx_todays_sales');
        Cache::forget('ai_ctx_active_vouchers');

        $third = app(AIService::class)->buildAdminSystemPrompt();
        $this->assertStringContainsString('650.00', $third);
        $this->assertStringContainsString('Active Wi-Fi Vouchers: 2', $third);
    }
}
