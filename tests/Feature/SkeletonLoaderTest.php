<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Placeholders for content that has not arrived.
 *
 * Two rules hold this together, and both are easy to break by accident:
 *
 *   1. A skeleton belongs only where a region is genuinely empty until a fetch
 *      resolves. Where the server already rendered a value, covering it with a
 *      grey bar would be a downgrade.
 *   2. A "nothing here" message must never be shown before the app has asked.
 *      Both header dropdowns used to announce "No notifications" / "Nothing
 *      pending" the instant they opened, then quietly fill in — a reassurance
 *      the app had no basis for yet.
 */
class SkeletonLoaderTest extends TestCase
{
    use RefreshDatabase;

    private function adminPage(): string
    {
        return $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/dashboard')->assertOk()->getContent();
    }

    public function test_the_empty_state_waits_for_the_fetch_before_claiming_nothing_is_there(): void
    {
        $html = $this->adminPage();

        // Both panels gate their empty state on the fetch having completed.
        $this->assertStringContainsString('loaded && notifications.length === 0', $html);
        $this->assertStringContainsString('loaded && items.length === 0', $html);

        // And neither renders a stale/empty list underneath the skeleton.
        $this->assertStringContainsString('notif in loaded ? notifications : []', $html);
        $this->assertStringContainsString('item in loaded ? items : []', $html);
    }

    /**
     * A dropped request has to clear the flag too. Setting it inside the try
     * would leave the panel shimmering forever on a flaky café network.
     */
    public function test_a_failed_fetch_still_ends_the_loading_state(): void
    {
        $html = $this->adminPage();

        $this->assertMatchesRegularExpression(
            '/catch \(error\) \{[^}]*\}\s*finally \{[^}]*this\.loaded = true;/s',
            $html
        );
    }

    /**
     * A throughput figure needs two counter samples. Printing 0.00 Mbps in the
     * meantime reported a measurement that had not been taken — on a busy
     * network it reads as an outage.
     */
    public function test_throughput_shows_a_placeholder_rather_than_a_zero_it_has_not_measured(): void
    {
        $html = $this->adminPage();

        $this->assertStringContainsString('hasRate: false', $html);
        $this->assertStringContainsString('x-show="!liveData.hasRate"', $html);
        $this->assertStringContainsString('x-show="liveData.hasRate"', $html);

        // The tiles no longer carry a server-rendered 0.00 as their fallback
        // text — the placeholder stands in until a rate exists.
        $this->assertStringNotContainsString('toFixed(2)">0.00', $html);
    }

    /** The insights modal is the slow one; its wait should have the shape of an answer. */
    public function test_the_ai_insights_modal_uses_a_skeleton_not_a_bare_spinner(): void
    {
        $html = $this->adminPage();

        $this->assertSame(1, preg_match(
            '/x-show="loadingInsights".*?(?=x-show="!loadingInsights)/s', $html, $m
        ), 'Expected a loadingInsights region on the dashboard.');

        $region = $m[0];
        $this->assertStringContainsString('lk-skeleton', $region);
        // The bouncing-dot spinner this replaced. Scoped to the region: the
        // chat widget still uses bouncing dots for "AI is typing", which is
        // the right pattern there — an indeterminate stream has no shape to
        // put a skeleton into.
        $this->assertStringNotContainsString('animate-bounce', $region);
    }

    /**
     * Server-rendered figures must NOT be hidden behind a placeholder — the
     * headline stats are in the HTML on first paint and have to stay there.
     */
    public function test_server_rendered_stats_are_not_replaced_by_skeletons(): void
    {
        $html = $this->adminPage();

        $this->assertStringContainsString('x-text="liveData.activeGuests"', $html);
        $this->assertStringNotContainsString('x-show="!liveData.hasGuests"', $html);
    }

    /**
     * The placeholder is decorative but the wait is not: a screen reader gets
     * one spoken "Loading…" per region rather than a silent gap.
     */
    public function test_skeletons_announce_the_wait_and_hide_the_decorative_bars(): void
    {
        $html = $this->adminPage();

        $this->assertStringContainsString('role="status" aria-live="polite"', $html);
        $this->assertStringContainsString('<span class="sr-only">Loading…</span>', $html);
        $this->assertMatchesRegularExpression('/aria-hidden="true"[^>]*>\s*<div class="lk-skeleton/s', $html);
    }

    /**
     * The component must not set its own width. `merge` concatenates rather
     * than resolving Tailwind conflicts, and `.w-full` is emitted after every
     * fixed width — a default there silently overrode every caller's `w-16`.
     */
    public function test_the_component_leaves_width_to_the_caller(): void
    {
        $component = file_get_contents(resource_path('views/components/skeleton.blade.php'));

        $this->assertStringNotContainsString("merge(['class' => 'w-full')", $component);
        $this->assertStringContainsString('<div {{ $attributes }} role="status"', $component);
    }
}
