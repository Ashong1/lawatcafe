<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Server-rendered regression guards for the Phase 1 UI/UX bug fixes (see
 * /root/.claude/plans/misty-plotting-bird.md). Actual visual/interaction
 * behavior (mobile positioning, scroll, focus) has no browser tooling this
 * session and is verified by manual code review instead — these tests only
 * guard the parts that are mechanically checkable via rendered HTML.
 */
class UiUxPhase1RegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_modal_shell_centered_panel_has_a_max_height_and_scrolls(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('inventory.ingredients.index'));

        $response->assertOk();
        $response->assertSee('max-h-[85vh]', false);
        $response->assertSee('overflow-y-auto', false);
    }

    public function test_admin_header_hides_low_value_elements_and_wraps_at_narrow_widths(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('hidden lg:inline', false);
        $response->assertSee('flex-wrap', false);
        // The odd-one-out contrast failure on this label is fixed in the same edit.
        $response->assertDontSee('text-[#A1887F] group-hover:text-[#3E2723]', false);
        $response->assertSee('text-[#6D4C41] group-hover:text-[#3E2723]', false);
    }

    public function test_staff_header_hides_low_value_elements_and_wraps_at_narrow_widths(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($staff)->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertSee('hidden lg:inline', false);
        $response->assertSee('flex-wrap', false);
    }

    public function test_notification_bell_and_pending_badge_dropdowns_cap_width_to_viewport(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        // Present twice: once from <x-notification-bell>, once from <x-agent-pending-badge>.
        $this->assertSame(2, substr_count($response->getContent(), 'max-w-[calc(100vw-2rem)]'));
    }

    public function test_network_infrastructure_table_scrolls_instead_of_clipping(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('listSessions')->andReturn([]);
            $mock->shouldReceive('getArpTable')->andReturn([]);
            $mock->shouldReceive('getDhcpLeases')->andReturn([]);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
            $mock->shouldReceive('getDhcpPools')->andReturn([]);
        });

        $response = $this->actingAs($admin)->get(route('network.sessions'));

        $response->assertOk();
        $response->assertDontSee('rounded-2xl border border-slate-200 overflow-hidden shadow-sm', false);
    }

    public function test_network_settings_uses_confirm_action_not_native_confirm(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
            $mock->shouldReceive('getDhcpPools')->andReturn([]);
        });

        $response = $this->actingAs($superAdmin)->get(route('admin.settings.network'));

        $response->assertOk();
        $response->assertDontSee('onsubmit="return confirm', false);
        $response->assertSee('window.confirmAction', false);
    }

    public function test_store_and_network_settings_forms_have_a_loading_state(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
            $mock->shouldReceive('getDhcpPools')->andReturn([]);
        });

        $store = $this->actingAs($superAdmin)->get(route('admin.settings.store'));
        $store->assertOk();
        $store->assertSee('submitting', false);

        $network = $this->actingAs($superAdmin)->get(route('admin.settings.network'));
        $network->assertOk();
        $network->assertSee('submitting', false);
    }
}
