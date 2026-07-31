<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSettingSubflowsTest extends TestCase
{
    use RefreshDatabase;

    // --- Store preferences (admin-or-above) ---

    public function test_admin_can_view_store_preferences(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Setting::set('receipt_header', 'Welcome!');

        $this->actingAs($admin)->get(route('admin.settings.store'))
            ->assertOk()
            ->assertViewHas('settings', fn ($settings) => $settings['receipt_header'] === 'Welcome!');
    }

    public function test_admin_can_update_store_preferences(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.settings.store.update'), [
            'low_stock_threshold' => 250,
            'store_open_time' => '07:00',
            'store_close_time' => '21:00',
            'receipt_header' => 'Salamat po!',
        ])->assertRedirect();

        $this->assertSame('250', Setting::get('low_stock_threshold'));
        $this->assertSame('Salamat po!', Setting::get('receipt_header'));
    }

    public function test_updating_store_preferences_replaces_the_qr_code_and_deletes_the_old_one(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        Storage::disk('public')->put('qrcodes/old.png', 'old-contents');
        Setting::set('payment_qr_code', 'qrcodes/old.png');

        $this->actingAs($admin)->post(route('admin.settings.store.update'), [
            'payment_qr_code' => UploadedFile::fake()->create('new-qr.png', 10, 'image/png'),
        ])->assertRedirect();

        Storage::disk('public')->assertMissing('qrcodes/old.png');
        $newPath = Setting::get('payment_qr_code');
        $this->assertNotSame('qrcodes/old.png', $newPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_staff_cannot_view_or_update_store_preferences(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get(route('admin.settings.store'))->assertRedirect(route('staff.dashboard'));
        $this->actingAs($staff)->post(route('admin.settings.store.update'), [])->assertRedirect(route('staff.dashboard'));
    }

    // --- Network settings (super_admin only) ---

    public function test_super_admin_can_view_network_settings(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        Setting::set('network_ignored_ips', '10.0.0.1');
        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
        });

        $this->actingAs($superAdmin)->get(route('admin.settings.network'))
            ->assertOk()
            ->assertViewHas('settings', fn ($settings) => $settings['network_ignored_ips'] === '10.0.0.1');
    }

    public function test_super_admin_can_update_network_settings(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)->post(route('admin.settings.network.update'), [
            'network_ignored_ips' => '192.168.1.1,192.168.1.2',
            'opnsense_zone' => '1',
        ])->assertRedirect();

        $this->assertSame('192.168.1.1,192.168.1.2', Setting::get('network_ignored_ips'));
        $this->assertSame('1', Setting::get('opnsense_zone'));
    }

    public function test_plain_admin_cannot_reach_network_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.settings.network'))->assertRedirect(route('dashboard'));
        $this->actingAs($admin)->post(route('admin.settings.network.update'), [])->assertRedirect(route('dashboard'));
    }

    // --- Agent permission tiers (super_admin only) ---

    public function test_super_admin_can_view_agent_permission_tiers(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)->get(route('admin.settings.agent'));

        $response->assertOk();
        $tools = collect($response->viewData('tools'));
        $this->assertTrue($tools->contains('name', 'checkMySession'));
        $checkMySession = $tools->firstWhere('name', 'checkMySession');
        $this->assertTrue($checkMySession['configurable']);
        $generateVoucherBatch = $tools->firstWhere('name', 'generateVoucherBatch');
        $this->assertFalse($generateVoucherBatch['configurable']);
    }

    public function test_super_admin_can_override_a_configurable_tools_tier(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)->post(route('admin.settings.agent.update'), [
            'tiers' => ['checkMySession' => 'confirm'],
        ])->assertRedirect();

        $response = $this->actingAs($superAdmin)->get(route('admin.settings.agent'));
        $tools = collect($response->viewData('tools'));
        $this->assertSame('confirm', $tools->firstWhere('name', 'checkMySession')['tier']);
    }

    public function test_overriding_an_admin_only_tools_tier_is_silently_ignored(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)->post(route('admin.settings.agent.update'), [
            'tiers' => ['generateVoucherBatch' => 'auto'],
        ])->assertRedirect();

        $response = $this->actingAs($superAdmin)->get(route('admin.settings.agent'));
        $tools = collect($response->viewData('tools'));
        $this->assertSame('admin_only', $tools->firstWhere('name', 'generateVoucherBatch')['tier']);
    }

    public function test_update_agent_permissions_rejects_an_invalid_tier_value(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)->post(route('admin.settings.agent.update'), [
            'tiers' => ['checkMySession' => 'not-a-real-tier'],
        ])->assertSessionHasErrors('tiers.checkMySession');
    }

    public function test_plain_admin_cannot_reach_agent_permission_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.settings.agent'))->assertRedirect(route('dashboard'));
        $this->actingAs($admin)->post(route('admin.settings.agent.update'), [])->assertRedirect(route('dashboard'));
    }
}
