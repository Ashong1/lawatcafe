<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Agent\ToolRegistry;
use App\Services\Agent\Tools\BareSiteResolver;
use App\Services\Agent\Tools\BlockSitesTool;
use App\Services\Agent\Tools\ListBlockedSitesTool;
use App\Services\Agent\Tools\SiteDomains;
use App\Services\PiholeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Asked "can you block these sites" with a photo of a list, Barista AI
 * replied it had no tool for blocking websites and offered to ban a device
 * by MAC instead. The Site Blocking page could always do it; the AI couldn't.
 */
class SiteBlockingToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_and_super_admins_get_the_site_tools_but_staff_and_guests_do_not(): void
    {
        $registry = app(ToolRegistry::class);

        foreach ([ToolRegistry::AUDIENCE_ADMIN, ToolRegistry::AUDIENCE_SUPER_ADMIN] as $audience) {
            $names = array_keys($registry->forAudience($audience));
            $this->assertContains('blockSites', $names);
            $this->assertContains('unblockSites', $names);
            $this->assertContains('listBlockedSites', $names);
        }

        foreach ([ToolRegistry::AUDIENCE_STAFF, ToolRegistry::AUDIENCE_GUEST] as $audience) {
            $this->assertNotContains('blockSites', array_keys($registry->forAudience($audience)));
        }
    }

    public function test_blocking_needs_a_human_to_confirm(): void
    {
        $this->assertSame('admin_only', app(BlockSitesTool::class)->permissionTier());
    }

    public function test_domains_read_off_a_screenshot_are_cleaned_up(): void
    {
        [$valid, $invalid] = SiteDomains::parse([
            'https://www.PinayFlix.tv/watch?v=1', 'sulasok.tv', 'sulasok.tv', 'not a domain', '',
        ]);

        $this->assertSame(['pinayflix.tv', 'sulasok.tv'], $valid);
        $this->assertSame(['not a domain'], $invalid);
    }

    public function test_block_sites_blocks_each_valid_domain(): void
    {
        $this->mock(PiholeService::class, function ($mock) {
            $mock->shouldReceive('blockDomain')->with('pinayflix.tv', \Mockery::any())->once()->andReturn(true);
            $mock->shouldReceive('blockDomain')->with('sulasok.tv', \Mockery::any())->once()->andReturn(true);
        });

        $this->fakeDns([]);
        $result = app(BlockSitesTool::class)->execute(['domains' => ['pinayflix.tv', 'https://sulasok.tv/', '???']], User::factory()->make(['name' => 'Owner']));

        $this->assertTrue($result->success);
        $this->assertStringContainsString('pinayflix.tv, sulasok.tv', $result->message);
        $this->assertStringContainsString('Could not find a website for: ???', $result->message);
    }

    public function test_block_sites_with_nothing_valid_fails_without_calling_pihole(): void
    {
        $this->mock(PiholeService::class, fn ($mock) => $mock->shouldNotReceive('blockDomain'));
        $this->fakeDns([]);

        $this->assertFalse(app(BlockSitesTool::class)->execute(['domains' => ['hello world']], null)->success);
    }

    public function test_list_blocked_sites_reports_entries_and_the_adult_list(): void
    {
        $this->mock(PiholeService::class, function ($mock) {
            $mock->shouldReceive('blockedDomains')->andReturn([
                ['domain' => 'pornhub.com', 'comment' => null, 'enabled' => true],
                ['domain' => 'roblox.com', 'comment' => null, 'enabled' => false],
            ]);
            $mock->shouldReceive('adultList')->andReturn(['enabled' => true, 'domains' => 76793]);
        });

        $message = app(ListBlockedSitesTool::class)->execute([], null)->message;

        $this->assertStringContainsString('Blocked: pornhub.com', $message);
        $this->assertStringContainsString('switched off: roblox.com', $message);
        $this->assertStringContainsString('ON (76,793 sites)', $message);
    }

    /** Fake DNS: only $existing resolve — plus every *.ph, mimicking that registry's wildcard. */
    private function fakeDns(array $existing): void
    {
        $this->app->instance(BareSiteResolver::class, new BareSiteResolver(
            fn (array $hosts) => array_values(array_filter($hosts, fn ($h) => in_array($h, $existing, true) || str_ends_with($h, '.ph')))
        ));
    }

    /**
     * Live report: the photo showed site names, not domains, so the model
     * passed "sulasok", "beeg"… and every one was rejected, blocking nothing.
     */
    public function test_bare_site_names_are_looked_up_and_blocked(): void
    {
        $this->fakeDns(['sulasok.tv', 'sulasok.run', 'beeg.com']);
        $this->mock(PiholeService::class, function ($mock) {
            foreach (['sulasok.tv', 'sulasok.run', 'beeg.com'] as $d) {
                $mock->shouldReceive('blockDomain')->with($d, \Mockery::any())->once()->andReturn(true);
            }
        });

        $result = app(BlockSitesTool::class)->execute(['domains' => ['sulasok', 'Beeg', 'zzznotasite']], null);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('sulasok → sulasok.tv, sulasok.run', $result->message);
        $this->assertStringContainsString('Could not find a website for: zzznotasite', $result->message);
    }

    /** .ph and .com.ph answer for ANY name (registry wildcard) — they must not count as a match. */
    public function test_wildcard_endings_are_ignored(): void
    {
        $this->fakeDns(['beeg.com']); // fakeDns also makes every *.ph resolve, like the real registry

        $found = app(BareSiteResolver::class)->resolve(['beeg']);

        $this->assertSame(['beeg.com'], $found['beeg']);
    }
}
