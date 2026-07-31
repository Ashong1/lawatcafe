<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sale_items')) {
            Schema::create('sale_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sale_id')->constrained()->onDelete('cascade');
                $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
                $table->integer('quantity');
                $table->decimal('price', 10, 2);
                $table->string('kds_status')->default('pending');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
