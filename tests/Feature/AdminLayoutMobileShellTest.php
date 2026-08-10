<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Two CSS-ordering defects in the shared admin/staff shell, both of which look
 * like layout bugs and neither of which is visible by reading the Blade alone.
 *
 * 1. The <aside> carried `fixed` AND `relative`. Tailwind emits the position
 *    utilities in a fixed order — .static, .fixed, .absolute, .relative, .sticky
 *    — all at the same specificity, so the later rule wins and the element
 *    resolved to `position: relative`. Below lg that left the drawer in the flex
 *    flow: it took its full 18rem out of a 360px phone and then translated
 *    itself off-screen, leaving an empty ~270px gutter with every page crushed
 *    into what was left and overflowing.
 *
 * 2. The width binding used Alpine's ARRAY class syntax while `lg:w-64` was also
 *    server-rendered into the static class attribute. Alpine's array/string form
 *    only removes classes it added itself, so collapsing added `lg:w-20` without
 *    ever removing `lg:w-64` — and .lg\:w-64 is emitted after .lg\:w-20, so the
 *    sidebar stayed full width no matter how often you clicked the toggle.
 *
 * Both are the sort of thing that reads as correct in review, so they are pinned
 * against the rendered markup rather than trusted to stay fixed.
 */
class AdminLayoutMobileShellTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('network_pulse_initial');
        Cache::forget('system_health');

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('getArpTable')->andReturn([]);
            $mock->shouldReceive('getInterfaceStats')->andReturn([]);
            $mock->shouldReceive('getGatewayStatus')->andReturn(['gateways' => []]);
            $mock->shouldReceive('listSessions')->andReturn([]);
            $mock->shouldReceive('getDhcpLeases')->andReturn([]);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
        });
    }

    private function renderDashboard(string $role = 'super_admin'): string
    {
        return $this->actingAs(User::factory()->create(['role' => $role]))
            ->get('/dashboard')
            ->assertOk()
            ->getContent();
    }

    /** Pull the static class attribute off the sidebar element. */
    private function asideClasses(string $html): string
    {
        $this->assertMatchesRegularExpression('/<aside\s+class="/', $html, 'No <aside> with a class attribute.');
        preg_match('/<aside\s+class="([^"]*)"/', $html, $m);

        return preg_replace('/\s+/', ' ', $m[1]);
    }

    public function test_sidebar_is_not_also_position_relative(): void
    {
        $classes = $this->asideClasses($this->renderDashboard());

        $this->assertStringContainsString('fixed', $classes);

        // Word-boundary match: `lg:relative` or `relative` as a substring of
        // another utility would be fine, a bare `relative` would not.
        $this->assertNotContains(
            'relative',
            explode(' ', $classes),
            'The sidebar carries both `fixed` and `relative`; `relative` wins on source order, '.
            'putting the mobile drawer back into the flex flow and offsetting every page.'
        );
    }

    public function test_sidebar_falls_back_to_static_only_from_lg(): void
    {
        $classes = $this->asideClasses($this->renderDashboard());

        // The drawer must be out of flow below lg and a column again from lg.
        $this->assertStringContainsString('lg:static', $classes);
        $this->assertStringContainsString('-translate-x-full', $this->renderDashboard());
    }

    public function test_collapse_binding_uses_object_syntax_so_the_seeded_width_is_removable(): void
    {
        $html = $this->renderDashboard();

        // Object syntax removes falsy keys outright, server-rendered or not. The
        // array form it replaced could only remove what it had added itself.
        $this->assertStringContainsString("'lg:w-64': sidebarOpen", $html);
        $this->assertStringContainsString("'lg:w-20': ! sidebarOpen", $html);
        $this->assertStringNotContainsString(
            ":class=\"[mobileNavOpen",
            $html,
            'Back on the array class binding — the collapse toggle will silently stop changing the width.'
        );
    }

    public function test_collapsed_cookie_seeds_the_narrow_width_not_the_wide_one(): void
    {
        $classes = $this->asideClasses(
            $this->actingAs(User::factory()->create(['role' => 'super_admin']))
                ->withUnencryptedCookie('lk_sidebar_open', '0')
                ->get('/dashboard')
                ->assertOk()
                ->getContent()
        );

        $this->assertStringContainsString('lg:w-20', $classes);
        $this->assertStringNotContainsString('lg:w-64', $classes);
    }

    public function test_expanded_is_still_the_default(): void
    {
        $classes = $this->asideClasses($this->renderDashboard());

        $this->assertStringContainsString('lg:w-64', $classes);
        $this->assertStringNotContainsString('lg:w-20', $classes);
    }

    public function test_mobile_drawer_state_is_never_persisted(): void
    {
        $html = $this->renderDashboard();

        // A drawer that reopens itself on every page load is a drawer in the way.
        $this->assertStringContainsString('mobileNavOpen: false', $html);
        $this->assertStringNotContainsString("\$persist(", $html);
    }

    public function test_viewport_meta_opts_into_the_safe_area(): void
    {
        $this->assertStringContainsString(
            '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">',
            $this->renderDashboard()
        );
    }

    public function test_main_reserves_room_below_the_floating_chat_button(): void
    {
        $html = $this->renderDashboard();

        // Written per-axis: as a `p-4 sm:p-6 lg:p-8` shorthand the sm/lg rules
        // would land after the bottom-padding rule and wipe it out again.
        $this->assertStringContainsString('pb-[calc(6.5rem+env(safe-area-inset-bottom))]', $html);
        $this->assertStringNotContainsString('overflow-y-auto p-4 sm:p-6 lg:p-8', $html);
    }

    public function test_content_column_can_shrink_below_its_min_content_width(): void
    {
        $this->assertStringContainsString('flex-1 min-w-0 flex flex-col overflow-hidden', $this->renderDashboard());
    }

    public function test_stat_tiles_stack_on_a_phone_and_keep_their_values_on_one_line(): void
    {
        $html = $this->renderDashboard();

        $this->assertStringContainsString('grid-cols-1 sm:grid-cols-2 lg:grid-cols-4', $html);

        // "3 / 3" contains spaces and broke at them in a narrow tile, rendering
        // one glyph per line.
        $this->assertMatchesRegularExpression(
            '/text-xl sm:text-2xl md:text-3xl font-black tracking-tighter whitespace-nowrap/',
            $html
        );
    }
}
