<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Super Admin Account — the single, fixed system/technical administrator.
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'asherlimbo@gmail.com',
            'password' => bcrypt('password1234'),
            'role' => 'super_admin',
        ]);

        // Admin Account — the coffee shop owner.
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('adminpass1234'),
            'role' => 'admin',
        ]);

        // Staff Account
        User::factory()->create([
            'name' => 'Staff',
            'email' => '0323-3659@lspu.edu.ph',
            'password' => bcrypt('password1234'),
            'role' => 'staff',
        ]);
    }
}
