<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: closing the floating Barista AI widget looked broken.
 *
 * Two independent causes, both layout rather than timing:
 *
 * 1. The chat panel sat in flow above the toggle button, so the button's screen
 *    position depended on whether the panel was open. toggle() compensated by
 *    shifting posY by exactly the panel's height — but posY animates (the root
 *    carries transition: all 0.3s) while x-show's display:none lands instantly
 *    at the end of the leave transition. On close the button swooped a full
 *    panel-height down over 300ms and then snapped back up.
 *
 * 2. The bot and chevron icons were in-flow flex siblings, so during the
 *    cross-fade the button briefly held both and shoved them apart sideways.
 *
 * These assertions are structural because the bug was structural — there is no
 * browser in this suite, and both fixes are "this element must not participate
 * in layout", which is exactly what a rendered-markup assertion can pin.
 */
class AgentChatCloseAnimationTest extends TestCase
{
    use RefreshDatabase;

    private function widgetMarkup(): string
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get(route('dashboard'));
        $response->assertOk();

        return $response->getContent();
    }

    public function test_the_chat_panel_is_out_of_flow_above_the_toggle_button(): void
    {
        $markup = $this->widgetMarkup();

        // bottom-full anchors it above the button; absolute keeps it from
        // contributing any height between the widget's top and that button.
        $this->assertStringContainsString('absolute bottom-full left-0 mb-4 w-full', $markup);
    }

    public function test_both_toggle_icons_are_stacked_rather_than_flex_siblings(): void
    {
        $markup = $this->widgetMarkup();

        // Matched via each icon's own rotate direction, because the bare
        // utility class is common enough to appear elsewhere on the page. If
        // either icon loses its absolute positioning the two reflow side by
        // side again during the cross-fade.
        foreach (['opacity-0 rotate-45 scale-75', 'opacity-0 -rotate-45 scale-75'] as $leaveEnd) {
            $this->assertMatchesRegularExpression(
                '/x-transition:leave-end="'.preg_quote($leaveEnd, '/').'"\s*\n\s*class="absolute inset-0 flex items-center justify-center"/',
                $markup,
                "The toggle icon leaving with \"{$leaveEnd}\" must be absolutely positioned, not an in-flow flex sibling."
            );
        }
    }

    /**
     * The compensation only existed to cancel out the in-flow panel's height.
     * With the panel out of flow it would move the button for no reason, which
     * is the swoop itself.
     */
    public function test_toggling_no_longer_shifts_the_widget_position(): void
    {
        $markup = $this->widgetMarkup();

        $this->assertStringNotContainsString('this.posY += this.open ? -offset : offset', $markup);
        $this->assertStringNotContainsString('posY += (chatHeight() + 16)', $markup);
        $this->assertStringNotContainsString('this.posY -= (this.chatHeight() + 16)', $markup);
    }

    /**
     * posY now means the toggle button's own top and the panel hangs above it,
     * so the open state constrains how close to the TOP the widget may sit —
     * the reverse of the old bound, which limited how close to the bottom.
     */
    public function test_clamping_reserves_room_above_the_button_while_open(): void
    {
        $markup = $this->widgetMarkup();

        $this->assertStringContainsString('const minY = this.open ? this.chatHeight() + 16 + 16 : 16;', $markup);
        $this->assertStringContainsString('Math.max(this.posY, minY)', $markup);
    }
}
