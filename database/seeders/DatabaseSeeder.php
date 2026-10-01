<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * One account per role. Emails and passwords come from .env
     * (SEED_SUPER_ADMIN_EMAIL / SEED_SUPER_ADMIN_PASSWORD, and the same for
     * ADMIN and STAFF). A password left unset is generated and printed once,
     * so no password ever lives in this public repository. Re-running the
     * seeder leaves existing accounts alone.
     */
    public function run(): void
    {
        $accounts = [
            ['role' => 'super_admin', 'name' => 'Super Admin', 'env' => 'SUPER_ADMIN', 'email' => 'superadmin@example.com'],
            ['role' => 'admin', 'name' => 'Admin', 'env' => 'ADMIN', 'email' => 'admin@example.com'],
            ['role' => 'staff', 'name' => 'Staff', 'env' => 'STAFF', 'email' => 'staff@example.com'],
        ];

        foreach ($accounts as $account) {
            $email = env("SEED_{$account['env']}_EMAIL", $account['email']);
            if (User::where('email', $email)->exists()) {
                $this->command?->line("{$account['role']}: {$email} already exists, left unchanged.");

                continue;
            }

            $password = env("SEED_{$account['env']}_PASSWORD") ?: Str::password(16, symbols: false);
            User::factory()->create([
                'name' => $account['name'],
                'email' => $email,
                'password' => Hash::make($password),
                'role' => $account['role'],
            ]);

            $this->command?->info(env("SEED_{$account['env']}_PASSWORD")
                ? "{$account['role']}: {$email} (password from .env)"
                : "{$account['role']}: {$email} / {$password}  (generated, shown once; change it after signing in)");
        }
    }
}
