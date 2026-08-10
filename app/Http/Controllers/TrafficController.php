<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;
use Illuminate\Http\Request;

/**
 * The guest network's traffic page: the one cap this gateway enforces.
 *
 * That cap is a single Shaper rule per direction across the whole guest
 * interface, with a per-IP mask so the figure is a ceiling PER DEVICE rather
 * than a total shared between them.
 *
 * The page used to carry a second form for per-tier voucher rates. Those were
 * recorded and never enforced — this OPNsense build can shape an interface and
 * nothing smaller: Shaper rules accept only "any" for source and destination,
 * filter rules naming an alias save and apply and then shape nothing, the portal
 * zone has no bandwidth fields, and while a Shaper rule can match DSCP no
 * endpoint can set a mark per tier. Offering four inputs that changed no traffic
 * was answering a question the gateway cannot be asked, so the form is gone and
 * the figure that does reach the network is editable in its place.
 *
 * The stored rates themselves are untouched: the Plans page still quotes them.
 */
class TrafficController extends Controller
{
    /** Matches ProvisionFairUseCap's default, so the two never disagree. */
    private const DEFAULT_CEILING = '20';

    public function index()
    {
        $settings = ['bw_fair_use_mbps' => Setting::get('bw_fair_use_mbps', self::DEFAULT_CEILING)];

        return view('network.traffic', compact('settings'));
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
                '%s The ceiling was not saved, and the gateway may be part-way through the change — '
                .'try again, or run php artisan shaper:fair-use %s --apply.',
                $shaper->lastError() ?? 'OPNsense rejected the configuration.',
                rtrim(rtrim(number_format($mbps, 2, '.', ''), '0'), '.')
            ));
        }

        Setting::set('bw_fair_use_mbps', (string) $mbps);

        return redirect()->back()->with('success', sprintf(
            'Fair-use ceiling is live at %s Mbps per device, each way. It applies to every device on '
            .'the guest interface — the POS and this server included.',
            rtrim(rtrim(number_format($mbps, 2, '.', ''), '0'), '.')
        ));
    }

    public function stats(OpnSenseService $opnsense)
    {
        $stats = $opnsense->getInterfaceStats();

        return response()->json($stats);
    }
}
