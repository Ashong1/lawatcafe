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
        Schema::create('purchase_order_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('suggested_quantity', 15, 2);
            $table->decimal('estimated_unit_cost', 10, 2)->nullable();
            $table->decimal('estimated_total_cost', 10, 2)->nullable();
            $table->string('status')->default('draft'); // draft|ordered|dismissed
            $table->text('notes')->nullable();
            $table->string('created_by_actor_type')->default('human'); // ai|human
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['ingredient_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_drafts');
    }
};
