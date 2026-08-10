<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shell has to survive a phone.
 *
 * The report was that every screen looked "cluttered and crowded" on a phone,
 * and the cause was structural rather than cosmetic: the sidebar was a flex
 * sibling of the page, so it took 256px (or 80px collapsed) off a ~390px
 * viewport before any content was laid out at all. Below lg it is now an
 * off-canvas drawer, which is a layout contract worth pinning — a stray
 * `flex-none` or a lost `fixed` puts the column straight back.
 *
 * These assertions are on rendered HTML. Real interaction (the slide, the
 * backdrop tap, the rotate-to-landscape reset) has no browser tooling here and
 * was reasoned through rather than clicked.
 */
class MobileLayoutTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string}> role => [role, route] */
    public static function shells(): array
    {
        return [
            'admin' => ['admin', '/dashboard'],
            'staff' => ['staff', '/staff-dashboard'],
        ];
    }

    /**
     * `fixed` is the whole point: it takes the sidebar out of the flex flow so
     * main gets the full width. `lg:static` is what hands the column back on a
     * desktop.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('shells')]
    public function test_the_sidebar_is_an_off_canvas_drawer_below_lg(string $role, string $url): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => $role]))
            ->get($url)->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<aside\b[^>]*\bfixed\b/', $html);
        $this->assertMatchesRegularExpression('/<aside\b[^>]*\blg:static\b/', $html);

        // `fixed` only holds if nothing later in the same declaration re-positions
        // the element. It carried a `relative` for a while, which beat it on source
        // order and quietly put the column back into the flow.
        preg_match('/<aside\s+class="([^"]*)"/', $html, $m);
        $this->assertNotContains('relative', preg_split('/\s+/', trim($m[1])),
            '`relative` on the sidebar overrides `fixed` — the drawer goes back into the flex flow.');

        // Off-screen until asked for, and never off-screen on a desktop.
        // Object syntax rather than the array form: see the note on the binding.
        $this->assertStringContainsString("'translate-x-0': mobileNavOpen", $html);
        $this->assertStringContainsString("'-translate-x-full': ! mobileNavOpen", $html);
        $this->assertMatchesRegularExpression('/<aside\b[^>]*\blg:translate-x-0\b/', $html);

        // A backdrop to tap, and a way out that isn't hunting for it.
        $this->assertStringContainsString('bg-black/60 z-40 lg:hidden', $html);
        $this->assertStringContainsString('@keydown.escape.window="mobileNavOpen = false"', $html);
    }

    /**
     * The drawer must not inherit the desktop "collapsed to icons" cookie —
     * there is room for the labels in a 288px drawer, and a menu of unlabelled
     * icons is the opposite of the fix.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('shells')]
    public function test_nav_labels_ignore_the_collapsed_cookie_below_lg(string $role, string $url): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => $role]))
            ->withUnencryptedCookie('lk_sidebar_open', '0')
            ->get($url)->assertOk()->getContent();

        $this->assertStringContainsString('return this.isDesktop ? this.sidebarOpen : true;', $html);
        // The label bindings read the getter, never the raw cookie state.
        $this->assertStringContainsString('x-show="navLabelsVisible"', $html);
    }

    /**
     * One button per job. A single toggle that tried to serve both would have
     * to guess the viewport in JS; two buttons let CSS decide.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('shells')]
    public function test_the_menu_button_opens_the_drawer_below_lg_and_collapses_the_column_above(string $role, string $url): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => $role]))
            ->get($url)->assertOk()->getContent();

        // A toggle, not a one-way open: tapping the same button again is the first
        // thing anyone tries to close a drawer with.
        $this->assertMatchesRegularExpression('/<button\b[^>]*@click="mobileNavOpen = ! mobileNavOpen"[^>]*\blg:hidden\b/', $html);
        // Plus an explicit X inside the drawer — backdrop-tap is not discoverable.
        $this->assertMatchesRegularExpression('/<button\b[^>]*@click="mobileNavOpen = false"[^>]*aria-label="Close menu"/', $html);
        $this->assertMatchesRegularExpression('/<button\b[^>]*@click="sidebarOpen = !sidebarOpen"[^>]*\bhidden lg:flex\b/', $html);
    }

    /**
     * 32px of padding on each side of a 390px screen spends a sixth of the
     * width on nothing.
     *
     * Written per-axis rather than as the `p-4 sm:p-6 lg:p-8` shorthand it used
     * to be, because the bottom now has a separate job: below lg the floating
     * chat button parks over the bottom-right of this scroller, and the last row
     * of every page sat underneath it. Expressed as a shorthand, `sm:p-6` and
     * `lg:p-8` are emitted after the bottom-padding rule and would wipe it out.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('shells')]
    public function test_main_padding_steps_up_with_the_viewport(string $role, string $url): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => $role]))
            ->get($url)->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<main\b[^>]*\bpx-4 pt-4 sm:px-6 sm:pt-6 lg:px-8 lg:pt-8\b/s', $html);
        // Nothing may set padding on all four sides at once here — see above.
        $this->assertDoesNotMatchRegularExpression('/<main\b[^>]*\bp-4 sm:p-6 lg:p-8\b/', $html);
    }

    /**
     * Room for the last row to scroll clear of the floating chat button, and for
     * the iOS home indicator underneath it now that the layout opts into
     * viewport-fit=cover.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('shells')]
    public function test_main_reserves_space_for_the_floating_chat_button(string $role, string $url): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => $role]))
            ->get($url)->assertOk()->getContent();

        $this->assertStringContainsString('pb-[calc(6.5rem+env(safe-area-inset-bottom))]', $html);
        // From lg the button has a wide margin of its own and POS locks the
        // viewport height, so the reserve is handed back.
        $this->assertMatchesRegularExpression('/<main\b[^>]*\blg:pb-8\b/s', $html);
        $this->assertStringContainsString('content="width=device-width, initial-scale=1, viewport-fit=cover"', $html);
    }

    /**
     * 100vh on a phone is the viewport *without* the browser chrome, so the
     * bottom of the app hides under the URL bar. h-screen stays as the
     * fallback for anything that has not heard of dvh.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('shells')]
    public function test_the_shell_is_sized_in_dynamic_viewport_height(string $role, string $url): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => $role]))
            ->get($url)->assertOk()->getContent();

        $this->assertStringContainsString('h-screen supports-[height:100dvh]:h-[100dvh]', $html);
    }

    /**
     * Full-bleed page wrappers cancel main's padding with a negative margin.
     * If the two stop matching at any breakpoint the page hangs over the edge
     * of its own scroll container and gets clipped, so they move together.
     */
    public function test_page_wrappers_cancel_exactly_the_padding_main_applies(): void
    {
        $offenders = [];

        $views = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($views as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $contents = file_get_contents($file->getPathname());

            // Only the padding-cancelling sizes. Small negative margins like
            // the `-m-1 p-1` hit-area nudge on a KDS button are local and
            // self-cancelling, and have nothing to do with the page shell.
            if (preg_match_all('/-m-[468]\b/', $contents, $m)) {
                if (! str_contains($contents, '-m-4 sm:-m-6 lg:-m-8')) {
                    $offenders[] = $file->getFilename().': '.implode(',', array_unique($m[0]));
                }
            }
        }

        $this->assertSame([], $offenders, 'Page wrappers must use -m-4 sm:-m-6 lg:-m-8 to match main.');
    }
}
