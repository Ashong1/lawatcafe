<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingInfrastructureIpsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression: OPNsense's own LAN IP was previously only excluded from
     * guest counts if an admin remembered to also add it to the freeform
     * network_infrastructure_ips setting — omitting it caused the router to
     * count itself as an "active guest" on the dashboard (2026-07-30).
     */
    public function test_configured_opnsense_ip_is_always_included_even_if_not_in_the_setting(): void
    {
        config(['services.opnsense.ip' => '192.168.2.251']);
        Setting::set('network_infrastructure_ips', '192.168.2.4,192.168.2.5');

        $ips = Setting::infrastructureIps();

        $this->assertContains('192.168.2.251', $ips);
        $this->assertContains('192.168.2.4', $ips);
        $this->assertContains('192.168.2.5', $ips);
    }

    public function test_it_does_not_duplicate_the_opnsense_ip_if_already_present(): void
    {
        config(['services.opnsense.ip' => '192.168.2.251']);
        Setting::set('network_infrastructure_ips', '192.168.2.4,192.168.2.251');

        $ips = Setting::infrastructureIps();

        $this->assertCount(2, $ips);
    }

    public function test_it_falls_back_to_the_documented_default_list_when_unset(): void
    {
        config(['services.opnsense.ip' => null]);

        $ips = Setting::infrastructureIps();

        $this->assertContains('192.168.2.4', $ips);
        $this->assertContains('192.168.2.100', $ips);
    }
}
