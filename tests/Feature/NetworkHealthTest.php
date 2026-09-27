<?php

namespace Tests\Feature;

use App\Models\NetworkHealthCheck;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\SystemAlert;
use App\Services\Agent\CrossDomainCorrelationService;
use App\Services\Agent\ToolRegistry;
use App\Services\AIService;
use App\Services\GhostDeviceDetectionService;
use App\Services\GuestSessionService;
use App\Services\LinkCapacityLearner;
use App\Services\NetworkHealthService;
use App\Services\OpnSenseService;
use App\Services\PiholeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Owner (a network administration major): make the system, especially the AI,
 * focused on managing the shop's whole network. NetworkHealthService is the
 * one definition of "healthy" behind the AI tool, the prompt, the alerts,
 * the monitoring signals and the Network > Health page.
 */
class NetworkHealthTest extends TestCase
{
    use RefreshDatabase;

    private array $pings = [];

    private ?string $dnsAnswer = '142.250.1.1';

    private array $leases = [];

    private array $arp = [];

    private array $ghosts = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.opnsense.ip' => '192.168.2.251', 'services.pihole.url' => 'http://192.168.2.4']);
        Setting::set('network_infrastructure_ips', '192.168.2.1');
        Http::fake(['127.0.0.1/*' => Http::response('portal', 200)]);
        $this->pings = ['1.1.1.1' => ['loss' => 0.0, 'avg' => 8.0], '8.8.8.8' => ['loss' => 0.0, 'avg' => 10.0], '192.168.2.1' => ['loss' => 0.0, 'avg' => 1.0], '192.168.2.251' => ['loss' => 0.0, 'avg' => 0.5]];
    }

    private function service(): NetworkHealthService
    {
        $opn = $this->mock(OpnSenseService::class, function ($m) {
            $m->shouldReceive('getGatewayStatus')->andReturn(['gateways' => [['name' => 'WAN_DHCP', 'status' => 'online']]]);
            $m->shouldReceive('getDhcpPools')->andReturn([['start' => ip2long('192.168.2.110'), 'end' => ip2long('192.168.2.119'), 'label' => '192.168.2.110-192.168.2.119']]);
            $m->shouldReceive('getDhcpLeases')->andReturnUsing(fn () => $this->leases);
            $m->shouldReceive('getArpTable')->andReturnUsing(fn () => $this->arp);
            $m->shouldReceive('listSessions')->andReturn([]);
        });
        $pihole = $this->mock(PiholeService::class, fn ($m) => $m->shouldReceive('summary')->andReturn([
            'blocking' => true, 'queries' => 100, 'blocked' => 5, 'percent_blocked' => 5.0, 'clients' => 3, 'domains_on_blocklists' => 1000, 'top_blocked' => [],
        ]));
        $guests = $this->mock(GuestSessionService::class, fn ($m) => $m->shouldReceive('activeGuestCount')->andReturn(2));
        $ghosts = $this->mock(GhostDeviceDetectionService::class, fn ($m) => $m->shouldReceive('detect')->andReturnUsing(fn () => collect($this->ghosts)));
        $capacity = $this->mock(LinkCapacityLearner::class, fn ($m) => $m->shouldReceive('estimate')->andReturn(['down' => null, 'up' => null, 'samples' => 0, 'informative' => 0, 'learned' => false]));

        $svc = new NetworkHealthService($opn, $pihole, $guests, $ghosts, $capacity,
            fn (array $hosts) => array_intersect_key($this->pings, $hosts) + array_map(fn () => ['loss' => 100.0, 'avg' => null], $hosts),
            fn (string $server, string $name) => $this->dnsAnswer,
        );
        $this->app->instance(NetworkHealthService::class, $svc);

        return $svc;
    }

    public function test_a_healthy_network_is_all_ok(): void
    {
        $r = $this->service()->run();

        $this->assertSame('ok', $r['overall']);
        $this->assertSame('Internet OK — 9 ms, no packet loss.', $r['checks']['internet']['summary']);
        $this->assertStringContainsString('2 guest(s) online', $r['checks']['portal']['summary']);
    }

    public function test_no_internet_fails_and_packet_loss_warns(): void
    {
        $this->pings['1.1.1.1'] = $this->pings['8.8.8.8'] = ['loss' => 100.0, 'avg' => null];
        $this->assertSame('fail', $this->service()->run()['checks']['internet']['status']);

        $this->pings['1.1.1.1'] = ['loss' => 25.0, 'avg' => 30.0];
        $this->pings['8.8.8.8'] = ['loss' => 25.0, 'avg' => 30.0];
        $this->assertSame('warn', $this->service()->run()['checks']['internet']['status']);
    }

    public function test_dns_not_answering_fails(): void
    {
        $this->dnsAnswer = null;

        $check = $this->service()->run()['checks']['dns'];

        $this->assertSame('fail', $check['status']);
        $this->assertStringContainsString('no internet', $check['summary']);
    }

    public function test_dhcp_pool_thresholds(): void
    {
        $lease = fn ($n) => ['address' => "192.168.2.1{$n}", 'state' => '0'];
        $this->leases = array_map($lease, range(10, 18)); // 9 of 10
        $this->assertSame('warn', $this->service()->run()['checks']['dhcp']['status']);

        $this->leases = array_map($lease, range(10, 19)); // 10 of 10
        $this->assertSame('fail', $this->service()->run()['checks']['dhcp']['status']);

        $this->leases = [['address' => '192.168.2.110', 'state' => '2']]; // expired leases don't count
        $this->assertSame('ok', $this->service()->run()['checks']['dhcp']['status']);
    }

    public function test_equipment_that_ignores_ping_but_is_in_arp_is_up(): void
    {
        $this->pings['192.168.2.1'] = ['loss' => 100.0, 'avg' => null];
        $this->assertSame('warn', $this->service()->run()['checks']['infrastructure']['status']);

        $this->arp = [['ip' => '192.168.2.1', 'expired' => false]];
        $this->assertSame('ok', $this->service()->run()['checks']['infrastructure']['status']);
    }

    public function test_only_a_banned_device_back_on_the_lan_is_a_warning(): void
    {
        $this->ghosts = [['mac_address' => 'AA', 'ip_address' => '192.168.2.130', 'hostname' => '', 'manufacturer' => null, 'seen_via' => ['arp'], 'is_banned' => false]];
        $this->assertSame('ok', $this->service()->run()['checks']['unknown_devices']['status'], 'a guest at the login page is normal');

        $this->ghosts[0]['is_banned'] = true;
        $this->assertSame('warn', $this->service()->run()['checks']['unknown_devices']['status']);

        $this->ghosts = [['mac_address' => 'BB', 'ip_address' => '192.168.254.105', 'hostname' => '', 'manufacturer' => null, 'seen_via' => ['arp'], 'is_banned' => true]];
        $this->assertSame('ok', $this->service()->run()['checks']['unknown_devices']['status'], 'the WAN side is not the shop LAN');
    }

    public function test_the_command_alerts_once_on_failure_and_once_on_recovery(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->service();

        $this->artisan('network:health'); // baseline
        $this->pings['1.1.1.1'] = $this->pings['8.8.8.8'] = ['loss' => 100.0, 'avg' => null];
        $this->artisan('network:health'); // goes down
        $this->artisan('network:health'); // still down: no repeat
        Notification::assertSentToTimes($admin, SystemAlert::class, 1);

        $this->pings['1.1.1.1'] = $this->pings['8.8.8.8'] = ['loss' => 0.0, 'avg' => 9.0];
        $this->artisan('network:health'); // recovers
        Notification::assertSentToTimes($admin, SystemAlert::class, 2);
        $this->assertSame(4, NetworkHealthCheck::count());
    }

    public function test_the_ai_gets_network_tools_and_a_network_first_prompt(): void
    {
        $svc = $this->service();
        $svc->record($svc->run());

        $staff = array_keys(app(ToolRegistry::class)->forAudience(ToolRegistry::AUDIENCE_STAFF));
        foreach (['checkNetworkHealth', 'lookupDevice', 'getTopBandwidthUsers'] as $tool) {
            $this->assertContains($tool, $staff);
        }
        $this->assertContains('getDnsStats', array_keys(app(ToolRegistry::class)->forAudience(ToolRegistry::AUDIENCE_ADMIN)));
        $this->assertNotContains('checkNetworkHealth', array_keys(app(ToolRegistry::class)->forAudience(ToolRegistry::AUDIENCE_GUEST)));

        $prompt = app(AIService::class)->buildAdminSystemPrompt();
        $this->assertStringContainsString('NETWORK LAYOUT', $prompt);
        $this->assertStringContainsString('LIVE NETWORK STATUS', $prompt);
        $this->assertStringContainsString('Internet link: OK', $prompt);
        $this->assertLessThan(strpos($prompt, 'SHOP (secondary)'), strpos($prompt, 'LIVE NETWORK STATUS'));
    }

    public function test_failing_checks_become_monitoring_signals(): void
    {
        $this->dnsAnswer = null;
        $svc = $this->service();
        $svc->record($svc->run());

        $types = array_column(app(CrossDomainCorrelationService::class)->run()['signals'], 'type');

        $this->assertContains('network_dns', $types);
    }

    public function test_the_health_page_works_for_admin_and_staff(): void
    {
        $svc = $this->service();
        $svc->record($svc->run());

        foreach (['admin', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('network.health'))
                ->assertOk()
                ->assertSee('Everything on the network is working.')
                ->assertSee('Internet link');
        }

        $this->getJson(route('network.health.status'))->assertOk()->assertJsonStructure(['checked_at']);
    }
}
