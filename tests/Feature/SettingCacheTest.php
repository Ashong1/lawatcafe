<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Regression: Setting::get used to cache the *default* alongside the value,
 * rememberForever. The first caller to ask for an unset key froze its own
 * fallback for everyone, and no code change could dislodge it. This is how
 * portal_browse_url resolved to neverssl.com long after the code stopped
 * saying so.
 */
class SettingCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('setting.unset_key');
    }

    public function test_one_callers_default_does_not_become_every_callers_value(): void
    {
        $this->assertSame('first-default', Setting::get('unset_key', 'first-default'));
        $this->assertSame('second-default', Setting::get('unset_key', 'second-default'));
        $this->assertNull(Setting::get('unset_key'));
    }

    public function test_a_real_stored_value_still_wins_over_the_default(): void
    {
        Setting::set('unset_key', 'stored');

        $this->assertSame('stored', Setting::get('unset_key', 'ignored-default'));
    }

    public function test_a_stored_value_is_still_cached_rather_than_requeried(): void
    {
        Setting::set('unset_key', 'stored');
        Setting::get('unset_key');

        // Delete underneath the cache; the cached value must survive, proving
        // the read is genuinely served from cache and not hitting the DB.
        Setting::where('key', 'unset_key')->delete();

        $this->assertSame('stored', Setting::get('unset_key'));
    }
}
