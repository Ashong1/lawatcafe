<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to Pi-hole v6's session-based API. Unlike the old v5 API (a static
 * bearer key), v6 exchanges an app password for a short-lived sid + csrf
 * pair via POST /api/auth (see session()). The pair is cached across
 * requests and re-authenticated automatically on a 401, since Pi-hole can
 * invalidate a session before its stated TTL (e.g. a config reload).
 *
 * Only the "deny/exact" domain list is used — this backs the one-click
 * site-blocking GUI, not Pi-hole's full regex/allow-list feature set.
 */
class PiholeService
{
    protected string $baseUrl;

    protected ?string $appPassword;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.pihole.url'), '/');
        $this->appPassword = config('services.pihole.app_password');
    }

    protected function session(bool $fresh = false): ?array
    {
        if (empty($this->baseUrl) || empty($this->appPassword)) {
            return null;
        }

        if ($fresh) {
            Cache::forget('pihole_session');
        }

        return Cache::remember('pihole_session', 1500, function () {
            try {
                $response = Http::timeout(5)->post("{$this->baseUrl}/api/auth", [
                    'password' => $this->appPassword,
                ]);

                $session = $response->json('session');

                if (! $response->successful() || ! ($session['valid'] ?? false)) {
                    Log::error('Pi-hole: authentication failed.', ['message' => $session['message'] ?? null]);

                    return null;
                }

                return ['sid' => $session['sid'], 'csrf' => $session['csrf']];
            } catch (\Exception $e) {
                Log::error('Pi-hole: exception during authentication: '.$e->getMessage());

                return null;
            }
        });
    }

    protected function client(array $session)
    {
        return Http::timeout(5)->baseUrl($this->baseUrl)->withHeaders([
            'X-FTL-SID' => $session['sid'],
            'X-FTL-CSRF' => $session['csrf'],
        ]);
    }

    /**
     * Run $callback($client) with a valid session, retrying once with a
     * freshly re-authenticated session if the first attempt comes back 401.
     */
    protected function withSession(callable $callback)
    {
        $session = $this->session();

        if (! $session) {
            return null;
        }

        $response = $callback($this->client($session));

        if ($response->status() === 401) {
            $session = $this->session(fresh: true);

            if (! $session) {
                return null;
            }

            $response = $callback($this->client($session));
        }

        return $response;
    }

    /**
     * All exact-match domains on Pi-hole's blacklist ("deny" list), including
     * ones currently disabled — the site-blocking page shows both so a
     * disabled entry can be re-enabled with one click instead of re-added.
     *
     * @return array<int, array{domain: string, comment: ?string, enabled: bool}>
     */
    public function blockedDomains(): array
    {
        try {
            $response = $this->withSession(fn ($client) => $client->get('/api/domains', [
                'type' => 'deny',
                'kind' => 'exact',
            ]));

            if (! $response || ! $response->successful()) {
                return [];
            }

            return collect($response->json('domains') ?? [])
                ->map(fn ($d) => [
                    // Lowercased so a domain added outside this app (e.g.
                    // directly in Pi-hole's own UI) with mixed case still
                    // matches the lowercase keys used everywhere else here
                    // (PRESETS, normalizeDomain()) instead of silently
                    // reading as a separate, unblocked entry.
                    'domain' => strtolower($d['domain']),
                    'comment' => $d['comment'] ?? null,
                    'enabled' => (bool) ($d['enabled'] ?? true),
                ])
                ->values()
                ->all();
        } catch (\Exception $e) {
            Log::error('Pi-hole: exception listing blocked domains: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Add a domain to the blacklist (enabled), or re-enable it if it's
     * already there but was previously toggled off — one call covers both
     * so the "block" button never has to know which state a domain is in.
     */
    /**
     * DNS lookups Pi-hole answered since $fromTimestamp, oldest first.
     *
     * Feeds the adult-site watch (WatchAdultSites). Returns [] when Pi-hole is
     * unreachable, so a missed run just catches up on the next one rather than
     * throwing inside the scheduler.
     *
     * @return array<int, array{time: int, domain: string, client: string, status: string}>
     */
    public function queriesSince(int $fromTimestamp, int $limit = 5000): array
    {
        try {
            $response = $this->withSession(fn ($client) => $client->timeout(15)->get('/api/queries', [
                'from' => $fromTimestamp,
                'length' => $limit,
            ]));

            if (! $response || ! $response->successful()) {
                return [];
            }

            return collect($response->json('queries') ?? [])
                ->map(fn ($q) => [
                    'time' => (int) ($q['time'] ?? 0),
                    'domain' => strtolower((string) ($q['domain'] ?? '')),
                    'client' => (string) ($q['client']['ip'] ?? ''),
                    'status' => (string) ($q['status'] ?? ''),
                ])
                ->filter(fn ($q) => $q['domain'] !== '' && $q['client'] !== '')
                ->sortBy('time')
                ->values()
                ->all();
        } catch (\Exception $e) {
            Log::error('Pi-hole: exception reading the query log: '.$e->getMessage());

            return [];
        }
    }

    public function blockDomain(string $domain, ?string $comment = null): bool
    {
        return $this->upsertDomain($domain, true, $comment);
    }

    /**
     * Toggle a domain off without removing it — what makes "unblock" a
     * one-click, reversible action instead of a delete the admin would have
     * to redo from scratch to block the same site again later.
     */
    public function unblockDomain(string $domain): bool
    {
        return $this->upsertDomain($domain, false);
    }

    protected function upsertDomain(string $domain, bool $enabled, ?string $comment = null): bool
    {
        $domain = $this->normalizeDomain($domain);
        $existing = collect($this->blockedDomains())->firstWhere('domain', $domain);

        // Pi-hole's PUT replaces the whole entry rather than merging fields,
        // so toggling `enabled` alone would silently wipe an existing
        // comment unless it's carried forward explicitly. The attribution
        // fallback only applies to a genuinely new entry (no $existing) —
        // an existing entry with no comment (e.g. added outside this app)
        // should stay commentless on a toggle, not get a fabricated "Added
        // via..." note it never actually had.
        $payload = [
            'enabled' => $enabled,
            'comment' => $existing
                ? ($comment ?? $existing['comment'])
                : ($comment ?? "Added via Lawa't Kape admin"),
        ];

        try {
            $response = $this->withSession(fn ($client) => $existing
                ? $client->put("/api/domains/deny/exact/{$domain}", $payload)
                : $client->post('/api/domains/deny/exact', array_merge($payload, ['domain' => $domain])));

            return (bool) $response?->successful() && $this->upsertSubdomainRule($domain, $enabled);
        } catch (\Exception $e) {
            Log::error("Pi-hole: exception setting {$domain} enabled=".($enabled ? 'true' : 'false').': '.$e->getMessage());

            return false;
        }
    }

    /**
     * Permanently remove a domain from the blacklist, as opposed to
     * unblockDomain() which just disables it — for custom entries the admin
     * wants gone rather than just switched off.
     */
    public function removeDomain(string $domain): bool
    {
        $domain = $this->normalizeDomain($domain);

        try {
            $response = $this->withSession(fn ($client) => $client->delete("/api/domains/deny/exact/{$domain}"));
        } catch (\Exception $e) {
            Log::error("Pi-hole: exception removing {$domain}: ".$e->getMessage());

            return false;
        }

        // Best-effort: an entry blocked before sub-domain rules existed has
        // none to delete, and that must not report the removal as failed.
        try {
            $this->withSession(fn ($client) => $client->delete('/api/domains/deny/regex/'.rawurlencode($this->subdomainRegex($domain))));
        } catch (\Exception $e) {
            Log::warning("Pi-hole: could not remove the sub-domain rule for {$domain}: ".$e->getMessage());
        }

        return (bool) $response?->successful();
    }

    /**
     * An exact entry blocks only that one name — "pornhub.com" left
     * m.pornhub.com wide open (seen in the live query log). Every exact entry
     * this app writes gets a companion regex covering all sub-domains, kept in
     * the same enabled state. The exact entry stays because it is what the
     * Site Blocking page lists.
     */
    public function subdomainRegex(string $domain): string
    {
        return '(\\.|^)'.preg_quote($this->normalizeDomain($domain), null).'$';
    }

    protected function upsertSubdomainRule(string $domain, bool $enabled): bool
    {
        $regex = $this->subdomainRegex($domain);
        $payload = ['enabled' => $enabled, 'comment' => "Sub-domains of {$domain} (Lawa't Kape)"];

        // PUT on a rule that doesn't exist yet is a 404, so fall back to POST.
        $response = $this->withSession(fn ($client) => $client->put('/api/domains/deny/regex/'.rawurlencode($regex), $payload));
        if ($response && $response->status() === 404) {
            $response = $this->withSession(fn ($client) => $client->post('/api/domains/deny/regex', array_merge($payload, ['domain' => $regex])));
        }

        return (bool) $response?->successful();
    }

    /**
     * StevenBlack's adult-only list (~77k domains, maintained upstream). The
     * two Adult presets can't keep up with a category — guests reached
     * pinayflix.tv and others no hand-written list named.
     */
    public const ADULT_LIST_URL = 'https://raw.githubusercontent.com/StevenBlack/hosts/master/alternates/porn-only/hosts';

    /** @return array{enabled: bool, domains: ?int}|null null when the list was never added or Pi-hole is unreachable. */
    public function adultList(): ?array
    {
        try {
            $response = $this->withSession(fn ($client) => $client->get('/api/lists', ['type' => 'block']));
            $list = collect($response?->json('lists') ?? [])->firstWhere('address', self::ADULT_LIST_URL);

            return $list ? ['enabled' => (bool) $list['enabled'], 'domains' => $list['number'] ?? null] : null;
        } catch (\Exception $e) {
            Log::error('Pi-hole: exception reading lists: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Turn the adult list on or off. Takes effect only after rebuildGravity().
     */
    public function setAdultList(bool $enabled): bool
    {
        try {
            // Pi-hole reads `type` from the query string only; in the body it
            // is ignored and the request 400s.
            $payload = ['enabled' => $enabled, 'comment' => "Adult content (Lawa't Kape)"];

            $response = $this->adultList() === null
                ? $this->withSession(fn ($client) => $client->post('/api/lists?type=block', array_merge($payload, ['address' => self::ADULT_LIST_URL])))
                : $this->withSession(fn ($client) => $client->put('/api/lists/'.rawurlencode(self::ADULT_LIST_URL).'?type=block', $payload));

            if (! $response?->successful()) {
                Log::error('Pi-hole: could not update the adult list.', ['status' => $response?->status(), 'body' => $response?->body()]);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Pi-hole: exception updating the adult list: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Re-download every enabled list so a list change applies now instead of at
     * Pi-hole's weekly update. Downloads ~2MB and can take a minute — callers
     * run it after the response.
     */
    public function rebuildGravity(): bool
    {
        try {
            $response = $this->withSession(fn ($client) => $client->timeout(300)->post('/api/action/gravity'));

            return (bool) $response?->successful();
        } catch (\Exception $e) {
            Log::error('Pi-hole: exception rebuilding gravity: '.$e->getMessage());

            return false;
        }
    }

    protected function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#^www\.#', '', $domain);

        return rtrim(explode('/', $domain)[0], '.');
    }
}
