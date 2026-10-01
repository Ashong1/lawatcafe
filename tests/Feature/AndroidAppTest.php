<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Android app (android/) is offered on the Profile page and downloaded by
 * signed-in staff; pages that print or open new tabs work inside it.
 */
class AndroidAppTest extends TestCase
{
    use RefreshDatabase;

    private string $apk;

    protected function setUp(): void
    {
        parent::setUp();
        // Never the real published file: this suite runs on the live server.
        $this->apk = sys_get_temp_dir().'/lawatkape-test-'.uniqid().'.apk';
        config(['services.android.apk_path' => $this->apk]);
    }

    protected function tearDown(): void
    {
        @unlink($this->apk);
        parent::tearDown();
    }

    public function test_profile_offers_the_app_once_it_is_built(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get(route('profile.edit'))
            ->assertOk()->assertSee("Lawa't Kape for Android", false)->assertSee("hasn't been built", false);

        file_put_contents($this->apk, 'PK fake apk');
        $this->actingAs($staff)->get(route('profile.edit'))->assertSee(route('app.android'), false);
    }

    public function test_the_card_is_hidden_inside_the_app(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14; wv) LawatKapeApp/1.0')
            ->get(route('profile.edit'))
            ->assertOk()->assertDontSee("Lawa't Kape for Android", false);
    }

    public function test_only_signed_in_accounts_can_download_it(): void
    {
        file_put_contents($this->apk, 'PK fake apk');

        $this->get(route('app.android'))->assertRedirect('/login');

        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get(route('app.android'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.android.package-archive')
            ->assertDownload('LawatKape.apk');
    }

    public function test_a_missing_build_is_a_plain_404(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))->get(route('app.android'))->assertNotFound();
    }

    public function test_print_pages_and_batch_print_work_inside_the_app(): void
    {
        foreach (['pos/receipt.blade.php', 'network/print-vouchers-batch.blade.php'] as $view) {
            $this->assertStringContainsString('LawatKapeApp.print()', file_get_contents(resource_path("views/{$view}")), $view);
        }
        $this->assertStringContainsString('window.LawatKapeApp', file_get_contents(resource_path('views/network/vouchers.blade.php')));
    }
}
