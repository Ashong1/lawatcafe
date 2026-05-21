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
        Schema::table('ingredients', function (Blueprint $table) {
            $table->decimal('current_stock', 20, 2)->change();
        });

        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->decimal('change_amount', 20, 2)->change();
            $table->decimal('after_amount', 20, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->decimal('current_stock', 10, 2)->change();
        });

        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->decimal('change_amount', 10, 2)->change();
            $table->decimal('after_amount', 10, 2)->change();
        });
    }
};
