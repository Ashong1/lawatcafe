<?php

namespace App\Services\Agent\Tools;

use App\Http\Controllers\SiteBlockingController;

/**
 * Cleans a model-supplied domain list for the site-blocking tools: strips a
 * scheme, "www." and any path (a model reading a screenshot will happily pass
 * "https://www.example.com/watch"), lowercases, de-duplicates, and splits out
 * anything that still isn't a domain — the same rule the Site Blocking page
 * enforces, so the AI can't write something a human couldn't.
 */
final class SiteDomains
{
    /** @return array{0: string[], 1: string[]} [valid, invalid] */
    public static function parse(mixed $domains): array
    {
        $valid = [];
        $invalid = [];

        foreach ((array) $domains as $raw) {
            $domain = strtolower(trim((string) $raw));
            $domain = preg_replace('#^[a-z]+://#', '', $domain);
            $domain = preg_replace('#^www\.#', '', $domain);
            $domain = rtrim(explode('/', $domain)[0], '.');

            if ($domain === '') {
                continue;
            }

            preg_match(SiteBlockingController::DOMAIN_REGEX, $domain)
                ? $valid[] = $domain
                : $invalid[] = $domain;
        }

        return [array_values(array_unique($valid)), $invalid];
    }
}
