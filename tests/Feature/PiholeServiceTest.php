<?php

namespace Tests\Feature;

use App\Services\PiholeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PiholeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.pihole.url' => 'http://pihole.test',
            'services.pihole.app_password' => 'test-app-password',
        ]);

        Cache::forget('pihole_session');
    }

    public function test_blocked_domains_authenticates_then_lists_deny_exact_entries(): void
    {
        Http::fake([
            'pihole.test/api/auth' => Http::response([
                'session' => ['valid' => true, 'sid' => 'sid-123', 'csrf' => 'csrf-123', 'validity' => 1800],
            ]),
            'pihole.test/api/domains*' => Http::response([
                'domains' => [
                    ['domain' => 'facebook.com', 'comment' => null, 'enabled' => true],
                    ['domain' => 'roblox.com', 'comment' => 'noisy', 'enabled' => false],
                ],
            ]),
        ]);

        $service = new PiholeService;
        $domains = $service->blockedDomains();

        $this->assertCount(2, $domains);
        $this->assertSame('facebook.com', $domains[0]['domain']);
        $this->assertTrue($domains[0]['enabled']);
        $this->assertFalse($domains[1]['enabled']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/auth')
            && $request['password'] === 'test-app-password');
    }

    public function test_block_domain_posts_a_new_entry_when_not_already_listed(): void
    {
        Http::fake([
            'pihole.test/api/auth' => Http::response([
                'session' => ['valid' => true, 'sid' => 'sid-123', 'csrf' => 'csrf-123', 'validity' => 1800],
            ]),
            'pihole.test/api/domains*' => Http::response(['domains' => []]),
            'pihole.test/api/domains/deny/exact' => Http::response(['processed' => ['success' => [['item' => 'facebook.com']]]]),
        ]);

        $service = new PiholeService;
        $this->assertTrue($service->blockDomain('https://www.facebook.com/'));

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/api/domains/deny/exact')
            && $request['domain'] === 'facebook.com'
            && $request['enabled'] === true);
    }

    public function test_unblock_domain_puts_the_existing_entry_disabled_and_keeps_its_comment(): void
    {
        Http::fake([
            'pihole.test/api/auth' => Http::response([
                'session' => ['valid' => true, 'sid' => 'sid-123', 'csrf' => 'csrf-123', 'validity' => 1800],
            ]),
            'pihole.test/api/domains*' => Http::response([
                'domains' => [['domain' => 'facebook.com', 'comment' => 'from admin', 'enabled' => true]],
            ]),
            'pihole.test/api/domains/deny/exact/facebook.com' => Http::response(['processed' => ['success' => [['item' => 'facebook.com']]]]),
        ]);

        $service = new PiholeService;
        $this->assertTrue($service->unblockDomain('facebook.com'));

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && str_contains($request->url(), '/api/domains/deny/exact/facebook.com')
            && $request['enabled'] === false
            && $request['comment'] === 'from admin');
    }

    public function test_remove_domain_sends_a_delete(): void
    {
        Http::fake([
            'pihole.test/api/auth' => Http::response([
                'session' => ['valid' => true, 'sid' => 'sid-123', 'csrf' => 'csrf-123', 'validity' => 1800],
            ]),
            'pihole.test/api/domains/deny/exact/facebook.com' => Http::response(['processed' => ['success' => [['item' => 'facebook.com']]]]),
        ]);

        $service = new PiholeService;
        $this->assertTrue($service->removeDomain('facebook.com'));

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '/api/domains/deny/exact/facebook.com'));
    }

    public function test_a_401_forces_reauthentication_and_retries_once(): void
    {
        $authCalls = 0;

        Http::fake([
            'pihole.test/api/auth' => Http::response([
                'session' => ['valid' => true, 'sid' => 'sid-123', 'csrf' => 'csrf-123', 'validity' => 1800],
            ]),
            'pihole.test/api/domains*' => Http::sequence()
                ->push(['error' => ['message' => 'Unauthorized']], 401)
                ->push(['domains' => []], 200),
        ]);

        $service = new PiholeService;
        $domains = $service->blockedDomains();

        $this->assertSame([], $domains);
        // auth, get (401), re-auth, get (retry, succeeds)
        Http::assertSentCount(4);
    }

    public function test_missing_app_password_returns_empty_without_calling_pihole(): void
    {
        config(['services.pihole.app_password' => null]);
        Http::fake();

        $service = new PiholeService;
        $this->assertSame([], $service->blockedDomains());

        Http::assertNothingSent();
    }
}
