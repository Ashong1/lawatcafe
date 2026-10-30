<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Models\Voucher;
use App\Services\BackupService;
use App\Services\InternetSpeedTest;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/** The minor findings left from the pre-defense review (Oct 30). */
class MinorReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_now_answers_at_once_and_measures_after_the_response(): void
    {
        $this->mock(InternetSpeedTest::class, fn ($m) => $m->shouldReceive('run')->once()->andReturn(['down' => 30.0, 'up' => 10.0, 'at' => now()->toIso8601String()]));

        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('network.traffic.speed-test'))
            ->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'about 15 seconds'));
    }

    public function test_a_refund_below_the_free_wifi_minimum_cancels_unused_free_codes(): void
    {
        Setting::set('free_wifi_min_amount', '200');
        $owner = User::factory()->create(['role' => 'admin']);
        $shift = Shift::create(['user_id' => $owner->id, 'starting_cash' => 0, 'status' => 'open', 'opened_at' => now()]);
        $sale = Sale::create(['transaction_number' => 'TRN-1', 'shift_id' => $shift->id, 'user_id' => $owner->id, 'total_amount' => 200, 'payment_method' => 'Cash', 'status' => 'completed']);
        $line = $sale->items()->create(['item_name' => 'Latte', 'quantity' => 2, 'price' => 100, 'type' => 'product']);
        Voucher::create(['code' => 'FREE-UNUSED', 'duration_minutes' => 60, 'tier' => 'free', 'is_used' => false, 'sale_id' => $sale->id]);
        Voucher::create(['code' => 'FREE-USED', 'duration_minutes' => 60, 'tier' => 'free', 'is_used' => true, 'used_at' => now(), 'sale_id' => $sale->id]);

        app(SaleService::class)->refund($sale, [$line->id => 1], 'too sweet', $owner);

        $this->assertNull(Voucher::where('code', 'FREE-UNUSED')->first());
        $this->assertNotNull(Voucher::where('code', 'FREE-USED')->first());
    }

    public function test_no_false_drawer_alert_while_another_shift_is_open(): void
    {
        Notification::fake();
        $a = User::factory()->create(['role' => 'staff']);
        $b = User::factory()->create(['role' => 'staff']);
        User::factory()->create(['role' => 'admin']);
        Shift::create(['user_id' => $a->id, 'starting_cash' => 300, 'expected_cash' => 900, 'ending_cash' => 900, 'status' => 'closed', 'opened_at' => now()->subHours(9), 'closed_at' => now()->subHours(8)]);
        Shift::create(['user_id' => $a->id, 'starting_cash' => 900, 'status' => 'open', 'opened_at' => now()->subHour()]);

        $this->actingAs($b)->post(route('shift.start'), ['starting_cash' => 500])->assertSessionHasNoErrors();
        Notification::assertNothingSent();
    }

    public function test_best_sellers_say_they_are_at_menu_price(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('reports.sales'))->assertSee('at menu price');
    }

    public function test_the_security_page_lists_the_newest_backups_and_hourly_wording(): void
    {
        $dir = storage_path('framework/testing/backups-minor');
        File::ensureDirectoryExists($dir);
        foreach (range(0, 29) as $h) {
            File::put($dir.'/lawat_db_2026-10-'.(10 + intdiv($h, 24)).'_'.sprintf('%02d', $h % 24).'0000.sql.gz', gzencode('-- dump'));
        }
        $this->mock(BackupService::class)->makePartial()->shouldReceive('dir')->andReturn($dir);

        $page = $this->actingAs(User::factory()->create(['role' => 'super_admin']))->get(route('security'))->assertOk();
        $page->assertDontSee('every night')->assertDontSee("tonight's")->assertSee('Oct 11, 2026 5:00 AM');
        $this->assertSame(24, substr_count($page->getContent(), 'id="restore-'));
        File::deleteDirectory($dir);
    }

    public function test_scheduled_backups_only_log_failures(): void
    {
        $dir = storage_path('framework/testing/backups-log');
        File::ensureDirectoryExists($dir);
        $this->mock(BackupService::class)->makePartial()->shouldReceive('dir')->andReturn($dir);
        config(['database.connections.mysql.database' => 'lawat_db']);
        Process::fake(function ($process) {
            if (preg_match("/> '([^']+\\.sql\\.gz)'/", implode(' ', (array) $process->command), $m)) {
                File::put($m[1], str_repeat('x', 200));
            }

            return Process::result();
        });

        app(BackupService::class)->create(null);
        $this->assertSame(0, ActivityLog::where('event', 'backup_made')->count());
        app(BackupService::class)->create(User::factory()->create(['role' => 'admin']));
        $this->assertSame(1, ActivityLog::where('event', 'backup_made')->count());
        File::deleteDirectory($dir);
    }

    public function test_escape_on_a_confirmation_does_not_close_the_form_underneath(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('inventory.wastage.index'))
            ->assertSee("document.querySelector('.swal2-container')", false);
    }
}
