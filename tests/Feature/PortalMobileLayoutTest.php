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

    public function test_the_status_page_uses_the_shared_phone_sizing(): void
    {
        $this->liveVoucher();
        $this->mockLiveSession();

        $this->withServerVariables(['REMOTE_ADDR' => self::IP])
            ->get(route('portal.index'))
            ->assertSee(self::PHONE_SIZING, false);
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
}
