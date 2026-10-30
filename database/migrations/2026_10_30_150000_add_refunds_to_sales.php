<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refunds go through the same owner-approval queue as voids: a request is a
 * 'void' or a 'refund' of some items. The sale keeps the total refunded so
 * reports can take it off net sales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_void_requests', function (Blueprint $table) {
            $table->string('type', 10)->default('void')->after('sale_id')->index();
            $table->decimal('amount', 10, 2)->nullable()->after('type');
            $table->json('items')->nullable()->after('amount');
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('refunded_amount', 10, 2)->default(0)->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sale_void_requests', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'amount', 'items']);
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('refunded_amount');
        });
    }
};
