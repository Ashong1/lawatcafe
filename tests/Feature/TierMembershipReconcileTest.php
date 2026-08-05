<?php

namespace Tests\Feature;

use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The guarantee the per-tier firewall rules will rest on.
 *
 * While the tier aliases only fed a shaper pipe, a stale member was harmless —
 * it shaped traffic for an address that had none. Once a filter rule PASSES
 * traffic for alias members, the same stale entry is a guest with working
 * internet after their time is up. So membership has to be reconciled against
 * what OPNsense actually has connected, and that has to be true BEFORE those
 * rules exist.
 */
class TierMembershipReconcileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  string[]  $liveIps  Addresses OPNsense reports as connected.
     * @param  string[]  $freeMembers  Addresses currently in the free alias.
     */
    private function fakeGateway(array $liveIps, array $freeMembers, bool $removalWorks = true): void
    {
        // listSessions() caches for 15s, which would leak one test's gateway
        // into the next.
        Cache::flush();

        Http::fake([
            // session/list, not search — and it returns a bare array, not rows.
            '*/api/captiveportal/session/list/*' => Http::response(
                array_map(fn ($ip) => ['ipAddress' => $ip.'/32', 'sessionId' => 's-'.$ip], $liveIps),
                200
            ),
            '*/api/firewall/alias_util/list/lawatcafe_free_tier' => Http::response([
                'rows' => array_map(fn ($ip) => ['ip' => $ip], $freeMembers),
            ], 200),
            '*/api/firewall/alias_util/list/*' => Http::response(['rows' => []], 200),
            '*/api/firewall/alias_util/delete/*' => Http::response(
                $removalWorks ? ['status' => 'done'] : ['status' => 'failed'],
                200
            ),
            '*' => Http::response(['rows' => []], 200),
        ]);
    }

    /** The whole point: an address with no session must not keep its membership. */
    public function test_a_member_with_no_live_session_is_removed(): void
    {
        $this->fakeGateway(liveIps: ['192.168.2.50'], freeMembers: ['192.168.2.50', '192.168.2.77']);

        $result = app(TrafficShapingService::class)->reconcileTierMembership(app(OpnSenseService::class));

        $this->assertSame(2, $result['checked']);
        $this->assertSame(1, $result['removed']);
        $this->assertSame(0, $result['failed']);

        // And it removed the right one.
        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'alias_util/delete')
            || ($request['address'] ?? null) === '192.168.2.77');
    }

    /** A connected guest must never be stripped of their tier mid-session. */
    public function test_a_member_with_a_live_session_is_left_alone(): void
    {
        $this->fakeGateway(liveIps: ['192.168.2.50'], freeMembers: ['192.168.2.50']);

        $result = app(TrafficShapingService::class)->reconcileTierMembership(app(OpnSenseService::class));

        $this->assertSame(0, $result['removed']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'alias_util/delete'));
    }

    /**
     * A removal that fails is exactly the state the filter rules must not be
     * built on, so it has to be counted and surfaced rather than swallowed.
     */
    public function test_a_failed_removal_is_reported_not_swallowed(): void
    {
        $this->fakeGateway(liveIps: [], freeMembers: ['192.168.2.77'], removalWorks: false);

        $result = app(TrafficShapingService::class)->reconcileTierMembership(app(OpnSenseService::class));

        $this->assertSame(0, $result['removed']);
        $this->assertSame(1, $result['failed']);
    }

    public function test_the_command_fails_loudly_when_a_member_cannot_be_removed(): void
    {
        $this->fakeGateway(liveIps: [], freeMembers: ['192.168.2.77'], removalWorks: false);

        $this->artisan('shaper:reconcile-tiers')->assertFailed();
    }

    public function test_the_command_succeeds_when_everything_reconciles(): void
    {
        $this->fakeGateway(liveIps: ['192.168.2.50'], freeMembers: ['192.168.2.50']);

        $this->artisan('shaper:reconcile-tiers')->assertSuccessful();
    }

    /**
     * releaseIp used to return void and ignore the result. With a PASS rule in
     * play a silent failure there means retained access, so the caller has to
     * be able to see it.
     */
    public function test_release_reports_a_failure(): void
    {
        $this->fakeGateway(liveIps: [], freeMembers: ['192.168.2.77'], removalWorks: false);

        $this->assertFalse(
            app(TrafficShapingService::class)->releaseIp('192.168.2.77', app(OpnSenseService::class))
        );
    }

    /**
     * Removal is attempted unconditionally, never gated on reading the alias
     * first. A read that fails returns an empty list, which would look exactly
     * like "not a member" and skip the removal — the one state this mechanism
     * exists to prevent, reachable through an unrelated API hiccup.
     */
    public function test_removal_is_attempted_even_when_the_alias_cannot_be_read(): void
    {
        Cache::flush();
        Http::fake([
            '*/api/captiveportal/session/list/*' => Http::response([], 200),
            // The alias read is broken.
            '*/api/firewall/alias_util/list/*' => Http::response([], 500),
            '*/api/firewall/alias_util/delete/*' => Http::response(['status' => 'done'], 200),
            '*' => Http::response([], 200),
        ]);

        $this->assertTrue(
            app(TrafficShapingService::class)->releaseIp('192.168.2.77', app(OpnSenseService::class))
        );

        // Once per tier, regardless of what the read said.
        $deletes = 0;
        Http::assertSent(function ($request) use (&$deletes) {
            if (str_contains($request->url(), 'alias_util/delete')) {
                $deletes++;
            }

            return true;
        });
        $this->assertSame(count(TrafficShapingService::TIERS), $deletes);
    }
}
