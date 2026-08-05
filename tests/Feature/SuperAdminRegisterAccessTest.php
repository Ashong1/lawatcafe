<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * super_admin is "exactly the developer/system account" (User::isSuperAdmin())
 * and manages the system rather than working the counter. RoleMiddleware is a
 * pure hierarchy, so it cannot express "admin and staff but not super_admin" —
 * DenySuperAdmin exists for that one carve-out.
 *
 * The point is not only role clarity: a sale rung from the system account would
 * be a real row in the shift and cash reconciliation figures.
 */
class SuperAdminRegisterAccessTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    public function test_super_admin_cannot_open_the_register(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('pos'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_super_admin_cannot_check_out_a_sale(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('pos.checkout'), [])
            ->assertRedirect(route('dashboard'));
    }

    public function test_super_admin_cannot_open_or_close_a_shift(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)->post(route('shift.start'), [])->assertRedirect(route('dashboard'));

        // A real shift, because route-model binding resolves before this
        // middleware — a made-up id would 404 first and prove nothing.
        $shift = Shift::create([
            'user_id' => User::factory()->create(['role' => 'staff'])->id,
            'starting_cash' => 1000,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $this->actingAs($superAdmin)->post(route('shift.end', $shift->id), [])->assertRedirect(route('dashboard'));
    }

    /** A fetch()-based caller needs a real status code, not a redirect it can't see. */
    public function test_a_json_request_is_refused_with_403_rather_than_a_redirect(): void
    {
        $this->actingAs($this->superAdmin())
            ->postJson(route('pos.checkout'), [])
            ->assertStatus(403);
    }

    public function test_admins_and_staff_keep_full_register_access(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('pos'))
                ->assertOk();
        }
    }

    /**
     * Reviewing sales and Z-reads is management work, so it must stay open to
     * super_admin — the carve-out is about ringing up orders, not about seeing
     * what was rung up.
     */
    public function test_super_admin_keeps_order_history_and_z_reads(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)->get(route('pos.history'))->assertOk();
        $this->actingAs($superAdmin)->get(route('admin.finance.z-reads'))->assertOk();
    }

    public function test_the_sidebar_stops_offering_the_register_to_super_admin(): void
    {
        $superAdminSidebar = $this->actingAs($this->superAdmin())->get(route('dashboard'));
        $superAdminSidebar->assertOk();
        $superAdminSidebar->assertDontSee('POS Register', false);
        $superAdminSidebar->assertDontSee('Open POS', false);

        // The same layout must still render it for an ordinary admin, otherwise
        // this assertion would pass for the wrong reason.
        $adminSidebar = $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('dashboard'));
        $adminSidebar->assertSee('POS Register', false);
        $adminSidebar->assertSee('Open POS', false);
    }
}
