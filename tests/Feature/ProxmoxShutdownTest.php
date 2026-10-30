<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Only a faked Proxmox is ever called here; nothing may turn a real machine off. */
class ProxmoxShutdownTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    private function connect(): void
    {
        Setting::set('proxmox_url', 'https://192.168.2.2:8006');
        Setting::set('proxmox_node', 'pve02');
        Setting::set('proxmox_token_id', 'root@pam!lawatkape');
        Setting::set('proxmox_token', Crypt::encryptString('secret-uuid'));
    }

    public function test_the_connection_is_saved_with_the_secret_encrypted_and_blank_keeps_it(): void
    {
        $this->actingAs($this->superAdmin())->post(route('proxmox.save'), [
            'url' => 'https://192.168.2.2:8006/', 'node' => 'pve02', 'token_id' => 'root@pam!lawatkape', 'token' => 'secret-uuid',
        ])->assertSessionHas('success');

        $this->assertSame('https://192.168.2.2:8006', Setting::get('proxmox_url'));
        $this->assertSame('secret-uuid', Crypt::decryptString(Setting::get('proxmox_token')));

        $this->actingAs($this->superAdmin())->post(route('proxmox.save'), [
            'url' => 'https://192.168.2.2:8006', 'node' => 'pve02', 'token_id' => 'root@pam!lawatkape', 'token' => '',
        ]);
        $this->assertSame('secret-uuid', Crypt::decryptString(Setting::get('proxmox_token')));
    }

    public function test_the_owner_cannot_see_or_use_the_proxmox_controls(): void
    {
        $owner = User::factory()->create(['role' => 'admin']);
        $this->connect();

        Http::fake();

        $this->actingAs($owner)->post(route('proxmox.save'), ['url' => 'https://x:8006', 'node' => 'n', 'token_id' => 'a@b!c'])->assertRedirect();
        $this->assertSame('pve02', Setting::get('proxmox_node'));
        $this->actingAs($owner)->post(route('proxmox.test'))->assertRedirect();
        $this->actingAs($owner)->post(route('system-power.act', 'shutdown-host'), ['password' => 'password', 'confirm' => 'SHUT DOWN'])->assertForbidden();
        $this->actingAs($owner)->get(route('security'))->assertDontSee('Proxmox connection');
        Http::assertNothingSent();
    }

    public function test_test_connection_only_reads_the_status(): void
    {
        $this->connect();
        Http::fake(['*/api2/json/nodes/pve02/status' => Http::response(['data' => ['uptime' => 3600, 'pveversion' => 'pve-manager/9.0.3']])]);

        $this->actingAs($this->superAdmin())->post(route('proxmox.test'))->assertSessionHas('success');

        Http::assertSent(fn ($r) => $r->method() === 'GET' && $r->header('Authorization')[0] === 'PVEAPIToken=root@pam!lawatkape=secret-uuid');
        Http::assertNotSent(fn ($r) => $r->method() === 'POST');
    }

    public function test_a_wrong_token_is_reported_plainly(): void
    {
        $this->connect();
        Http::fake(['*' => Http::response('', 401)]);

        $this->actingAs($this->superAdmin())->post(route('proxmox.test'))->assertSessionHas('error');
    }

    public function test_shutting_down_the_machine_needs_the_typed_word(): void
    {
        $this->connect();
        Http::fake();

        $this->actingAs($this->superAdmin())->post(route('system-power.act', 'shutdown-host'), ['password' => 'password', 'confirm' => 'shut down'])
            ->assertSessionHasErrors('confirm');
        Http::assertNothingSent();
    }

    public function test_shutting_down_the_machine_is_logged_then_asks_proxmox(): void
    {
        $this->connect();
        Http::fake(['*' => Http::response(['data' => null])]);
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post(route('system-power.act', 'shutdown-host'), ['password' => 'password', 'confirm' => 'SHUT DOWN'])
            ->assertSessionHas('success');

        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/api2/json/nodes/pve02/status') && $r['command'] === 'shutdown');
        $this->assertDatabaseHas('activity_logs', ['event' => 'host_shutting_down', 'user_id' => $admin->id]);
    }

    public function test_without_a_connection_the_machine_is_not_touched(): void
    {
        config(['services.proxmox' => []]);
        Http::fake();

        $this->actingAs($this->superAdmin())->post(route('system-power.act', 'shutdown-host'), ['password' => 'password', 'confirm' => 'SHUT DOWN'])
            ->assertSessionHas('error');
        Http::assertNothingSent();
    }

    public function test_the_env_connection_is_used_and_the_node_found_by_itself(): void
    {
        config(['services.proxmox' => ['host' => '192.168.2.250', 'user' => 'root@pam', 'token_id' => 'laravel-monitor', 'token_secret' => 'env-secret']]);
        Http::fake([
            '*/api2/json/nodes' => Http::response(['data' => [['node' => 'pve02', 'status' => 'online']]]),
            '*/api2/json/nodes/pve02/status' => Http::response(['data' => ['uptime' => 7200]]),
        ]);

        $this->actingAs($this->superAdmin())->post(route('proxmox.test'))->assertSessionHas('success', 'Connected to pve02, up for 2 hours.');

        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://192.168.2.250:8006/') && $r->header('Authorization')[0] === 'PVEAPIToken=root@pam!laravel-monitor=env-secret');
    }

    public function test_with_its_certificate_an_ip_address_is_reached_by_the_node_name_it_was_issued_for(): void
    {
        config(['services.proxmox' => ['host' => '192.168.2.250', 'user' => 'root@pam', 'token_id' => 'laravel-monitor', 'token_secret' => 's']]);
        Setting::set('proxmox_node', 'pve02');
        $ca = app(\App\Services\ProxmoxService::class)->caFile();
        $this->assertFileDoesNotExist($ca, 'a real certificate is saved; this test would remove it');
        file_put_contents($ca, "-----BEGIN CERTIFICATE-----\n");
        Http::fake(['*' => Http::response(['data' => ['uptime' => 3600]])]);

        try {
            $this->actingAs($this->superAdmin())->post(route('proxmox.test'))->assertSessionHas('success');
        } finally {
            unlink($ca);
        }

        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://pve02:8006/api2/json/nodes/pve02/status'));
    }
}
