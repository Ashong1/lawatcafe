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

            return (bool) $response?->successful();
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

            return (bool) $response?->successful();
        } catch (\Exception $e) {
            Log::error("Pi-hole: exception removing {$domain}: ".$e->getMessage());

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
