<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Submenu open/closed state is derived purely from the current route on
 * every page load — no cookie-based cross-page stickiness. That cookie
 * blending went through two prior designs, each with its own bug:
 *   1. array_merge($routeDefaults, $cookieMenus) let a stale cookie value
 *      beat the current page (a dropdown left open stayed visibly open on
 *      totally unrelated pages).
 *   2. "cookie only when no section matches the current route" still let a
 *      section unexpectedly collapse/expand depending on which route
 *      pattern happened to match, and depended on every section having a
 *      complete route pattern (an incomplete one, like the original
 *      `finance` pattern missing the Z-Reads/audit routes, silently fell
 *      through to the cookie in a confusing way).
 * Removed entirely: x-cloak (see AgentChatHistoryUiTest era fix) already
 * solves the visible-flash problem the cookie was introduced to prevent,
 * so pure route-derived state can't ever contradict the page you're on.
 */
class SidebarMenuStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_inventory_page_opens_only_the_inventory_section(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('inventory.categories.index'));

        $response->assertOk();
        $response->assertSee('menus: {"inventory":true,"network":false,"finance":false,"settings":false,"system":false}', false);
    }

    public function test_admin_network_page_opens_only_the_network_section(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('network.sessions'));

        $response->assertOk();
        $response->assertSee('menus: {"inventory":false,"network":true,"finance":false,"settings":false,"system":false}', false);
    }

    /**
     * Regression: the original `finance` pattern (`request()->is('sales*')`
     * only) didn't cover the Z-Reads/end-of-day-audit routes, so this page
     * showed Finance closed despite clearly belonging to that section.
     */
    public function test_admin_zreads_page_opens_the_finance_section(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($admin)->get(route('admin.finance.z-reads'));

        $response->assertOk();
        $response->assertSee('menus: {"inventory":false,"network":false,"finance":true,"settings":false,"system":false}', false);
    }

    public function test_admin_dashboard_opens_no_section_regardless_of_any_cookie(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($admin)
            ->withCookie('lk_admin_menus', json_encode(['inventory' => true, 'network' => false, 'finance' => false, 'settings' => false, 'system' => false]))
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('menus: {"inventory":false,"network":false,"finance":false,"settings":false,"system":false}', false);
    }

    public function test_staff_network_page_opens_only_the_network_section(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($staff)->get(route('network.vouchers.index'));

        $response->assertOk();
        $response->assertSee('menus: {"inventory":false,"network":true}', false);
    }

    public function test_submenu_dropdowns_have_x_cloak_to_prevent_a_flash_before_alpine_hydrates(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        foreach (['menus.inventory', 'menus.network', 'menus.settings', 'menus.system'] as $expr) {
            $response->assertSee("x-show=\"{$expr} && sidebarOpen\" x-cloak", false);
        }
    }
}
