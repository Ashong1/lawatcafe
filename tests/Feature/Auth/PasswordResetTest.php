<?php

namespace Tests\Feature\Auth;

use App\Mail\AccountSetupLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * "Forgot password?" emails the same choose-your-password link as a staff
 * invite. Laravel's token reset pages stay for links already in inboxes.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee('Username or email');
    }

    public function test_a_link_can_be_requested_by_email_or_username(): void
    {
        Mail::fake();
        $user = User::factory()->create(['username' => 'ana']);

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        $this->post('/forgot-password', ['email' => 'ANA'])->assertSessionHas('status');

        Mail::assertQueued(AccountSetupLink::class, 2);
        Mail::assertQueued(AccountSetupLink::class, fn ($m) => $m->hasTo($user->email));
    }

    public function test_an_unknown_account_gets_the_same_reply_and_no_email(): void
    {
        Mail::fake();

        $this->post('/forgot-password', ['email' => 'nobody'])
            ->assertSessionHas('status', 'If that account exists, a link to choose a new password is on its way to its email.');

        Mail::assertNothingQueued();
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->get('/reset-password/'.$token)->assertOk();
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create(['password_set_at' => null]);
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        $this->assertNotNull($user->fresh()->password_set_at);
    }
}
