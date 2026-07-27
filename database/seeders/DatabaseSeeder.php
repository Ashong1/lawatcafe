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
        // Admin Account
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'asherlimbo@gmail.com',
            'password' => bcrypt('password1234'),
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
