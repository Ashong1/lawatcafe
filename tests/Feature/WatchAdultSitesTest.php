<?php

namespace Tests\Feature;

use App\Console\Commands\WatchAdultSites;
use App\Models\User;
use App\Notifications\SystemAlert;
use App\Services\AdultSiteDetector;
use App\Services\PiholeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WatchAdultSitesTest extends TestCase
{
    use RefreshDatabase;

    public static function domainProvider(): array
    {
        return [
            'preset site' => ['pornhub.com', true],
            'preset sub-domain' => ['www.pornhub.com', true],
            // Both seen live, both on no list — the reason for the keyword match.
            'local site by keyword' => ['pinayflix.tv', true],
            'local site 2' => ['sulasok.tv', true],
            'keyword anywhere' => ['cdn.freexxxstream.net', true],
            'ordinary site' => ['shopee.ph', false],
            'google' => ['www.google.com', false],
            'innocent sex substring' => ['www.essex.ac.uk', false],
            'innocent sussex' => ['sussex.com', false],
        ];
    }

    #[DataProvider('domainProvider')]
    public function test_detector_classifies_domains(string $domain, bool $adult): void
    {
        $this->assertSame($adult, app(AdultSiteDetector::class)->isAdult($domain));
    }

    public function test_site_for_groups_sub_domains_and_country_slds(): void
    {
        $d = app(AdultSiteDetector::class);

        $this->assertSame('pornhub.com', $d->siteFor('cdn1.www.pornhub.com'));
        $this->assertSame('example.com.ph', $d->siteFor('www.example.com.ph'));
    }

    private function fakeQueries(array $queries): void
    {
        $this->mock(PiholeService::class, fn ($m) => $m->shouldReceive('queriesSince')->andReturn($queries));
    }

    private function q(string $domain, string $client = '192.168.2.111', string $status = 'FORWARDED'): array
    {
        return ['time' => time(), 'domain' => $domain, 'client' => $client, 'status' => $status];
    }

    public function test_an_allowed_adult_lookup_notifies_admins_with_a_block_link(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->fakeQueries([$this->q('pinayflix.tv'), $this->q('www.pinayflix.tv'), $this->q('shopee.ph')]);

        $this->artisan('network:watch-adult-sites')->assertSuccessful();

        Notification::assertSentToTimes($admin, SystemAlert::class, 1);
        Notification::assertNotSentTo($staff, SystemAlert::class);
        Notification::assertSentTo($admin, SystemAlert::class, function ($n) use ($admin) {
            $data = $n->toArray($admin);

            return str_contains($data['title'], 'not blocked')
                && str_contains($data['message'], 'pinayflix.tv')
                && str_contains($data['url'], 'domain=pinayflix.tv');
        });
    }

    public function test_a_blocked_attempt_is_reported_as_blocked(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->fakeQueries([$this->q('pornhub.com', status: 'DENYLIST')]);

        $this->artisan('network:watch-adult-sites')->assertSuccessful();

        Notification::assertSentTo($admin, SystemAlert::class, fn ($n) => str_contains($n->toArray($admin)['title'], 'Blocked'));
    }

    public function test_the_same_device_and_site_alerts_only_once_per_cooldown(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->fakeQueries([$this->q('pinayflix.tv')]);
        $this->artisan('network:watch-adult-sites');
        $this->artisan('network:watch-adult-sites');

        Notification::assertSentToTimes($admin, SystemAlert::class, 1);
    }

    public function test_infrastructure_devices_are_ignored(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $infra = config('services.opnsense.ip');

        $this->fakeQueries([$this->q('pinayflix.tv', client: $infra), $this->q('pinayflix.tv', client: '127.0.0.1')]);
        $this->artisan('network:watch-adult-sites');

        Notification::assertNothingSent();
        $this->assertNotNull(Cache::get(WatchAdultSites::CURSOR_KEY));
    }
}
