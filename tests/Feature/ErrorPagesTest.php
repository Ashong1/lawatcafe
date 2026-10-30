<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Owner (Oct 30): a bare "500 | SERVER ERROR" told nobody anything. Error pages in the shop's style. */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    private function assertSelfContained(string $html): void
    {
        // Guests may see these before they're online: nothing loaded from outside.
        $this->assertDoesNotMatchRegularExpression('/(src|href)="https?:\/\//', $html);
        $this->assertStringContainsString("Lawa't Kape", $html);
    }

    public function test_page_not_found(): void
    {
        $html = $this->get('/no-such-page')->assertNotFound()->assertSee("We couldn't find that page")->getContent();
        $this->assertSelfContained($html);
    }

    public function test_something_went_wrong(): void
    {
        config(['app.debug' => false]);
        Route::get('/_boom', fn () => throw new \RuntimeException('boom'));

        $html = $this->get('/_boom')->assertStatus(500)->assertSee('Something went wrong on our side')->assertDontSee('boom')->getContent();
        $this->assertSelfContained($html);
    }

    public function test_no_access_expired_and_too_many(): void
    {
        Route::get('/_403', fn () => abort(403));
        Route::get('/_419', fn () => abort(419));
        Route::get('/_429', fn () => abort(429));
        Route::get('/_502', fn () => abort(502));

        $this->get('/_403')->assertForbidden()->assertSee("You can't open this page");
        $this->get('/_419')->assertStatus(419)->assertSee('This page expired');
        $this->get('/_429')->assertStatus(429)->assertSee('Too many tries');
        $this->get('/_502')->assertStatus(502)->assertSee('Something went wrong on our side');
    }
}
