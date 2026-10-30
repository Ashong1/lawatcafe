<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One Z-Read per business day: its number never repeats or goes back, and it
 * keeps a snapshot of the day's totals and the running grand total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('z_readings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('number')->unique();
            $table->date('business_date')->unique();
            $table->decimal('grand_total', 14, 2);
            $table->string('first_transaction')->nullable();
            $table->string('last_transaction')->nullable();
            $table->json('totals');
            $table->foreignId('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('z_readings');
    }
};
