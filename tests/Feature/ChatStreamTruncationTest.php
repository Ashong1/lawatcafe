<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Agent\ToolCallOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Replies that stopped mid-sentence, showed no sign of being unfinished, and
 * turned out complete after a browser refresh.
 *
 * The cause was a mismatch nobody had to introduce — it was there from the
 * start. The browser armed a flat 20-second abort when the request began and
 * never touched it again, while the server is allowed 60 seconds for a
 * conversation and each of up to five round trips gets its own ~18s provider
 * cascade. Any answer past 20 seconds of wall clock was killed by the client
 * *while it was still streaming correctly*; the server finished and persisted
 * the whole thing, which is why reloading produced the rest.
 *
 * The fix is a timeout that measures silence rather than duration, so these
 * assert the contract on both sides of the wire: the client rearms on data, and
 * the server sends something immediately so there is data to rearm on.
 */
class ChatStreamTruncationTest extends TestCase
{
    use RefreshDatabase;

    private function widgetSource(): string
    {
        return $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/dashboard')
            ->assertOk()
            ->getContent();
    }

    /**
     * The regression itself. A fixed abort armed once at request time cannot
     * tell a stalled stream from a long one.
     */
    public function test_the_client_no_longer_arms_a_flat_abort_for_the_whole_request(): void
    {
        $html = $this->widgetSource();

        $this->assertStringNotContainsString('controller.abort(), 20000', $html);
        // Rearmed from the read loop, on raw chunks rather than parsed events —
        // keep-alives and partial frames are evidence of life too.
        $this->assertStringContainsString('giveUpAfterSilence()', $html);
    }

    /** A dead socket must still end, or the widget hangs forever instead. */
    public function test_the_client_still_gives_up_on_a_silent_stream(): void
    {
        $html = $this->widgetSource();

        $this->assertMatchesRegularExpression('/IDLE_TIMEOUT_MS:\s*\d+/', $html);
        preg_match('/IDLE_TIMEOUT_MS:\s*(\d+)/', $html, $m);
        $idleMs = (int) $m[1];

        // Above the worst legitimate silence — one provider stalling its full
        // ~18s stream timeout, then the next in the cascade taking a few seconds
        // to its first token.
        $this->assertGreaterThan(22000, $idleMs, 'Idle window is inside the normal provider-cascade gap; slow replies will be cut off again.');

        // And still bounded well under the server's own conversation budget.
        $this->assertLessThan(60000, $idleMs);
    }

    /**
     * The hard stop is a backstop against a socket that dribbles forever, so it
     * must sit ABOVE the server's budget — below it, it would just reintroduce
     * the original bug with a bigger number.
     */
    public function test_the_absolute_ceiling_outlives_the_servers_own_budget(): void
    {
        $html = $this->widgetSource();

        preg_match('/controller\.abort\(\), (\d+)\)/', $html, $m);
        $this->assertNotEmpty($m, 'No absolute ceiling found.');

        $budgetSeconds = (new \ReflectionClass(ToolCallOrchestrator::class))
            ->getConstant('DEFAULT_MAX_TOTAL_SECONDS');

        $this->assertGreaterThan(
            $budgetSeconds * 1000,
            (int) $m[1],
            'The browser gives up before the server does — the same truncation, one number larger.'
        );
    }

    /**
     * Nothing reaches the client between the request and the first token, so a
     * provider that stalls produces a completely silent connection. This makes
     * the first byte immediate.
     */
    public function test_the_stream_opens_with_an_immediate_event(): void
    {
        $responder = new \ReflectionClass(\App\Services\Agent\ChatStreamResponder::class);
        $source = file_get_contents($responder->getFileName());

        $this->assertStringContainsString("'type' => 'open'", $source);

        // Before the orchestrator is given control, or it is not immediate.
        $this->assertLessThan(
            strpos($source, '$this->orchestrator->run('),
            strpos($source, "'type' => 'open'"),
        );
    }

    /**
     * The new 'open' event has to be safe for a client that predates it — and
     * for the reverse, a cached page still running the old script. Every branch
     * in the handler is an explicit equality check on a known type, so anything
     * unrecognised falls through and is ignored rather than throwing.
     */
    public function test_every_event_branch_matches_an_explicit_known_type(): void
    {
        $html = $this->widgetSource();

        foreach (["'tool_start'", "'delta'", "'meta'"] as $known) {
            $this->assertStringContainsString('event.type === '.$known, $html);
        }

        // Exactly those three, and no catch-all branch that would try to render
        // 'open' — or any future event type — as if it were reply content.
        $this->assertSame(
            3,
            substr_count($html, "event.type === '"),
            'An extra event branch appeared; check it cannot treat an unknown type as content.'
        );
    }

    // ------------------------------------------------- the "is it alive" signal

    /**
     * The other half of the report: "it looks like it is hanging". `thinking`
     * goes false on the first token, and nothing replaced it, so a pause
     * part-way through an answer looked identical to a dead request.
     */
    public function test_a_streaming_reply_is_marked_as_still_being_written(): void
    {
        $html = $this->widgetSource();

        $this->assertStringContainsString("streaming: true", $html);
        $this->assertStringContainsString('x-show="msg.streaming"', $html);
    }

    public function test_the_caret_is_cleared_however_the_stream_ends(): void
    {
        $html = $this->widgetSource();

        // In the finally, so an abort and an error clear it as well as success.
        $this->assertStringContainsString('if (assistantEntry) assistantEntry.streaming = false;', $html);
    }

    /** A flag left in session storage would come back as a caret under a finished reply. */
    public function test_a_restored_conversation_never_comes_back_still_streaming(): void
    {
        $html = $this->widgetSource();

        $this->assertStringContainsString('m.streaming ? { ...m, streaming: false } : m', $html);
    }

    /**
     * Sending was gated on `thinking`, which clears at the first token — so a
     * second message could be sent while the first reply was still arriving,
     * interleaving two streams into one transcript.
     */
    public function test_a_second_message_cannot_be_sent_while_a_reply_is_still_arriving(): void
    {
        $html = $this->widgetSource();

        $this->assertStringContainsString('this.thinking || this.streaming) return;', $html);
        $this->assertStringContainsString(':disabled="streaming"', $html);
        $this->assertStringNotContainsString(':disabled="thinking"', $html);
    }

    /**
     * "I'm having trouble connecting" under half a written answer is simply
     * untrue, and it hid the useful fact: the reply finished and was saved.
     */
    public function test_a_reply_cut_off_part_way_says_where_the_rest_is(): void
    {
        $html = $this->widgetSource();

        $this->assertStringContainsString('finished on the server', $html);
        $this->assertStringContainsString('const cutOffMidReply = assistantEntry && assistantEntry.content;', $html);
    }
}
