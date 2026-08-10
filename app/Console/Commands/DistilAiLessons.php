<?php

namespace App\Console\Commands;

use App\Models\AiActionAudit;
use App\Models\AiFeedback;
use App\Models\AiLesson;
use App\Models\Setting;
use App\Services\Agent\LessonLibrary;
use App\Services\AIService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The learning step: read what actually happened, conclude something durable.
 *
 * Runs on the scheduler with no human trigger, which is what makes the loop
 * autonomous. What it produces is a *proposal*, not a change — nothing here can
 * alter a live prompt on its own unless ai_learning_auto_apply is switched on.
 *
 * That gate is not ceremony. Guest chat is unauthenticated, so without it an
 * afternoon of somebody feeding the bot nonsense could end up written into its
 * permanent instructions — a persistent prompt injection with a scheduled job
 * helpfully doing the writing.
 */
class DistilAiLessons extends Command
{
    protected $signature = 'ai:learn {--dry-run : Show what would be proposed without saving anything}';

    protected $description = 'Distil recent conversations, ratings and failures into candidate lessons for review.';

    /** Evidence rows per run. Enough to see a pattern, small enough to fit a prompt. */
    private const MAX_EVIDENCE = 60;

    /**
     * A pattern seen once is an anecdote. Below this, say nothing.
     *
     * Public because the review page reports progress towards it. An admin
     * looking at an empty queue cannot otherwise tell "not enough evidence yet"
     * apart from "this feature is broken" — and that ambiguity is what made the
     * loop look dead for its whole first week.
     */
    public const MIN_EVIDENCE_FOR_A_RUN = 3;

    public function handle(AIService $ai): int
    {
        Cache::put('ai_learn_last_run', now()->timestamp, 7200);

        $evidence = AiFeedback::undistilled()
            ->orderBy('created_at')
            ->limit(self::MAX_EVIDENCE)
            ->get();

        // Failed tool calls are evidence nobody had to type. Harvested here
        // rather than written at failure time so the tool path stays untouched.
        $failures = AiActionAudit::where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->latest()
            ->limit(15)
            ->get();

        if ($evidence->count() + $failures->count() < self::MIN_EVIDENCE_FOR_A_RUN) {
            $this->info('Not enough new evidence to learn from yet.');

            return self::SUCCESS;
        }

        $proposals = $this->askForLessons($ai, $evidence, $failures);

        if ($proposals === null) {
            $this->warn('AI stack unreachable — evidence left undistilled for the next run.');

            // Deliberately NOT marking the evidence distilled: an outage must
            // not silently consume the only record of what went wrong.
            return self::FAILURE;
        }

        $autoApply = Setting::get('ai_learning_auto_apply', '0') === '1';
        $created = 0;

        foreach ($proposals as $proposal) {
            if ($this->store($proposal, $evidence, $autoApply)) {
                $created++;
            }
        }

        if (! $this->option('dry-run')) {
            AiFeedback::whereIn('id', $evidence->pluck('id'))->update(['distilled_at' => now()]);
            LessonLibrary::forget('all');
        }

        $this->info("{$created} new lesson(s) proposed from {$evidence->count()} feedback rows and {$failures->count()} tool failures.");

        return self::SUCCESS;
    }

    /**
     * Assemble the corpus and hand it to AIService::distilLessons().
     *
     * Building the evidence is this command's job; wording the prompt is the
     * AI service's, alongside every other prompt in the system.
     */
    private function askForLessons(AIService $ai, $evidence, $failures): ?array
    {
        $corpus = $evidence->map(fn (AiFeedback $f) => [
            'audience' => $f->audience,
            'signal' => $f->signal,
            'sentiment' => $f->sentiment,
            'asked' => Str::limit((string) $f->user_message, 300),
            'answered' => Str::limit((string) $f->assistant_reply, 400),
            'note' => Str::limit((string) $f->note, 300),
        ])->all();

        $failureList = $failures->map(fn (AiActionAudit $a) => [
            'tool' => $a->tool_name,
            'result' => Str::limit(json_encode($a->result), 200),
        ])->all();

        // Already-decided lessons go in so the model does not re-propose them.
        // Rejections matter more than approvals here: without them, every run
        // cheerfully suggests the same idea a human already turned down, and
        // reviewing becomes a treadmill.
        $decided = AiLesson::whereIn('status', [AiLesson::STATUS_APPROVED, AiLesson::STATUS_REJECTED])
            ->latest()
            ->limit(40)
            ->get()
            ->map(fn (AiLesson $l) => ['status' => $l->status, 'lesson' => $l->body])
            ->all();

        return $ai->distilLessons($corpus, $failureList, $decided);
    }

    /** Validate and persist one proposal. Returns false when it is not worth keeping. */
    private function store(array $proposal, $evidence, bool $autoApply): bool
    {
        $audience = $proposal['audience'] ?? null;
        $kind = $proposal['kind'] ?? AiLesson::KIND_LESSON;
        $body = trim((string) ($proposal['body'] ?? ''));

        // The model is a suggester, not an authority: everything it hands back
        // is validated as if it came from a form. An unrecognised audience would
        // otherwise create a lesson that silently reaches nobody.
        if (! in_array($audience, ['guest', 'staff', 'admin', 'all'], true)) {
            return false;
        }

        if (! in_array($kind, [AiLesson::KIND_LESSON, AiLesson::KIND_EXEMPLAR], true)) {
            return false;
        }

        // Too short to carry an instruction; too long to be worth its place in
        // every future prompt.
        if (strlen($body) < 20 || strlen($body) > 600) {
            return false;
        }

        if ($kind === AiLesson::KIND_EXEMPLAR && empty($proposal['trigger'])) {
            return false;
        }

        $fingerprint = AiLesson::fingerprintFor($audience, $kind, $body);

        // Covers approved AND rejected: a rejected lesson must never come back.
        if (AiLesson::where('fingerprint', $fingerprint)->exists()) {
            return false;
        }

        if ($this->option('dry-run')) {
            $this->line("  [{$audience}/{$kind}] {$body}");

            return true;
        }

        AiLesson::create([
            'audience' => $audience,
            'kind' => $kind,
            'title' => Str::limit((string) ($proposal['title'] ?? 'Untitled lesson'), 120, ''),
            'body' => $body,
            'trigger' => $proposal['trigger'] ?? null,
            'evidence' => [
                'confidence' => $proposal['confidence'] ?? null,
                'feedback_ids' => $evidence->pluck('id')->take(20)->all(),
                'distilled_at' => now()->toDateTimeString(),
            ],
            'evidence_count' => $evidence->count(),
            'status' => $autoApply ? AiLesson::STATUS_APPROVED : AiLesson::STATUS_PROPOSED,
            'reviewed_at' => $autoApply ? now() : null,
            'review_note' => $autoApply ? 'Auto-applied (ai_learning_auto_apply is on).' : null,
            'fingerprint' => $fingerprint,
        ]);

        return true;
    }
}
