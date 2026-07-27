<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_action_audits', function (Blueprint $table) {
            $table->id();
            $table->string('tool_name');
            $table->json('input_params')->nullable();
            $table->json('result')->nullable();
            $table->string('actor_type'); // ai|human
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status'); // executed|proposed|rejected
            $table->timestamps();

            $table->index(['tool_name', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_action_audits');
    }
};
