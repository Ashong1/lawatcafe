<?php

namespace App\Services\Agent\Tools;

use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;

/**
 * Turns a bare site name ("sulasok") into the real domains behind it.
 *
 * Asked to block the sites in a photo, the assistant passed what the photo
 * showed — names without a TLD — and blockSites rejected every one, leaving
 * the admin with nothing. Pi-hole needs full domains, so each bare name is
 * tried against the endings sites like these actually use, and every
 * combination that exists in public DNS is returned. Only runs for names an
 * admin has confirmed blocking.
 *
 * Resolved against this server's own resolver, which is public DNS, not
 * Pi-hole — so a site Pi-hole already blocks still shows up as existing.
 */
class BareSiteResolver
{
    public const TLDS = ['com', 'net', 'org', 'tv', 'xxx', 'porn', 'ph', 'com.ph', 'co', 'me', 'to', 'run', 'lol'];

    /** Bare names past this are left unresolved rather than making the confirm hang. */
    public const MAX_NAMES = 10;

    /** @var callable(string[]): string[] hosts in, the ones that resolve out */
    private $lookup;

    /**
     * Lookups run in parallel: done one at a time with checkdnsrr(), seven
     * names across every ending took 23s while an admin waited on a confirm.
     */
    public function __construct(?callable $lookup = null)
    {
        $this->lookup = $lookup ?? function (array $hosts): array {
            $results = Process::pool(function (Pool $pool) use ($hosts) {
                foreach ($hosts as $host) {
                    $pool->as($host)->timeout(8)->command(['dig', '+short', '+time=2', '+tries=1', $host, 'A']);
                }
            })->start()->wait();

            return array_values(array_filter($hosts, fn ($h) => isset($results[$h]) && trim($results[$h]->output()) !== ''));
        };
    }

    public static function isBareName(string $name): bool
    {
        return (bool) preg_match('/^(?!-)[a-z0-9-]{2,63}(?<!-)$/', $name);
    }

    /**
     * @param  string[]  $names
     * @return array<string, string[]> bare name => domains found (empty if none)
     */
    public function resolve(array $names): array
    {
        $names = array_slice(array_values(array_unique($names)), 0, self::MAX_NAMES);
        if ($names === []) {
            return [];
        }

        // One parallel batch: a random probe per ending (see below) plus every
        // name × ending combination.
        $probe = 'lk-nx-'.bin2hex(random_bytes(6));
        $hosts = array_map(fn ($tld) => "{$probe}.{$tld}", self::TLDS);
        foreach ($names as $name) {
            foreach (self::TLDS as $tld) {
                $hosts[] = "{$name}.{$tld}";
            }
        }

        $resolving = array_flip(($this->lookup)($hosts));
        $tlds = $this->realTlds($probe, $resolving);

        $found = [];
        foreach ($names as $name) {
            $found[$name] = array_values(array_filter(
                array_map(fn ($tld) => "{$name}.{$tld}", $tlds),
                fn ($host) => isset($resolving[$host])
            ));
        }

        return $found;
    }

    /**
     * Endings whose registry answers only for names that really exist. Some
     * run wildcard DNS — .ph and .com.ph resolved even a made-up name to one
     * parking IP (2026-09-28), which made every bare name "exist" there. A
     * random label is probed once per ending; one that resolves is skipped.
     */
    private function realTlds(string $probe, array $resolving): array
    {
        return array_values(array_filter(self::TLDS, fn ($tld) => ! isset($resolving["{$probe}.{$tld}"])));
    }
}
