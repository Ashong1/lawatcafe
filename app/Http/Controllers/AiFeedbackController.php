<?php

namespace App\Http\Controllers;

use App\Console\Commands\DistilAiLessons;
use App\Models\AiActionAudit;
use App\Models\AiConversation;
use App\Models\AiFeedback;
use App\Models\AiLesson;
use App\Models\Setting;
use App\Services\Agent\LessonLibrary;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Capture side of the learning loop, plus the human review gate on what it
 * concluded.
 *
 * Feedback is deliberately fire-and-forget from the client's point of view: a
 * rating that fails to save must never interrupt somebody's conversation, so
 * every response here is a plain 200 with a boolean.
 */
class AiFeedbackController extends Controller
{
    /**
     * Record a thumb, a reason, or a staff correction.
     *
     * Reachable unauthenticated because the guest portal chat is — that is the
     * surface with the most to learn from, and demanding a login to say "that
     * answer was wrong" would collect nothing. It is throttled at the route,
     * writes only to its own table, and stores no identity beyond an optional
     * user_id when somebody happens to be signed in.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'audience' => 'required|in:guest,staff,admin',
            // 1 = helpful, -1 = not helpful, 0 = a correction, which carries no
            // direction of its own.
            'sentiment' => 'required|integer|in:-1,0,1',
            'user_message' => 'nullable|string|max:2000',
            'assistant_reply' => 'nullable|string|max:4000',
            'note' => 'nullable|string|max:1000',
            'conversation_id' => 'nullable|integer|exists:ai_conversations,id',
        ]);

        $user = $request->user();

        // Only a signed-in staff/admin user may file a correction — a correction
        // becomes high-priority training signal, and accepting them from an
        // anonymous guest would hand the agent's future behaviour to whoever is
        // on the WiFi.
        $isCorrection = $validated['sentiment'] === 0 && ! empty($validated['note']);
        if ($isCorrection && ! ($user && $user->isAdminOrAbove())) {
            $isCorrection = false;
        }

        AiFeedback::create([
            'audience' => $validated['audience'],
            'user_id' => $user?->id,
            // Guests have no durable conversation, so this is null for them —
            // see ConversationHistoryService.
            'conversation_id' => $user ? ($validated['conversation_id'] ?? null) : null,
            'signal' => $isCorrection ? AiFeedback::SIGNAL_CORRECTION : AiFeedback::SIGNAL_RATING,
            'sentiment' => $validated['sentiment'],
            'user_message' => $validated['user_message'] ?? null,
            'assistant_reply' => $validated['assistant_reply'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json(['recorded' => true]);
    }

    /** The review queue: what the agent has concluded, awaiting a decision. */
    public function index()
    {
        return view('admin.ai.lessons', [
            'proposed' => AiLesson::proposed()->latest()->get(),
            'approved' => AiLesson::approved()->orderByDesc('reviewed_at')->limit(50)->get(),
            'rejected' => AiLesson::where('status', AiLesson::STATUS_REJECTED)->latest('reviewed_at')->limit(20)->get(),
            'satisfaction' => [
                'overall' => AiFeedback::satisfactionRate(7),
                'guest' => AiFeedback::satisfactionRate(7, 'guest'),
                'staff' => AiFeedback::satisfactionRate(7, 'staff'),
                'admin' => AiFeedback::satisfactionRate(7, 'admin'),
            ],
            'ratingsThisWeek' => AiFeedback::where('signal', AiFeedback::SIGNAL_RATING)
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
            'autoApply' => Setting::get('ai_learning_auto_apply', '0') === '1',
            // Where the loop actually is, rather than only what came out of it.
            // Two empty boxes look the same whether the loop is broken or has
            // simply never been fed, and it had never been fed: not one rating
            // had been recorded, so every hourly run correctly concluded there
            // was nothing to generalise from and stopped. That is a working
            // pipeline and an idle one, and the page could not tell them apart.
            'pipeline' => $this->pipelineStatus(),
        ]);
    }

    /**
     * @return array{evidence:int, needed:int, lastRun:?string, everRated:bool}
     */
    private function pipelineStatus(): array
    {
        // Counted the same way DistilAiLessons counts it, so the page cannot
        // promise a run the command will not make — including the settled
        // admin/owner conversations the loop now mines passively.
        $evidence = AiFeedback::undistilled()->count()
            + AiActionAudit::where('status', 'failed')
                ->where('created_at', '>=', now()->subDay())
                ->count()
            + AiConversation::minable()->where('context', 'admin')->whereHas('user')->count();

        $stamp = Cache::get('ai_learn_last_run');

        return [
            'evidence' => $evidence,
            'needed' => DistilAiLessons::MIN_EVIDENCE_FOR_A_RUN,
            'lastRun' => $stamp ? Carbon::createFromTimestamp($stamp)->diffForHumans() : null,
            'everRated' => AiFeedback::exists(),
        ];
    }

    public function approve(Request $request, AiLesson $lesson)
    {
        $this->decide($request, $lesson, AiLesson::STATUS_APPROVED);

        return back()->with('success', 'Lesson approved — it will be included in the next conversation.');
    }

    public function reject(Request $request, AiLesson $lesson)
    {
        $this->decide($request, $lesson, AiLesson::STATUS_REJECTED);

        // Rejected rows are kept, not deleted: the distiller reads them back so
        // it stops re-proposing the same idea every run.
        return back()->with('success', 'Lesson rejected — the agent will not propose it again.');
    }

    private function decide(Request $request, AiLesson $lesson, string $status): void
    {
        $request->validate(['review_note' => 'nullable|string|max:500']);

        $lesson->update([
            'status' => $status,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $request->input('review_note'),
        ]);

        LessonLibrary::forget($lesson->audience);
    }
}
