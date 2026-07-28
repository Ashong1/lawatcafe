<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Data migration for the new super_admin role: promotes the existing
     * asherlimbo@gmail.com account (seeded as 'admin') to 'super_admin', and
     * creates a new admin@gmail.com account as the actual coffee shop owner.
     * Idempotent — safe to run against a database that's already in either state.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('email', 'asherlimbo@gmail.com')
            ->update(['role' => 'super_admin']);

        if (!DB::table('users')->where('email', 'admin@gmail.com')->exists()) {
            DB::table('users')->insert([
                'name' => 'Admin',
                'email' => 'admin@gmail.com',
                'password' => Hash::make('adminpass1234'),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('users')
            ->where('email', 'asherlimbo@gmail.com')
            ->update(['role' => 'admin']);

        DB::table('users')->where('email', 'admin@gmail.com')->delete();
    }
};
