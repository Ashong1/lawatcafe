<?php

namespace App\Console\Commands;

use App\Models\AiActionAudit;
use App\Models\AiConversation;
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
 *
 * Beyond explicit signals (thumbs, corrections, tool failures), this also
 * mines the admin and super_admin CONVERSATIONS themselves — the transcript of
 * what the owner asked and how the assistant answered. A busy owner almost
 * never rates their own assistant, so before this the admin/super_admin side of
 * the loop was starved and the whole thing leaned on guest-portal ratings.
 * Mining the transcripts is safe where mining guest chat would not be: these
 * are authenticated, trusted users, not anonymous WiFi traffic — and every
 * conclusion still passes the same human review gate before it reaches a prompt.
 */
class DistilAiLessons extends Command
{
    protected $signature = 'ai:learn {--dry-run : Show what would be proposed without saving anything}';

    protected $description = 'Distil recent conversations, ratings and failures into candidate lessons for review.';

    /** Evidence rows per run. Enough to see a pattern, small enough to fit a prompt. */
    private const MAX_EVIDENCE = 60;

    /** Settled conversations mined per run. Bounded so the prompt stays small. */
    private const MAX_CONVERSATIONS = 15;

    /** Text turns kept per conversation — the tail, where the resolution is. */
    private const MAX_TURNS_PER_CONVERSATION = 12;

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

        // Settled admin/owner conversations — the passive evidence source. The
        // user relation is eager-loaded so role (admin vs super_admin) can tag
        // each transcript without an N+1, and rows with a deleted user are
        // skipped rather than crashing on a null role.
        $conversations = AiConversation::query()
            ->minable()
            ->where('context', 'admin')
            ->with('user:id,role')
            ->whereHas('user')
            ->orderBy('last_message_at')
            ->limit(self::MAX_CONVERSATIONS)
            ->get();

        if ($evidence->count() + $failures->count() + $conversations->count() < self::MIN_EVIDENCE_FOR_A_RUN) {
            $this->info('Not enough new evidence to learn from yet.');

            return self::SUCCESS;
        }

        $proposals = $this->askForLessons($ai, $evidence, $failures, $conversations);

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
            // Same discipline as the feedback rows: only stamp mined_at once the
            // distiller has actually run and returned, so an AI-stack outage
            // (handled above) never silently consumes a transcript unread.
            AiConversation::whereIn('id', $conversations->pluck('id'))->update(['mined_at' => now()]);
            LessonLibrary::forget('all');
        }

        $this->info("{$created} new lesson(s) proposed from {$evidence->count()} feedback rows, {$failures->count()} tool failures and {$conversations->count()} conversation(s).");

        return self::SUCCESS;
    }

    /**
     * Assemble the corpus and hand it to AIService::distilLessons().
     *
     * Building the evidence is this command's job; wording the prompt is the
     * AI service's, alongside every other prompt in the system.
     */
    private function askForLessons(AIService $ai, $evidence, $failures, $conversations): ?array
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

        $conversationList = $conversations
            ->map(fn (AiConversation $c) => $this->transcriptFor($c))
            ->filter()
            ->values()
            ->all();

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

        return $ai->distilLessons($corpus, $failureList, $decided, $conversationList);
    }

    /**
     * Flatten one stored conversation into a tagged transcript for the distiller.
     *
     * The audience is decided HERE from the conversation owner's role, never
     * from anything the model returns: a super_admin's transcript can only
     * produce a super_admin-tagged lesson, and an admin's only an admin one, so
     * an infrastructure conclusion can never be filed against the cafe bucket by
     * a hallucinated audience field. Only user/assistant text turns are kept —
     * tool-call rows carry no natural-language pattern to generalise from — and
     * a conversation with nothing the assistant actually said is dropped.
     */
    private function transcriptFor(AiConversation $conversation): ?array
    {
        $audience = $conversation->user->isSuperAdmin() ? 'super_admin' : 'admin';

        $turns = collect($conversation->messages ?? [])
            ->filter(fn ($m) => in_array($m['role'] ?? null, ['user', 'assistant'], true)
                && ! empty($m['content']))
            ->map(fn ($m) => [
                'role' => $m['role'],
                'text' => Str::limit((string) $m['content'], 300),
            ])
            ->take(-self::MAX_TURNS_PER_CONVERSATION)
            ->values()
            ->all();

        if (empty($turns) || ! collect($turns)->contains(fn ($t) => $t['role'] === 'assistant')) {
            return null;
        }

        return ['audience' => $audience, 'turns' => $turns];
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
        if (! in_array($audience, ['guest', 'staff', 'admin', 'super_admin', 'all'], true)) {
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
