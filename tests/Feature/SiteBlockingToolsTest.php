<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Agent\ToolRegistry;
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

        $result = app(BlockSitesTool::class)->execute(['domains' => ['pinayflix.tv', 'https://sulasok.tv/', '???']], User::factory()->make(['name' => 'Owner']));

        $this->assertTrue($result->success);
        $this->assertStringContainsString('pinayflix.tv, sulasok.tv', $result->message);
        $this->assertStringContainsString('Skipped', $result->message);
    }

    public function test_block_sites_with_nothing_valid_fails_without_calling_pihole(): void
    {
        $this->mock(PiholeService::class, fn ($mock) => $mock->shouldNotReceive('blockDomain'));

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
}
