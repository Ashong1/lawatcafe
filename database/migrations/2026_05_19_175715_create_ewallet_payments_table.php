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
        Schema::create('ewallet_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('sender_details')->nullable();
            $table->boolean('is_used')->default(false);
            $table->timestamp('email_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ewallet_payments');
    }
};
