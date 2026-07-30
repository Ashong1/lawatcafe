<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: `array_merge($routeDefaults, $cookieMenus)` let a stale
 * cookie value win even when it contradicted the current page — e.g. the
 * Inventory dropdown, once opened, stayed rendered as open while browsing
 * completely unrelated pages like Network, since the cookie's stale `true`
 * always overrode the fresh route-computed `false`. Reported as the sidebar
 * dropdown "opening on every page" (2026-07-30).
 *
 * Fix: when the current page belongs to a section, that section's own
 * route-computed state is authoritative for every key (no blending), so
 * only the current section is ever open. The cookie only applies as a
 * sticky fallback on pages that don't belong to any section at all.
 */
class SidebarMenuStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_network_page_does_not_show_inventory_stuck_open_from_a_stale_cookie(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staleCookie = json_encode(['inventory' => true, 'network' => false, 'finance' => false, 'settings' => false, 'system' => false]);

        $response = $this->actingAs($admin)
            ->withCookie('lk_admin_menus', $staleCookie)
            ->get(route('network.sessions'));

        $response->assertOk();
        $response->assertSee('menus: {"inventory":false,"network":true,"finance":false,"settings":false,"system":false}', false);
    }

    public function test_admin_dashboard_falls_back_to_the_sticky_cookie_since_it_has_no_section_of_its_own(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $cookie = json_encode(['inventory' => true, 'network' => false, 'finance' => false, 'settings' => false, 'system' => false]);

        $response = $this->actingAs($admin)
            ->withCookie('lk_admin_menus', $cookie)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('menus: {"inventory":true,"network":false,"finance":false,"settings":false,"system":false}', false);
    }

    public function test_staff_network_page_does_not_show_inventory_stuck_open_from_a_stale_cookie(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $staleCookie = json_encode(['inventory' => true, 'network' => false]);

        $response = $this->actingAs($staff)
            ->withCookie('lk_staff_menus', $staleCookie)
            ->get(route('network.vouchers.index'));

        $response->assertOk();
        $response->assertSee('menus: {"inventory":false,"network":true}', false);
    }

    /**
     * Separate bug from the stale-cookie one above: these submenu
     * <div x-show="menus.X && sidebarOpen"> blocks had no x-cloak and no
     * static display:none fallback, so on every page load the browser
     * painted them in their default *visible* state before Alpine.js (a
     * deferred module script) finished hydrating and applied the correct
     * hidden/shown state — a visible "flash open, then closes" on every
     * closed section, on every navigation, independent of whether the
     * final logical state was correct. x-cloak (already defined in
     * app.css as `[x-cloak] { display: none !important; }`, applied via a
     * synchronously-loaded stylesheet) hides them from first paint until
     * Alpine settles, eliminating the flash.
     */
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
