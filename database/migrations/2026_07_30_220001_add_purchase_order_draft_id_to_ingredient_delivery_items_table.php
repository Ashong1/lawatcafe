<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredient_delivery_items', function (Blueprint $table) {
            // Null when this line item wasn't matched to a sent purchase order
            // (or the delivery was recorded by an admin, who bypasses matching).
            $table->foreignId('purchase_order_draft_id')->nullable()->after('ingredient_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ingredient_delivery_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_draft_id');
        });
    }
};
