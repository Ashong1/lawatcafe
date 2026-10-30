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

    protected $description = 'Measure the internet line once a day, at the first chance after opening time (prepaid data: ~13 MB a test).';

    public function handle(InternetSpeedTest $speed, TrafficShapingService $shaper, OpnSenseService $opnsense): int
    {
        $opens = now()->setTimeFromTimeString((string) Setting::get('store_open_time', '08:00'));
        if (now()->lt($opens) || $speed->testedToday() || InternetStatus::isDown()) {
            return self::SUCCESS;
        }

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
        $lower = array_map(fn () => (float) floor($advice['usable']), array_filter($advice['plans'], fn ($fit) => $fit['too_fast']));

        if ($lower === [] || $advice['usable'] < 1 || ! $shaper->savePlanSpeeds($lower, $opnsense)) {
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
