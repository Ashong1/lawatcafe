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
        Schema::create('ingredient_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_name');
            $table->timestamp('delivery_date');
            $table->decimal('total_cost', 10, 2)->default(0);
            $table->string('reference_number')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredient_deliveries');
    }
};
