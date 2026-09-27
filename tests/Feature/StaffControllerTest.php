<?php

namespace Tests\Feature;

use App\Models\AiAnalysisRun;
use App\Models\AiFinding;
use App\Models\Ingredient;
use App\Models\Sale;
use App\Models\User;
use App\Models\Voucher;
use App\Services\GuestSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_and_live_data_return_the_same_underlying_figures(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        Ingredient::create(['name' => 'Milk', 'current_stock' => 50, 'unit' => 'ml', 'low_stock_threshold' => 500, 'status' => 'Low Stock']);
        Sale::create([
            'transaction_number' => 'TRN-STAFF01', 'total_amount' => 150, 'status' => 'pending',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $staff->id,
        ]);
        Voucher::create(['code' => 'LAWA-STAFF1', 'duration_minutes' => 60, 'tier' => 'free', 'is_used' => false]);
        $run = AiAnalysisRun::create(['narrative' => 'Test narrative', 'signal_count' => 1]);
        AiFinding::create(['run_id' => $run->id, 'type' => 'low_stock_high_demand', 'severity' => 'warning', 'summary' => 'Milk is low', 'audience' => 'staff']);

        $indexResponse = $this->actingAs($staff)->get(route('staff.dashboard'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('Milk is low');

        // The live poll is the only place the guest count is fetched (it's a
        // firewall round-trip the page render must never wait on).
        $this->mock(GuestSessionService::class, fn ($m) => $m->shouldReceive('activeGuestCount')->andReturn(4));

        $liveResponse = $this->actingAs($staff)->getJson(route('staff.dashboard.live'));
        $liveResponse->assertOk();
        $liveResponse->assertJsonFragment(['pendingOrdersCount' => 1, 'unusedVouchers' => 1, 'guestsOnline' => 4]);
        $liveResponse->assertJsonFragment(['name' => 'Milk', 'current_stock' => 50, 'is_sold_out' => false]);
        $liveResponse->assertJsonFragment(['summary' => 'Milk is low']);
    }

    public function test_the_live_poll_survives_an_unreachable_firewall(): void
    {
        $this->mock(GuestSessionService::class, fn ($m) => $m->shouldReceive('activeGuestCount')->andThrow(new \RuntimeException('OPNsense down')));

        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->getJson(route('staff.dashboard.live'))
            ->assertOk()
            ->assertJsonFragment(['guestsOnline' => null]);
    }

    /** Staff sell Wi-Fi through the register; the dashboard links straight to its Wi-Fi items. */
    public function test_staff_dashboard_offers_wifi_and_the_pos_honours_the_category(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee(route('pos', ['category' => 'Wi-Fi']), false)
            ->assertSee(route('kds.index'), false);
    }
}
