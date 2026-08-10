<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\OpnSenseService;

/**
 * The guest network's traffic monitor.
 *
 * This page used to carry a QoS form: a per-device ceiling, a free and a
 * premium tier, a burst toggle. Only the ceiling was ever enforced — the
 * tier fields wrote values that this OPNsense build cannot act on, and the
 * burst toggle wrote a setting nothing reads. A form whose controls mostly
 * do nothing is worse than no form, so the page now reports the shaping that
 * is in force and the ceiling is set with `php artisan shaper:fair-use`.
 *
 * The free and premium settings themselves stay: the Plans page shows them to
 * guests and `shaper:provision` still reads them. (Spelling them out rather
 * than as a glob with a star and a slash — that sequence ends a docblock, and
 * writing it here took the whole page down with a ParseError.)
 */
class TrafficController extends Controller
{
    public function index()
    {
        $settings = [
            'bw_fair_use_mbps' => Setting::get('bw_fair_use_mbps', '20'),
        ];

        return view('network.traffic', compact('settings'));
    }

    public function stats(OpnSenseService $opnsense)
    {
        $stats = $opnsense->getInterfaceStats();

        return response()->json($stats);
    }
}
