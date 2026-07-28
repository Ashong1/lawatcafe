<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndOfDayControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_redirects_an_open_shift_to_the_live_closing_report_instead_of_crashing(): void
    {
        // Regression test: EndOfDayController::show()'s view formats
        // closed_at/ending_cash unconditionally, which are both null for an
        // open shift ("Call to a member function format() on null" in
        // production on a shift that hadn't been closed yet).
        $admin = User::factory()->create(['role' => 'admin']);
        $staffMember = User::factory()->create(['role' => 'staff']);
        $shift = Shift::create([
            'user_id' => $staffMember->id, 'starting_cash' => 500, 'status' => 'open', 'opened_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.finance.shift-detail', $shift));

        $response->assertRedirect(route('shift.closing-report', $shift));
        $response->assertSessionHas('error');
    }

    public function test_show_renders_the_audit_report_for_a_closed_shift(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staffMember = User::factory()->create(['role' => 'staff']);
        $shift = Shift::create([
            'user_id' => $staffMember->id, 'starting_cash' => 500, 'status' => 'closed',
            'opened_at' => now()->subHours(8), 'closed_at' => now(), 'ending_cash' => 700, 'expected_cash' => 700,
        ]);
        Sale::create([
            'transaction_number' => 'TRN-ZREAD1', 'total_amount' => 200, 'status' => 'completed',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $staffMember->id, 'shift_id' => $shift->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.finance.shift-detail', $shift));

        $response->assertOk();
        $response->assertSee($staffMember->name);
        $response->assertViewHas('summary', function ($summary) {
            return $summary['cash_sales'] === 200.0;
        });
    }

    public function test_z_reads_list_links_open_shifts_to_the_live_report_and_closed_shifts_to_the_audit_detail(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staffMember = User::factory()->create(['role' => 'staff']);
        $openShift = Shift::create(['user_id' => $staffMember->id, 'starting_cash' => 500, 'status' => 'open', 'opened_at' => now()]);
        $closedShift = Shift::create(['user_id' => $staffMember->id, 'starting_cash' => 500, 'status' => 'closed', 'opened_at' => now()->subHours(8), 'closed_at' => now()]);

        $response = $this->actingAs($admin)->get(route('admin.finance.z-reads', ['all' => 1]));

        $response->assertOk();
        $response->assertSee(route('shift.closing-report', $openShift), false);
        $response->assertSee(route('admin.finance.shift-detail', $closedShift), false);
        $response->assertSee('View Live');
        $response->assertSee('Audit Detail');
    }
}
