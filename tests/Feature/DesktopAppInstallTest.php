<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesktopAppInstallTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_manifest_has_what_chrome_needs_to_install_the_app(): void
    {
        $m = json_decode(file_get_contents(public_path('manifest.json')), true);

        $this->assertSame('standalone', $m['display']);
        $this->assertSame('/', $m['start_url']);
        $this->assertEqualsCanonicalizing(['192x192', '512x512'], array_column($m['icons'], 'sizes'));
        foreach ($m['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
    }

    public function test_staff_pages_offer_the_install_but_the_guest_portal_does_not(): void
    {
        $this->get('/login')->assertSee('rel="manifest"', false);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('profile.edit'))
            ->assertSee('rel="manifest"', false)->assertSee('Install on this computer')->assertSee('Brave:')->assertSee('Download for Windows')->assertSee('Download for Linux');
        $this->get(route('portal.index'))->assertDontSee('rel="manifest"', false)->assertDontSee('sw.js');
    }

    public function test_the_windows_and_linux_apps_download_only_for_signed_in_accounts(): void
    {
        $this->get(route('app.desktop', 'windows'))->assertRedirect(route('login'));

        $staff = User::factory()->create(['role' => 'staff']);
        foreach (\App\Http\Controllers\DesktopAppController::FILES as $os => $file) {
            if (! \App\Http\Controllers\DesktopAppController::available($os)) {
                $this->markTestSkipped('desktop apps not built on this machine');
            }
            $this->actingAs($staff)->get(route('app.desktop', $os))->assertOk()->assertDownload($file);
        }
        $this->actingAs($staff)->get('/app/desktop/macos')->assertNotFound();
    }

    public function test_the_card_is_hidden_inside_the_desktop_app(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->withHeader('User-Agent', 'Mozilla/5.0 Chrome/130 Electron/33 LawatKapeDesktop/1.0.0')
            ->get(route('profile.edit'))->assertDontSee('Download for Windows');
    }
}
