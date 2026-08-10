<?php

namespace Tests\Feature;

use App\Console\Commands\DistilAiLessons;
use App\Models\AiFeedback;
use App\Models\AiLesson;
use App\Models\Setting;
use App\Models\User;
use App\Services\Agent\LessonLibrary;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The agent's experiential learning loop, end to end:
 *
 *   conversation -> feedback -> ai:learn -> proposed lesson -> approval
 *      -> injected into the next system prompt
 *
 * The claim this feature makes is that the assistant's behaviour measurably
 * changes from experience, so what these tests pin is the seams where that could
 * silently stop being true — evidence not reaching the distiller, an unapproved
 * lesson leaking into a prompt, a rejected one coming back.
 */
class AiLearningLoopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        LessonLibrary::forget('all');
    }

    private function makeLesson(array $overrides = []): AiLesson
    {
        $body = $overrides['body'] ?? 'When guests ask about parking, tell them there are six slots behind the building.';

        return AiLesson::create(array_merge([
            'audience' => 'guest',
            'kind' => AiLesson::KIND_LESSON,
            'title' => 'Parking',
            'body' => $body,
            'status' => AiLesson::STATUS_PROPOSED,
            'fingerprint' => AiLesson::fingerprintFor(
                $overrides['audience'] ?? 'guest',
                $overrides['kind'] ?? AiLesson::KIND_LESSON,
                $body
            ),
        ], $overrides));
    }

    // --- Capture -----------------------------------------------------------

    public function test_a_guest_can_rate_a_reply_without_being_logged_in(): void
    {
        $this->postJson(route('ai.feedback.store'), [
            'audience' => 'guest',
            'sentiment' => -1,
            'user_message' => 'Do you have parking?',
            'assistant_reply' => 'I am not sure about that.',
        ])->assertOk()->assertJson(['recorded' => true]);

        $this->assertDatabaseHas('ai_feedback', [
            'audience' => 'guest',
            'signal' => AiFeedback::SIGNAL_RATING,
            'sentiment' => -1,
            'user_id' => null,
        ]);
    }

    /**
     * A correction becomes high-priority training signal, so accepting one from
     * an anonymous guest would hand the assistant's future behaviour to whoever
     * is on the WiFi. It is downgraded to a plain rating rather than rejected —
     * the thumb is still worth keeping.
     */
    public function test_an_anonymous_correction_is_downgraded_to_a_rating(): void
    {
        $this->postJson(route('ai.feedback.store'), [
            'audience' => 'guest',
            'sentiment' => 0,
            'note' => 'Actually you should tell them we close at 9pm.',
        ])->assertOk();

        $this->assertDatabaseHas('ai_feedback', ['signal' => AiFeedback::SIGNAL_RATING]);
        $this->assertDatabaseMissing('ai_feedback', ['signal' => AiFeedback::SIGNAL_CORRECTION]);
    }

    public function test_an_admin_correction_is_stored_as_a_correction(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('ai.feedback.store'), [
                'audience' => 'admin',
                'sentiment' => 0,
                'note' => 'Use getSalesSummary for weekly totals, not shiftHandoffSummary.',
            ])->assertOk();

        $this->assertDatabaseHas('ai_feedback', ['signal' => AiFeedback::SIGNAL_CORRECTION]);
    }

    // --- Injection ---------------------------------------------------------

    /** The approval gate is the whole safety story; a proposal must never leak. */
    public function test_only_approved_lessons_reach_a_system_prompt(): void
    {
        $this->makeLesson(['status' => AiLesson::STATUS_PROPOSED]);

        $library = app(LessonLibrary::class);
        $this->assertSame('', $library->promptBlockFor('guest'));

        LessonLibrary::forget('guest');
        AiLesson::first()->update(['status' => AiLesson::STATUS_APPROVED, 'reviewed_at' => now()]);
        LessonLibrary::forget('guest');

        $this->assertStringContainsString('six slots behind the building', $library->promptBlockFor('guest'));
    }

    public function test_a_rejected_lesson_never_reaches_a_prompt(): void
    {
        $this->makeLesson(['status' => AiLesson::STATUS_REJECTED, 'reviewed_at' => now()]);

        $this->assertSame('', app(LessonLibrary::class)->promptBlockFor('guest'));
    }

    /** A lesson for one surface must not bleed into another's instructions. */
    public function test_lessons_are_scoped_to_their_audience(): void
    {
        $this->makeLesson([
            'audience' => 'admin',
            'status' => AiLesson::STATUS_APPROVED,
            'reviewed_at' => now(),
            'body' => 'When the owner asks about wastage, lead with the highest-cost ingredient.',
        ]);

        $library = app(LessonLibrary::class);

        $this->assertStringContainsString('highest-cost ingredient', $library->promptBlockFor('admin'));
        $this->assertSame('', $library->promptBlockFor('guest'));
    }

    /** 'all' is for conclusions that hold whoever is asking. */
    public function test_an_all_audience_lesson_reaches_every_surface(): void
    {
        $this->makeLesson([
            'audience' => 'all',
            'status' => AiLesson::STATUS_APPROVED,
            'reviewed_at' => now(),
            'body' => 'The shop closes at 9pm on weekdays and 11pm at weekends.',
        ]);

        $library = app(LessonLibrary::class);

        foreach (['guest', 'staff', 'admin'] as $audience) {
            LessonLibrary::forget($audience);
            $this->assertStringContainsString('closes at 9pm', $library->promptBlockFor($audience));
        }
    }

    /**
     * times_applied is what separates "approved" from "earning its token
     * budget" — a lesson approved weeks ago and never retrieved is prunable,
     * and without this counter there is no way to know.
     */
    public function test_applying_a_lesson_counts_it(): void
    {
        $lesson = $this->makeLesson(['status' => AiLesson::STATUS_APPROVED, 'reviewed_at' => now()]);

        app(LessonLibrary::class)->promptBlockFor('guest');

        $this->assertSame(1, $lesson->fresh()->times_applied);
    }

    // --- Exemplar retrieval ------------------------------------------------

    public function test_a_worked_example_is_retrieved_for_a_similar_question(): void
    {
        $this->makeLesson([
            'kind' => AiLesson::KIND_EXEMPLAR,
            'status' => AiLesson::STATUS_APPROVED,
            'reviewed_at' => now(),
            'trigger' => 'How do I connect to the wifi?',
            'body' => 'Buy a voucher at the counter, then enter the code on the sign-in page.',
        ]);

        $library = app(LessonLibrary::class);

        $hit = $library->exemplarBlockFor('guest', 'how do i connect my phone to the wifi');
        $this->assertStringContainsString('enter the code on the sign-in page', $hit);

        // An unrelated question must not drag it in — that would spend tokens on
        // an example the model should ignore, and invite it to answer the wrong
        // question entirely.
        $miss = $library->exemplarBlockFor('guest', 'what pastries do you have today');
        $this->assertSame('', $miss);
    }

    // --- Distillation ------------------------------------------------------

    private function seedEvidence(int $count = 4): void
    {
        for ($i = 0; $i < $count; $i++) {
            AiFeedback::create([
                'audience' => 'guest',
                'signal' => AiFeedback::SIGNAL_RATING,
                'sentiment' => -1,
                'user_message' => 'Do you have parking?',
                'assistant_reply' => 'I am not sure about that.',
            ]);
        }
    }

    private function mockDistiller(?array $returns): void
    {
        $ai = Mockery::mock(AIService::class);
        $ai->shouldReceive('distilLessons')->andReturn($returns);
        $this->app->instance(AIService::class, $ai);
    }

    public function test_learning_proposes_rather_than_applies(): void
    {
        $this->seedEvidence();
        $this->mockDistiller([[
            'audience' => 'guest',
            'kind' => 'lesson',
            'title' => 'Parking',
            'body' => 'When guests ask about parking, tell them there are six slots behind the building.',
            'confidence' => 0.8,
        ]]);

        $this->artisan('ai:learn')->assertSuccessful();

        $lesson = AiLesson::first();
        $this->assertNotNull($lesson);
        $this->assertSame(AiLesson::STATUS_PROPOSED, $lesson->status);
        // And therefore still invisible to the model.
        $this->assertSame('', app(LessonLibrary::class)->promptBlockFor('guest'));
    }

    public function test_evidence_is_only_consumed_once(): void
    {
        $this->seedEvidence();
        $this->mockDistiller([]);

        $this->artisan('ai:learn')->assertSuccessful();

        $this->assertSame(0, AiFeedback::undistilled()->count());
        // Second run has nothing left and must not re-derive from the same rows.
        $this->artisan('ai:learn')->assertSuccessful();
    }

    /**
     * An outage must not silently consume the only record of what went wrong —
     * that evidence is unrecoverable once marked distilled.
     */
    public function test_an_ai_outage_leaves_the_evidence_intact(): void
    {
        $this->seedEvidence();
        $this->mockDistiller(null);

        $this->artisan('ai:learn')->assertFailed();

        $this->assertSame(4, AiFeedback::undistilled()->count());
    }

    /** A pattern seen once is an anecdote — and a wasted AI call. */
    public function test_learning_does_not_call_the_ai_without_enough_evidence(): void
    {
        AiFeedback::create([
            'audience' => 'guest',
            'signal' => AiFeedback::SIGNAL_RATING,
            'sentiment' => -1,
        ]);

        $ai = Mockery::mock(AIService::class);
        $ai->shouldNotReceive('distilLessons');
        $this->app->instance(AIService::class, $ai);

        $this->artisan('ai:learn')->assertSuccessful();
    }

    /**
     * Without this the distiller re-proposes what a human already turned down on
     * every single run, and reviewing becomes a treadmill.
     */
    public function test_a_rejected_lesson_is_never_proposed_again(): void
    {
        $body = 'When guests ask about parking, tell them there are six slots behind the building.';
        $this->makeLesson(['status' => AiLesson::STATUS_REJECTED, 'body' => $body, 'reviewed_at' => now()]);

        $this->seedEvidence();
        $this->mockDistiller([[
            'audience' => 'guest', 'kind' => 'lesson', 'title' => 'Parking', 'body' => $body,
        ]]);

        $this->artisan('ai:learn')->assertSuccessful();

        $this->assertSame(1, AiLesson::count());
        $this->assertSame(AiLesson::STATUS_REJECTED, AiLesson::first()->status);
    }

    /** The model is a suggester, not an authority — its output is validated. */
    public static function junkProposalProvider(): array
    {
        return [
            'unknown audience' => [['audience' => 'everyone', 'kind' => 'lesson', 'body' => 'Something plausible but misfiled entirely.']],
            'unknown kind' => [['audience' => 'guest', 'kind' => 'directive', 'body' => 'Something plausible but misfiled entirely.']],
            'too short to instruct' => [['audience' => 'guest', 'kind' => 'lesson', 'body' => 'Be nice.']],
            'exemplar with no trigger' => [['audience' => 'guest', 'kind' => 'exemplar', 'body' => 'A perfectly good answer to nothing in particular.']],
        ];
    }

    #[DataProvider('junkProposalProvider')]
    public function test_malformed_proposals_are_discarded(array $proposal): void
    {
        $this->seedEvidence();
        $this->mockDistiller([$proposal]);

        $this->artisan('ai:learn')->assertSuccessful();

        $this->assertSame(0, AiLesson::count());
    }

    public function test_auto_apply_setting_skips_the_review_gate(): void
    {
        Setting::set('ai_learning_auto_apply', '1');
        Cache::forget('setting.ai_learning_auto_apply');

        $this->seedEvidence();
        $this->mockDistiller([[
            'audience' => 'guest',
            'kind' => 'lesson',
            'title' => 'Parking',
            'body' => 'When guests ask about parking, tell them there are six slots behind the building.',
        ]]);

        $this->artisan('ai:learn')->assertSuccessful();

        $this->assertSame(AiLesson::STATUS_APPROVED, AiLesson::first()->status);
    }

    // --- Review ------------------------------------------------------------

    public function test_an_admin_can_approve_a_lesson_and_it_takes_effect(): void
    {
        $lesson = $this->makeLesson();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.ai.lessons.approve', $lesson))
            ->assertRedirect();

        $lesson->refresh();
        $this->assertSame(AiLesson::STATUS_APPROVED, $lesson->status);
        $this->assertSame($admin->id, $lesson->reviewed_by);
        $this->assertStringContainsString('six slots', app(LessonLibrary::class)->promptBlockFor('guest'));
    }

    public function test_staff_cannot_reach_the_review_queue(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get(route('admin.ai.lessons.index'))
            ->assertRedirect(route('staff.dashboard'));
    }

    public function test_the_review_page_reports_satisfaction_and_pending_lessons(): void
    {
        $this->makeLesson();
        AiFeedback::create(['audience' => 'guest', 'signal' => AiFeedback::SIGNAL_RATING, 'sentiment' => 1]);
        AiFeedback::create(['audience' => 'guest', 'signal' => AiFeedback::SIGNAL_RATING, 'sentiment' => -1]);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.ai.lessons.index'));

        $response->assertOk();
        $response->assertSee('Awaiting Your Decision', false);
        $response->assertSee('50%', false);
    }

    /**
     * "No data" and "everyone hated it" are very different things to put on a
     * page, so the rate is null rather than 0 when nobody has rated anything.
     */
    public function test_satisfaction_is_null_rather_than_zero_with_no_ratings(): void
    {
        $this->assertNull(AiFeedback::satisfactionRate(7));

        AiFeedback::create(['audience' => 'guest', 'signal' => AiFeedback::SIGNAL_RATING, 'sentiment' => -1]);

        $this->assertSame(0.0, AiFeedback::satisfactionRate(7));
    }

    /**
     * The gap that made a working loop look like a dead one.
     *
     * With nothing ever rated, the review page showed two empty boxes — the
     * same two empty boxes it would show if the distiller were crashing every
     * hour. An admin has no way to tell those apart, and the honest state
     * ("nothing has been fed in yet") is the one that tells them what to do
     * about it.
     */
    public function test_the_review_page_says_when_nothing_has_ever_been_rated(): void
    {
        $this->assertSame(0, AiFeedback::count());

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.ai.lessons.index'));

        $response->assertOk();
        $response->assertSee('Waiting for its first rating', false);
        // And it says where the control is, rather than leaving them to hunt.
        $response->assertSee('thumbs sit under every reply', false);
    }

    /** Once evidence exists, the page counts it against the threshold. */
    public function test_the_review_page_reports_progress_towards_the_evidence_threshold(): void
    {
        AiFeedback::create(['audience' => 'guest', 'signal' => AiFeedback::SIGNAL_RATING, 'sentiment' => 1]);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.ai.lessons.index'));

        $response->assertOk();
        $response->assertSee('Evidence Waiting', false);
        $response->assertDontSee('Waiting for its first rating', false);
        $response->assertSee(DistilAiLessons::MIN_EVIDENCE_FOR_A_RUN - 1 .' more before it can generalise', false);
    }

    /**
     * The page must not promise a run the command will not make, so it counts
     * evidence exactly the way DistilAiLessons does — undistilled feedback only.
     */
    public function test_already_distilled_feedback_does_not_count_towards_the_threshold(): void
    {
        AiFeedback::create([
            'audience' => 'guest', 'signal' => AiFeedback::SIGNAL_RATING,
            'sentiment' => 1, 'distilled_at' => now(),
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.ai.lessons.index'));

        $response->assertOk();
        // It HAS been rated before, so the first-run notice would be wrong —
        // but the distilled row is spent, so it must not be counted as evidence
        // still waiting.
        $response->assertDontSee('Waiting for its first rating', false);
        $response->assertSee('Evidence Waiting', false);
        $response->assertSee(DistilAiLessons::MIN_EVIDENCE_FOR_A_RUN.' more before it can generalise', false);
    }
}
