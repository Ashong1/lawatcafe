<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('ai_analysis_runs')->cascadeOnDelete();
            $table->string('type');
            $table->string('severity');
            $table->string('summary');
            $table->json('data')->nullable();
            // 'staff' = visible to staff+admin (operational signals); 'admin' = admin-only (security/abuse signals).
            $table->string('audience');
            $table->timestamps();

            $table->index(['audience', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_findings');
    }
};
