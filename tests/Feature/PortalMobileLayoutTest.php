<?php

namespace Tests\Feature;

use App\Models\Voucher;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three guest-portal pages are one flow, so a phone must not see the card
 * change shape as it moves through them. They each used to carry their own
 * numbers — 90%/360px/85dvh on the entry card against 96%/672px/92dvh on the
 * other two — so the card visibly jumped wider and roughly 180px taller the
 * instant a code was accepted, mid-flow, on the one screen a guest is already
 * unsure about.
 */
class PortalMobileLayoutTest extends TestCase
{
    use RefreshDatabase;

    /** The shared phone contract. Any page that drifts off it breaks the flow. */
    private const PHONE_SIZING = 'w-[92%] max-w-[420px] h-[88dvh] max-h-[640px]';

    private const IP = '192.168.2.50';

    private const MAC = 'AA:BB:CC:DD:EE:FF';

    private function liveVoucher(): Voucher
    {
        return Voucher::create([
            'code' => 'LAWA-LAYOUT',
            'duration_minutes' => 120,
            'tier' => 'free',
            'is_used' => true,
            'used_at' => now(),
            'activated_at' => now(),
            'ip_address' => self::IP,
            'mac_address' => self::MAC,
        ]);
    }

    private function mockLiveSession(): void
    {
        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('resolveMacForIp')->andReturn(self::MAC);
            $mock->shouldReceive('listSessions')->andReturn([[
                'sessionId' => 'sess-1',
                'ipAddress' => self::IP.'/32',
                'macAddress' => self::MAC,
                'startTime' => now()->subMinutes(5)->timestamp,
                'userName' => 'LAWA-LAYOUT',
            ]]);
        });
    }

    public function test_the_entry_page_uses_the_shared_phone_sizing(): void
    {
        $this->get(route('portal.index'))->assertSee(self::PHONE_SIZING, false);
    }

    public function test_the_success_page_uses_the_shared_phone_sizing(): void
    {
        $this->liveVoucher();
        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('resolveMacForIp')->andReturn(self::MAC);
            $mock->shouldReceive('listSessions')->andReturn([]);
        });

        $this->withServerVariables(['REMOTE_ADDR' => self::IP])
            ->get(route('portal.success'))
            ->assertSee(self::PHONE_SIZING, false);
    }

    /**
     * The status page is the exception, and deliberately so: it is a working
     * screen a guest sits on, not a hand-off card, so on a phone it fills the
     * viewport instead of floating in the middle of one. It adopts the shared
     * card sizing from sm up, which is where the other two pages start.
     */
    public function test_the_status_page_is_full_bleed_on_phones_and_a_card_above_sm(): void
    {
        $this->liveVoucher();
        $this->mockLiveSession();

        $content = $this->withServerVariables(['REMOTE_ADDR' => self::IP])
            ->get(route('portal.index'))
            ->getContent();

        // Full-bleed base, no card chrome.
        $this->assertStringContainsString('w-full h-full rounded-none border-0 shadow-none', $content);
        // The same numbers the other two pages use, gated to sm and up.
        $this->assertStringContainsString('sm:w-[92%] sm:max-w-[420px] sm:h-[88dvh] sm:max-h-[640px]', $content);
        // 100dvh, so the collapsing Android URL bar cannot overflow the layout.
        $this->assertStringContainsString('h-[100dvh]', $content);
        $this->assertStringContainsString('viewport-fit=cover', $content);
    }

    /**
     * The countdown read "5:30" under a label saying "Minutes Left", so guests
     * took it for five and a half minutes, or for a clock time. The number and
     * its unit have to agree.
     */
    public function test_the_countdown_shows_whole_minutes_not_a_clock_reading(): void
    {
        $this->liveVoucher();
        $this->mockLiveSession();

        $content = $this->withServerVariables(['REMOTE_ADDR' => self::IP])
            ->get(route('portal.index'))
            ->getContent();

        // The m:ss template is what produced "5:30".
        $this->assertStringNotContainsString("String(seconds).padStart(2, '0')", $content);
        $this->assertStringContainsString('this.remainingLabel = `${minutes}`;', $content);
        // Singular/plural must track the value, or it reads "1 Minutes Left".
        $this->assertStringContainsString("minutes === 1 ? 'Minute Left' : 'Minutes Left'", $content);
        // The last minute still counts in seconds, so the display keeps moving.
        $this->assertStringContainsString('this.remainingLabel = `${seconds}`;', $content);
    }

    /**
     * The chat history would not scroll at all.
     *
     * A flex item defaults to min-height:auto and refuses to shrink below its
     * content, so every ancestor between the fixed-height portal card and the
     * overflow-y-auto history has to carry min-h-0 — otherwise the scroll box is
     * never given a height smaller than the conversation and has nothing to
     * scroll; the messages just push the column taller. The nearest ancestor
     * already had it, which is why this looked correct in review.
     */
    public function test_every_ancestor_of_the_chat_scroll_area_can_shrink(): void
    {
        $this->liveVoucher();
        $this->mockLiveSession();

        $content = $this->withServerVariables(['REMOTE_ADDR' => self::IP])
            ->get(route('portal.index'))
            ->getContent();

        // The AI tab itself. Asserted without the padding that sits beside it,
        // which is breakpoint-specific and not what this test is about.
        $this->assertStringContainsString('flex flex-col h-full min-h-0', $content);
        // The chat card. A fixed 300px floor was the same bug in another form.
        $this->assertStringContainsString('flex-1 min-h-0 bg-white border-0 sm:border-2', $content);
        $this->assertStringNotContainsString('min-h-[300px]', $content);
        // The scroll area, with a visible scrollbar — hiding it left no cue that
        // there was anything above, which is how this was reported.
        $this->assertStringContainsString('overflow-y-auto overscroll-contain', $content);
        $this->assertDoesNotMatchRegularExpression(
            '/id="portal-chat-history"[^>]*no-scrollbar/',
            $content
        );
    }

    /**
     * The "AI Agent Active" badge was absolutely positioned inside the chat
     * container and reserved no space, so the oldest message in the scroll area
     * sat underneath it — and it got worse as the conversation grew, because
     * the history scrolls beneath a fixed overlay.
     */
    public function test_the_ai_agent_badge_does_not_float_over_the_chat_history(): void
    {
        $this->liveVoucher();
        $this->mockLiveSession();

        $content = $this->withServerVariables(['REMOTE_ADDR' => self::IP])
            ->get(route('portal.index'))
            ->getContent();

        $this->assertStringContainsString('AI Agent Active', $content);
        $this->assertStringNotContainsString('absolute top-5 left-8', $content);
        // In flow, and not allowed to be squashed by the chat area beside it.
        $this->assertStringContainsString('shrink-0 relative z-10 mb-4 flex', $content);
    }

    /**
     * The escape hatch for a guest who never copied the address: a tap that
     * asks the OS to open the status page in their own browser.
     *
     * Shown only inside the sign-in window — in a real browser the guest is
     * already where it would send them — and gated on a wrapper rather than the
     * anchor, because the partial's .cna-only rule is display:block and would
     * otherwise flatten the button's inline-flex.
     */
    public function test_the_status_page_offers_an_open_in_browser_button_inside_the_sign_in_window(): void
    {
        $this->liveVoucher();
        $this->mockLiveSession();

        $content = $this->withServerVariables(['REMOTE_ADDR' => self::IP])
            ->get(route('portal.index'))
            ->getContent();

        $this->assertStringContainsString('Open in Browser', $content);
        $this->assertStringContainsString(route('portal.handoff'), $content);
        // The detection + .cna-only rule the button depends on.
        $this->assertStringContainsString('isCaptiveAssistant', $content);
        $this->assertStringContainsString('cna-only ml-auto shrink-0', $content);
    }
}
