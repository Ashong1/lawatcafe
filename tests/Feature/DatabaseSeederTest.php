<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The repository is public, so seeding must never carry a password in source:
 * accounts come from .env or get a generated password printed once.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_install_gets_one_account_per_role_and_reseeding_is_safe(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        foreach (['super_admin', 'admin', 'staff'] as $role) {
            $this->assertSame(1, User::where('role', $role)->count(), $role);
        }
        $this->assertTrue(User::where('email', 'admin@example.com')->exists());
    }

    public function test_no_password_is_written_in_the_seeding_code(): void
    {
        foreach (['database/seeders/DatabaseSeeder.php', 'database/migrations/2026_07_27_150148_promote_asherlimbo_to_super_admin.php'] as $file) {
            $source = file_get_contents(base_path($file));
            $this->assertDoesNotMatchRegularExpression("/(bcrypt|Hash::make)\\(\\s*['\"]/", $source, "{$file} hardcodes a password");
        }
    }
}
