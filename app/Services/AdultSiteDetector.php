<?php

namespace App\Services;

use App\Http\Controllers\SiteBlockingController;

/**
 * Decides whether a looked-up domain is probably an adult site.
 *
 * A heuristic, not a blocklist: the Adult Content presets only name two
 * sites, and guests were seen reaching others (pinayflix.tv, sulasok.tv) that
 * no list covered. Matching keywords in the domain name catches those so an
 * admin hears about them and can decide whether to block. False positives
 * cost one notification; the exceptions below cover the known innocent words
 * that contain "sex".
 */
class AdultSiteDetector
{
    private const KEYWORDS = [
        'porn', 'xxx', 'hentai', 'xvideo', 'xnxx', 'xhamster', 'redtube', 'youporn',
        'spankbang', 'eporner', 'brazzers', 'onlyfans', 'chaturbate', 'stripchat',
        'bongacams', 'livejasmin', 'camsoda', 'nsfw', 'erotic', 'nude', 'milf',
        'pinayflix', 'sulasok', 'sex',
    ];

    private const INNOCENT = ['essex', 'sussex', 'middlesex', 'wessex', 'sextant'];

    /** Second-level labels under a country TLD that aren't the site's own name (e.g. .com.ph). */
    private const GENERIC_SLDS = ['com', 'net', 'org', 'gov', 'edu', 'co', 'ac'];

    public function isAdult(string $domain): bool
    {
        $domain = strtolower(trim($domain, '.'));
        $site = $this->siteFor($domain);

        if (in_array($site, $this->presetDomains(), true)) {
            return true;
        }

        $name = str_replace(self::INNOCENT, '', $domain);

        foreach (self::KEYWORDS as $keyword) {
            if (str_contains($name, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The site a lookup belongs to — "www.pornhub.com" and "cdn.pornhub.com"
     * are one visit, so alerts are grouped and de-duplicated on this.
     */
    public function siteFor(string $domain): string
    {
        $labels = explode('.', strtolower(trim($domain, '.')));
        $count = count($labels);

        if ($count <= 2) {
            return implode('.', $labels);
        }

        $take = strlen($labels[$count - 1]) === 2 && in_array($labels[$count - 2], self::GENERIC_SLDS, true) ? 3 : 2;

        return implode('.', array_slice($labels, -$take));
    }

    /** @return string[] */
    private function presetDomains(): array
    {
        return array_keys(SiteBlockingController::PRESETS['Adult Content'] ?? []);
    }
}
