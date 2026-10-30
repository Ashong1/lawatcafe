<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What kind of stock movement each row is, so the stock report can total
 * received / used / wasted / adjusted without parsing the free-text reason.
 * Existing rows are classified from the reason prefixes the app has written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->string('type', 20)->default('adjustment')->after('reason')->index();
        });

        DB::table('inventory_logs')->where('reason', 'like', 'Sale:%')->update(['type' => 'sale']);
        DB::table('inventory_logs')->where('reason', 'like', 'Wastage%')->update(['type' => 'wastage']);
        DB::table('inventory_logs')->where(fn ($q) => $q->where('reason', 'like', 'Supplier Delivery%')
            ->orWhere('reason', 'Quick Add')->orWhere('reason', 'AI Agent Restock'))->update(['type' => 'delivery']);
    }

    public function down(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
