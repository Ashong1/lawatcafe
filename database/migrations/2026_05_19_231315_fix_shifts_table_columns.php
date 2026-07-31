<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            if (Schema::hasColumn('shifts', 'start_time') && ! Schema::hasColumn('shifts', 'opened_at')) {
                $table->renameColumn('start_time', 'opened_at');
            }
            if (Schema::hasColumn('shifts', 'end_time') && ! Schema::hasColumn('shifts', 'closed_at')) {
                $table->renameColumn('end_time', 'closed_at');
            }
            if (! Schema::hasColumn('shifts', 'expected_cash')) {
                $table->decimal('expected_cash', 10, 2)->default(0)->after('starting_cash');
            }
            if (! Schema::hasColumn('shifts', 'notes')) {
                $table->text('notes')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            if (Schema::hasColumn('shifts', 'opened_at')) {
                $table->renameColumn('opened_at', 'start_time');
            }
            if (Schema::hasColumn('shifts', 'closed_at')) {
                $table->renameColumn('closed_at', 'end_time');
            }
            $table->dropColumn(['expected_cash', 'notes']);
        });
    }
};
