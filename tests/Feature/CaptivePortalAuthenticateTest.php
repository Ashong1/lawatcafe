<?php

namespace Tests\Feature;

use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CaptivePortalAuthenticateTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_code_gets_a_not_found_message(): void
    {
        $response = $this->post(route('portal.authenticate'), ['passcode' => 'NOPE-0000']);

        $response->assertSessionHas('error', "That code doesn't match any voucher — double-check it against your receipt.");
    }

    public function test_already_used_code_gets_a_distinct_message(): void
    {
        Voucher::create([
            'code' => 'FREE-USED',
            'duration_minutes' => 60,
            'tier' => 'free',
            'is_used' => true,
            'used_at' => now(),
        ]);

        $response = $this->post(route('portal.authenticate'), ['passcode' => 'FREE-USED']);

        $response->assertSessionHas('error', 'This code has already been used.');
    }

    /**
     * A phone keyboard mangles a typed voucher in predictable ways: it
     * capitalises, it appends a space, and guests skip the printed dash. None
     * of those mean the code is wrong, so none of them should be reported as
     * "doesn't match any voucher". Asserted via the already-used message
     * because reaching it proves the row was found — the redeem path itself
     * needs a live firewall.
     */
    public static function mangledCodeProvider(): array
    {
        return [
            'trailing space' => ['FREE-USED '],
            'leading space' => [' FREE-USED'],
            'space around the dash' => ['FREE - USED'],
            'lowercased by autocorrect' => ['free-used'],
            'dash omitted entirely' => ['FREEUSED'],
            'dash omitted and lowercased' => ['freeused'],
        ];
    }

    #[DataProvider('mangledCodeProvider')]
    public function test_a_mangled_but_valid_code_still_finds_its_voucher(string $typed): void
    {
        Voucher::create([
            'code' => 'FREE-USED',
            'duration_minutes' => 60,
            'tier' => 'free',
            'is_used' => true,
            'used_at' => now(),
        ]);

        $response = $this->post(route('portal.authenticate'), ['passcode' => $typed]);

        $response->assertSessionHas('error', 'This code has already been used.');
    }

    /**
     * The normalisation must not turn a genuinely wrong code into a match —
     * stripping separators could otherwise collide two different vouchers.
     */
    public function test_normalisation_does_not_make_a_wrong_code_match(): void
    {
        Voucher::create([
            'code' => 'FREE-USED',
            'duration_minutes' => 60,
            'tier' => 'free',
            'is_used' => true,
            'used_at' => now(),
        ]);

        $response = $this->post(route('portal.authenticate'), ['passcode' => 'FREE-USE']);

        $response->assertSessionHas('error', "That code doesn't match any voucher — double-check it against your receipt.");
    }

    /**
     * A phone's sign-in window sends no Referer, so back() fell through to '/',
     * which redirects to the staff login page — where the error flash was
     * consumed and never displayed. A guest who mistyped their code saw the form
     * reset with no explanation, which is exactly how it was reported.
     */
    public function test_an_invalid_code_returns_to_the_portal_not_the_login_page(): void
    {
        $response = $this->post(route('portal.authenticate'), ['passcode' => 'NOPE-0000']);

        $response->assertRedirect(route('portal.index'));
        $response->assertSessionHas('error');
    }

    public function test_an_already_used_code_also_returns_to_the_portal(): void
    {
        Voucher::create([
            'code' => 'FREE-SPENT',
            'duration_minutes' => 60,
            'tier' => 'free',
            'is_used' => true,
            'used_at' => now()->subHours(5),
        ]);

        $this->post(route('portal.authenticate'), ['passcode' => 'FREE-SPENT'])
            ->assertRedirect(route('portal.index'))
            ->assertSessionHas('error');
    }

    /** The portal renders the flash as a toast; without this the message is invisible. */
    public function test_the_portal_page_renders_a_flashed_error(): void
    {
        $response = $this->withSession(['error' => 'That code does not match any voucher.'])
            ->get(route('portal.index'));

        $response->assertOk();
        $response->assertSee('That code does not match any voucher.', false);
        $response->assertSee('Swal.fire', false);
    }
}
