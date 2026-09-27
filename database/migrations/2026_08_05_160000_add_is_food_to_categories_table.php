<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks which categories are food rather than drink, so the POS pairing
 * suggestion offers a pastry with a drink (not "some other category", which
 * paired a Classic Latte with a Matcha Latte). A boolean is all the pairing
 * needs — no taxonomy for an admin to invent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_food')->default(false)->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('is_food');
        });
    }
};
