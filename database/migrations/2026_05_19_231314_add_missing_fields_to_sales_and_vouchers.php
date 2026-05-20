<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'amount_received')) {
                $table->decimal('amount_received', 10, 2)->after('total_amount')->nullable();
            }
            if (!Schema::hasColumn('sales', 'order_type')) {
                $table->string('order_type')->default('dine_in')->after('payment_method');
            }
            if (!Schema::hasColumn('sales', 'discount_type')) {
                $table->string('discount_type')->nullable()->after('order_type');
            }
            if (!Schema::hasColumn('sales', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)->default(0)->after('discount_type');
            }
            if (!Schema::hasColumn('sales', 'shift_id')) {
                $table->foreignId('shift_id')->nullable()->after('user_id')->constrained();
            }
        });

        Schema::table('vouchers', function (Blueprint $table) {
            if (!Schema::hasColumn('vouchers', 'sale_id')) {
                $table->foreignId('sale_id')->nullable()->after('id')->constrained()->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->dropColumn(['amount_received', 'order_type', 'discount_type', 'discount_amount', 'shift_id']);
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->dropColumn('sale_id');
        });
    }
};
