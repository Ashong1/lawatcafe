<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Submenu open/closed state is sticky: once a section is opened it stays
 * open across navigation to any page, until manually closed (explicit user
 * request, 2026-07-30). Two earlier route-derived designs (stale-cookie-wins
 * array_merge, then "cookie only when no section matches", then pure
 * route-based with no stickiness at all) were each tried and rejected before
 * landing back on stickiness — see memory
 * project_sidebar_submenu_final_design_2026-07-30.md for the full history.
 *
 * Route defaults ($routeDefaults) only seed the very first visit, before any
 * lk_admin_menus/lk_staff_menus cookie has ever been written. Once that
 * cookie exists, it wins wholesale for every key it contains — the current
 * route never overrides a manually-set value.
 *
 * These cookies are set by plain client-side JS (document.cookie), not
 * Laravel's Cookie facade, so they're excluded from EncryptCookies in
 * bootstrap/app.php. Tests must use withUnencryptedCookie() (not
 * withCookie(), which auto-encrypts) to match what a real browser sends —
 * using withCookie() here made every "sticky cookie" test pass while the
 * feature was actually completely non-functional in production, since
 * EncryptCookies silently nulls any cookie it can't decrypt.
 *
 * In admin.blade.php specifically, the server-computed state is exposed as
 * `initialMenus` (not `menus`) in the Alpine data object — `menus` itself
 * starts as {} and only gets set to `initialMenus` ~50ms after mount, so
 * Alpine's x-transition treats it as a real state change and the submenu
 * panel visibly animates open every time a section carries over open to a
 * new page, instead of rendering already-open with no animation (explicit
 * user request, 2026-07-30). staff.blade.php has no visual dropdowns, so its
 * `menus` key is untouched and still holds the value directly.
 */
class SidebarMenuStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_inventory_page_opens_only_the_inventory_section_on_first_visit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('inventory.categories.index'));

        $response->assertOk();
        $response->assertSee('initialMenus: {"inventory":true,"network":false,"finance":false,"settings":false,"system":false}', false);
    }

    public function test_admin_network_page_opens_only_the_network_section_on_first_visit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('network.sessions'));

        $response->assertOk();
        $response->assertSee('initialMenus: {"inventory":false,"network":true,"finance":false,"settings":false,"system":false}', false);
    }

    /**
     * Regression: the original `finance` pattern (`request()->is('sales*')`
     * only) didn't cover the Z-Reads/end-of-day-audit routes, so this page
     * showed Finance closed despite clearly belonging to that section.
     */
    public function test_admin_zreads_page_opens_the_finance_section_on_first_visit(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($admin)->get(route('admin.finance.z-reads'));

        $response->assertOk();
        $response->assertSee('initialMenus: {"inventory":false,"network":false,"finance":true,"settings":false,"system":false}', false);
    }

    public function test_admin_section_opened_via_cookie_stays_open_on_an_unrelated_page(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($admin)
            ->withUnencryptedCookie('lk_admin_menus', json_encode(['inventory' => true, 'network' => false, 'finance' => false, 'settings' => false, 'system' => false]))
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('initialMenus: {"inventory":true,"network":false,"finance":false,"settings":false,"system":false}', false);
    }

    public function test_admin_section_closed_via_cookie_stays_closed_even_on_its_own_page(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($admin)
            ->withUnencryptedCookie('lk_admin_menus', json_encode(['inventory' => false, 'network' => false, 'finance' => false, 'settings' => false, 'system' => false]))
            ->get(route('inventory.categories.index'));

        $response->assertOk();
        $response->assertSee('initialMenus: {"inventory":false,"network":false,"finance":false,"settings":false,"system":false}', false);
    }

    public function test_staff_network_page_opens_only_the_network_section_on_first_visit(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($staff)->get(route('network.vouchers.index'));

        $response->assertOk();
        $response->assertSee('menus: {"inventory":false,"network":true}', false);
    }

    public function test_staff_section_opened_via_cookie_stays_open_on_an_unrelated_page(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($staff)
            ->withUnencryptedCookie('lk_staff_menus', json_encode(['inventory' => true, 'network' => false]))
            ->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertSee('menus: {"inventory":true,"network":false}', false);
    }

    /**
     * Submenu panels animate open/closed via a CSS grid-template-rows
     * transition (0fr <-> 1fr), not x-show/x-cloak — this avoids the classic
     * Alpine quirk where x-show keeps an element's layout space reserved for
     * the entire leave transition and only collapses it in one abrupt jump
     * at the very end, making "closing" feel like it drags on longer than
     * "opening" even at an identical duration (explicit user request,
     * 2026-07-30: sync the perceived speed of closing to opening).
     *
     * The static `grid-template-rows: 0fr` inline style is what prevents any
     * flash before Alpine hydrates (replacing x-cloak's old role here) —
     * every submenu genuinely starts collapsed regardless of JS timing.
     */
    public function test_submenu_dropdowns_start_collapsed_via_grid_rows_to_prevent_a_flash_before_alpine_hydrates(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        foreach (['inventory', 'network', 'settings', 'system'] as $section) {
            // navLabelsVisible, not sidebarOpen: from lg it IS sidebarOpen, but
            // below lg the sidebar is a drawer whose labels are always shown, so
            // a submenu must expand there even with the desktop cookie collapsed.
            $response->assertSee("x-bind:style=\"(menus.{$section} && navLabelsVisible) ? 'grid-template-rows: 1fr' : 'grid-template-rows: 0fr'\"", false);
        }
    }
}
