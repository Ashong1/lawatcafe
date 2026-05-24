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
            $table->string('packaging_unit')->nullable()->after('unit'); // piece, bottle, box, cans, etc.
            $table->decimal('capacity_per_pack', 15, 2)->default(1)->after('packaging_unit'); // How many g, ml, etc. per pack
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn(['packaging_unit', 'capacity_per_pack']);
        });
    }
};
