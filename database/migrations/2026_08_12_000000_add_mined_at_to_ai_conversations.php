<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the learning loop mine admin/owner conversations passively.
 *
 * The loop used to learn only from signals a person had to click — a thumb or
 * a typed correction — so the admin/super_admin side was starved: an owner
 * almost never rates their own assistant, and the guest portal carried the
 * whole thing. This marker is the conversation-side equivalent of
 * ai_feedback.distilled_at: once ai:learn has read a settled transcript and
 * drawn what it can from it, mined_at is stamped so the next run does not
 * re-account for the same dialogue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->timestamp('mined_at')->nullable()->after('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn('mined_at');
        });
    }
};
