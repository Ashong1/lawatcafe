<?php

namespace App\Services\Agent;

use App\Models\AiLesson;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The read side of the learning loop: turns approved lessons into the text that
 * actually reaches a model.
 *
 * Everything here is budgeted. A prompt that grows without limit as the agent
 * learns would get slower and more expensive every week, and would eventually
 * push the genuinely essential parts — the security rules, the live menu — out
 * of the model's attention. Learning has to make the agent better, not just
 * bigger, so both the count and the length of what gets injected are capped.
 */
class LessonLibrary
{
    /** Standing instructions injected on every request for an audience. */
    private const MAX_LESSONS = 12;

    /** Worked examples retrieved per message, on top of the lessons above. */
    private const MAX_EXEMPLARS = 2;

    private const MAX_BODY_CHARS = 400;

    private const CACHE_TTL = 300;

    /**
     * Approved standing lessons for a surface, newest-reviewed first.
     *
     * Cached because this runs on every single chat turn — including the guest
     * portal, which is the latency-sensitive one.
     */
    public function lessonsFor(string $audience): array
    {
        return Cache::remember("ai_lessons_{$audience}", self::CACHE_TTL, function () use ($audience) {
            return AiLesson::approved()
                ->forAudience($audience)
                ->where('kind', AiLesson::KIND_LESSON)
                ->orderByDesc('reviewed_at')
                ->limit(self::MAX_LESSONS)
                ->get()
                ->map(fn (AiLesson $l) => [
                    'id' => $l->id,
                    'body' => Str::limit($l->body, self::MAX_BODY_CHARS),
                ])
                ->all();
        });
    }

    /**
     * Renderable block of learned guidance, or '' when there is nothing yet.
     *
     * Deliberately delimited and labelled. For the guest prompt in particular
     * this text sits alongside a knowledge base that is explicitly marked
     * non-instructional — these lessons ARE instructions, and the only reason
     * that is safe is that a human approved each one. Marking the boundary keeps
     * the distinction legible to both the model and the next reader of the code.
     */
    public function promptBlockFor(string $audience): string
    {
        $lessons = $this->lessonsFor($audience);

        if (empty($lessons)) {
            return '';
        }

        $this->markApplied(array_column($lessons, 'id'));

        $lines = array_map(fn ($l, $i) => ($i + 1).'. '.$l['body'], $lessons, array_keys($lessons));

        return "\n\n=== BEGIN LEARNED GUIDANCE (lessons from past conversations, reviewed and approved by staff — follow these) ===\n"
            .implode("\n", $lines)
            ."\n=== END LEARNED GUIDANCE ===";
    }

    /**
     * Worked examples whose question resembles the one just asked.
     *
     * Scored on shared significant words rather than embeddings: there is no
     * vector store on this deployment, and for a single cafe's question space —
     * menu, WiFi, hours, stock — word overlap separates "what time do you close"
     * from "how do I connect" perfectly well. Worth revisiting only if the
     * corpus ever gets large enough for that to break down.
     */
    public function exemplarsFor(string $audience, string $message): array
    {
        $keywords = $this->keywords($message);

        if (empty($keywords)) {
            return [];
        }

        $candidates = AiLesson::approved()
            ->forAudience($audience)
            ->where('kind', AiLesson::KIND_EXEMPLAR)
            ->whereNotNull('trigger')
            ->where(function ($q) use ($keywords) {
                foreach ($keywords as $word) {
                    // Bound the LIKE scan: only the most distinctive words, and
                    // this table holds curated lessons, not raw traffic.
                    $q->orWhere('trigger', 'like', '%'.$word.'%');
                }
            })
            ->limit(20)
            ->get();

        return $candidates
            ->map(function (AiLesson $lesson) use ($keywords) {
                $triggerWords = $this->keywords($lesson->trigger ?? '');
                $overlap = count(array_intersect($keywords, $triggerWords));

                return ['lesson' => $lesson, 'score' => $overlap];
            })
            // A single shared word is noise — "the" survives the stopword list
            // in some phrasings and would match everything.
            ->filter(fn ($c) => $c['score'] >= 2)
            ->sortByDesc('score')
            ->take(self::MAX_EXEMPLARS)
            ->map(fn ($c) => [
                'id' => $c['lesson']->id,
                'question' => $c['lesson']->trigger,
                'answer' => Str::limit($c['lesson']->body, self::MAX_BODY_CHARS),
            ])
            ->values()
            ->all();
    }

    /** Renderable block of worked examples for this specific message, or ''. */
    public function exemplarBlockFor(string $audience, string $message): string
    {
        $exemplars = $this->exemplarsFor($audience, $message);

        if (empty($exemplars)) {
            return '';
        }

        $this->markApplied(array_column($exemplars, 'id'));

        $block = "\n\n=== BEGIN WORKED EXAMPLES (past questions like this one, answered well — match their substance, not their wording) ===\n";
        foreach ($exemplars as $e) {
            $block .= "Q: {$e['question']}\nA: {$e['answer']}\n";
        }

        return $block.'=== END WORKED EXAMPLES ===';
    }

    /**
     * Count that a lesson actually reached a prompt.
     *
     * This is what separates "approved" from "earning its place": a lesson
     * approved months ago and never once retrieved is a candidate for pruning,
     * and without this counter there is no way to tell. Incremented with a bare
     * query rather than a model save — it must never touch updated_at, which the
     * review page orders by.
     */
    private function markApplied(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        DB::table('ai_lessons')->whereIn('id', $ids)->increment('times_applied');
    }

    /**
     * Distinctive words from a message, lowercased and de-duplicated.
     *
     * The stopword list is deliberately small and cafe-specific rather than a
     * general English list: "wifi", "coffee" and "menu" are the whole subject
     * matter here, so stripping common *domain* words would leave nothing to
     * match on.
     */
    private function keywords(string $text): array
    {
        $stopwords = ['the', 'and', 'for', 'you', 'are', 'can', 'how', 'what', 'why', 'when', 'where', 'is', 'it', 'do', 'does', 'my', 'me', 'to', 'a', 'of', 'in', 'on', 'i', 'we', 'this', 'that', 'have', 'has', 'with', 'your', 'please', 'there'];

        $words = preg_split('/[^a-z0-9]+/', strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $words,
            fn ($w) => strlen($w) > 2 && ! in_array($w, $stopwords, true)
        )));
    }

    /** Called whenever a lesson's status changes, so prompts pick it up promptly. */
    public static function forget(string $audience): void
    {
        foreach (array_unique([$audience, 'guest', 'staff', 'admin', 'super_admin']) as $key) {
            Cache::forget("ai_lessons_{$key}");
        }
    }
}
