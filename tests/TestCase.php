<?php

namespace Tests;

use App\Services\AiBudget;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

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

        // Start with OpenRouter's model catalog "unknown" (cached empty), so
        // no test fetches it by accident: a catalog request would also eat
        // the first item of any wildcard Http::sequence() a test scripts.
        // Discovery tests Cache::flush() and fake /models on purpose.
        Cache::put('openrouter_catalog', [], 86400);

        // Never read the real OpenRouter account's allowance from a test: the
        // .env key is live, and its count would make scheduled-job tests pass
        // or fail with the time of day. "Unknown" never blocks a job; the
        // quota flag in the cache still applies. AiDailyQuotaTest exercises
        // the real AiBudget against a faked endpoint.
        $this->app->instance(AiBudget::class, new class extends AiBudget
        {
            public function dailyRequestsRemaining(): ?int
            {
                return null;
            }
        });

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
