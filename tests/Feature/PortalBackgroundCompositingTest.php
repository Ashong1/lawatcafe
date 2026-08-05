<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The guest portal's background must not cost a blur every frame.
 *
 * Reported as older phones going sluggish and running hot on the menu and chat
 * screens — the pages rendered fine, then stopped responding. Every portal page
 * stacked a full-screen `backdrop-blur` scrim directly on top of a full-screen
 * photo running an infinite `transform: scale()` animation. A backdrop-filter
 * has to re-sample and re-blur everything beneath it on every frame, and what
 * was beneath it never stopped moving, so the compositor re-blurred the whole
 * viewport continuously for as long as the page was open. It hurt worst on the
 * two screens that also scroll.
 *
 * The blur now lives on the photo layer itself (`.portal-bg-photo`, a plain
 * `filter`), which is rasterised once and then just transformed. Same look,
 * without the per-frame recomputation.
 *
 * These assertions read the Blade sources rather than rendered output on
 * purpose: `x-modal-shell` legitimately uses a full-screen backdrop-blur, but
 * only while a modal is open (`x-show`, so `display:none` at rest), and it
 * renders into every portal page. Checking the sources keeps this guard aimed
 * at the always-on background stack and off the transient modal.
 */
class PortalBackgroundCompositingTest extends TestCase
{
    /** Portal views that paint the full-bleed background photo. */
    private const VIEWS = ['index', 'menu', 'status', 'success', 'unlock'];

    private function source(string $view): string
    {
        $path = resource_path("views/portal/{$view}.blade.php");

        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    public function test_every_portal_page_blurs_the_photo_layer_rather_than_a_backdrop(): void
    {
        foreach (self::VIEWS as $view) {
            $this->assertStringContainsString(
                'portal-bg-photo',
                $this->source($view),
                "portal/{$view} does not use the pre-blurred background photo layer."
            );
        }
    }

    /**
     * The exact class the old full-screen scrims carried. Its return would mean
     * a viewport-wide blur recomputed against a moving backdrop, which is the
     * whole bug.
     */
    public function test_no_portal_page_puts_a_backdrop_filter_over_the_moving_photo(): void
    {
        foreach (self::VIEWS as $view) {
            $this->assertStringNotContainsString(
                'backdrop-blur-[4px]',
                $this->source($view),
                "portal/{$view} reintroduced a full-screen backdrop blur over the animated background."
            );
        }
    }

    /**
     * A blur over a scrolling container is recomputed on every scroll frame.
     * These bars sat at 90% opacity, where the blur was doing next to nothing
     * visually and everything to the frame budget.
     */
    public function test_scrolling_nav_bars_are_opaque_rather_than_frosted(): void
    {
        foreach (['index', 'menu', 'status'] as $view) {
            $this->assertStringNotContainsString(
                'bg-white/90 backdrop-blur',
                $this->source($view),
                "portal/{$view} has a frosted bar over scrolling content."
            );
        }
    }

    /**
     * The blurred photo is scaled up so the filter's soft edge cannot expose
     * the page behind it. If the keyframes ever drop back to scale(1) — or the
     * resting transform is removed — a pale border appears around the whole
     * screen, and only on the frames where the pan is at its narrowest.
     */
    public function test_the_blurred_photo_always_overfills_the_viewport(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        preg_match('/\.portal-bg-photo\s*\{(.*?)\}/s', $css, $rule);
        $this->assertNotEmpty($rule, '.portal-bg-photo is missing.');
        $this->assertStringContainsString('filter: blur(', $rule[1]);
        $this->assertMatchesRegularExpression('/transform:\s*scale\(1\.0[6-9]|transform:\s*scale\(1\.[1-9]/', $rule[1]);

        preg_match('/@keyframes portal-bg-pan\s*\{(.*?)\}\s*\}/s', $css, $frames);
        $this->assertNotEmpty($frames, 'portal-bg-pan keyframes are missing.');
        $this->assertStringNotContainsString('scale(1)', $frames[1]);
    }

    /**
     * The admin/staff login page shares the pan but has no scrim over it, so it
     * must keep the unblurred .ambient-pan. Folding the blur into that shared
     * class would have quietly blurred a page nobody complained about.
     */
    public function test_the_login_page_keeps_its_unblurred_background(): void
    {
        $guest = file_get_contents(resource_path('views/layouts/guest.blade.php'));

        $this->assertStringContainsString('ambient-pan', $guest);
        $this->assertStringNotContainsString('portal-bg-photo', $guest);
    }
}
