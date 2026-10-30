<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\InternetSpeedTest;
use App\Services\InternetStatus;
use Illuminate\Console\Command;

class RunInternetSpeedTest extends Command
{
    protected $signature = 'network:speed-test';

    protected $description = 'Measure the internet line once a day, at the first chance after opening time (prepaid data: ~13 MB a test).';

    public function handle(InternetSpeedTest $speed): int
    {
        $opens = now()->setTimeFromTimeString((string) Setting::get('store_open_time', '08:00'));
        if (now()->lt($opens) || $speed->testedToday() || InternetStatus::isDown()) {
            return self::SUCCESS;
        }

        $result = $speed->run();
        $this->info($result ? "Internet line: {$result['down']} Mbps down, {$result['up']} Mbps up." : 'The speed test could not run.');

        return self::SUCCESS;
    }
}
