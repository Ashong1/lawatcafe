<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks which categories are food rather than drink.
 *
 * The POS pairing suggestion is meant to offer a pastry alongside a drink and a
 * drink alongside a pastry. Nothing in the schema could express that: the
 * fallback could only pick "a product from some other category", which on this
 * menu meant a Classic Latte suggested a Matcha Latte — a different category,
 * still a second drink, and a poor thing to offer someone who just ordered one.
 *
 * A boolean rather than a free-text kind: the only distinction the pairing
 * actually needs is "is this the same sort of thing as what they just ordered",
 * and two values answer that without asking an admin to invent a taxonomy.
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
