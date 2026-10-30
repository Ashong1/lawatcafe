<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Oct 30: phones kept getting the old app from a cache; new builds must always come fresh. */
class AppDownloadCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_downloads_are_never_cached_and_links_change_per_build(): void
    {
        $owner = User::factory()->create(['role' => 'admin']);

        $this->actingAs($owner)->get(route('app.android'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($owner)->get(route('app.desktop', 'linux'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        $this->actingAs($owner)->get(route('profile.edit'))
            ->assertSee('/app/android?v=', false)->assertSee('/app/desktop/linux?v=', false)->assertSee('.deb (Ubuntu)');
    }
}
