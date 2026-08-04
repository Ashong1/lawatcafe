<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The self-service GCash flow (EwalletPayment, PaymentController, the
 * /network/verifications page) was removed once the system went cash-only —
 * this table was its last leftover, empty ever since.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ewallet_payments');
    }

    public function down(): void
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
};
