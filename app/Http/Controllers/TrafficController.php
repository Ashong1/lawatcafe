<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\OpnSenseService;
use Illuminate\Http\Request;

/**
 * The guest network's traffic page: what is enforced, and what each voucher
 * tier is rated at.
 *
 * Two different kinds of number live here and the page must never blur them.
 *
 * The fair-use ceiling is real. One Shaper rule per direction caps every
 * device on the guest interface, and `shaper:fair-use` owns it — no form, so
 * there is no way to half-apply it from a browser.
 *
 * The per-tier rates are a record. This OPNsense build can shape an interface
 * and nothing smaller: its Shaper rules accept only "any" for source and
 * destination, filter rules that name an alias save and apply and then shape
 * nothing, and the portal zone has no bandwidth fields. Re-checked live on
 * 2026-08-10 — a Shaper rule can match DSCP, but no endpoint can set a DSCP
 * mark per tier, so that lane is closed too.
 *
 * Saving therefore writes settings and calls no firewall. The values still
 * matter: `shaper:provision` reads them, vouchers carry a tier, and the Plans
 * page quotes them to guests.
 *
 * (The free and premium keys are spelled out below rather than written as a
 * glob with a star and a slash — that sequence ends a docblock, and writing it
 * here once took the whole page down with a ParseError.)
 */
class TrafficController extends Controller
{
    /**
     * Defaults match the seeded plan figures, so a fresh install shows the
     * same rates the Plans page quotes.
     */
    private const TIER_DEFAULTS = [
        'bw_free_down' => '2',
        'bw_free_up' => '1',
        'bw_premium_down' => '10',
        'bw_premium_up' => '5',
    ];

    public function index()
    {
        $settings = ['bw_fair_use_mbps' => Setting::get('bw_fair_use_mbps', '20')];

        foreach (self::TIER_DEFAULTS as $key => $default) {
            $settings[$key] = Setting::get($key, $default);
        }

        return view('network.traffic', compact('settings'));
    }

    /**
     * Record the per-tier rates.
     *
     * Deliberately touches no firewall. An earlier version of this action
     * provisioned per-tier pipes and rules on every save; OPNsense rejected
     * the rules every time, so the page reported a failure for values that had
     * in fact been stored. A save that only saves cannot fail that way.
     */
    public function update(Request $request)
    {
        // A rate below 0.1 is not a slow tier, it is a broken one, and the
        // upper bound keeps a typo from advertising a gigabit café.
        $validated = $request->validate([
            'bw_free_down' => 'required|numeric|min:0.1|max:1000',
            'bw_free_up' => 'required|numeric|min:0.1|max:1000',
            'bw_premium_down' => 'required|numeric|min:0.1|max:1000',
            'bw_premium_up' => 'required|numeric|min:0.1|max:1000',
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, $value);
        }

        return redirect()->back()->with('success', sprintf(
            'Tier rates saved — free %s/%s Mbps, premium %s/%s Mbps (down/up). '
            .'They are recorded against vouchers and shown on the Plans page. '
            .'Traffic is unchanged: this gateway shapes by interface only, so every '
            .'guest stays on the %s Mbps per-device ceiling.',
            $validated['bw_free_down'],
            $validated['bw_free_up'],
            $validated['bw_premium_down'],
            $validated['bw_premium_up'],
            Setting::get('bw_fair_use_mbps', '20')
        ));
    }

    public function stats(OpnSenseService $opnsense)
    {
        $stats = $opnsense->getInterfaceStats();

        return response()->json($stats);
    }
}
