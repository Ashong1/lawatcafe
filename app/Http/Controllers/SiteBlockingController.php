<?php

namespace App\Http\Controllers;

use App\Services\PiholeService;
use Illuminate\Http\Request;

class SiteBlockingController extends Controller
{
    /**
     * Curated one-click presets: category => [domain => display label].
     * Nothing here is enforced until an admin actually toggles it on — this
     * just saves typing for the sites a cafe network is most often asked to
     * restrict, without hiding the ability to block anything else by domain.
     */
    protected const PRESETS = [
        'Social Media' => [
            'facebook.com' => 'Facebook',
            'instagram.com' => 'Instagram',
            'tiktok.com' => 'TikTok',
            'x.com' => 'X (Twitter)',
            'reddit.com' => 'Reddit',
        ],
        'Streaming & Gaming' => [
            'youtube.com' => 'YouTube',
            'twitch.tv' => 'Twitch',
            'roblox.com' => 'Roblox',
        ],
        'Adult Content' => [
            'pornhub.com' => 'Pornhub',
            'xvideos.com' => 'XVideos',
        ],
        'Piracy & Torrents' => [
            'thepiratebay.org' => 'The Pirate Bay',
            '1337x.to' => '1337x',
        ],
    ];

    /**
     * Shared with toggle() and store() — a malformed domain must not reach
     * PiholeService from either endpoint, not just the one that happens to
     * accept free-text input.
     */
    protected const DOMAIN_REGEX = '/^(?!-)[A-Za-z0-9-]{1,63}(?<!-)(\.[A-Za-z0-9-]{1,63})+$/';

    public function index(PiholeService $pihole)
    {
        $blocked = collect($pihole->blockedDomains())->keyBy('domain');

        $presetDomains = collect(self::PRESETS)->flatMap(fn ($sites) => array_keys($sites));

        $presets = collect(self::PRESETS)->map(fn ($sites, $category) => collect($sites)->map(
            function ($label, $domain) use ($blocked) {
                $entry = $blocked->get($domain);

                return [
                    'domain' => $domain,
                    'label' => $label,
                    'blocked' => $entry ? $entry['enabled'] : false,
                ];
            }
        )->values());

        $customDomains = $blocked->reject(fn ($entry, $domain) => $presetDomains->contains($domain))->values();

        return view('network.site-blocking', [
            'presets' => $presets,
            'customDomains' => $customDomains,
            'piholeConfigured' => ! empty(config('services.pihole.app_password')),
        ]);
    }

    public function toggle(Request $request, PiholeService $pihole)
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255', 'regex:'.self::DOMAIN_REGEX],
            'block' => 'required|boolean',
        ]);

        $ok = $validated['block']
            ? $pihole->blockDomain($validated['domain'])
            : $pihole->unblockDomain($validated['domain']);

        $verb = $validated['block'] ? 'blocked' : 'unblocked';

        return redirect()->back()->with(
            $ok ? 'success' : 'error',
            $ok ? "{$validated['domain']} has been {$verb}." : "Could not reach Pi-hole to update {$validated['domain']}."
        );
    }

    public function store(Request $request, PiholeService $pihole)
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255', 'regex:'.self::DOMAIN_REGEX],
        ]);

        $ok = $pihole->blockDomain($validated['domain'], "Added via Lawa't Kape admin");

        return redirect()->back()->with(
            $ok ? 'success' : 'error',
            $ok ? "{$validated['domain']} has been added and blocked." : "Could not reach Pi-hole to add {$validated['domain']}."
        );
    }

    public function destroy(string $domain, PiholeService $pihole)
    {
        $ok = $pihole->removeDomain($domain);

        return redirect()->back()->with(
            $ok ? 'success' : 'error',
            $ok ? "{$domain} has been removed from the blocklist." : "Could not reach Pi-hole to remove {$domain}."
        );
    }
}
