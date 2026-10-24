<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalPrivacyNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_notice_is_linked_beside_the_wifi_rules_on_the_sign_in_page(): void
    {
        $html = $this->get(route('portal.index'))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('portal.privacy').'"', $html);
        // In the agree line, above Connect, where a phone shows it without scrolling.
        $this->assertLessThan(strpos($html, 'type="submit" :disabled="isSubmitting"'), strpos($html, route('portal.privacy')));
    }

    public function test_the_notice_names_the_law_and_what_is_kept_in_both_languages(): void
    {
        $this->get(route('portal.privacy', ['lang' => 'en']))->assertOk()
            ->assertSee('Republic Act No. 10173')->assertSee('90 days')->assertSee('91 days')->assertSee('OpenRouter');
        $this->get(route('portal.privacy', ['lang' => 'fil']))->assertOk()
            ->assertSee('Ano ang kinokolekta namin')->assertSee('National Privacy Commission')->assertSee('May-ari ng Lawa\'t Kape');
    }

    public function test_the_notice_loads_nothing_from_outside(): void
    {
        $html = $this->get(route('portal.privacy'))->getContent();

        $this->assertDoesNotMatchRegularExpression('#(src|href)="https?://(?!'.preg_quote(parse_url(url('/'), PHP_URL_HOST)).')#', $html);
        $this->assertStringNotContainsString('<script src', $html);
        $this->assertStringNotContainsString('<link rel="stylesheet"', $html);
    }
}
