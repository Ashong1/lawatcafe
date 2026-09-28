<?php

namespace Tests\Feature;

use App\Console\Commands\CheckNetworkHealth;
use App\Http\Controllers\VoucherController;
use App\Models\PortalEvent;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Services\GhostDeviceDetectionService;
use App\Services\NetworkHealthService;
use App\Services\OpnSenseService;
use App\Services\PortalQuickReplies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Second portal pass: the time-up screen, staff adding time, instant quick
 * answers, slip instructions + join-Wi-Fi QR, unused-code expiry, drop
 * tracking and the Portal report.
 */
class PortalRoundTwoTest extends TestCase
{
    use RefreshDatabase;

    private const IP = '192.168.2.60';

    private const MAC = 'AA:BB:CC:00:11:22';

    private function network(array $sessions = []): MockInterface
    {
        return $this->mock(OpnSenseService::class, function ($m) use ($sessions) {
            $m->shouldReceive('resolveMacForIp')->andReturn(self::MAC);
            $m->shouldReceive('listSessions')->andReturnUsing(fn () => $sessions);
            $m->shouldReceive('getDhcpLeases')->andReturn([]);
            $m->shouldReceive('isProtectedIp')->andReturn(false);
        });
    }

    private function voucher(int $usedMinutesAgo, int $duration = 60, array $extra = []): Voucher
    {
        return Voucher::create(array_merge([
            'code' => 'LAWA-TIME1', 'duration_minutes' => $duration, 'tier' => 'free', 'is_used' => true,
            'used_at' => now()->subMinutes($usedMinutesAgo), 'activated_at' => now()->subMinutes($usedMinutesAgo),
            'ip_address' => self::IP, 'mac_address' => self::MAC,
        ], $extra));
    }

    public function test_a_guest_whose_time_ran_out_is_told_so(): void
    {
        $this->voucher(90);
        $this->network();

        $this->withServerVariables(['REMOTE_ADDR' => self::IP])->get(route('portal.index'))
            ->assertOk()
            ->assertSee('Your Wi-Fi time is up')
            ->assertSee('LAWA-TIME1')
            ->assertSee(route('portal.more-time'), false);

        $this->assertSame(1, PortalEvent::where('type', PortalEvent::TIME_UP)->count());
    }

    public function test_a_code_that_ended_long_ago_is_not_shown(): void
    {
        $this->voucher(60 * 24);
        $this->network();

        $this->withServerVariables(['REMOTE_ADDR' => self::IP])->get(route('portal.index'))
            ->assertDontSee('Your Wi-Fi time is up');
    }

    public function test_staff_add_time_to_a_code_and_expired_time_counts_from_now(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $voucher = $this->voucher(90, 60, ['disconnected_at' => now()]);

        $this->actingAs($staff)->post(route('network.sessions.add-time'), ['voucher_code' => $voucher->code, 'minutes' => 60])
            ->assertSessionHas('success');

        $voucher->refresh();
        $end = $voucher->used_at->copy()->addMinutes($voucher->duration_minutes);
        $this->assertEqualsWithDelta(now()->addMinutes(60)->timestamp, $end->timestamp, 90);
        $this->assertNull($voucher->disconnected_at, 'auto-reconnect must be allowed to bring them back');
        $this->assertSame(1, PortalEvent::where('type', PortalEvent::TIME_ADDED)->count());

        $this->actingAs($staff)->post(route('network.sessions.add-time'), ['voucher_code' => $voucher->code, 'minutes' => 999])
            ->assertSessionHasErrors('minutes');
    }

