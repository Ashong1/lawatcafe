<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

// Standard: Feature tests use RefreshDatabase (per-class, not enforced here), not
// DatabaseTransactions — phpunit.xml runs against an in-memory sqlite connection,
// which DatabaseTransactions alone can't rely on having migrations already applied to.
abstract class TestCase extends BaseTestCase
{
    //
}
