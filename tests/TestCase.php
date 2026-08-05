<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

// Standard: Feature tests use RefreshDatabase (per-class, not enforced here), not
// DatabaseTransactions — phpunit.xml runs against an in-memory sqlite connection,
// which DatabaseTransactions alone can't rely on having migrations already applied to.
abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run against anything but the in-memory sqlite database.
     *
     * phpunit.xml sets DB_CONNECTION=sqlite / DB_DATABASE=:memory:, but a
     * cached config (bootstrap/cache/config.php, written by `artisan
     * config:cache`) is loaded ahead of those env vars and silently wins — the
     * suite then points at the live MySQL database on this box, where
     * RefreshDatabase would happily run migrate:fresh over real sales,
     * vouchers and users. Fail loudly on the first test instead.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            $this->fail(
                "Tests are pointed at [{$connection}:{$database}], not sqlite::memory:. "
                .'A cached config is overriding phpunit.xml — run `php artisan config:clear` before testing.'
            );
        }
    }
}