    public function test_add_time_buttons_show_when_finding_a_guest_by_code(): void
    {
        $this->voucher(90);
        $this->mock(OpnSenseService::class, function ($m) {
            $m->shouldIgnoreMissing([]);
        });
        $this->mock(GhostDeviceDetectionService::class, fn ($m) => $m->shouldReceive('detect')->andReturn(collect()));

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('network.sessions', ['find' => 'lawa-time1']))
            ->assertOk()
            ->assertSee(route('network.sessions.add-time'), false)
            ->assertSee('+1 hr');
    }

    public function test_quick_replies_answer_from_the_shop_settings(): void
    {
        Setting::set('voucher_durations', '{"50":180,"20":60}');
        Setting::set('free_wifi_min_amount', '200');
        Setting::set('store_open_time', '08:00');
        Setting::set('store_close_time', '22:00');

        $replies = collect(app(PortalQuickReplies::class)->all(now()->setTime(9, 0)))->pluck('answer', 'label');

        $this->assertStringContainsString("₱20 — 1 hour\n• ₱50 — 3 hours", $replies['Wi-Fi prices']);
        $this->assertStringContainsString('₱200', $replies['Wi-Fi prices']);
        $this->assertStringContainsString('We are open now.', $replies['Opening hours']);
        $this->assertStringContainsString('wifi.lawatkape.lab', $replies['How do I reconnect?']);

        $this->get(route('portal.index', ['tab' => 'help']))->assertSee('Wi-Fi prices')->assertSee('askQuick(', false);
    }

    public function test_an_unused_code_past_its_use_by_date_is_refused(): void
    {
        Setting::set('voucher_unused_expiry_days', '30');
        $old = Voucher::create(['code' => 'LAWA-OLDIE', 'duration_minutes' => 60, 'tier' => 'free', 'is_used' => false]);
        $old->forceFill(['created_at' => now()->subDays(31)])->save();
        $this->network();

        $this->withServerVariables(['REMOTE_ADDR' => self::IP])->post(route('portal.authenticate'), ['passcode' => 'LAWA-OLDIE'])
            ->assertRedirect(route('portal.index'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'expired'));
        $this->assertFalse($old->fresh()->is_used);

        Setting::set('voucher_unused_expiry_days', '0');
        $this->assertNull($old->fresh()->unusedExpiresAt(), '0 means never');
    }

    public function test_slips_carry_steps_and_a_join_wifi_qr(): void
    {
        Setting::set('wifi_ssid', 'Lawa\'t Kape; 2');
        $voucher = Voucher::create(['code' => 'LAWA-SLIP1', 'duration_minutes' => 60, 'tier' => 'free', 'is_used' => false]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('network.vouchers.print', $voucher))
            ->assertOk()
            ->assertSee('Scan to join the Wi-Fi')
            ->assertSee('Kumonekta sa Wi-Fi')
            ->assertSee('Use by');

        $this->assertNotNull(VoucherController::slipJoinWifi()['joinQr']);
        $escaped = preg_replace('/([\\\\;,:"])/', '\\\\$1', 'Lawa\'t Kape; 2');
        $this->assertSame('Lawa\'t Kape\\; 2', $escaped);
    }

    public function test_a_session_that_vanishes_with_time_left_is_recorded_as_a_drop(): void
    {
        $this->voucher(10, 60);
        $sessions = [['ipAddress' => self::IP.'/32', 'last_accessed' => time() - 600, 'startTime' => time() - 600]];
        $this->mock(OpnSenseService::class, function ($m) use (&$sessions) {
            $m->shouldReceive('isProtectedIp')->andReturn(false);
            $m->shouldReceive('listSessions')->andReturnUsing(function () use (&$sessions) {
                return $sessions;
            });
        });
        $health = $this->mock(NetworkHealthService::class, function ($m) {
            $m->shouldReceive('run')->andReturn(['checked_at' => now()->toIso8601String(), 'overall' => 'ok', 'checks' => []]);
            $m->shouldReceive('record');
        });

        $this->artisan('network:health');
        $this->assertSame(0, PortalEvent::where('type', PortalEvent::DROPPED)->count());

        $sessions = [];
        $this->artisan('network:health');

        $drop = PortalEvent::where('type', PortalEvent::DROPPED)->sole();
        $this->assertSame('LAWA-TIME1', $drop->voucher_code);
        $this->assertSame(10, $drop->meta['idle_minutes']);
        Cache::forget(CheckNetworkHealth::SESSIONS_KEY);
    }

    public function test_the_portal_report_shows_the_funnel_and_drops(): void
    {
        foreach (['10', '11', '12'] as $n) {
            PortalEvent::record(PortalEvent::VISIT, "192.168.2.1{$n}");
        }
        PortalEvent::record(PortalEvent::CODE_TRIED, '192.168.2.110', 'LAWA-XXXXX');
        PortalEvent::record(PortalEvent::CODE_FAILED, '192.168.2.110', 'LAWA-XXXXX', ['reason' => 'no_match']);
        PortalEvent::record(PortalEvent::CONNECTED, '192.168.2.111', 'LAWA-OK123');
        PortalEvent::record(PortalEvent::DROPPED, '192.168.2.111', 'LAWA-OK123', ['idle_minutes' => 5, 'minutes_left' => 40]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('network.portal-report'))
            ->assertOk()
            ->assertSee('From opening the page to getting online')
            ->assertSee('Code not found (mistyped)')
            ->assertSee('LAWA-OK123')
            ->assertSee('median of <b>5 min</b>', false);

        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get(route('network.portal-report'))
            ->assertRedirect(route('staff.dashboard'));
    }
}
