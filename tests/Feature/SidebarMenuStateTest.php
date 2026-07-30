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
}
