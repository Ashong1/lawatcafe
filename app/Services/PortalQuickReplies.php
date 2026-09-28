<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * The guest chat's quick-question buttons, answered from the shop's own
 * settings with no AI call. They cover what guests ask most, spare the
 * shared OpenRouter daily allowance, and still work when the AI is down.
 */
class PortalQuickReplies
{
    /** @return array<int, array{label: string, answer: string}> */
    public function all(?Carbon $now = null): array
    {
        return [
            ['label' => __('Where is my code?'), 'answer' => $this->whereIsMyCode()],
            ['label' => __('Opening hours'), 'answer' => $this->hours($now ?? now())],
            ['label' => __('Wi-Fi prices'), 'answer' => $this->prices()],
            ['label' => __('How do I reconnect?'), 'answer' => $this->reconnect()],
        ];
    }

    private function whereIsMyCode(): string
    {
        return Setting::receiptPrintingEnabled()
            ? __('Your Wi-Fi code is printed at the very bottom of your receipt.')
            : __('Your Wi-Fi code is on the slip we gave you at the counter. You can also scan its QR code. Ask our staff if you cannot find it.');
    }

    private function hours(Carbon $now): string
    {
        try {
            $open = $now->copy()->setTimeFromTimeString(Setting::get('store_open_time', '08:00'));
            $close = $now->copy()->setTimeFromTimeString(Setting::get('store_close_time', '22:00'));
        } catch (\Throwable) {
            return __('Please ask our staff at the counter for our opening hours.');
        }

        // Hours can run past midnight (e.g. 4 PM to 2 AM).
        $isOpen = $close->gt($open) ? $now->gte($open) && $now->lt($close) : $now->gte($open) || $now->lt($close);

        return __('We are open :open to :close.', ['open' => $open->format('g:i A'), 'close' => $close->format('g:i A')])
            .' '.($isOpen ? __('We are open now.') : __('We are closed right now.'));
    }

    private function prices(): string
    {
        $plans = json_decode((string) Setting::get('voucher_durations', '{"20":60,"50":180,"100":1440}'), true) ?: [];
        ksort($plans, SORT_NUMERIC);

        $lines = [];
        foreach ($plans as $price => $minutes) {
            $lines[] = '• ₱'.$price.' — '.$this->duration((int) $minutes);
        }

        $freeMin = (float) Setting::get('free_wifi_min_amount', '200');
        if ($freeMin > 0) {
            $lines[] = __('• Free :time with any order of ₱:amount or more', [
                'time' => $this->duration((int) Setting::get('free_wifi_duration', '60')),
                'amount' => rtrim(rtrim(number_format($freeMin, 2), '0'), '.'),
            ]);
        }

        return $lines
            ? __('Wi-Fi plans (pay at the counter):')."\n".implode("\n", $lines)
            : __('Please ask our staff at the counter for Wi-Fi prices.');
    }

    private function reconnect(): string
    {
        return __('Open any browser and go to :host, or scan the QR code on your slip. Your code works on the same phone until its time runs out.', [
            'host' => config('services.portal.host'),
        ]);
    }

    private function duration(int $minutes): string
    {
        if ($minutes >= 1440 && $minutes % 1440 === 0) {
            return trans_choice(':count day|:count days', intdiv($minutes, 1440));
        }
        if ($minutes >= 60 && $minutes % 60 === 0) {
            return trans_choice(':count hour|:count hours', intdiv($minutes, 60));
        }

        return trans_choice(':count minute|:count minutes', $minutes);
    }
}
