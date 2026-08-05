<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storage for the agent's experiential learning loop.
 *
 * Two tables, deliberately separated by what they are:
 *
 * - ai_feedback is RAW EVIDENCE. Every rating, correction and detected failure,
 *   exactly as observed, never edited. It is the audit trail behind any claim
 *   that the agent improved, and it is what the distiller reads.
 *
 * - ai_lessons is DERIVED GUIDANCE. What the distiller concluded from that
 *   evidence, pending human approval before it can influence a live prompt.
 *
 * Keeping them apart matters: evidence must stay immutable and complete even
 * when a lesson drawn from it is rejected, or the satisfaction trend would
 * quietly rewrite itself every time someone declined a suggestion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_feedback', function (Blueprint $table) {
            $table->id();

            // guest | staff | admin — which chat surface this came from. Guest
            // feedback is unauthenticated by nature, so user_id stays null there.
            $table->string('audience', 16)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->cascadeOnDelete();

            // rating       — a guest/staff thumb, the least ambiguous signal
            // correction   — staff supplying the answer that should have been given
            // tool_failure — a tool call that errored, harvested automatically
            // repetition   — the same question asked several ways in one session
            $table->string('signal', 24)->index();

            // -1, 0 or 1. Zero for signals that carry no direction of their own
            // (a correction is not "bad", it is "here is better").
            $table->tinyInteger('sentiment')->default(0);

            $table->text('user_message')->nullable();
            $table->text('assistant_reply')->nullable();
            // The guest's reason for a thumbs-down, or the staff member's
            // corrected answer.
            $table->text('note')->nullable();

            // Set once a distillation run has read this row, so the next run
            // does not keep re-deriving the same lessons from the same evidence.
            $table->timestamp('distilled_at')->nullable()->index();

            $table->timestamps();

            $table->index(['audience', 'signal', 'created_at']);
        });

        Schema::create('ai_lessons', function (Blueprint $table) {
            $table->id();

            // guest | staff | admin | all
            $table->string('audience', 16)->index();

            // lesson   — a standing instruction added to the system prompt
            // exemplar — a question plus the answer that worked, retrieved and
            //            shown to the model when a similar question comes in
            $table->string('kind', 16)->default('lesson')->index();

            $table->string('title');
            $table->text('body');
            // For exemplars only: the question this answer is the model for.
            $table->text('trigger')->nullable();

            // What the distiller was looking at when it proposed this, kept so a
            // reviewer can judge it rather than take it on trust.
            $table->json('evidence')->nullable();
            $table->unsignedSmallInteger('evidence_count')->default(0);

            // proposed | approved | rejected. Rejected rows are KEPT and fed back
            // to the distiller — otherwise it cheerfully re-proposes the same
            // rejected idea on every run, and review becomes a treadmill.
            $table->string('status', 16)->default('proposed')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            // Bumped each time this lesson is actually injected into a prompt —
            // the difference between "approved" and "earning its token budget".
            $table->unsignedInteger('times_applied')->default(0);

            // A stable digest of the normalised body, so an identical lesson
            // cannot be inserted twice across runs.
            $table->string('fingerprint', 64)->unique();

            $table->timestamps();

            $table->index(['audience', 'status', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_lessons');
        Schema::dropIfExists('ai_feedback');
    }
};
