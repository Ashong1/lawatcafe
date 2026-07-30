<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredient_deliveries', function (Blueprint $table) {
            // 'confirmed' | 'pending_review' | 'rejected'. Existing rows (all
            // recorded directly by admins, who apply stock immediately with no
            // review gate) default to 'confirmed' to reflect that stock was
            // already applied for them.
            $table->string('status')->default('confirmed')->after('user_id');
            $table->boolean('auto_confirmed')->default(false)->after('status');
            $table->foreignId('reviewed_by')->nullable()->after('auto_confirmed')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('ingredient_deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['status', 'auto_confirmed', 'reviewed_at']);
        });
    }
};
