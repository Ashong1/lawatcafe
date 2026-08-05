<?php

namespace Tests\Feature;

use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sign-in form has to survive Alpine never starting.
 *
 * Reported as "older phones lag or hang when signing in". It was neither: the
 * voucher panel sat behind x-cloak, which is `display:none !important` until
 * Alpine boots. A captive-portal WebView too old to run the bundle — no ES
 * module support, or a SyntaxError on the ES2020 tokens inside it — left the
 * guest looking at the portal shell with no code field anywhere on it.
 *
 * The form posts normally to portal.authenticate and that route redirects like
 * any Laravel form, so sign-in works with JavaScript switched off entirely.
 * These tests hold that line: the guest's way in must be in the raw HTML.
 */
class CaptivePortalNoScriptSignInTest extends TestCase
{
    use RefreshDatabase;

    /** A device the firewall has never seen, so index() renders the sign-in page. */
    private function unauthenticatedGuest(): self
    {
        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('resolveMacForIp')->andReturn(null);
            $mock->shouldReceive('listSessions')->andReturn([]);
        });

        return $this->withServerVariables(['REMOTE_ADDR' => '192.168.2.77']);
    }

    /** The panel wrapping the voucher form, as rendered. */
    private function codePanel(string $html): string
    {
        preg_match('/<div x-show="activeTab === \'code\'"[^>]*>/', $html, $m);

        $this->assertNotEmpty($m, 'The voucher panel is missing from the portal entirely.');

        return $m[0];
    }

    private function helpPanel(string $html): string
    {
        preg_match('/<div x-show="activeTab === \'help\'"[^>]*>/', $html, $m);

        $this->assertNotEmpty($m, 'The help panel is missing from the portal entirely.');

        return $m[0];
    }

    public function test_the_voucher_form_is_visible_without_any_javascript(): void
    {
        $html = $this->unauthenticatedGuest()->get(route('portal.index'))->getContent();

        $panel = $this->codePanel($html);

        $this->assertStringNotContainsString('x-cloak', $panel, 'x-cloak hides the sign-in form until Alpine boots.');
        $this->assertStringNotContainsString('display: none', $panel, 'The sign-in form is hidden on first paint.');
        $this->assertStringContainsString('id="lawat-login-form"', $html);
    }

    /**
     * Without Alpine the browser submits the form natively, so it needs a real
     * action and method — not just an @submit handler.
     */
    public function test_the_form_posts_natively_rather_than_only_through_alpine(): void
    {
        $html = $this->unauthenticatedGuest()->get(route('portal.index'))->getContent();

        preg_match('/<form[^>]*id="lawat-login-form"[^>]*>/', $html, $m);

        $this->assertNotEmpty($m, 'The sign-in form is missing.');
        $this->assertStringContainsString('action="'.route('portal.authenticate').'"', $m[0]);
        $this->assertStringContainsString('method="POST"', $m[0]);
    }

    /**
     * The server picks the opening panel and Alpine must agree. If they
     * disagree the server paints one panel and Alpine hides it on boot,
     * which looks exactly like the bug this file exists to prevent.
     */
    public function test_help_tab_link_opens_the_help_panel_on_the_server(): void
    {
        $html = $this->unauthenticatedGuest()->get(route('portal.index', ['tab' => 'help']))->getContent();

        $this->assertStringContainsString('display: none', $this->codePanel($html));
        $this->assertStringNotContainsString('display: none', $this->helpPanel($html));
        $this->assertStringContainsString("activeTab: 'help'", $html);
    }

    /**
     * A junk ?tab= value used to leave activeTab set to that junk, matching
     * neither panel and rendering a blank portal. It has to land on the form.
     */
    public function test_an_unknown_tab_value_still_lands_the_guest_on_the_form(): void
    {
        $html = $this->unauthenticatedGuest()->get(route('portal.index', ['tab' => 'garbage']))->getContent();

        $this->assertStringNotContainsString('display: none', $this->codePanel($html));
        $this->assertStringContainsString("activeTab: 'code'", $html);
    }
}
