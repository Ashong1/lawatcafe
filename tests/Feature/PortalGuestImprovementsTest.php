<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Voucher;
use App\Notifications\SystemAlert;
use App\Services\NetworkHealthService;
use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The guest portal pass: Filipino, a confirm step on log out, "Need more
 * time?", the phone's name instead of the firewall's login name, a warning
 * when sign-in is down, and no terms checkbox.
 */
class PortalGuestImprovementsTest extends TestCase
{
    use RefreshDatabase;

    private const IP = '192.168.2.50';

    private const MAC = 'AA:BB:CC:DD:EE:FF';

    private function onlineGuest(?array $sessions = null): Voucher
    {
        $voucher = Voucher::create([
            'code' => 'LAWA-GUEST', 'duration_minutes' => 60, 'tier' => 'free', 'is_used' => true,
            'used_at' => now(), 'activated_at' => now(), 'ip_address' => self::IP, 'mac_address' => self::MAC,
        ]);

        $this->mock(OpnSenseService::class, function ($m) use ($sessions) {
            $m->shouldReceive('resolveMacForIp')->andReturn(self::MAC);
            $m->shouldReceive('getDhcpLeases')->andReturn([['address' => self::IP, 'hostname' => 'Lolas-Redmi', 'mac_info' => 'Xiaomi']]);
            $m->shouldReceive('listSessions')->andReturn($sessions ?? [[
                'sessionId' => 'sess-1', 'ipAddress' => self::IP.'/32', 'macAddress' => self::MAC,
                'startTime' => now()->subMinutes(5)->timestamp, 'userName' => 'guest_LAWA-GUEST',
            ]]);
        });

        return $voucher;
    }

    private function asGuest()
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::IP]);
    }

    public function test_the_status_page_shows_the_phone_name_not_the_firewall_login(): void
    {
        $this->onlineGuest();

        $this->asGuest()->get(route('portal.index'))
            ->assertOk()
            ->assertSee('Lolas-Redmi')
            ->assertDontSee('guest_LAWA-GUEST');
    }

    public function test_logging_out_asks_first_and_need_more_time_is_offered(): void
    {
        $this->onlineGuest();

        $html = $this->asGuest()->get(route('portal.index'))->getContent();

        $this->assertStringContainsString('Log out of the Wi-Fi?', $html);
        $this->assertStringContainsString('@submit.prevent="Swal.fire(', $html);
        $this->assertStringContainsString(route('portal.more-time'), $html);
    }

    public function test_need_more_time_tells_the_staff(): void
    {
        Notification::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        $this->onlineGuest();

        $this->asGuest()->post(route('portal.more-time'))
            ->assertRedirect(route('portal.index'))
            ->assertSessionHas('message');

        Notification::assertSentTo($staff, SystemAlert::class, fn ($n) => str_contains($n->toArray($staff)['message'], 'Lolas-Redmi') && str_contains($n->toArray($staff)['message'], 'LAWA-GUEST'));
    }

    public function test_the_portal_switches_to_filipino_and_remembers_it(): void
    {
        $this->get(route('portal.index', ['lang' => 'fil']))
            ->assertOk()
            ->assertSee('Kumonekta sa Wi-Fi')
            ->assertCookie('portal_lang');

        $this->withCookie('portal_lang', 'fil')->get(route('portal.menu'))->assertSee('Aming Menu');
        $this->get(route('portal.index', ['lang' => 'en']))->assertSee('Connect to Wi-Fi');
        $this->get(route('portal.index', ['lang' => 'xx']))->assertOk()->assertSee('Connect to Wi-Fi');
    }

    public function test_every_portal_string_has_a_filipino_translation(): void
    {
        $fil = json_decode(file_get_contents(lang_path('fil.json')), true);
        $files = array_merge(glob(resource_path('views/portal/*.blade.php')), glob(resource_path('views/portal/partials/*.blade.php')));

        $missing = [];
        foreach ($files as $file) {
            preg_match_all("/__\\((?:'((?:[^'\\\\]|\\\\.)*)'|\"((?:[^\"\\\\]|\\\\.)*)\")/", file_get_contents($file), $m, PREG_SET_ORDER);
            foreach ($m as $match) {
                $key = stripslashes($match[1] !== '' ? $match[1] : ($match[2] ?? ''));
                if ($key !== '' && ! isset($fil[$key])) {
                    $missing[] = basename($file).': '.$key;
                }
            }
        }

        $this->assertSame([], $missing);
    }

    public function test_the_terms_checkbox_must_be_ticked_before_connecting(): void
    {
        $html = $this->get(route('portal.index'))->getContent();

        $this->assertMatchesRegularExpression('/id="terms-voucher" required/', $html);
        $this->assertStringContainsString('I agree to the', $html);
        // Above the Connect button, where a phone shows it without scrolling.
        $this->assertLessThan(strpos($html, 'type="submit" :disabled="isSubmitting"'), strpos($html, 'id="terms-voucher"'));
    }

    public function test_a_warning_shows_when_the_firewall_cannot_be_reached(): void
    {
        $this->get(route('portal.index'))->assertDontSee('Wi-Fi sign-in is having trouble');

        Cache::forever(NetworkHealthService::LATEST_KEY, [
            'checked_at' => now()->toIso8601String(),
            'checks' => ['firewall' => ['status' => 'fail']],
        ]);
        $this->get(route('portal.index'))->assertSee('Wi-Fi sign-in is having trouble');

        // A stale result doesn't count.
        Cache::forever(NetworkHealthService::LATEST_KEY, [
            'checked_at' => now()->subHour()->toIso8601String(),
            'checks' => ['firewall' => ['status' => 'fail']],
        ]);
        $this->get(route('portal.index'))->assertDontSee('Wi-Fi sign-in is having trouble');
    }

    /** activeVoucherFor() also matches by IP; a new phone on the same address must not inherit the time. */
    public function test_auto_reconnect_needs_the_phone_the_code_was_used_on(): void
    {
        Voucher::create([
            'code' => 'LAWA-OTHER', 'duration_minutes' => 60, 'tier' => 'free', 'is_used' => true,
            'used_at' => now(), 'activated_at' => now(), 'ip_address' => self::IP, 'mac_address' => '11:22:33:44:55:66',
        ]);
        $this->mock(OpnSenseService::class, function ($m) {
            $m->shouldReceive('resolveMacForIp')->andReturn(self::MAC);
            $m->shouldReceive('listSessions')->andReturn([]);
            $m->shouldNotReceive('authorizeDevice');
        });
        $this->mock(TrafficShapingService::class)->shouldNotReceive('assignTier');

        $this->asGuest()->get(route('portal.index'))->assertOk()->assertViewIs('portal.index');
    }
}
