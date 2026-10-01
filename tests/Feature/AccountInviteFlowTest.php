<?php

namespace Tests\Feature;

use App\Mail\AccountSetupLink;
use App\Models\Sale;
use App\Models\User;
use App\Services\AccountInviteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Adding staff: the owner gives a name, username and email; the person gets
 * an email link to choose their own password and signs in with either.
 */
class AccountInviteFlowTest extends TestCase
{
    use RefreshDatabase;

    private function invite(array $overrides = []): User
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('accounts.store'), array_merge([
                'name' => 'Ana Santos', 'username' => 'Ana', 'email' => 'ana@example.com', 'role' => 'staff',
            ], $overrides))
            ->assertSessionHasNoErrors();
        auth()->logout();

        return User::where('email', 'ana@example.com')->firstOrFail();
    }

    private function linkFor(User $user): string
    {
        $url = app(AccountInviteService::class)->link($user, 'http://192.168.2.100');

        return substr($url, strlen('http://192.168.2.100'));
    }

    public function test_adding_someone_emails_an_invite_and_they_cannot_sign_in_yet(): void
    {
        Mail::fake();
        $ana = $this->invite();

        $this->assertSame('ana', $ana->username);
        $this->assertTrue($ana->isPendingInvite());
        Mail::assertQueued(AccountSetupLink::class, fn ($m) => $m->hasTo('ana@example.com') && str_contains($m->link, '/welcome/'.$ana->id));

        $this->post('/login', ['email' => 'ana', 'password' => 'anything'])
            ->assertSessionHasErrors(['email' => "This account isn't set up yet. Open the invite in your email to choose a password, or ask the owner to send it again."]);
        $this->assertGuest();
    }

    public function test_the_link_sets_the_password_signs_them_in_and_works_once(): void
    {
        Mail::fake();
        $ana = $this->invite();
        $link = $this->linkFor($ana);

        $this->get($link)->assertOk()->assertSee('Choose a password')->assertSee('ana');

        $this->post($link, ['password' => 'kape-2026', 'password_confirmation' => 'kape-2026'])
            ->assertRedirect('/staff-dashboard');
        $this->assertAuthenticatedAs($ana);
        $this->assertFalse($ana->fresh()->isPendingInvite());

        auth()->logout();
        $this->get($link)->assertOk()->assertSee('already been set');
        $this->post($link, ['password' => 'other-pass', 'password_confirmation' => 'other-pass']);
        $this->assertGuest();
    }

    public function test_an_expired_or_tampered_link_is_refused(): void
    {
        Mail::fake();
        $ana = $this->invite();
        $link = $this->linkFor($ana);

        // Ana's signature on someone else's account.
        $other = User::factory()->create();
        $this->get(str_replace('/welcome/'.$ana->id, '/welcome/'.$other->id, $link))->assertSee('no longer works');

        $this->travel(AccountInviteService::LINK_DAYS + 1)->days();
        $this->get($link)->assertSee('has expired');
    }

    public function test_the_link_works_on_any_address_the_shop_uses(): void
    {
        Mail::fake();
        $ana = $this->invite();
        $path = $this->linkFor($ana);

        $this->get('http://lawatkape.lab'.$path)->assertSee('Save and sign in');
    }

    public function test_someone_signed_in_on_the_device_is_not_switched_silently(): void
    {
        Mail::fake();
        $ana = $this->invite();
        $owner = User::factory()->create(['role' => 'admin']);

        $this->actingAs($owner)->get($this->linkFor($ana))->assertSee('Someone is signed in');
    }

    public function test_sign_in_with_username_or_email(): void
    {
        $user = User::factory()->create(['username' => 'ana', 'password' => 'kape-2026']);

        $this->post('/login', ['email' => 'Ana', 'password' => 'kape-2026']);
        $this->assertAuthenticatedAs($user);
        auth()->logout();

        $this->post('/login', ['email' => $user->email, 'password' => 'kape-2026']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_username_rules_and_typed_values_survive_an_error(): void
    {
        User::factory()->create(['username' => 'ana']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->from(route('accounts.index'))
            ->post(route('accounts.store'), ['name' => 'Ana Reyes', 'username' => 'ana', 'email' => 'reyes@example.com', 'role' => 'staff'])
            ->assertSessionHasErrors(['username']);
        $this->actingAs($admin)
            ->post(route('accounts.store'), ['name' => 'Ana Reyes', 'username' => 'ana reyes', 'email' => 'reyes@example.com', 'role' => 'staff'])
            ->assertSessionHasErrors(['username']);

        $this->actingAs($admin)->from(route('accounts.index'))
            ->post(route('accounts.store'), ['name' => 'Ana Reyes', 'username' => 'ana', 'email' => 'reyes@example.com', 'role' => 'staff']);
        $this->actingAs($admin)->get(route('accounts.index'))
            ->assertSee('reyes@example.com')
            ->assertSee('Someone already has the username ana');
    }

    public function test_resend_and_copy_link_for_a_waiting_invite(): void
    {
        Mail::fake();
        $ana = $this->invite();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('accounts.index'))
            ->assertSee('Waiting to set a password')
            ->assertSee('\\/welcome\\/'.$ana->id, false); // JSON-escaped inside the Copy link handler

        $this->actingAs($admin)->post(route('accounts.send-link', $ana))->assertSessionHas('success');
        Mail::assertQueued(AccountSetupLink::class, 2);
    }

    public function test_no_copyable_link_for_an_account_already_in_use(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('accounts.index'))
            ->assertSee('Active')
            ->assertDontSee('\\/welcome\\/'.$staff->id, false);
    }

    public function test_admin_cannot_send_links_for_another_admin(): void
    {
        $other = User::factory()->create(['role' => 'admin']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('accounts.send-link', $other))->assertForbidden();
    }

    public function test_removing_someone_with_sales_keeps_their_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff', 'username' => 'ben', 'password' => 'kape-2026']);
        DB::table('sales')->insert(['user_id' => $staff->id, 'transaction_number' => 'T-1', 'total_amount' => 100, 'payment_method' => 'cash', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($admin)->delete(route('accounts.destroy', $staff))->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $staff->id]);
        $this->assertSame(1, Sale::where('user_id', $staff->id)->count());
        $this->actingAs($admin)->get(route('accounts.index'))->assertSee('Removed (1)');

        auth()->logout();
        $this->post('/login', ['email' => 'ben', 'password' => 'kape-2026'])
            ->assertSessionHasErrors(['email' => 'This account was removed. Ask the owner if you need access again.']);
        $this->assertGuest();

        $this->actingAs($admin)->post(route('accounts.restore', $staff));
        auth()->logout();
        $this->post('/login', ['email' => 'ben', 'password' => 'kape-2026']);
        $this->assertAuthenticatedAs($staff);
    }

    public function test_removing_someone_with_no_history_deletes_them(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))->delete(route('accounts.destroy', $staff));

        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
    }

    public function test_a_removed_person_still_signed_in_is_signed_out(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'deactivated_at' => now()]);

        $this->actingAs($staff)->get('/staff-dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /** The email is plain text: an HTML-escaped "&amp;" in the link breaks its signature. */
    public function test_the_emailed_link_is_the_exact_working_link(): void
    {
        Mail::fake();
        $ana = $this->invite(['name' => "Ana D'Souza"]);

        Mail::assertQueued(AccountSetupLink::class, function (AccountSetupLink $mail) {
            $body = $mail->render();
            $this->assertStringContainsString($mail->link, $body);
            $this->assertStringNotContainsString('&amp;', $body);
            $this->assertStringContainsString("Hi Ana D'Souza", $body);

            $this->get(substr($mail->link, strpos($mail->link, '/welcome/')))->assertSee('Save and sign in');

            return true;
        });
    }
}
