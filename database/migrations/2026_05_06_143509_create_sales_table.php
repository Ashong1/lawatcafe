<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique(); // e.g., TRN-2026-001
            $table->decimal('total_amount', 10, 2);
            $table->string('payment_method')->default('Cash'); // Cash, GCash, etc.
            $table->foreignId('user_id')->constrained(); // Who made the sale
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
