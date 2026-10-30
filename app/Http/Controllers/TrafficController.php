<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AdaptiveBandwidthService;
use App\Services\InternetSpeedTest;
use App\Services\LinkCapacityLearner;
use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The guest network's traffic page: each plan's speed cap (Shaper rules that
 * list the plan's guest IPs), and the shop-wide fair-use ceiling behind them.
 * Every figure shown is read back from OPNsense, so the page agrees with a
 * speed test rather than with what was last typed.
 */
class TrafficController extends Controller
{
    /** Matches ProvisionFairUseCap's default, so the two never disagree. */
    private const DEFAULT_CEILING = '20';

    /** Mbps kept for the register, AI and staff devices. */
    private const DEFAULT_RESERVE = 5;

    /** Guests online together at the busiest time, until the owner says otherwise. */
    private const DEFAULT_BUSY_GUESTS = 10;

    public function index(AdaptiveBandwidthService $adaptive, LinkCapacityLearner $learner, TrafficShapingService $shaper, OpnSenseService $opnsense)
    {
        $stored = collect(TrafficShapingService::PLAN_DEFAULTS)->map(fn ($default, $key) => Setting::get($key, $default))->all();
        $live = $shaper->liveStatus($opnsense, $stored);

        $settings = [
            'bw_fair_use_mbps' => Setting::get('bw_fair_use_mbps', self::DEFAULT_CEILING),
            'bw_adaptive_enabled' => Setting::get('bw_adaptive_enabled', AdaptiveBandwidthService::DEFAULTS['bw_adaptive_enabled']),
            'bw_adaptive_min' => Setting::get('bw_adaptive_min', AdaptiveBandwidthService::DEFAULTS['bw_adaptive_min']),
            'bw_adaptive_max' => Setting::get('bw_adaptive_max', AdaptiveBandwidthService::DEFAULTS['bw_adaptive_max']),
        ];

        // What the loop has worked out for itself, shown whether or not it is
        // switched on — the sampling runs either way, so an owner deciding
        // whether to enable it can see how much the system already knows.
        $learned = [
            'capacity' => $learner->estimate(),
            'peak_hours' => $learner->peakHours(),
            'last_decision' => $adaptive->lastDecision(),
        ];

        // The measured line, and what it can give each guest after the shop's share.
        $speed = app(InternetSpeedTest::class);
        $reserve = (float) Setting::get('bw_shop_reserve_mbps', self::DEFAULT_RESERVE);
        $busyGuests = (int) Setting::get('bw_busy_guests', self::DEFAULT_BUSY_GUESTS);
        $line = [
            'latest' => $speed->latest(),
            'history' => array_slice($speed->history(), 0, 7),
            'reserve' => $reserve,
            'busy_guests' => $busyGuests,
            'auto_fit' => Setting::get('bw_auto_fit_plans', '1') === '1',
            'advice' => ($latest = $speed->latest()) ? InternetSpeedTest::recommend($latest['down'], $reserve, $busyGuests, [
                'Free' => (float) Setting::get('bw_free_down', TrafficShapingService::PLAN_DEFAULTS['bw_free_down']),
                'Premium' => (float) Setting::get('bw_premium_down', TrafficShapingService::PLAN_DEFAULTS['bw_premium_down']),
            ]) : null,
            'plans' => $this->currentPlans(),
            'suggestion' => $this->suggestedPlans(),
        ];

        return view('network.traffic', compact('settings', 'learned', 'live', 'line'));
    }

    /** @return array<string, float> download speed per plan */
    private function currentPlans(): array
    {
        return collect(['bw_free_down', 'bw_premium_down'])->mapWithKeys(fn ($k) => [$k => (float) Setting::get($k, TrafficShapingService::PLAN_DEFAULTS[$k])])->all();
    }

    private function suggestedPlans(): array
    {
        return InternetSpeedTest::suggestPlans(app(InternetSpeedTest::class)->history(),
            (float) Setting::get('bw_shop_reserve_mbps', self::DEFAULT_RESERVE),
            (int) Setting::get('bw_busy_guests', self::DEFAULT_BUSY_GUESTS),
            $this->currentPlans());
    }

    /** The owner's one decision about speeds: take what Barista AI suggests. */
    public function acceptSuggestedPlans(TrafficShapingService $shaper, OpnSenseService $opnsense)
    {
        $lower = $this->suggestedPlans();
        if ($lower === []) {
            return back()->with('success', 'Your Wi-Fi plans already fit your internet. Nothing to change.');
        }
        if (! $shaper->savePlanSpeeds($lower, $opnsense)) {
            return back()->with('error', "The router didn't accept the new speeds, so nothing changed. Try again in a minute.");
        }

        $names = ['bw_free_down' => 'Free', 'bw_premium_down' => 'Premium'];

        return back()->with('success', 'Done. '.implode(', ', array_map(fn ($k) => "{$names[$k]} is now {$lower[$k]} Mbps", array_keys($lower))).'.');
    }

    public function speedTest(InternetSpeedTest $speed)
    {
        // About 13 MB each on a prepaid line: 3 an hour, and say when the next one is possible.
        if (RateLimiter::tooManyAttempts('speed-test', 3)) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn('speed-test') / 60));

            return back()->with('error', "The internet can be tested up to 3 times an hour to save data. Try again in {$minutes} ".Str::plural('minute', $minutes).'.');
        }
        RateLimiter::hit('speed-test', 3600);

        // Measured after the page answers: on a slow line the test outlasts the proxy's timeout.
        dispatch(fn () => app(InternetSpeedTest::class)->run())->afterResponse();

        return back()->with('success', 'Testing your internet now. It takes about 15 seconds; refresh this page to see the result.');
    }

    public function updateLine(Request $request)
    {
        $validated = $request->validate([
            'bw_shop_reserve_mbps' => 'required|numeric|min:0|max:1000',
            'bw_busy_guests' => 'required|integer|min:1|max:200',
        ]);
        foreach ($validated as $key => $value) {
            Setting::set($key, (string) $value);
        }
        Setting::set('bw_auto_fit_plans', $request->boolean('bw_auto_fit_plans') ? '1' : '0');

        return back()->with('success', 'Saved. The advice below uses the new numbers.');
    }

    /** Apply each plan's speed caps, then record them. */
    public function updatePlans(Request $request, TrafficShapingService $shaper, OpnSenseService $opnsense)
    {
        $rule = 'required|numeric|min:0.5|max:1000';
        $validated = $request->validate(
            array_fill_keys(array_keys(TrafficShapingService::PLAN_DEFAULTS), $rule),
            [],
            [
                'bw_free_down' => 'free download speed', 'bw_free_up' => 'free upload speed',
                'bw_premium_down' => 'premium download speed', 'bw_premium_up' => 'premium upload speed',
            ]
        );

        if (! $shaper->savePlanSpeeds($validated, $opnsense)) {
            return redirect()->back()->withInput()->with('error',
                ($shaper->lastError() ?? 'The router refused the plan speeds.').' The plan speeds were not saved.');
        }

        return redirect()->back()->with('success', sprintf(
            'Plan speeds saved and working. Free: %s download / %s upload. Premium: %s download / %s upload (Mbps, per device).',
            ...array_map(fn ($k) => (float) $validated[$k], ['bw_free_down', 'bw_free_up', 'bw_premium_down', 'bw_premium_up'])
        ));
    }

    /**
     * The adaptive loop's own settings. Separate from the ceiling's own form
     * because it writes no firewall: it changes the envelope the agent may move
     * within, not the cap currently in force.
     */
    public function updateAdaptive(Request $request)
    {
        $validated = $request->validate([
            'bw_adaptive_enabled' => 'nullable|boolean',
            // Same floor as the manual form, for the same reason: the shop's own
            // till and kitchen display sit on this interface.
            'bw_adaptive_min' => 'required|numeric|min:5|max:1000',
            'bw_adaptive_max' => 'required|numeric|min:5|max:1000|gte:bw_adaptive_min',
        ], [
            'bw_adaptive_max.gte' => 'The maximum must be at least the minimum.',
        ]);

        Setting::set('bw_adaptive_enabled', $request->boolean('bw_adaptive_enabled') ? '1' : '0');
        Setting::set('bw_adaptive_min', (string) $validated['bw_adaptive_min']);
        Setting::set('bw_adaptive_max', (string) $validated['bw_adaptive_max']);

        return redirect()->back()->with('success', $request->boolean('bw_adaptive_enabled')
            ? sprintf(
                'Automatic speed limit is on. Barista AI may move the limit between %s and %s Mbps as the shop fills up and empties.',
                $validated['bw_adaptive_min'],
                $validated['bw_adaptive_max']
            )
            : 'Automatic speed limit is off. The limit stays where you set it; Barista AI keeps learning your busy hours in the background.');
    }

    /**
     * Apply the fair-use ceiling, then record it.
     *
     * That order is the whole point. This action really does rewrite the live
     * firewall, so the stored figure must never be allowed to describe a cap the
     * gateway is not running — on a rejection nothing is saved and the page says
     * what OPNsense refused. It shares applyFairUseCap() with `shaper:fair-use`
     * so the browser and the CLI cannot drift into differently-behaving copies
     * of the same provisioning.
     */
    public function update(Request $request, TrafficShapingService $shaper, OpnSenseService $opnsense)
    {
        // The floor is not cosmetic. The captive portal zone is bound to `lan`,
        // which also carries the POS, this application server, Pi-hole and
        // OPNsense itself, and the ceiling applies to all of them. At the old
        // per-tier value (2 Mbit) it would have throttled the register and every
        // AI call this app makes, so anything that low is a mistake rather than
        // a policy. The upper bound keeps a typo from quietly removing the cap.
        $validated = $request->validate([
            'bw_fair_use_mbps' => 'required|numeric|min:5|max:1000',
        ], [], ['bw_fair_use_mbps' => 'fair-use ceiling']);

        $mbps = (float) $validated['bw_fair_use_mbps'];

        if (! $shaper->applyFairUseCap($mbps, $opnsense)) {
            // Deliberately not saved. A rejection can land after the download
            // pipe is already written, so the gateway may be holding a partly
            // applied cap — say so rather than letting a stored number imply a
            // clean state.
            return redirect()->back()->withInput()->with('error', sprintf(
                '%s The speed limit was not saved, and the router may be part-way through the change. '
                .'Try again; if it keeps failing, ask the system administrator (it can be applied with php artisan shaper:fair-use %s --apply).',
                $shaper->lastError() ?? 'The router refused the change.',
                rtrim(rtrim(number_format($mbps, 2, '.', ''), '0'), '.')
            ));
        }

        Setting::set('bw_fair_use_mbps', (string) $mbps);
        Setting::set('bw_fair_use_enabled', '1');

        return redirect()->back()->with('success', sprintf(
            'Speed limit is on: %s Mbps per device, download and upload. It applies to every device '
            .'not on a Wi-Fi plan, including the register and the server.',
            rtrim(rtrim(number_format($mbps, 2, '.', ''), '0'), '.')
        ));
    }

    /** Switch the ceiling off. Plan speeds keep applying to guests. */
    public function disableFairUse(TrafficShapingService $shaper, OpnSenseService $opnsense)
    {
        if (! $shaper->disableFairUseCap($opnsense)) {
            return redirect()->back()->with('error',
                ($shaper->lastError() ?? 'The router refused the change.').' The speed limit may still be on.');
        }

        Setting::set('bw_fair_use_enabled', '0');

        return redirect()->back()->with('success',
            'Speed limit is off. Guests still get their plan speeds; other devices are no longer limited.');
    }

    public function stats(OpnSenseService $opnsense)
    {
        $stats = $opnsense->getInterfaceStats();

        return response()->json($stats);
    }
}
