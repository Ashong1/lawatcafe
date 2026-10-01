<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable: accounts made before usernames existed sign in by email.
            $table->string('username', 30)->nullable()->unique()->after('name');
            // Null while an invited account is waiting for its owner to choose a password.
            $table->timestamp('password_set_at')->nullable()->after('password');
            // Removed staff with sales or shifts on record: kept so reports
            // still show who did what, but they can't sign in.
            $table->timestamp('deactivated_at')->nullable()->after('password_set_at');
        });

        // Every existing account already has a password its owner chose.
        DB::table('users')->whereNull('password_set_at')->update(['password_set_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'password_set_at', 'deactivated_at']);
        });
    }
};
