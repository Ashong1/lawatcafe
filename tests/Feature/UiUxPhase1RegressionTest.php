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

    /** The layout's own <header> opening tag, so class assertions can be scoped to it. */
    private function headerTag(string $html): string
    {
        $this->assertSame(1, preg_match('/<header\\b[^>]*>/', $html, $m), 'Expected exactly one <header> tag in the layout.');

        return $m[0];
    }

    public function test_modal_shell_centered_panel_has_a_max_height_and_scrolls(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('inventory.ingredients.index'));

        $response->assertOk();
        $response->assertSee('max-h-[85vh]', false);
        $response->assertSee('overflow-y-auto', false);
    }

    /**
     * The header used to be allowed to wrap at narrow widths. Wrapping is what
     * a header does when it has more in it than it can fit, and the result was
     * two or three stacked rows shoving the page down the screen on a phone.
     * It no longer wraps: the role label hides, the logout word becomes an
     * icon, and the user's name — the one item that can grow without limit —
     * truncates. Same goal, without spending vertical space to reach it.
     */
    public function test_admin_header_fits_one_row_at_narrow_widths(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        // Scoped to the header tag: flex-wrap is legitimate elsewhere on the
        // page (shortcut rows, tag chips), so a whole-document assertion would
        // pass or fail for unrelated reasons.
        $this->assertStringNotContainsString('flex-wrap', $this->headerTag($response->getContent()));
        $response->assertSee('hidden lg:inline', false);
        $response->assertSee('truncate max-w-[7rem]', false);
        // Logout: word from sm up, icon below it.
        $response->assertSee('hidden sm:inline', false);
        // The odd-one-out contrast failure on this label is fixed in the same edit.
        $response->assertDontSee('text-[#A1887F] group-hover:text-[#3E2723]', false);
        $response->assertSee('text-[#6D4C41] group-hover:text-[#3E2723]', false);
    }

    public function test_staff_header_fits_one_row_at_narrow_widths(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($staff)->get(route('staff.dashboard'));

        $response->assertOk();
        $this->assertStringNotContainsString('flex-wrap', $this->headerTag($response->getContent()));
        $response->assertSee('hidden lg:inline', false);
        $response->assertSee('truncate max-w-[7rem]', false);
        $response->assertSee('hidden sm:inline', false);
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
