<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\SystemAlert;
use App\Services\InternetSpeedTest;
use App\Services\InternetStatus;
use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class RunInternetSpeedTest extends Command
{
    protected $signature = 'network:speed-test';

    /** The slowest a plan is ever lowered to by itself. */
    private const MIN_PLAN_MBPS = 2.0;

    protected $description = 'Measure the internet line once a day, at the first chance after opening time (prepaid data: ~13 MB a test).';

    public function handle(InternetSpeedTest $speed, TrafficShapingService $shaper, OpnSenseService $opnsense): int
    {
        $opens = now()->setTimeFromTimeString((string) Setting::get('store_open_time', '08:00'));
        if (now()->lt($opens) || $speed->testedToday() || InternetStatus::isDown()
            || Setting::get('speed_test_attempted_on') === today()->toDateString()) {
            return self::SUCCESS;
        }

        // One try a day, even if it fails: a retry every 15 minutes would eat the prepaid data.
        Setting::set('speed_test_attempted_on', today()->toDateString());
        $result = $speed->run();
        $this->info($result ? "Internet line: {$result['down']} Mbps down, {$result['up']} Mbps up." : 'The speed test could not run.');

        if ($result && Setting::get('bw_auto_fit_plans', '1') === '1') {
            $this->fitPlansToLine($result['down'], $speed, $shaper, $opnsense);
        }

        return self::SUCCESS;
    }

    /**
     * A plan set faster than the line can give guests is lowered to fit
     * (AI-managed speeds, owner's choice Oct 30), and the admins are told.
     */
    private function fitPlansToLine(float $down, InternetSpeedTest $speed, TrafficShapingService $shaper, OpnSenseService $opnsense): void
    {
        $plans = ['bw_free_down' => 'Free', 'bw_premium_down' => 'Premium'];
        $current = array_map(fn ($key) => (float) Setting::get($key, TrafficShapingService::PLAN_DEFAULTS[$key]), array_combine(array_keys($plans), array_keys($plans)));
        $advice = InternetSpeedTest::recommend($down, (float) Setting::get('bw_shop_reserve_mbps', 5), (int) Setting::get('bw_busy_guests', 10), $current);
        // Act only on two slow days in a row (one bad reading is often the test, not the line),
        // never below MIN_PLAN_MBPS, and Premium stays faster than Free.
        $previous = $speed->history()[1]['down'] ?? null;
        if ($previous === null || $previous - (float) Setting::get('bw_shop_reserve_mbps', 5) >= max($current)) {
            return;
        }
        $target = max(self::MIN_PLAN_MBPS, (float) floor($advice['usable']));
        $lower = array_filter(array_map(fn ($speedNow) => $target < $speedNow ? $target : null, $current));
        if (isset($lower['bw_premium_down']) && $lower['bw_premium_down'] <= ($lower['bw_free_down'] ?? $current['bw_free_down'])) {
            unset($lower['bw_premium_down']);
        }

        if ($lower === [] || ! $shaper->savePlanSpeeds($lower, $opnsense)) {
            return;
        }

        $what = implode(', ', array_map(fn ($key) => "{$plans[$key]} from {$current[$key]} to {$lower[$key]} Mbps", array_keys($lower)));
        Notification::send(User::active()->whereIn('role', ['admin', 'super_admin'])->get(), new SystemAlert(
            'Wi-Fi plan lowered to fit the internet',
            "Today's speed test measured {$down} Mbps, so Barista AI lowered {$what}. Change it any time on Wi-Fi Speed.",
            'gauge',
            route('network.traffic')
        ));
    }
}
